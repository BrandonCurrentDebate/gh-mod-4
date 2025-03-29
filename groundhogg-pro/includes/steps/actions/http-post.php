<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Contact;
use Groundhogg\Event;
use Groundhogg\Step;
use Groundhogg\Steps\Actions\Action;
use function Groundhogg\do_replacements;
use function Groundhogg\generate_contact_with_map;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_hostname;
use function Groundhogg\get_mappable_fields;
use function Groundhogg\has_replacements;
use function Groundhogg\html;
use function GroundhoggPro\set_in_data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP Post
 *
 * This allows the user send an http post with contact information to any specified URL.
 * The URL must be HTTPS
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Actions
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */
class HTTP_Post extends Action {

	/**
	 * @return string
	 */
	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/actions/http-post/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Webhook', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'http_post';
	}

	public function get_sub_group() {
		return 'developer';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Send an HTTP Post to your favorite external software.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/webhook.svg';
	}

	/**
	 * Show wrapper
	 *
	 * @param $step
	 *
	 * @return void
	 */
	public function settings( $step ) {
		?>
        <div id="step_<?php echo $step->get_id() ?>_webhook_settings"></div>
		<?php

		if ( ! $this->get_last_response() ) {
			return;
		}

		$last_response         = $this->get_last_response();
		$last_response_decoded = json_decode( $last_response );

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Response' ) ) );

		echo html()->textarea( [
			'name'        => $this->setting_name_prefix( 'last_response' ),
			'id'          => $this->setting_id_prefix( 'last_response' ),
			'class'       => 'code json full-width',
			'placeholder' => wp_json_encode( [ 'error' => 'No response received yet.' ] ),
			'value'       => wp_json_encode( $last_response_decoded, JSON_PRETTY_PRINT ),
			'cols'        => 50,
			'rows'        => 4,
			'readonly'    => true,
		] );

		echo html()->description( __( 'This is the response data from the webhook request. If the response is in <code>JSON</code> format you can map it to contact fields.', 'groundhogg-pro' ) );

		if ( ! empty( $last_response_decoded ) ):

			?><p><?php
			echo html()->modal_link( [
				'title'              => __( 'Map Response Fields', 'groundhogg-pro' ),
				'text'               => __( 'Map Response Fields', 'groundhogg-pro' ),
				'footer_button_text' => __( 'Save Changes' ),
				'id'                 => '',
				'class'              => 'gh-button secondary no-padding',
				'source'             => $this->setting_id_prefix( 'field_map' ),
				'height'             => 600,
				'width'              => 600,
				'footer'             => 'true',
				'preventSave'        => 'true',
			] );
			?></p><?php

			echo html()->wrap( $this->field_map_table(), 'div', [
				'class' => 'hidden field-map-wrapper',
				'id'    => $this->setting_id_prefix( 'field_map' )
			] );

		endif;

		?><p></p><?php

	}

	public function generate_step_title( $step ) {

		$method   = strtoupper( $this->get_setting( 'method', 'post' ) );
		$post_url = $this->get_setting( 'post_url' );

		$host = wp_parse_url( $post_url, PHP_URL_HOST );

		if ( $host !== get_hostname() ) {
			$path = '<b>' . str_replace( 'www.', '', $host ) . '</b>';
		} else {
			$path = '<code>' . wp_parse_url( $post_url, PHP_URL_PATH ) . '</code>';
		}

		return sprintf( '<b>%s</b> to %s', $method, $path );
	}

	/**
	 * Build the field mapping table
	 *
	 * @return false|string
	 */
	private function field_map_table() {
		$field_map = $this->get_setting( 'field_map' );

		$request_fields = json_decode( $this->get_last_response(), true );

		$fields = $this->array_flatten( $request_fields );

		$ignore = array(
			'key',
			'token',
			'auth_token',
			'step_id',
			'wpgh_user_id',
			'user_id'
		);

		foreach ( $ignore as $key ) {
			unset( $fields[ $key ] );
		}

		if ( empty( $fields ) ) {
			return sprintf( '<p>%s</p>', __( 'Please send a test request to the provided URL first.', 'groundhogg-pro' ) );
		}

		$rows = [];

		foreach ( $fields as $key => $value ) {

			$rows[] = [
				html()->wrap( $key, 'code' ),
				html()->dropdown( [
					'option_none' => '-----',
					'options'     => get_mappable_fields(),
					'selected'    => get_array_var( $field_map, $key ),
					'name'        => $this->setting_name_prefix( 'field_map' ) . sprintf( '[%s]', $key ),
				] )
			];

		}

		ob_start();

		html()->list_table(
			[
				'class' => 'field-map'
			],
			[
				__( 'Field ID', 'groundhogg-pro' ),
				__( 'Map To', 'groundhogg-pro' ),
			],
			$rows
		);

		return ob_get_clean();
	}

	/**
	 * Save the settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {

		if ( $this->get_posted_data( 'field_map' ) ) {
			$this->save_setting( 'field_map', array_map( 'sanitize_key', $this->get_posted_data( 'field_map', '' ) ) );
		}
	}

	/**
	 * Maybe upgrade the webhook settings
	 *
	 * @return void
	 */
	public function maybe_upgrade() {

		$post_keys   = $this->get_setting( 'post_keys' );
		$post_values = $this->get_setting( 'post_values' );
		$body        = $this->get_setting( 'body' ) ?: [];

		if ( ! empty( $post_keys ) && empty( $body ) ) {
			foreach ( $post_keys as $i => $key ) {
				$body[] = [ $key, $post_values[ $i ] ];
			}
			$this->save_setting( 'body', $body );
		}

		$header_keys   = $this->get_setting( 'header_keys' );
		$header_values = $this->get_setting( 'header_values' );
		$headers       = $this->get_setting( 'headers' ) ?: [];

		if ( ! empty( $header_keys ) && empty( $headers ) ) {
			foreach ( $header_keys as $i => $key ) {
				$headers[] = [ $key, $header_values[ $i ] ];
			}
			$this->save_setting( 'headers', $headers );
		}
	}

	/**
	 * Do the POST
	 *
	 * @param $contact
	 *
	 * @return false|mixed
	 */
	public function post( $contact ) {

		$body    = $this->get_setting( 'body' ) ?: [];
		$headers = $this->get_setting( 'headers' ) ?: [];

		if ( ! is_array( $body ) ) {
			return false;
		}

		$_body = [];

		foreach ( $body as $pair ) {

			$key   = $pair[0];
			$value = $pair[1];

			if ( empty( $key ) ) {
				continue;
			}

			if ( has_replacements( $value ) ) {
				$value = do_replacements( sanitize_text_field( $value ), $contact );
			} else if ( preg_match( '/^[A-Za-z_]/', $value ) && $contact->$value ) {
				$value = $contact->$value;
			}

			$converted = json_decode( $value );
			$value     = $converted ?? $value;

			$keys  = explode( '.', $key );
			$_body = set_in_data( $_body, $keys, $value );
		}


		if ( empty( $_body ) ) {
			$_body = $contact->get_as_array();
		}

		$post_url = $this->get_setting( 'post_url' );
		$post_url = do_replacements( $post_url, $contact );

		$content_type = $this->get_setting( 'content_type' );

		if ( $this->get_setting( 'send_as_json' ) ) {
			$content_type = 'json';
		}

		$_headers = [];

		switch ( $content_type ) {
			case 'json':
				$_headers['Content-Type'] = sprintf( 'application/json; charset=%s', get_bloginfo( 'charset' ) );
				$_body                    = wp_json_encode( $_body );
				break;
			case 'form':
				$_headers['Content-Type'] = sprintf( 'application/x-www-form-urlencoded; charset=%s', get_bloginfo( 'charset' ) );
				break;
		}

		// allow default headers to be overridden
		foreach ( $headers as $pair ) {
			$key   = $pair[0];
			$value = $pair[1];

			if ( empty( $key ) ) {
				continue;
			}

			$_headers[ sanitize_text_field( $key ) ] = do_replacements( sanitize_text_field( $value ), $contact );
		}

		// force method case
		$method = strtoupper( $this->get_setting( 'method', 'post' ) );

		switch ( $method ) {
			default:
			case 'POST':
			case 'PUT':
			case 'PATCH':
			case 'DELETE':
				$func = 'wp_remote_post';
				break;
			case 'GET':
				$func = 'wp_remote_get';
		}

		// Filter the request data
		$args = apply_filters( 'groundhogg/steps/http_post/run/request_data', [
			'method'      => $method,
			'headers'     => $_headers,
			'body'        => $_body,
			'data_format' => 'body',
			'sslverify'   => true
		] );

		$response = call_user_func( $func, $post_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );

		$this->save_setting( 'last_response', $response_body );

		$field_map = $this->get_setting( 'field_map' );
		$_body     = json_decode( $response_body, true );

		if ( $field_map && $_body ) {
			generate_contact_with_map( $this->array_flatten( $_body ), $field_map, [
				'type'    => 'webhook_response',
				'step_id' => $this->get_current_step()->get_id()
			], $contact );
		}

		return $response;
	}

	/**
	 * Process the http post step...
	 *
	 * @param $contact Contact
	 * @param $event   Event
	 *
	 * @return \WP_Error|true
	 */
	public function run( $contact, $event ) {

		$this->maybe_upgrade();

		$response = $this->post( $contact );

		return is_wp_error( $response ) ? $response : true;
	}

	protected function add_additional_actions() {
		add_filter( 'groundhogg/pro/test_webhook', [ $this, 'rest_test' ], 10, 2 );
	}

	/**
	 * Test webhook from REST API
	 *
	 * @param $response null
	 * @param $step     Step
	 *
	 * @return mixed|\WP_Error
	 */
	public function rest_test( $response, $step ) {

		$this->set_current_step( $step );

		$contact = get_contactdata( wp_get_current_user()->user_email );

		if ( ! $contact ) {
			return new \WP_Error( 'error', 'No contact record.' );
		}

		$response = $this->post( $contact );

		if ( ! $response ) {
			return new \WP_Error( 'error', 'Got not response.' );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( json_decode( $body ) ) {
			$body = json_decode( $body );
		}

		return [
			'code' => $code,
			'body' => $body,
		];

	}

	protected function get_last_response() {
		return $this->get_setting( 'last_response' );
	}

	/**
	 * Flattens a multidimensional array
	 *
	 * @param array  $array
	 * @param string $parent_key
	 *
	 * @return array
	 */
	private function array_flatten( $array, $parent_key = '' ) {

		// Use new method...
		if ( ! is_array( $array ) ) {
			return [];
		}

		$return = [];

		$key_prefix = ! empty( $parent_key ) ? $parent_key . '.' : '';

		foreach ( $array as $key => $value ) {

			if ( ! is_array( $value ) ) {
				$return[ $key_prefix . $key ] = $value;
			} else {
				$return = array_merge( $return, $this->array_flatten( $value, $key_prefix . $key ) );
			}
		}

		return $return;
	}
}
