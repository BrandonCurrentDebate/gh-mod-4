<?php

namespace GroundhoggPro;

use Groundhogg\Contact;
use Groundhogg\Step;

class Handle_Timers {

	public function __construct() {
		add_filter( 'groundhogg/steps/enqueue', [ $this, 'handle_enqueue' ], 100, 3 );
	}

	/**
	 * Filter the enqueuing of a timer step as it may not be relevant.
	 *
	 * @param $enqueue bool
	 * @param $contact Contact
	 * @param $step    Step
	 *
	 * @return null
	 */
	public function handle_enqueue( $enqueue, $contact, $step ) {

		// If the enqueue was already set to false
		if ( ! $enqueue
		     // or the current step is not a timer
		     || ! in_array( $step->get_type(), [
				'date_timer',
				'advanced_timer',
				'field_timer',
			] ) ) {
			return $enqueue;
		}

		// If the delay time of the step is greater than the current time, return true.
		if ( $step->get_run_time() > time() ) {
			return true;
		}

		// If the timer was a date in the past, the execution time will be modified to a minute less than the current system time.
		// This will mean the scheduled time will be less than the enqueued time still and all is well.
		$date_passed = $step->get_meta( 'date_passed' ) ?: 'passthru';

		switch ( $date_passed ) {

			// Just queue up the next step instead
			default:
			case 'passthru':

				$next_step = $step->get_next_action();

				if ( $next_step instanceof Step && $next_step->is_active() ) {
					$next_step->enqueue( $contact );
				}

				break;

			// Skip to another step.
			case 'skip_to':

				$skip_to_step_id = absint( $step->get_meta( 'skip_to_step' ) );

				$step = new Step( $skip_to_step_id );

				if ( $step->exists() ) {
					$step->enqueue( $contact );
				}

				break;

			// Stop the funnel
			case 'stop':
				// do nothing...
				break;
		}

		return false;

	}

}
