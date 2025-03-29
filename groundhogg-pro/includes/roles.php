<?php

namespace GroundhoggPro;

class Roles extends \Groundhogg\Roles {

	/**
	 * Returns an array  of role => [
	 *  'role' => '',
	 *  'name' => '',
	 *  'caps' => []
	 * ]
	 *
	 * In this case caps should just be the meta cap map for other WP related stuff.
	 *
	 * @return array[]
	 */
	public function get_roles() {
		return [];
	}


	/**
	 * Superlinks:
	 * - Add Superlinks
	 * - Delete Superlinks
	 * - Edit Superlinks
	 *
	 * Get caps related to managing superlinks
	 *
	 * @return array
	 */
	public function get_superlink_caps() {
		$caps = array(
			'add_superlinks',
			'delete_superlinks',
			'edit_superlinks',
			'view_superlinks',
		);

		return apply_filters( 'groundhogg/pro/caps/superlinks', $caps );
	}


	public function get_administrator_caps() {
		return $this->get_superlink_caps();
	}

	public function get_marketer_caps() {
		return $this->get_superlink_caps();
	}


	/**
	 * Return a cap to check against the admin to ensure caps are also installed.
	 *
	 * @return mixed
	 */
	protected function get_admin_cap_check() {
		return 'add_superlinks';
	}
}
