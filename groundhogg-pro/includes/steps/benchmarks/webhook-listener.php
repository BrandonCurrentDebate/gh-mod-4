<?php

namespace GroundhoggPro\Steps\Benchmarks;

use Exception;
use Groundhogg\Api\V4\Base_Api;
use Groundhogg\Contact;
use GroundhoggPro\Api\Webhooks_Api;
use function Groundhogg\do_replacements;
use function Groundhogg\generate_contact_with_map;
use function Groundhogg\get_array_var;
use Groundhogg\Step;
use Groundhogg\Steps\Benchmarks\Benchmark;
use function Groundhogg\html;
use function Groundhogg\get_mappable_fields;
use function GroundhoggPro\set_in_data;

class Webhook_Listener extends Benchmark {

	const TYPE = 'webhook_listener';

	public function get_name() {
		return __( 'Webhook Listener', 'groundhogg-pro' );
	}

	public function get_type() {
		return 'webhook_listener';
	}

	public function get_sub_group() {
		return 'developer';
	}

	public function get_description() {
		return __( 'Listen for requests from Webhooks.', 'groundhogg-pro' );
	}

	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/webhook.svg';
	}

	public function get_help_article() {
//        return 'https://docs.groundhogg.io/docs/extensions/webhook-integration/webhook-listener-2/';
	}

	protected function get_auth_token() {
		$token = $this->get_setting( 'webhook_auth_token_plaintext' );

		if ( $token ) {
			return $token;
		}

		$token = wp_generate_password( 7, false, false );
		$md5ed = md5( $token );

		$this->save_setting( 'webhook_auth_token_plaintext', $token );
		$this->save_setting( 'webhook_auth_token', $md5ed );

		return $token;
	}

	protected function get_last_request() {
		$request = $this->get_setting( 'last_response', [
			'error' => 'No request received yet.'
		] );

		// Backwards compat for storing data as json encoded string
		if ( ! is_array( $request ) && is_string( $request ) ) {
			$request = json_decode( $request, true );
		}

		if ( ! is_array( $request ) && is_object( $request ) ) {
			$request = json_decode( wp_json_encode( $request ), true );
		}

		// ignore these
//		unset( $request['slug'] );
//		unset( $request['step_id'] );
//		unset( $request['token'] );

		return $request;
	}

	public function settings( $step ) {

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Listener URL' ) ) );

		echo html()->input( [
			'type'     => 'text',
			'onfocus'  => 'this.select()',
			'class'    => 'full-width code',
			'value'    => add_query_arg( [
				'token' => $this->get_auth_token(),
			], rest_url( Base_Api::NAME_SPACE . '/webhooks/' . $step->get_slug() ) ),
			'readonly' => true,
		] );

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Request' ) ) );

		echo html()->textarea( [
			'name'        => $this->setting_name_prefix( 'last_response' ),
			'id'          => $this->setting_id_prefix( 'last_response' ),
			'class'       => 'code json full-width',
			'placeholder' => wp_json_encode( [ 'error' => 'No response received yet.' ] ),
			'value'       => wp_json_encode( $this->get_last_request(), JSON_PRETTY_PRINT ),
			'cols'        => 50,
			'rows'        => 4,
			'readonly'    => true,
		] );

		echo html()->description( __( 'The webhook listener accepts <code>POST</code> requests with a body encoding of <code>JSON</code> or <code>x-www-form-urlencoded</code>.', 'groundhogg-pro' ) );
		?><p><?php
		echo html()->modal_link( [
			'title'              => __( 'Map Request Fields', 'groundhogg-pro' ),
			'text'               => __( 'Map Request Fields', 'groundhogg-pro' ),
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
		echo html()->description( __( 'You can map the fields from the request to contact fields.', 'groundhogg-pro' ) );

		echo html()->wrap( $this->field_map_table(), 'div', [
			'class' => 'hidden field-map-wrapper',
			'id'    => $this->setting_id_prefix( 'field_map' )
		] );

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Response' ) ) );

		?>
        <div id="step_<?php echo $step->get_id() ?>_webhook_listener_settings"></div>
		<?php
	}

	public function save( $step ) {

		$this->maybe_upgrade();

		$this->save_setting( 'field_map', array_map( 'sanitize_key', $this->get_posted_data( 'field_map', [] ) ) );
	}

	protected function get_complete_hooks() {
		return [
			'groundhogg/pro/webhook/api/listener' => 2
		];
	}

	/**
	 * @param $step Step
	 * @param $params
	 */
	public function setup( $step, $params ) {
		$this->add_data( 'step_id', $step->get_id() );
		$this->add_data( 'params', $params );
	}

	protected function maybe_upgrade() {

		$response_type       = $this->get_setting( 'response_type' );
		$contact_in_response = $this->get_setting( 'contact_in_response' );

        if ( $contact_in_response === 'yes' && ! $response_type ){
            $this->save_setting( 'response_type', 'contact' );
        }

	}

	/**
	 * Get the contact record
	 *
	 * @throws Exception
	 * @return bool|false|Contact
	 */
	protected function get_the_contact() {

		$this->maybe_upgrade();

		// SKIP if not the right form.
		if ( ! $this->can_complete_step() ) {
			return false;
		}

		$posted_data = $this->array_flatten( $this->get_data( 'params' ) );
		$field_map   = $this->get_setting( 'field_map', [] );

		$contact = generate_contact_with_map( $posted_data, $field_map, [
			'type'    => 'webhook',
			'step_id' => $this->get_current_step()->get_id(),
		] );

		if ( ! $contact || is_wp_error( $contact ) ) {
			Webhooks_Api::set_response( [ 'warning' => 'Please setup your field mapping first.' ] );

			return false;
		}

		$response_type = $this->get_setting( 'response_type', 'none' );

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

				$body = $this->get_setting( 'body', [] );

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

		//v3
		Webhooks_Api::set_response( $response );

		//v4
		\GroundhoggPro\Api\V4\Webhooks_Api::set_response( $response );

		return $contact;

	}

	/**
	 * Check that the current benchmark is the one we want to use...
	 *
	 * @return bool
	 */
	protected function can_complete_step() {
		// Only check is to whether the steps are the same or not.
		return $this->get_data( 'step_id' ) === $this->get_current_step()->get_id();
	}

	/**
	 * Build the field mapping table
	 *
	 * @return false|string
	 */
	private function field_map_table() {
		$field_map = $this->get_setting( 'field_map' );

        if ( ! is_array( $field_map ) ){
            $field_map = [];
        }

		$request_fields = $this->get_last_request();

		$fields = $this->array_flatten( $request_fields );

		$ignore = array(
			'key',
			'token',
			'auth_token',
			'step_id',
			'slug',
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
				html()->wrap( esc_html( $value ), 'code' ),
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
				__( 'Value', 'groundhogg-pro' ),
				__( 'Map To', 'groundhogg-pro' ),
			],
			$rows
		);

		return ob_get_clean();
	}

	/**
	 * Wrapper in case of legacy array flatten in use
	 *
	 * @param array  $array
	 * @param string $parent_key
	 *
	 * @return array
	 */
	private function array_flatten( $array, $parent_key = '' ) {

		//  use the old array flatten method to preserve the keys...
		if ( $this->get_setting( 'is_pre_2_1_17' ) ) {
			return $this->array_flatten_old( $array );
		}

		return self::_array_flatten( $array, $parent_key );
	}

	/**
	 * Flattens a multidimensional array
	 *
	 * @param array  $array
	 * @param string $parent_key
	 *
	 * @return array
	 */
	public static function _array_flatten( $array, $parent_key = '' ) {

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
				$return = array_merge( $return, self::_array_flatten( $value, $key_prefix . $key ) );
			}
		}

		return $return;

	}

	/**
	 * Flattens a multidimensional array
	 * Only use if the webhook listener was used pre 2.1.17
	 *
	 * @param $array
	 *
	 * @return array
	 */
	private function array_flatten_old( $array ) {

		if ( ! is_array( $array ) ) {
			return [];
		}

		$return = array();

		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$return = array_merge( $return, $this->array_flatten( $value ) );
			} else {
				$return[ $key ] = $value;
			}
		}

		return $return;
	}
}
