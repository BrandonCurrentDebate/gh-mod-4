<?php

namespace GroundhoggPro\Steps\Benchmarks;

use Groundhogg\Contact;
use Groundhogg\Plugin;
use Groundhogg\Step;
use Groundhogg\Steps\Benchmarks\Benchmark;
use function Groundhogg\array_bold;
use function Groundhogg\create_contact_from_user;
use function Groundhogg\html;
use function Groundhogg\orList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Role Changed
 *
 * This will run whenever a user's role is changed to the specified role
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Benchmarks
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */
class Role_Changed extends Benchmark {

	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/benchmarks/role-changed/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Role Changed', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'role_changed';
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
		return _x( "Runs whenever a user's role is changed.", 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/role-changed.svg';
	}


	/**
	 * @param $step Step
	 */
	public function settings( $step ) {

		echo html()->e( 'p', [], __( 'Run when a user\'s role is changed to any of the following...', 'groundhogg-pro' ) );

		echo html()->select2( [
			'name'     => $this->setting_name_prefix( 'role' ) . '[]',
			'multiple' => true,
			'options'  => Plugin::$instance->roles->get_roles_for_select(),
			'selected' => $this->get_setting( 'role' ),
		] );

		?><p></p><?php
	}

	/**
     * Generate a step title for the step
     *
	 * @param $step
	 *
	 * @return false|string
	 */
	public function generate_step_title( $step ) {

		$roles = $this->get_setting( 'role', [] );
		$roles = is_array( $roles ) ? $roles : [ $roles ];

		if ( empty( $roles ) ) {
			return 'When a user\'s role is changed.';
		}

		$roles = array_map( function ( $role ) {
			global $wp_roles;

			return $wp_roles->roles[ $role ]['name'];
		}, $roles );

		return sprintf( 'When a user\'s role is changed to %s', orList( array_bold( $roles ) ) );
	}

	/**
	 * Save the step settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {
		$this->save_setting( 'role', array_map( 'sanitize_text_field', $this->get_posted_data( 'role', [ 'subscriber' ] ) ) );
	}

	/**
	 * get the hook for which the benchmark will run
	 *
	 * @return string[]
	 */
	protected function get_complete_hooks() {
		return [ 'set_user_role' => 3, 'add_user_role' => 2 ];
	}

	/**
	 * @param $userId    int the ID of a user.
	 * @param $cur_role  string the new role of the user
	 * @param $old_roles array list of previous user roles.
	 */
	public function setup( $userId, $cur_role, $old_roles = array() ) {
		$this->add_data( 'user_id', $userId );
		$this->add_data( 'role', $cur_role );
	}


	/**
	 * Get the contact from the data set.
	 *
	 * @return Contact
	 */
	protected function get_the_contact() {
		return create_contact_from_user( $this->get_data( 'user_id' ) );
	}

	/**
	 * Based on the current step and contact,
	 *
	 * @return bool
	 */
	protected function can_complete_step() {
		$roles = $this->get_setting( 'role', [] );
		$roles = is_array( $roles ) ? $roles : [ $roles ];

		// Any role change
		if ( empty( $roles ) ) {
			return true;
		}

		$added_role = $this->get_data( 'role' );

		return in_array( $added_role, $roles );
	}
}
