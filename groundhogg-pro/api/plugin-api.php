<?php

namespace GroundhoggPro\Api;

// Exit if accessed directly
use Groundhogg\Api\V3\Base;
use Groundhogg\Step;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use function Groundhogg\do_api_benchmark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Api extends Base {

	public function register_routes() {
		register_rest_route( self::NAME_SPACE, '/plugin-api/', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'complete' ],
				'permission_callback' => [ $this, 'auth' ],
				'args'                => [
					'call_name'           => [
						'required'    => true,
						'description' => __( 'The call name of api benchmark.', 'groundhogg-pro' )
					],
					'id_or_email' => [
						'required'    => true,
						'description' => __( 'The ID or email of the contact.', 'groundhogg-pro' )
					],
					'by_user_id' => [
						'required'    => false,
						'description' => __( 'If the contact should be referenced by the user ID.', 'groundhogg-pro' )
					]
				]
			]
		] );

	}


	/**
	 * Do an API trigger
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return mixed|WP_Error|WP_REST_Response
	 */
	public function complete( WP_REST_Request $request ) {

		$contact = self::get_contact_from_request( $request );

		if ( is_wp_error( $contact ) ){
			return $contact;
		}

		$call_name = sanitize_text_field( $request->get_param( 'call_name' ) );

		do_api_benchmark( $call_name, $contact->get_id(), false );

		return self::SUCCESS_RESPONSE();
	}

}