<?php

namespace GroundhoggPro\Steps\Actions;

use Groundhogg\Contact;
use Groundhogg\Event;
use Groundhogg\Properties;
use Groundhogg\Step;
use Groundhogg\Steps\Actions\Action;
use function Groundhogg\andList;
use function Groundhogg\bold_it;
use function Groundhogg\code_it;
use function GroundhoggPro\do_meta_changes;

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
class Edit_Meta extends Action {
	/**
	 * @return string
	 */
	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/actions/edit-meta/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Edit Meta', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'edit_meta';
	}

	public function get_sub_group() {
		return 'crm';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Directly edit the meta data of the contact.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/edit-meta.svg';
	}

	public function settings( $step ) {
		?>
        <div id="step_<?php echo $step->get_id() ?>_edit_meta_settings"></div>
		<?php
	}

	protected function maybe_upgrade() {
		$keys      = $this->get_setting( 'meta_keys' );
		$values    = $this->get_setting( 'meta_values' );
		$functions = $this->get_setting( 'meta_functions' );

		$changes = $this->get_setting( 'changes' ) ?: [];

		if ( ! empty( $keys ) && empty( $changes ) ) {
			foreach ( $keys as $i => $key ) {
				$changes[] = [ $key, $functions[ $i ], $values[ $i ] ];
			}

			$this->save_setting( 'changes', $changes );
		}
	}

	/**
	 * Save the settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {

		$changes = $this->get_setting( 'changes', [] );

		if ( empty( $changes ) ) {
			return;
		}

		foreach ( $changes as &$change ) {
			$change[0] = sanitize_key( $change[0] );
			$change[1] = sanitize_text_field( $change[1] );
			$change[2] = sanitize_text_field( $change[2] );
		}

		$this->save_setting( 'changes', $changes );
	}

	public function generate_step_title( $step ) {

		$changes = $this->get_setting( 'changes', [] );
		$keys    = wp_list_pluck( $changes, 0 );

        if ( empty( $changes ) ){
            return 'Edit meta';
        }

		if ( count( $keys ) > 4 ) {
			return sprintf( 'Edit <b>%s</b> custom fields', count( $keys ) );
		}

		$keys = array_map( function ( $key ) {

			$field = Properties::instance()->get_field( $key );

			if ( ! $field ) {
				return code_it( $key );
			}

			return bold_it( $field['label'] );

		}, $keys );

        if ( count( $keys ) > 1 ){
	        return sprintf( 'Edit %s', andList( $keys ) );
        }

		$key = $keys[0];
		$function = $changes[0][1];
		$modifier = code_it( esc_html( $changes[0][2] ) );

		switch ( $function ) {
			default:
			case 'set':
				return sprintf( 'Set %s to %s', $key, $modifier );
			case 'add':
				return sprintf( 'Increase %s by %s', $key, $modifier );
			case 'subtract':
				return sprintf( 'Decrease %s by %s', $key, $modifier );
			case 'multiply':
				return sprintf( 'Multiply %s by %s', $key, $modifier );
			case 'divide':
				return sprintf( 'Divide %s by %s', $key, $modifier );
			case 'delete':
				return sprintf( 'Delete %s', $key );
		}
	}

	/**
	 * Process the http post step...
	 *
	 * @param $contact Contact
	 * @param $event   Event
	 *
	 * @return bool|object
	 */
	public function run( $contact, $event ) {

		$this->maybe_upgrade();

		$changes = $this->get_setting( 'changes' ) ?: [];

		if ( empty( $changes ) ) {
			return false;
		}

		do_meta_changes( $contact, $changes );

		return true;

	}
}
