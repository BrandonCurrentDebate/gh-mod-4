<?php

namespace Groundhogg\Steps\Actions;

use Groundhogg\Contact;
use Groundhogg\Event;
use Groundhogg\Step;
use Groundhogg\Utils\DateTimeHelper;
use function Groundhogg\force_custom_step_names;
use function Groundhogg\get_date_time_format;
use function Groundhogg\html;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DelayDateTime extends DateTimeHelper {

	private $min;
	private $max;

	public function setMin() {
		$this->min = $this->getTimestamp();
	}

	public function setMax() {
		$this->max = $this->getTimestamp();
	}

	public function useMax() {
		$this->setTimestamp( $this->max );
	}

	/**
	 * Modify the date but don't make it smaller than the min and larger than the max
	 *
	 * @param $modifier
	 *
	 * @return $this
	 */
	public function minMax( $modifier ) {

		$orig = $this->getTimestamp();

		$this->modify( $modifier );

		// Don't make it smaller than the min
		if ( $this->min && $this->getTimestamp() >= $this->min ) {
			if ( ! $this->max || $this->getTimestamp() < $this->max ) {
				$this->setMax();
			}
		}

		// Set the timestamp back to the orig
		$this->setTimestamp( $orig );

		return $this;
	}

	public function isPast() {
		return $this->getTimestamp() < time();
	}
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
class Delay_Timer extends Action {

	const TYPE = 'delay_timer';

	/**
	 * @return string
	 */
	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/actions/delay-timer/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Delay Timer', 'step_name', 'groundhogg' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'delay_timer';
	}

    public function get_sub_group() {
	    return 'delay';
    }

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Pause for the specified amount of time.', 'step_description', 'groundhogg' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
//		return GROUNDHOGG_ASSETS_URL . '/images/funnel-icons/delay-timer.png';
		return GROUNDHOGG_ASSETS_URL . '/images/funnel-icons/delay-timer.svg';
	}

	public function admin_scripts() {
		wp_enqueue_script( 'groundhogg-funnel-delay-timer' );
	}

	/**
	 * @param $step Step
	 */
	public function settings( $step ) {
		echo html()->e( 'div', [
			'id' => "step_{$step->ID}_delay_timer_settings"
		], 'Delay Timer' );

	}

	/**
     * Show a preview of the run time
     *
	 * @param Step $step
	 *
	 * @return void
	 */
	protected function before_step_notes( Step $step ) {

		?>
        <div class="gh-panel">
            <div class="gh-panel-header">
                <h2><?php _e( 'Delay Preview' ) ?></h2>
            </div>
            <div class="inside">
				<?php

				$date = new DelayDateTime( 'now' );

				$date->setTimestamp( self::calc_run_time( time(), $step ) );

				echo html()->e( 'div', [
					'class' => "display-flex gap-10 column"
				], [
					'<b>' . __( 'Runs on...' ) . '</b>',
					'<span>' . $date->wpDateTimeFormat() . '</span>'
				] );

				?>
            </div>
        </div>
		<?php
	}

    public function save( $step ) {
        // silence is golden
    }

	public function generate_step_title( $step ) {
	    return $step->get_meta( 'delay_preview' );
    }

	/**
     * Replaces the get_enqueue_time() method and utilizes a base timestamp
     *
	 * @throws \Exception
	 *
	 * @param Step $step
	 * @param int  $baseTimestamp
	 *
	 * @return int
	 */
	public function calc_run_time( int $baseTimestamp, Step $step ): int {

		$settings = wp_parse_args( $step->get_meta(), [
			'delay_amount'      => 3,
			'delay_type'        => 'days',
			'run_on_type'       => 'any',
			'run_when'          => 'now',
			'run_time'          => '09:00:00',
			'send_in_timezone'  => false,
			'run_time_to'       => '17:00:00',
			'run_on_dow_type'   => 'any', // Run on days of week type
			'run_on_dow'        => [], // Run on days of week
			'run_on_month_type' => 'any', // Run on month type
			'run_on_months'     => [], // Run on months
			'run_on_dom'        => [], // Run on days of month,
		] );

		$contact = $step->enqueued_contact;
		$date    = new DelayDateTime( $baseTimestamp );
		$tz      = $settings['send_in_timezone'] && $contact ? $contact->get_time_zone( false ) : wp_timezone();
		$date->setTimezone( $tz );

		// The base amount of time which we need to wait for
		if ( $settings['delay_type'] !== 'none' ) {
			$date->modify( sprintf( '+%d %s', $settings['delay_amount'], $settings['delay_type'] ) );
		}

		switch ( $settings['run_when'] ) {

			default:
			case 'now':
				// do nothing
				break;
			case 'later':

				$date->modify( $settings['run_time'] );

				if ( $date->isPast() ) {
					$date->modify( '+1 day' );
				}

				break;
			case 'between':

				$from = clone $date;
				$from->modify( $settings['run_time'] );
				$to = clone $date;
				$to->modify( $settings['run_time_to'] );

				// If the time does not fall within the given from/to modify it to the next day run time.
				if ( $date < $from ) {
					$date->modify( $settings['run_time'] );
				}

				if ( $date > $to ) {
					$date->modify( '+1 day ' . $settings['run_time'] );
				}

				break;
		}

		$date->setMin();

		$next_year = date( 'Y', strtotime( '+1 year' ) );
		$time      = $date->format( 'H:i:s' );

		// The date to run on
		switch ( $settings['run_on_type'] ) {
			default:
			case 'any':
				// Do nothing :)
				break;
			case 'weekday':
				// If it is not a weekday modify to the next Monday
				if ( ! in_array( $date->format( 'l' ), [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ] ) ) {
					$date->modify( "next Monday {$time}" );
				}
				break;
			case 'weekend':
				// If is a weekday modify to the following saturday
				if ( ! in_array( $date->format( 'l' ), [ 'Saturday', 'Sunday' ] ) ) {
					$date->modify( "next Saturday {$time}" );
				}
				break;
			case 'day_of_week':

				$run_on_dow_type       = $settings['run_on_dow_type'];
				$run_on_month_type     = $settings['run_on_month_type'];
				$selected_days_of_week = $settings['run_on_dow'];

				// Generate a list of all possible combinations of days and months
				// TODO There is probably a more efficient way to do this other than brute forcing it.
				foreach ( $selected_days_of_week as $day_of_week ) {

					if ( $run_on_month_type !== 'any' ) {

						foreach ( $settings['run_on_months'] as $month ) {

							if ( $run_on_dow_type === 'any' ) {
								foreach ( [ 'first', 'second', 'third', 'fourth', 'last' ] as $type ) {
									$date->minMax( "$type $day_of_week of $month $time" );
									$date->minMax( "$type $day_of_week of $month $next_year $time" );
								}
							} else {
								$date->minMax( "$run_on_dow_type $day_of_week of $month $time" );
								$date->minMax( "$run_on_dow_type $day_of_week of $month $next_year $time" );
							}

						}

					} else {

						if ( $run_on_dow_type === 'any' ) {
							$date->minMax( "$day_of_week $time" );
							$date->minMax( "next $day_of_week $time" );
						} else {
							$date->minMax( "$run_on_dow_type $day_of_week of this month $time" );
							$date->minMax( "$run_on_dow_type $day_of_week of next month $time" );
						}

					}

				}

				$date->useMax();

				break;
			case 'day_of_month':

				// Generate a list of all possible combinations of days and months
				// TODO There is probably a more efficient way to do this other than brute forcing it.
				foreach ( $settings['run_on_dom'] as $day_of_month ) {

					if ( $settings['run_on_month_type'] !== 'any' ) {

						foreach ( $settings['run_on_months'] as $month ) {

							if ( $day_of_month === 'last' ) {
								$date->minMax( "last day of $month this year" );
								$date->minMax( "last day of $month $next_year" );
							} else {

								// do this year and next year
								$date->minMax( "$month $day_of_month" );
								$date->minMax( "$month $day_of_month $next_year" );
							}

						}

					} else {
						if ( $day_of_month === 'last' ) {
							$date->minMax( "last day of this month" );
							$date->minMax( "last day of next month" );
						} else {

							$thisMonth = $date->format( 'F' );

							$date->minMax( "$thisMonth $day_of_month" );

							$nextMonthDate = clone $date;
							$nextMonthDate->modify( '+1 month' );

							$date->minMax( $nextMonthDate->format( "Y-m-$day_of_month" ) );

						}
					}
				}

				$date->useMax();

				break;
		}

		return $date->getTimestamp();
	}

	/**
	 * Delay timers don't do anything, they just have the delay and enqueue the next step.
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
