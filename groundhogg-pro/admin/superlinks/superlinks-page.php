<?php

namespace GroundhoggPro\Admin\Superlinks;

use Groundhogg\Admin\Admin_Page;
use Groundhogg\Plugin;
use GroundhoggPro\Classes\Superlink;
use function Groundhogg\get_db;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use function Groundhogg\validate_tags;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Superlinks Page
 *
 * This is the superlinks page, it also contains the add form since it's the same layout as the terms.php
 *
 * @since       File available since Release 0.1
 * @subpackage  Admin/Supperlinks
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Admin
 */
class Superlinks_Page extends Admin_Page {

	//UNUSED FUNCTIONS
	protected function add_ajax_actions() {
		add_action( 'wp_ajax_groundhogg_link_table_row', [ $this, 'link_table_row' ] );
	}

	protected function add_additional_actions() {
	}

	public function scripts() {
		wp_enqueue_style( 'groundhogg-admin' );
		wp_enqueue_style( 'groundhogg-admin-element' );
		wp_enqueue_script( 'groundhogg-pro-superlinks' );
	}

	public function get_slug() {
		return 'gh_superlinks';
	}

	public function get_name() {
		return _x( 'Superlinks', 'page_title', 'groundhogg-pro' );
	}

	public function get_cap() {
		return 'edit_superlinks';
	}

	public function get_item_type() {
		return 'superlink';
	}

	public function get_priority() {
		return 75;
	}

	/* Register the help bar */
	public function help() {
		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'gh_overview',
				'title'   => __( 'Overview' ),
				'content' => '<p>' . __( "Superlinks are special superlinks that allow you to apply/remove tags whenever clicked and then take the contact to a page of your choice. To use them, just copy the replacement code and paste in in email, button, or link.", 'groundhogg-pro' ) . '</p>'
			)
		);
	}

	/**
	 * @return string
	 */
	protected function get_title() {
		switch ( $this->get_current_action() ) {
			default:
			case 'add':
			case 'view':
				return $this->get_name();
				break;
			case 'edit':
				return _x( 'Edit Superlink', 'page_title', 'groundhogg-pro' );
				break;
		}
	}

	protected function get_title_actions() {
		return [
			[
				'link'   => '#',
				'id'     => 'add-link',
				'action' => __( 'Add New', 'groundhogg' ),
				'target' => '_self',
			]
		];
	}


	/**
	 * Add new superlink
	 *
	 * @return bool|\WP_Error
	 */
	public function process_add() {

		if ( ! current_user_can( 'add_superlinks' ) ) {
			$this->wp_die_no_access();
		}

		if ( ! get_request_var( 'superlink_name' ) ) {
			return new \WP_Error( 'no_name', __( 'Please enter super link name.', 'groundhogg-pro' ) );
		}

		if ( ! get_request_var( 'superlink_target' ) ) {
			return new \WP_Error( 'no_target', __( 'Please enter super link target URL.', 'groundhogg-pro' ) );
		}

		$superlink_tags = isset( $_POST['superlink_tags'] ) ? validate_tags( get_request_var( 'superlink_tags' ) ) : '';

		$args = [
			'name'   => sanitize_text_field( get_request_var( 'superlink_name' ) ),
			'target' => sanitize_text_field( get_request_var( 'superlink_target' ) ),
			'tags'   => $superlink_tags
		];

		$superlink_id = get_db( 'superlinks' )->add( $args );

		if ( ! $superlink_id ) {
			return new \WP_Error( 'unable_to_add_superlink', "Something went wrong adding the superlink." );
		}

		$this->add_notice( 'new-superlink', _x( 'Superlink created!', 'notice', 'groundhogg-pro' ) );

		return false;
	}

	/**
	 * Edit superlink from the admin
	 *
	 * @return bool|\WP_Error
	 */
	public function process_edit() {
		if ( ! current_user_can( 'edit_superlinks' ) ) {
			$this->wp_die_no_access();
		}

		if ( ! get_request_var( 'superlink' ) ) {
			return new \WP_Error( 'no_id', __( 'Given superlink not found!', 'groundhogg-pro' ) );
		}

		if ( ! get_request_var( 'superlink_name' ) ) {
			return new \WP_Error( 'no_name', __( 'Please enter super link name.', 'groundhogg-pro' ) );
		}

		if ( ! get_request_var( 'superlink_target' ) ) {
			return new \WP_Error( 'no_target', __( 'Please enter super link target URL.', 'groundhogg-pro' ) );
		}

		$id = absint( get_request_var( 'superlink' ) );

		$args = array(
			'name'   => sanitize_text_field( get_request_var( 'superlink_name' ) ),
			'target' => sanitize_text_field( get_request_var( 'superlink_target' ) ),
			'tags'   => validate_tags( get_request_var( 'superlink_tags', '' ) )
		);

		$result = get_db( 'superlinks' )->update( $id, $args );

		if ( ! $result ) {
			return new \WP_Error( 'unable_to_update_superlink', "Something went wrong while updating the Superlink..." );
		}

		$this->add_notice( 'updated', _x( 'Superlink updated!', 'notice', 'groundhogg-pro' ) );

		// Return false to return to main page.
		return false;

	}

	/**
	 * Delete superlink from the admin
	 *
	 * @return bool|\WP_Error
	 */
	public function process_delete() {

		if ( ! current_user_can( 'delete_superlinks' ) ) {
			$this->wp_die_no_access();
		}

		foreach ( $this->get_items() as $id ) {

			if ( ! Plugin::$instance->dbs->get_db( 'superlinks' )->delete( $id ) ) {
				return new \WP_Error( 'unable_to_delete_superlink', "Something went wrong while deleting the superlink." );
			}
		}

		$this->add_notice(
			'deleted',
			sprintf( _nx( '%d superlink deleted', '%d superlinks deleted', count( $this->get_items() ), 'notice', 'groundhogg-pro' ),
				count( $this->get_items() )
			)
		);

		return true;
	}

	public function view() {
		if ( ! class_exists( 'Superlinks_Table' ) ) {
			include dirname( __FILE__ ) . '/superlinks-table.php';
		}

		$superlinks_table = new Superlinks_Table();

		$this->search_form( __( 'Search Superlinks', 'groundhogg-pro' ) );
		?>
            <style>
                th#replacement{
                    width: 150px;
                }
            </style>
        <form method="post" class="wp-clearfix">
            <!-- search form -->
			<?php $superlinks_table->prepare_items(); ?>
			<?php $superlinks_table->display(); ?>
        </form>
		<?php
	}

	function edit() {
		if ( ! current_user_can( 'edit_superlinks' ) ) {
			$this->wp_die_no_access();
		}
		include dirname( __FILE__ ) . '/edit.php';
	}

	public function link_table_row() {
		if ( ! wp_doing_ajax() ) {
			return;
		}

		if ( ! current_user_can( 'edit_superlinks' ) ) {
			$this->wp_die_no_access();
		}

		$rule = new Superlink( get_post_var( 'link' ) );

		$rulesTable = new Superlinks_Table();

		ob_start();

		$rulesTable->single_row( $rule );

		$row = ob_get_clean();

		wp_send_json_success( [
			'row' => $row
		] );
	}
}
