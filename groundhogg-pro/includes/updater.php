<?php

namespace GroundhoggPro;

use Groundhogg\Step;
use function Groundhogg\get_db;
use function Groundhogg\words_to_key;

class Updater extends \Groundhogg\Updater {

	protected function get_plugin_file() {
		return GROUNDHOGG_PRO__FILE__;
	}

	/**
	 * A unique name for the updater to avoid conflicts
	 *
	 * @return string
	 */
	protected function get_updater_name() {
		return words_to_key( GROUNDHOGG_PRO_NAME );
	}

	/**
	 * Get a list of updates which are available.
	 *
	 * @return string[]
	 */
	protected function get_available_updates() {
		return [
			'2.1.6',
			'2.1.17',
			'2.3',
			'2.3.1',
			'2.6',
		];
	}

	/**
	 * List of automatic updates not requires user action
	 *
	 * @return string[]
	 */
	protected function get_automatic_updates() {
		return [
			'2.1.17',
			'2.3',
			'2.3.1',
			'2.6',
		];
	}

	/**
	 * Change default create user behaviour to 'set' the new user role if the user already exists to
	 * be consistent with current behaviour
	 */
	public function version_2_3_1() {

		$steps = get_db( 'steps' )->query( [
			'step_type' => 'field_changed'
		] );

		foreach ( $steps as $step ) {
			$step = new Step( $step );

			$value    = $step->get_meta( 'change_field_value' );
			$function = $step->get_meta( 'change_field_function' );

			// If the value is not empty, and no function has been defined, or the function is 'any'
			// set to 'equals' to match previous behaviour
			if ( ! empty( $value ) && ( empty( $function ) || $function === 'any' ) ){
				$step->update_meta( 'change_field_function', 'equals' );
			}
		}
	}

	/**
	 * Change default create user behaviour to 'set' the new user role if the user already exists to
	 * be consistent with current behaviour
	 */
	public function version_2_3() {

		$steps = get_db( 'steps' )->query( [
			'step_type' => 'create_user'
		] );

		foreach ( $steps as $step ) {
			$step = new Step( $step );
			$step->update_meta( 'if_user_exists', 'set' );
		}
	}

	/**
	 * Set any existing webhook listeners to use the older format
	 */
	public function version_2_1_17() {

		$webhook_listeners = get_db( 'steps' )->query( [
			'step_type' => 'webhook_listener'
		] );

		foreach ( $webhook_listeners as $webhook_listener ) {
			$webhook_listener = new Step( absint( $webhook_listener->ID ) );
			$webhook_listener->update_meta( 'is_pre_2_1_17', true );
		}
	}

	/**
	 * Force stats optin. You are using a premium plugin now, you dont get a choice.
	 */
	public function version_2_1_6() {
		\Groundhogg\Plugin::instance()->stats_collection->optin();
	}

	/**
	 * New superlink stuff
	 *
	 * @return void
	 */
	public function version_2_6(){
		$roles = new Roles();
		$roles->install_roles_and_caps();
		get_db('superlinks')->create_table();
	}
}
