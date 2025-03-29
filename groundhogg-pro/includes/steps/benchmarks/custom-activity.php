<?php

namespace GroundhoggPro\Steps\Benchmarks;

use Groundhogg\Classes\Activity;
use Groundhogg\Contact;
use Groundhogg\Step;
use Groundhogg\Steps\Benchmarks\Benchmark;
use function Groundhogg\code_it;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Edit Meta
 *
 * This allows the user to add information to a contact depeding on where they are in their customer journey. Potentially using them as merge fields later on.
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Actions
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */
class Custom_Activity extends Benchmark {

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Custom Activity', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'custom_activity';
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
		return _x( 'Listen for custom activity.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/custom-activity.svg';
	}

	public function settings( $step ) {
		?>
        <div id="step_<?php echo $step->get_id() ?>_custom_activity"></div>
		<?php
	}

	/**
	 * Save the settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {

	}

	public function generate_step_title( $step ) {

		$type = $this->get_setting( 'type' );

		if ( ! $type ) {
			return 'Custom activity tracked';
		}

		return sprintf( 'Tracked %s', code_it( $type ) );
	}

	protected function get_complete_hooks() {
		return [
			'groundhogg/track_activity' => 2
		];
	}

	/**
	 *
	 * @param $activity Activity
	 * @param $contact  Contact
	 *
	 * @return void
	 */
	public function setup( $activity, $contact ) {
		$this->add_data( 'activity', $activity );
		$this->add_data( 'contact', $contact );
	}

	protected function get_the_contact() {
		return $this->get_data( 'contact' );
	}

	protected function can_complete_step() {
		$activity = $this->get_data( 'activity' );

		$type = $this->get_setting( 'type', '' );

		if ( $type !== $activity->activity_type ) {
			return false;
		}

		$conditions = $this->get_setting( 'conditions', [] );

		foreach ( $conditions as $condition ) {
			$key   = $condition[0];
			$comp  = $condition[1];
			$check = $condition[2];

            if ( empty( $key ) ){
                continue;
            }

			$val = $activity->$key;

			switch ( $comp ) {
				default:
				case 'equals':
				case '=':
					$pass = $check == $val;
					break;
				case '!=':
				case 'not_equals':
					$pass = $check != $val;
					break;
				case 'contains':
					$pass = strpos( $val, $check ) !== false;
					break;
				case 'not_contains':
					$pass = strpos( $val, $check ) === false;
					break;
				case 'starts_with':
				case 'begins_with':
					$pass = str_starts_with( $val, $check );
					break;
				case 'does_not_start_with':
					$pass = ! str_starts_with( $val, $check );
					break;
				case 'ends_with':
					$pass = str_ends_with( $val, $check );
					break;
				case 'does_not_end_with':
					$pass = ! str_ends_with( $val, $check );
					break;
				case 'empty':
					$pass = empty( $check );
					break;
				case 'not_empty':
					$pass = ! empty( $check );
					break;
				case 'less_than':
					$pass = $val < $check;
					break;
				case 'greater_than':
					$pass = $val > $check;
					break;
				case 'greater_than_or_equal_to':
					$pass = $val >= $check;
					break;
				case 'less_than_or_equal_to':
					$pass = $val <= $check;
					break;
			}

			if ( ! $pass ) {
				return false;
			}
		}

		return true;

	}
}
