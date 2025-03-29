<?php

namespace GroundhoggPro;

use Groundhogg\Background_Tasks;
use Groundhogg\Contact;
use Groundhogg\Email;
use Groundhogg\Preferences;
use Groundhogg\Saved_Searches;
use Groundhogg\Step;
use GroundhoggPro\Classes\Superlink;
use function Groundhogg\array_map_to_step;
use function Groundhogg\do_replacements;
use function Groundhogg\get_active_steps;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\is_a_contact;
use function Groundhogg\is_option_enabled;
use function Groundhogg\managed_page_url;
use function Groundhogg\permissions_key_url;
use function Groundhogg\preg_quote_except;

/**
 * @param $data  array
 * @param $keys  array
 * @param $value mixed
 *
 * @return mixed
 */
function set_in_data( $data, $keys, $value ) {

	if ( empty( $keys ) ) {
		return $value;
	}

	$key = array_shift( $keys );

	$data[ $key ] = set_in_data( $data[ $key ] ?? [], $keys, $value );

	return $data;
}

/**
 * Handler the plugin API benchmark
 *
 * @param $call_name           string
 * @param $contact_id_or_email Contact|int|string
 * @param $by_user_id          bool
 *
 * @return void
 */
function plugin_api_benchmark_handler( $call_name, $contact_id_or_email, $by_user_id ) {

	if ( ! $call_name || ! $contact_id_or_email ) {
		return;
	}

	$contact = get_contactdata( $contact_id_or_email, $by_user_id );

	$call_name = sanitize_key( $call_name );

	$_meta = get_db( 'stepmeta' )->query( [
		'meta_key'   => 'call_name',
		'meta_value' => $call_name
	] );

	$step_ids = wp_parse_id_list( wp_list_pluck( $_meta, 'step_id' ) );
	$steps    = array_map_to_step( $step_ids );

	foreach ( $steps as $step ) {
		if ( $step->type_is( 'plugin_api' ) ) {
			$step->benchmark_enqueue( $contact );
		}
	}
}

add_action( 'groundhogg/steps/benchmarks/api', __NAMESPACE__ . '\plugin_api_benchmark_handler', 10, 3 );

/**
 * Handle the looping functionality
 *
 * @param $next    Step
 * @param $current Step
 */
function handle_loops_and_skips( $next, $current ) {

	if ( ! in_array( $current->get_type(), [ 'loop', 'skip' ] ) ) {
		return $next;
	}

	$_next = absint( $current->get_meta( 'next' ) );

	if ( ! $_next ) {
		return $next;
	}

	$_next = new Step( $_next );

	if ( $_next->exists() ) {
		return $_next;
	}

	return $next;
}

add_filter( 'groundhogg/step/next_action', __NAMESPACE__ . '\handle_loops_and_skips', 10, 2 );

/**
 * Allow the superlink class to use the Utils object API
 *
 * @param $class
 * @param $object
 *
 * @return string
 */
function map_superlink_class_for_object_api( $class, $object ) {
	if ( $object === 'superlink' ) {
		return '/GroundhoggPro/Classes/Superlink';
	}

	return $class;
}

add_filter( 'groundhogg/utils/get_object', __NAMESPACE__ . '\map_superlink_class_for_object_api', 10, 2 );

/**
 * get a superlink class or false if it doesn't exist.
 *
 * @param $id int
 *
 * @return false|Superlink
 */
function get_superlinkdata( $id ) {
	return \Groundhogg\Plugin::instance()->utils->get_object( $id, 'ID', 'superlink', true );
}

/**
 * Do the link replacement...
 *
 * @param $linkId int the ID of the link
 *
 * @return string the superlink url
 */
function replacement_superlink( $linkId ) {
	$linkId = absint( intval( $linkId ) );

	return sprintf( managed_page_url( 'superlinks/link/%s/' ), $linkId );
}

/**
 * Retrieve the email steps ahead of the given one.
 *
 * @return Step[]
 */
