<?php

namespace GroundhoggPro\Admin\Broadcasts;

use Groundhogg\Broadcast;
use Groundhogg\Plugin;
use function Groundhogg\get_url_var;
use function Groundhogg\html;

class Resend_To_Unopened {

	public function __construct() {
		add_filter( 'groundhogg/admin/broadcasts/table/handle_row_actions', [ $this, 'add_row_action' ], 10, 2 );
		add_action( 'groundhogg/admin/gh_broadcasts/view/scripts', [ $this, 'scripts' ] );
	}

	/**
	 * Enqueue the resend-to-broadcast script
	 *
	 * @param $page
	 *
	 * @return void
	 */
	public function scripts( $page ){
		wp_enqueue_script( 'groundhogg-resend-broadcast' );
	}

	/**
	 * Add the re-send link here instead of as an additional column
	 *
	 * @param $actions   string[]
	 * @param $broadcast Broadcast
	 *
	 * @return string[]
	 */
	public function add_row_action( $actions, $broadcast ) {

		if ( $broadcast->is_sent() && $broadcast->is_email() ) {
			$actions['resend_to_unopened'] = html()->e( 'a', [
				'href'           => '#',
				'class'          => 'resend-unopened',
				'data-broadcast' => $broadcast->get_id(),
				'data-email'     => $broadcast->get_object_id()
			], __( 'Re-send to unopened' ) );
		}

		return $actions;
	}

}
