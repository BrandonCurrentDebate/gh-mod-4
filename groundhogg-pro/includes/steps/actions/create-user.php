<?php

namespace GroundhoggPro\Steps\Actions;


use Groundhogg\Contact;
use Groundhogg\Event;
use Groundhogg\Plugin;
use Groundhogg\Step;
use Groundhogg\Steps\Actions\Action;
use function Groundhogg\do_replacements;
use function Groundhogg\html;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create User
 *
 * Creates a WordPress user account for the contact, or assigns one to the contact if one exists.
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Actions
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */
class Create_User extends Action {

	/**
	 * @return string
	 */
	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/actions/create-user/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Create User', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'create_user';
	}

	public function get_sub_group() {
		return 'wordpress';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Create a WP User account at the specified level. Username is the contact\'s email.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/create-user.svg';
	}

    public function generate_step_title( $step ) {

        global $wp_roles;

        $role = $this->get_setting( 'role', 'subscriber' );

        return sprintf( 'Create a new <b>%s</b>', translate_user_role( $wp_roles->roles[ $role ]['name'] ) );
    }

	/**
	 * @param $step Step
	 */
	public function settings( $step ) {

        $role = $this->get_setting( 'role', 'subscriber' );

		echo html()->e( 'p', [], __( 'What role should be assign to the new user?', 'groundhogg-pro' ) );

		echo html()->dropdown( [
			'name'     => $this->setting_name_prefix( 'role' ),
			'options'  => Plugin::$instance->roles->get_roles_for_select(),
			'selected' => $role,
		] );

		echo html()->e( 'p', [], __( 'What should the format of the username be?', 'groundhogg-pro' ) );

		echo html()->dropdown( [
			'name'     => $this->setting_name_prefix( 'username_format' ),
			'class'    => 'auto-save',
			'options'  => [
				'email_address' => __( 'Email Address' ),
				'first_last'    => __( 'First + Last', 'groundhogg-pro' ),
				'last_first'    => __( 'Last + First', 'groundhogg-pro' ),
				'custom'        => __( 'Custom Format' )
			],
			'selected' => $this->get_setting( 'username_format', 'email_address' ),
		] );

		if ( $this->get_setting( 'username_format' ) === 'custom' ) {

			echo html()->e( 'p', [], __( 'Declare the format for custom usernames...', 'groundhogg-pro' ) );

			echo html()->input( [
				'name'  => $this->setting_name_prefix( 'custom_username_format' ),
				'value' => $this->get_setting( 'custom_username_format', '{first}_{last}' ),
			] );
		}

		echo html()->e( 'div', [
			'class' => 'display-flex gap-10 align-center'
		], [
			html()->e( 'p', [], __( "Send a notification to the new user about their account?", 'groundhogg' ) ),
			html()->checkbox( [
				'label'    => __( 'Yes' ),
				'name'     => $this->setting_name_prefix( 'send_new_user_notification' ),
				'value'    => '1',
				'checked'  => (bool) $this->get_setting( 'send_new_user_notification' ),
				'required' => false,
			] )
		] );

		echo html()->e( 'p', [], __( 'What if the user already exists?', 'groundhogg-pro' ) );

        global $wp_roles;

		$role_name = translate_user_role( $wp_roles->roles[ $role ]['name'] );

		echo html()->dropdown( [
			'name'        => $this->setting_name_prefix( 'if_user_exists' ),
			'option_none' => __( 'Do nothing' ),
			'options'     => [
				'set' => sprintf( 'Set the role to %s', $role_name ),
				'add' => sprintf( 'Add %s as an additional role', $role_name )
			],
			'selected'    => $this->get_setting( 'if_user_exists', 'email_address' ),
		] );

		?><p></p><?php

	}

	/**
	 * Save the step settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {
		$this->save_setting( 'role', sanitize_text_field( $this->get_posted_data( 'role', 'subscriber' ) ) );
		$this->save_setting( 'username_format', sanitize_text_field( $this->get_posted_data( 'username_format', 'email_address' ) ) );
		$this->save_setting( 'if_user_exists', sanitize_text_field( $this->get_posted_data( 'if_user_exists', false ) ) );
		$this->save_setting( 'custom_username_format', sanitize_text_field( $this->get_posted_data( 'custom_username_format', '' ) ) );
		$this->save_setting( 'send_new_user_notification', (bool) $this->get_posted_data( 'send_new_user_notification', false ) );
	}

	/**
	 * Process the apply tag step...
	 *
	 * @param $contact Contact
	 * @param $event   Event
	 *
	 * @return bool
	 */
	public function run( $contact, $event ) {

		$email_address = $contact->get_email();

		$password = wp_generate_password();

		$role = $this->get_setting( 'role', 'subscriber' );

		// Email already exists...
		if ( email_exists( $email_address ) ) {
			$user = get_user_by( 'email', $email_address );

			if ( ! $user ) {
				return false;
			}

			// don't modify privileged users
			if ( user_can( $user, 'edit_contacts' ) ) {
				return false;
			}

			$if_user_exists = $this->get_setting( 'if_user_exists' );

			switch ( $if_user_exists ) {
				default:
					return true;
				case 'set':
					$user->set_role( $role );
					break;
				case 'add':
					$user->add_role( $role );
					break;
			}

			$user_id = $user->ID;
			$contact->update( [ 'user_id' => $user_id ] );

			return true;
		}

		switch ( $this->get_setting( 'username_format' ) ) {
			default:
			case 'email_address':
				$username = $contact->get_email();
				break;
			case 'first_last':
				$username = strtolower( sprintf( "%s_%s", $contact->get_first_name(), $contact->get_last_name() ) );
				break;
			case 'last_first':
				$username = strtolower( sprintf( "%s_%s", $contact->get_last_name(), $contact->get_first_name() ) );
				break;
			case 'custom':
				$username = strtolower( do_replacements( $this->get_setting( 'custom_username_format', '{first}_{last}' ), $contact ) );
				break;
		}

		// More or less guaranteed unique at this point.
		$username = generate_unique_username( $username );

		$user_id = wp_create_user( $username, $password, $email_address );
		$user    = new \WP_User( $user_id );
		$user->set_role( $role );

		$user->first_name = $contact->get_first_name();
		$user->last_name  = $contact->get_last_name();

		wp_update_user( $user );

		if ( $this->get_setting( 'send_new_user_notification' ) ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}

		$contact->update( [ 'user_id' => $user_id ] );

		return true;
	}
}

/**
 * Ensure a username is unique by checking if it is already taken, and if it is adding a unique string after it.
 *
 * @param string $username
 * @param bool   $known_exists to avoid double-checking the same username during every recursion
 *
 * @return string
 */
function generate_unique_username( $username, $known_exists = false ) {

	$username = sanitize_user( $username );

	if ( ! $known_exists && ! username_exists( $username ) ) {
		return $username;
	}

	$new_username = uniqid( $username . '_' );

	if ( ! username_exists( $new_username ) ) {
		return $new_username;
	} else {
		return generate_unique_username( $username, true );
	}
}
