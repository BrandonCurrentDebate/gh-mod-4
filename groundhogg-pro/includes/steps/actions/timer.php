<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Contact;
use Groundhogg\Event;
use Groundhogg\Step;
use Groundhogg\Steps\Actions\Action;
use function Groundhogg\get_db;
use function Groundhogg\html;
use function GroundhoggPro\get_steps_after_given_id;

abstract class Timer extends Action {

	protected function labels() {

		$passed = $this->get_setting( 'date_passed' );

		switch ( $passed ) {
			case 'skip_to':
				?>
				<div class="step-label">Skips</div>
				<?php

				break;
			case 'stop':
				?>
				<div class="step-label">Stops</div>
				<?php
				break;
		}
	}

	public function get_sub_group() {
		return 'delay';
	}

	/**
	 * Save date passed actions
	 *
	 * @param Step $step
	 */
	public function after_save( $step ) {

		$this->save_setting( 'date_passed', sanitize_text_field( $this->get_posted_data( 'date_passed' ) ) );
		$this->save_setting( 'skip_to_step', absint( $this->get_posted_data( 'skip_to_step' ) ) );

		parent::after_save( $step );
	}

	/**
	 * Date passed actions
	 *
	 * @param Step $step
	 */
	public function settings( $step ) {


		$available_steps      = get_steps_after_given_id( $step->get_id() );
		$skip_to_step_options = [];
		foreach ( $available_steps as $available_step ) {
			$skip_to_step_options[ $available_step->get_id() ] = sprintf( "%d. %s", $available_step->get_order(), $available_step->get_title() );
		}

		$skip_to_step = html()->dropdown( [
			'name'        => $this->setting_name_prefix( 'skip_to_step' ),
			'id'          => $this->setting_id_prefix( 'skip_to_step' ),
//			'class'       => 'gh-select2',
			'options'     => $skip_to_step_options,
			'selected'    => [ $this->get_setting( 'skip_to_step' ) ],
			'multiple'    => false,
			'placeholder' => 'Please Select One',
			'tags'        => false,
			'style'       => [ 'min-width' => '200px' ]
		] );

		echo html()->e( 'p', [], __( 'If the date has already passed...', 'groundhogg-pro' ) );
		echo html()->e( 'div', [
			'class' => 'gh-input-group'
		], [
			html()->dropdown( [
				'name'        => $this->setting_name_prefix( 'date_passed' ),
				'id'          => $this->setting_id_prefix( 'date_passed' ),
				'class'       => 'auto-save',
				'options'     => [
					'passthru' => __( 'Pass through to the next step', 'groundhogg-pro' ),
					'skip_to'  => __( 'Skip to a proceeding step', 'groundhogg-pro' ),
					'stop'     => __( 'Stop the funnel', 'groundhogg-pro' ),
				],
				'selected'    => $this->get_setting( 'date_passed', 'passthru' ),
				'multiple'    => false,
				'option_none' => false,
			] ),
			$this->get_setting( 'date_passed', 'passthru' ) === 'skip_to' ? $skip_to_step : '',
		] );

		?><p></p><?php

	}

	/**
	 * update the skip_to_step if defined
	 *
	 * @param $step Step
	 *
	 * @return void
	 */
	public function post_import( $step ) {

		// This will be the ID of the imported step
		$old_skip_to_step = absint( $step->get_meta( 'skip_to_step' ) );

		if ( ! $old_skip_to_step ) {
			return;
		}

		// the latest one will always be the most recently imported
		$meta = get_db( 'stepmeta' )->query( [
			'meta_key'   => 'imported_step_id',
			'meta_value' => $old_skip_to_step,
			'limit'      => 1,
			'orderby'    => 'step_id',
			'order'      => 'desc'
		] );

		$step_id = $meta[0]->step_id;

		$step->update_meta( 'skip_to_step', $step_id );

	}

	/**
	 * Timers always return true
	 *
	 * @param $contact Contact
	 * @param $event   Event
	 *
	 * @return true
	 */
	public function run( $contact, $event ) {
		//do nothing
		return true;
	}
}
