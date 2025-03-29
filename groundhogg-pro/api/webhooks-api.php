<?php

namespace GroundhoggPro\Api;

// Exit if accessed directly
use Groundhogg\Api\V3\Base;
use Groundhogg\Step;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhooks_Api extends Base {

	protected static $response = [];

	/**
	 *
	 *
	 * @param array $response
	 */
	public static function set_response( $response=[] ){
		self::$response = $response;
	}

	public function register_routes() {
		register_rest_route( self::NAME_SPACE, '/webhook-listener', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'complete' ],
				'permission_callback' => [ $this, 'webhook_token_auth' ],
				'args'                => [
					'step_id'    => [
						'required'    => true,
						'description' => __( 'The ID of the step.', 'groundhogg-pro' )
					],
					'auth_token' => [
						'required'    => true,
						'description' => __( 'The token to log in and allow the step to complete.', 'groundhogg-pro' )
					]
				]
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return self::SUCCESS_RESPONSE();
				},
				'permission_callback' => '__return_true',
			]
		] );

	}

	/**
	 * token auth that is independent of user privileges.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function webhook_token_auth( WP_REST_Request $request ) {

		$step  = intval( $request->get_param( 'step_id' ) );
		$token = $request->get_param( 'auth_token' );

		$step              = new Step( $step );
		$md5ed_saved_token = $step->get_meta( 'webhook_auth_token' );

		return $md5ed_saved_token === md5( $token );

	}

	/**
	 * provide a method for webhook to add/move contacts in a funnel.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return mixed|WP_Error|WP_REST_Response
	 */
	public function complete( WP_REST_Request $request ) {

		/* Get the step ID */
		$step = intval( $request->get_param( 'step_id' ) );
		$step = new Step( $step );

		/* Was the step ID given */
		if ( ! $step->exists() ) {
			return self::ERROR_400( 'INVALID_STEP_ID', __( 'You must provide a valid step ID.', 'groundhogg-pro' ) );
		}

		if ( $step->get_type() !== 'webhook_listener' ) {
			return self::ERROR_400( 'INVALID_STEP_TYPE', __( 'The provided step was not of type "webhook_listener".', 'groundhogg-pro' ) );
		}

		/* cast to array */
		$params = $request->get_params();
		$params = wp_unslash( $params );
		$params = map_deep( $params, 'sanitize_text_field' );

		// Add compat for encoded JSON in the encoded response by adding more slashes!
		$step->update_meta( 'last_response', $params );

		/* Check that the step is active and can be completed. */
		if ( ! $step->is_active() ) {
			return self::ERROR_400( 'STEP_INACTIVE', __( 'The given step is currently inactive.', 'groundhogg-pro' ) );
		}

		do_action( 'groundhogg/pro/webhook/api/listener', $step, $params );

		if ( ! empty( self::$response ) ) {
			return self::SUCCESS_RESPONSE( self::$response );
		}

		return self::ERROR_500( 'COULD_NOT_COMPLETE', __( 'Could not complete webhook benchmark.', 'groundhogg-pro' ) );

	}

}