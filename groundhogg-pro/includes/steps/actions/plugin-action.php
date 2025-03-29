<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Step;
use Groundhogg\Steps\Actions\Action;
use function Groundhogg\html;

class Plugin_Action extends Action {

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Plugin API Action', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'plugin_action';
	}

	public function get_sub_group() {
		return 'developer';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Developer friendly action.' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . 'images/funnel-icons/plugin-action.svg';
	}

	/**
	 * Display the settings based on the given ID
	 *
	 * @param $step Step
	 */
	public function settings( $step ) {

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Action' ) ) );

		echo html()->input( [
			'id'    => $this->setting_id_prefix( 'call_name' ),
			'name'  => $this->setting_name_prefix( 'call_name' ),
			'value' => $this->get_setting( 'call_name' ),
			'class' => 'full-width code'
		] );
		echo html()->description( __( 'The plugin action to do.', 'groundhogg-pro' ) );

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Usage' ) ) );


		echo html()->textarea( [
			'class'    => 'code full-width',
			'value'    => "<?php

add_filter( '{$this->get_setting('call_name')}', 'my_custom_function', 10, 2 );

/**
 * Your custom function to run
 * 
 * @param \$success bool
 * @param \$contact \Groundhogg\Contact
 */
function my_custom_function( \$success, \$contact ){
	
	// Return out if the action previously failed or the contact does not exist
	if ( ! \$success || ! \Groundhogg\is_a_contact( \$contact ) ){
		return \$success;
	}
	
	// todo you code here
	\$contact->update_meta( 'some_field', 'your data' );
	
	return true;
}

?>",
			'cols'     => '',
			'wrap'     => 'off',
			'readonly' => true,
			'onfocus'  => "this.select()"
		] );

		echo html()->description( __( 'Copy and paste the above code into a custom plugin or your theme\'s functions.php file.', 'groundhogg-pro' ) );
	}

	/**
	 * @param \Groundhogg\Contact $contact
	 * @param \Groundhogg\Event   $event
	 *
	 * @return bool|mixed|void
	 */
	public function run( $contact, $event ) {
		return apply_filters( $this->get_setting( 'call_name' ), true, $contact );
	}

	/**
	 * Save the step based on the given ID
	 *
	 * @param $step Step
	 */
	public function save( $step ) {
		$this->save_setting( 'call_name', sanitize_key( $this->get_posted_data( 'call_name' ) ) );
	}

	public function generate_step_title( $step ) {
		return sprintf( 'Call <code>%s</code>', $this->get_setting( 'call_name' ) );
	}
}
