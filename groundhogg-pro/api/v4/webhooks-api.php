<?php

namespace GroundhoggPro\Api\V4;

// Exit if accessed directly
use Groundhogg\Api\V4\Base_Api;
use Groundhogg\Step;
use GroundhoggPro\Steps\Benchmarks\Webhook_Listener;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function Groundhogg\do_replacements;
use function Groundhogg\generate_contact_with_map;
use function Groundhogg\is_a_contact;
use function Groundhogg\sanitize_payload;
use function GroundhoggPro\set_in_data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhooks_Api extends Base_Api {

	protected static $response = [];

	/**
	 *
	 *
	 * @param array $response
	 */
	public static function set_response( $response = [] ) {
		self::$response = $response;
	}

	public function register_routes() {

		register_rest_route( self::NAME_SPACE, '/webhooks/send-test', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test' ],
				'permission_callback' => [ $this, 'test_permission_callback' ],
			],
		] );

		register_rest_route( self::NAME_SPACE, '/webhooks/(?P<slug>[A-z0-9-]+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'listen' ],
				'permission_callback' => [ $this, 'token_auth' ],
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
	public function token_auth( WP_REST_Request $request ) {

		$slug  = $request->get_param( 'slug' );
		$token = $request->get_param( 'token' );

		$step = new Step( $slug );

		if ( ! $step->exists() ) {
			return false;
		}

		$md5ed_saved_token = $step->get_meta( 'webhook_auth_token' );

		return $md5ed_saved_token === md5( $token );

	}

	/**
	 * Can the current user send test webhooks
	 *
	 * @return bool
	 */
	public function test_permission_callback() {
		return current_user_can( 'read' );
	}

	/**
	 * Send a webhook test
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function test( WP_REST_Request $request ) {

		$step = new Step( $request->get_param( 'ID' ) );
		$step->update_meta( $request->get_param( 'meta' ) );

		$response = apply_filters( 'groundhogg/pro/test_webhook', null, $step );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return self::SUCCESS_RESPONSE( [
			'response' => $response
		] );
	}

	/**
	 * Provide a method for webhook to add/move contacts in a funnel.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function listen( WP_REST_Request $request ) {

		/* Get the step ID */
		$slug = $request->get_param( 'slug' );
		$step = new Step( $slug );

		/* Was the step ID given */
		if ( ! $step->exists() ) {
			return self::ERROR_400( 'INVALID_STEP_ID', __( 'You must provide a valid step ID.', 'groundhogg-pro' ) );
		}

		if ( ! $step->type_is( 'webhook_listener' ) ) {
			return self::ERROR_400( 'INVALID_STEP_TYPE', __( 'The provided step was not of type "webhook_listener".', 'groundhogg-pro' ) );
		}

		/* cast to array */
		$params          = $request->get_params();
		$params          = wp_unslash( $params );
		$sanitizedParams = sanitize_payload( $params );

		// Add compat for encoded JSON in the encoded response by adding more slashes!
		$step->update_meta( 'last_response', $sanitizedParams );

		/* Check that the step is active and can be completed. */
		if ( ! $step->is_active() ) {
			return self::ERROR_400( 'STEP_INACTIVE', __( 'The given step is currently inactive.', 'groundhogg-pro' ) );
		}

		$posted_data = Webhook_Listener::_array_flatten( $params );
		$field_map   = array_filter( $step->get_meta( 'field_map', [] ) );

		if ( empty ( $field_map ) ) {
			return new WP_Error( 'invalid_field_map', 'Configure your webhook listener field mapping first!' );
		}

		$contact = generate_contact_with_map( $posted_data, $field_map, [
			'type'    => 'webhook',
			'step_id' => $step->get_id(),
		] );

		if ( ! $contact || ! is_a_contact( $contact ) ) {
			return new WP_Error( 'error', 'Something went wrong' );
		}

		$response_type = $step->get_meta( 'response_type' );

		if ( ! $response_type && $step->get_meta( 'contact_in_response' ) === 'yes' ) {
			$response_type = 'contact';
			$step->update_meta( 'response_type', 'contact' );
		}

		switch ( $response_type ) {
			default:
			case 'none':
				$response = [
					'message' => __( 'Contact added to funnel successfully!', 'groundhogg-pro' )
				];
				break;
			case 'contact':
				$response = [
					'message' => __( 'Contact added to funnel successfully!', 'groundhogg-pro' ),
					'contact' => $contact
				];
				break;
			case 'json':

				$body = $step->get_meta( 'body' ) ?: [];

				$response = [];

				foreach ( $body as $pair ) {

					$key   = $pair[0];
					$value = $pair[1];

					if ( empty( $key ) ) {
						continue;
					}

					$value     = do_replacements( sanitize_text_field( $value ), $contact );
					$converted = json_decode( $value );
					$value     = $converted ?? $value;
					$keys      = explode( '.', $key );
					$response  = set_in_data( $response, $keys, $value );
				}

				break;
		}

		$step->benchmark_enqueue( $contact );

		return self::SUCCESS_RESPONSE( $response );
	}


}
