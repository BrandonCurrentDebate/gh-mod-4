<?php

namespace GroundhoggPro\Steps\Benchmarks;

use Groundhogg\Contact;
use Groundhogg\Step;
use Groundhogg\Steps\Benchmarks\Benchmark;
use function Groundhogg\get_contactdata;
use function Groundhogg\html;

class Plugin_Api extends Benchmark {


	/**
	 * get the hook for which the benchmark will run
	 *
	 * @return int[]
	 */
	protected function get_complete_hooks() {
		return [];
	}

	public function setup( $call_name = '', $id_or_email = '', $by_user_id = false ) {
	}

	/**
	 * Get the contact from the data set.
	 *
	 * @return Contact
	 */
	protected function get_the_contact() {
	}

	/**
	 * Based on the current step and contact,
	 *
	 * @return bool
	 */
	protected function can_complete_step() {
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Plugin API Benchmark', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'plugin_api';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Developer friendly benchmark.' );
	}

	public function get_sub_group() {
		return 'developer';
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
//		return GROUNDHOGG_PRO_ASSETS_URL . 'images/funnel-icons/plugin-api.png';
		return GROUNDHOGG_PRO_ASSETS_URL . 'images/funnel-icons/plugin-benchmark.svg';
	}

	public function generate_step_title( $step ) {
		return sprintf( '<code>%s</code> is called', $this->get_setting( 'call_name' ) );
	}

	/**
	 * Display the settings based on the given ID
	 *
	 * @param $step Step
	 */
	public function settings( $step ) {
		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Call Name' ) ) );

		echo html()->input( [
			'id'    => $this->setting_id_prefix( 'call_name' ),
			'name'  => $this->setting_name_prefix( 'call_name' ),
			'value' => $this->get_setting( 'call_name' ),
			'class' => 'code full-width',
		] );
		echo html()->description( __( 'The call name for the API trigger.', 'groundhogg-pro' ) );

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Usage' ) ) );


		echo html()->textarea( [
			'class'    => 'code full-width',
			'value'    => "<?php 

add_action( 'some_hook', 'my_handler_function' );

/**
 * A listener function for some arbitrary WordPress or plugin hook.
 *
 * @param \$args mixed
 */
function my_handler_function( \$args ) {

	// todo get the contact ID or email from any passed arguments

	/**
	 * @param \$call_name           string the call name from the step settings
	 * @param \$contact_id_or_email string|int the email or ID of the contact record
	 */
	\Groundhogg\do_plugin_api_benchmark( '{$this->get_setting('call_name')}', \$contact_id_or_email, false );

}

?>",
			'style'    => [ 'width' => '100%' ],
			'cols'     => '',
			'wrap'     => 'off',
			'readonly' => true,
			'onfocus'  => "this.select()"
		] );
		echo html()->description( __( 'Copy and paste the above code into a custom plugin or your theme\'s functions.php file.', 'groundhogg-pro' ) );
	}

	/**
	 * Save the step based on the given ID
	 *
	 * @param $step Step
	 */
	public function save( $step ) {
		$this->save_setting( 'call_name', sanitize_key( $this->get_posted_data( 'call_name' ) ) );
	}
}