function get_email_steps_before_given_step( $step_id = 0 ) {

	$given_step = new Step( $step_id );

	$steps = $given_step->get_funnel()->get_steps();

	$email_steps = [];

	foreach ( $steps as $step ) {

		if ( $step->get_type() === 'send_email' && $step->get_order() < $given_step->get_order() ) {
			$email_steps[] = $step;
		}
	}

	return $email_steps;
}

/**
 * Get a list of steps that come after the given step.
 *
 * @param int $step_id
 *
 * @return Step[]
 */
function get_steps_after_given_id( $step_id = 0 ) {
	$given_step = new Step( $step_id );

	$steps = $given_step->get_funnel()->get_steps();

	$return_steps = [];

	foreach ( $steps as $step ) {

		if ( $step->get_group() === 'action' && $step->get_order() > $given_step->get_order() ) {
			$return_steps[] = $step;
		}

	}

	return $return_steps;
}

/**
 * Check whether a specific URL is to be excluded from click tracking.
 *
 * @param string       $url
 *
 * @param array|string $matches
 *
 * @return false|int
 */
function check_url_matches( $url, $matches = [] ) {

	if ( ! is_array( $matches ) ) {
		$matches = explode( PHP_EOL, $matches );
	}

	if ( empty( $matches ) ) {
		return false;
	}

	$matches = array_map( function ( $exclusion ) {
		return preg_quote_except( $exclusion, [ '$', '^' ] );
	}, $matches );

	$matches_regex = implode( '|', $matches );

	// No exclusions? Exit.
	if ( empty( $matches_regex ) ) {
		return false;
	}

	return preg_match( "@$matches_regex@", $url );
}

/**
 * Generate an auto expiring login link for support instead of sending the password.
 *
 * @param $contact Contact
 *
 * @return string
 */
function generate_auto_login_link_for_support( $contact ) {

	$link_url    = managed_page_url( 'auto-login' );
	$redirect_to = admin_url();

	$link_url = permissions_key_url( $link_url, $contact, 'auto_login', 7 * DAY_IN_SECONDS, false );

	if ( $redirect_to && is_string( $redirect_to ) ) {
		$link_url = add_query_arg( [
			'cid'         => $contact->get_id(),
			'redirect_to' => urlencode( $redirect_to ),
		], $link_url );
	}

	return $link_url;
}

/**
 * Create a global store of all the tracked pages so we don't have to run the tracking script on every single page.
 *
 * @deprecated
 */
function compile_all_tracked_pages_regex() {
	$url_matches = get_db( 'stepmeta' )->query( [
		'meta_key' => 'url_match'
	] );

	$url_matches = wp_list_pluck( $url_matches, 'meta_value' );
	$matches     = explode( PHP_EOL, implode( PHP_EOL, $url_matches ) );
	$matches     = array_map( function ( $exclusion ) {
		return preg_quote_except( $exclusion, [ '$', '^' ] );
	}, $matches );

	$matches_regex = implode( '|', $matches );

	update_option( 'gh_tracked_pages_regex', $matches_regex );
}

/**
 * Get the tracked pages regex for use in the frontend script
 *
 * @return mixed|void
 */
function get_global_tracked_pages_regex() {
	return get_option( 'gh_tracked_pages_regex' );
}

/**
 * Process a batch of meta changes
 *
 * @param $contact Contact
 * @param $changes array[]
 *
 * @return void
 */
