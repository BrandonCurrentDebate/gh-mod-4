<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Step;
use Groundhogg\Utils\DateTimeHelper;
use function Groundhogg\get_date_time_format;
use function Groundhogg\html;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date Timer
 *
 * This allows the adition of an event which "does nothing" but runs at the specified time according to the date provided.
 * Essentially delaying proceeding events.
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Actions
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */
class Date_Timer extends Timer {
	/**
	 * @return string
	 */
	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/actions/date-timer/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Date Timer', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'date_timer';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Pause until a specific date & time.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/date-timer.svg';
//		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/date-timer.png';
	}

	public function generate_step_title( $step ) {

		$run_date = $this->get_setting( 'run_date', date( 'Y-m-d', strtotime( '+1 day' ) ) );
		$run_time = $this->get_setting( 'run_time', '09:00:00' );

		$time_string = $run_date . ' ' . $run_time;

		return sprintf( 'Wait until <b>%s</b>', date_i18n( get_date_time_format(), strtotime( $time_string ) ) );
	}

	/**
	 * @param $step Step
	 */
	public function settings( $step ) {

		echo html()->e( 'p', [], __( 'Wait until...', 'groundhogg-pro' ) );

		echo html()->e( 'div', [
			'class' => 'gh-input-group'
		], [
			html()->input( [
				'class'       => 'input',
				'type'        => 'date',
				'name'        => $this->setting_name_prefix( 'run_date' ),
				'id'          => $this->setting_id_prefix( 'run_date' ),
				'value'       => $this->get_setting( 'run_date', date( 'Y-m-d', strtotime( '+3 days' ) ) ),
				'placeholder' => 'yyy-mm-dd',
			] ),
			html()->input( [
				'type'  => 'time',
				'class' => 'input',
				'name'  => $this->setting_name_prefix( 'run_time' ),
				'id'    => $this->setting_id_prefix( 'run_time' ),
				'value' => $this->get_setting( 'run_time', "09:00:00" ),
			] )
		] );

		echo html()->e( 'div', [
			'class' => 'display-flex gap-10 align-center'
		], [
			html()->e( 'p', [], __( "Run in the contact's timezone?", 'groundhogg' ) ),
			html()->checkbox( [
				'label'    => __( 'Yes' ),
				'name'     => $this->setting_name_prefix( 'send_in_timezone' ),
				'id'       => $this->setting_id_prefix( 'send_in_timezone' ),
				'value'    => '1',
				'checked'  => (bool) $this->get_setting( 'send_in_timezone' ),
				'title'    => __( "Run in the contact's local time.", 'groundhogg-pro' ),
				'required' => false,
			] )
		] );

		parent::settings( $step );
	}

	/**
	 * Compare timers to see if one which date comes first compared to the order of appearance
	 *
	 * @param $step1 Step
	 * @param $step2 Step
	 *
	 * @return int;
	 */
	public function compare_timer( $step1, $step2 ) {

		$step1_run_time = $this->calc_run_time( time(), $step1 );
		$step2_run_time = $this->calc_run_time( time(), $step2 );

		return $step2_run_time - $step1_run_time;

	}

	/**
	 * Save the step settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {
		$this->save_setting( 'run_date', date( 'Y-m-d', strtotime( $this->get_posted_data( 'run_date' ) ) ) );
		$this->save_setting( 'run_time', sanitize_text_field( $this->get_posted_data( 'run_time' ) ) );

		$send_in_timezone = $this->get_posted_data( 'send_in_timezone', false );
		$this->save_setting( 'send_in_timezone', (bool) $send_in_timezone );

		$other_timers = $this->get_like_steps( [ 'funnel_id' => $step->get_funnel_id() ] );

		foreach ( $other_timers as $date_timer ) {
			if ( $date_timer->get_order() < $step->get_order() && $this->compare_timer( $date_timer, $step ) < 0 ) {
				$this->add_error( 'timer-error', __( 'You have date timers with descending dates! Your funnel may not work as expected!' ) );
			}
		}

		if ( $this->calc_run_time( time(), $step ) < time() ) {
			$this->add_error( 'timer-error', __( 'You have date timers with dates in the past! Your funnel may not work as expected!' ) );
		}

	}

	/**
	 * @param Step $step
	 */
	public function validate_settings( Step $step ) {
		if ( $this->calc_run_time( time(), $step ) < time() ) {
			$step->add_error( 'timer-error', __( 'The selected date is in the past, consider updating the timer to a future date!' ) );
		}
	}

	/**
	 * Override the parent and set the run time of this function to the settings
	 *
	 *
	 * @param int  $baseTimestamp
	 * @param Step $step *
	 *
	 * @return int
	 */
	public function calc_run_time( int $baseTimestamp, Step $step ): int {

		$run_date         = $this->get_setting( 'run_date', date( 'Y-m-d', strtotime( '+1 day' ) ) );
		$run_time         = $this->get_setting( 'run_time', '09:00:00' );
		$send_in_timezone = $this->get_setting( 'send_in_timezone', false );
		$tz               = $send_in_timezone && $step->enqueued_contact ? $step->enqueued_contact->get_time_zone( false ) : wp_timezone();
		$time_string      = $run_date . ' ' . $run_time;

		// If we are sending in local time and there is an enqueued contact...
		$date = new DateTimeHelper( $time_string, $tz );

		return $date->getTimestamp();
	}
}
