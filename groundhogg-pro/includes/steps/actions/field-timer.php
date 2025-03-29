<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Properties;
use Groundhogg\Step;
use Groundhogg\Utils\DateTimeHelper;
use function Groundhogg\do_replacements;
use function Groundhogg\force_custom_step_names;
use function Groundhogg\get_time_format;
use function Groundhogg\has_replacements;
use function Groundhogg\html;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delay Timer
 *
 * This allows the adition of an event which "does nothing" but runs at the specified time according to the time provided.
 * Essentially delaying proceeding events.
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Actions
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */
class Field_Timer extends Timer {

	/**
	 * @return string
	 */
	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/actions/field-timer/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Field Timer', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'field_timer';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Pause for the specified amount of time before a date in the meta.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
//		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/field-timer.png';
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/field-timer.svg';
	}

	/**
	 * @param $step Step
	 */
	public function settings( $step ) {

		?>
		<div id="step_<?php echo $step->get_id() ?>_field_timer_settings"></div>
		<?php

		parent::settings( $step );

	}

	/**
	 * Save the step settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {

	}

	public function generate_step_title( $step ) {

		$amount          = $this->get_setting( 'delay_amount', 3 );
		$type            = $this->get_setting( 'delay_type', 'days' );
		$run_when        = $this->get_setting( 'run_when', 'now' );
		$run_time        = $this->get_setting( 'run_time', '09:30:00' );
		$date_field      = $this->get_setting( 'date_field' );
		$before_or_after = $this->get_setting( 'before_or_after', 'before' );

		if ( $field = Properties::instance()->get_field( $date_field ) ) {
			$date_field = $field['label'];
		} else {
			$date_field = '<code>' . $date_field . '</code>';
		}

		$name = [ 'Wait until' ];

		if ( $type !== 'no_delay' ) {
			$name[] = strtolower( sprintf( '<b>%d %s %s</b>', $amount, $type, $before_or_after ) );
		}

		$name[] = '<b>' . $date_field . '</b>';

		if ( $run_when !== 'now' ) {
			$name[] = sprintf( 'and run at <b>%s</b>', date_i18n( get_time_format(), strtotime( $run_time ) ) );
		}

		return implode( ' ', $name );
	}

	/**
	 * Override the parent and set the run time of this function to the settings
	 *
	 * @param int  $baseTimestamp
	 * @param Step $step
	 *
	 * @return int
	 */
	public function calc_run_time( int $baseTimestamp, Step $step ): int {

		$contact = $step->enqueued_contact;

		$amount          = absint( $this->get_setting( 'delay_amount' ) );
		$type            = $this->get_setting( 'delay_type' );
		$run_when        = $this->get_setting( 'run_when' );
		$run_time        = $this->get_setting( 'run_time' );
		$before_or_after = $this->get_setting( 'before_or_after', 'before' );
		$date_field      = $this->get_setting( 'date_field' );
		$in_local_tz     = $this->get_setting( 'run_in_local_tz', false );
		$timezone        = $in_local_tz ? $contact->get_time_zone( false ) : wp_timezone();

		// Using replacements for dynamic time
		if ( has_replacements( $date_field ) ) {
			$date = do_replacements( $date_field, $contact );
		} // Assume we are retrieving a meta field
		else {
			$date = $contact->get_meta( $date_field );
		}

		// Compat for unix timestamp
		if ( is_numeric( $date ) ) {
			$date = absint( $date );
		}

		try {
			$date = new DateTimeHelper( $date, $timezone );
		} catch ( \Exception $e ){
			return parent::calc_run_time( $baseTimestamp, $step );
		}

		if ( $date->getTimestamp() < 0 ) {
			return parent::calc_run_time( $baseTimestamp, $step );
		}

		if ( $run_when === 'later' && $run_time ){
			$date->modify( $run_time );
		}

		if ( $type !== 'no_delay' && $amount && $type && $before_or_after ) {
			$date->modify( sprintf( '%s%d %s', $before_or_after === 'before' ? '-' : '+', $amount, $type ) );
		}

		// This will return UTC-0 timestamp YAY!
		return $date->getTimestamp();
	}

}