function do_meta_changes( $contact, $changes = [] ) {

	if ( ! is_a_contact( $contact ) || empty( $changes ) ) {
		return;
	}

	foreach ( $changes as $change ) {

		$meta_key = sanitize_key( $change[0] );

		if ( empty( $meta_key ) ) {
			continue;
		}

		$current_value = $contact->get_meta( $meta_key );
		$modifier      = do_replacements( $change[2], $contact );
		$converted     = json_decode( $modifier );
		$modifier      = $converted ?? $modifier;
		$function      = $change[1];

		// Make sure that values for math functions are numeric
		if ( in_array( $function, [ 'add', 'subtract', 'multiply', 'divide' ] ) ) {
			$current_value = is_numeric( $current_value ) ? $current_value : 0;
			$modifier      = is_numeric( $modifier ) ? $modifier : 0;
		}

		switch ( $function ) {
			default:
			case 'set':
				$new_value = $modifier;
				break;
			case 'add':
				$new_value = $current_value + $modifier;
				break;
			case 'subtract':
				$new_value = $current_value - $modifier;
				break;
			case 'multiply':
				$new_value = $current_value * $modifier;
				break;
			case 'divide':
				$new_value = $current_value / $modifier;
				break;
			case 'delete':
				$contact->delete_meta( $meta_key );
				break;
		}

		if ( isset( $new_value ) ) {
			$contact->update_meta( $meta_key, $new_value );
		}
	}
}

add_action( 'future_to_publish', __NAMESPACE__ . '\handle_post_published_benchmark' );
add_action( 'draft_to_publish', __NAMESPACE__ . '\handle_post_published_benchmark' );
add_action( 'auto-draft_to_publish', __NAMESPACE__ . '\handle_post_published_benchmark' );
add_action( 'new_to_publish', __NAMESPACE__ . '\handle_post_published_benchmark' );
add_action( 'pending_to_publish', __NAMESPACE__ . '\handle_post_published_benchmark' );

/**
 * When a post is published do publish post benchmarks
 *
 * @param $post \WP_Post
 *
 * @return void
 */
function handle_post_published_benchmark( $post ) {

	$steps = get_active_steps( 'post_published' );

	if ( empty( $steps ) ) {
		return;
	}

	foreach ( $steps as $step ) {

		$post_type = $step->get_meta( 'post_type' );

		if ( $post_type !== $post->post_type ) {
			continue;
		}

		$taxonomies = get_object_taxonomies( $post );

		foreach ( $taxonomies as $taxonomy ) {

			$terms = $step->get_meta( $taxonomy );

			// Any terms, no more checks
			if ( empty( $terms ) ) {
				continue;
			}

			$filter_term_ids = wp_parse_id_list( wp_list_pluck( $terms, 'id' ) );

			$post_terms    = wp_get_post_terms( $post->ID, $taxonomy );
			$post_term_ids = wp_list_pluck( $post_terms, 'term_id' );

			// Does not have any of the terms
			if ( count( array_intersect( $filter_term_ids, $post_term_ids ) ) == 0 ) {
				// Continue the main foreach loop
				continue 2;
			}
		}

		// Default
		$search_method = $step->get_meta( 'search_method' ) ?: 'marketable-contacts';

		switch ( $search_method ) {
			case 'marketable-contacts':
				$query = [
					'marketable' => true
				];
				break;
			case 'confirmed-contacts':
				$query = [
					'optin_status' => Preferences::CONFIRMED
				];
				break;
			case 'all-contacts':
				$query = [];
				break;
			default:
				$search = Saved_Searches::instance()->get( $search_method );

				if ( ! $search ) {
					continue 2;
				}

				$query = $search['query'];
				break;
		}

		Background_Tasks::add_contacts_to_funnel( $step->get_id(), $query );
	}
}

/**
 * Modify the list unsubscribe mailto to send to unsubscribe-me.com
 *
 * @return string
 */
function send_unsubscribe_notifications_to_unsubscribe_me_com( string $mailto, Email $email, string $unsub_pk, string $event_id ) {

	if ( ! is_option_enabled( 'gh_use_unsubscribe_me' ) || ! Plugin::$instance->license_is_valid() ) {
		return $mailto;
	}

	return sprintf( 'unsubscribe=%s=%s/%s@unsubscribe-me.com?subject=%s', wp_parse_url( home_url(), PHP_URL_HOST ), $event_id, $unsub_pk, __( 'Unsubscribe', 'groundhogg' ) );

}

add_filter( 'groundhogg/list_unsubscribe_header/mailto', __NAMESPACE__ . '\send_unsubscribe_notifications_to_unsubscribe_me_com', 10, 4 );
