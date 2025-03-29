<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Queue\Event_Queue;
use Groundhogg\Step;
use Groundhogg\Steps\Actions\Action;
use function Groundhogg\get_db;
use function Groundhogg\html;

class Skip extends Loop {

	public function get_name() {
		return 'Skip';
	}

	public function get_type() {
		return 'skip';
	}

	public function get_description() {
		return 'Skip to a proceeding step.';
	}

	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/skip.svg';
	}

	public function settings( $step ) {

		echo html()->e( 'p', [], __( 'Wait at least...', 'groundhogg-pro' ) );

		echo html()->e( 'div', [
			'class' => 'gh-input-group'
		], [
			html()->number( [
				'class'       => 'input',
				'name'        => $this->setting_name_prefix( 'delay_amount' ),
				'id'          => $this->setting_id_prefix( 'delay_amount' ),
				'value'       => $this->get_setting( 'delay_amount', 3 ),
				'placeholder' => 3,
			] ),
			// DELAY TYPE
			html()->dropdown( [
				'name'        => $this->setting_name_prefix( 'delay_type' ),
				'id'          => $this->setting_id_prefix( 'delay_type' ),
				'options'     => [
					'minutes' => __( 'Minutes' ),
					'hours'   => __( 'Hours' ),
					'days'    => __( 'Days' ),
					'weeks'   => __( 'Weeks' ),
					'months'  => __( 'Months' ),
				],
				'selected'    => $this->get_setting( 'delay_type', 'minutes' ),
				'option_none' => false,
			] )
		] );

		echo html()->e( 'p', [], __( 'And then run...', 'groundhogg-pro' ) );

		echo html()->e( 'div', [
			'class' => 'gh-input-group'
		], [
			html()->dropdown( [
				'name'        => $this->setting_name_prefix( 'run_when' ),
				'id'          => $this->setting_id_prefix( 'run_when' ),
				'class'       => 'run_when',
				'options'     => [
					'now'   => __( 'Immediately', 'groundhogg' ),
					'later' => __( 'At time of day', 'groundhogg' ),
				],
				'selected'    => $this->get_setting( 'run_when', 'now' ),
				'option_none' => false,
			] ),
			// RUN TIME
			html()->input( [
				'type'  => 'time',
				'class' => ( 'now' === $this->get_setting( 'run_when', 'now' ) ) ? 'input run_time hidden' : 'run_time input',
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

		echo html()->e( 'p', [], __( 'The skip to...', 'groundhogg-pro' ) );

		$proceeding = $step->get_proceeding_actions();

		$options = [];

		foreach ( $proceeding as $_s ) {
			$options[ $_s->get_id() ] = sprintf( '%d. %s', $_s->get_order(), sanitize_text_field( $_s->get_title() ) );
		}

		echo html()->dropdown( [
			'name'     => $this->setting_name_prefix( 'next' ),
			'options'  => $options,
			'selected' => $this->get_setting( 'next' )
		] );

		?><p></p><?php
	}

    public function generate_step_title( $step ) {

        $next = new Step( $this->get_setting( 'next' ) );

	    return sprintf( 'Skip to <b>%s</b>', $next->get_title() );
    }
}
