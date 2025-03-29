<?php

namespace GroundhoggPro\Classes;

use Groundhogg\Base_Object;
use Groundhogg\Contact;
use function Groundhogg\do_replacements;
use function Groundhogg\get_db;
use function Groundhogg\is_a_contact;
use function Groundhogg\managed_page_url;
use function Groundhogg\maybe_url_decrypt_id;
use function Groundhogg\no_and_amp;
use function Groundhogg\parse_tag_list;
use function Groundhogg\track_activity;
use function Groundhogg\track_live_activity;
use function GroundhoggPro\do_meta_changes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Superlink
 *
 * Process a superlink if one is in progress.
 *
 * @since       File available since Release 0.9
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Includes
 */
class Superlink extends Base_Object {

	public function __construct( $identifier_or_args = 0, $field = null ) {

		if ( is_string( $identifier_or_args ) && ! is_numeric( $identifier_or_args ) ) {

			$slug_or_id = maybe_url_decrypt_id( $identifier_or_args );

			// We got an ID
			if ( is_numeric( $slug_or_id ) ) {
				return parent::__construct( $slug_or_id, 'ID' );
			}

			// We got a slug
			$parts = explode( '-', $slug_or_id );
			$ID    = absint( $parts[0] );

			return parent::__construct( $ID, 'ID' );
		}

		return parent::__construct( $identifier_or_args, $field );
	}

	protected function get_db() {
		return get_db( 'superlinks' );
	}

	protected function get_object_type() {
		return 'superlink';
	}

	public function get_as_array() {
		return array_merge( parent::get_as_array(), [
			'add_tags'    => parse_tag_list( $this->tags, 'tags' ),
			'remove_tags' => parse_tag_list( $this->remove_tags, 'tags' ),
		] );
	}

	/**
	 * Parse tags when updating
	 *
	 * @param $data
	 *
	 * @return array|mixed
	 */
	protected function sanitize_columns( $data = [] ) {

		foreach ( $data as $column => &$value ){
			switch ( $column ){
				case 'tags':
				case 'remove_tags':
					$value = parse_tag_list( $value );
					break;
			}
		}

		return $data;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_source_url() {
		return trailingslashit( sprintf( managed_page_url( 'superlinks/link/%d-%s' ), $this->get_id(), sanitize_title( $this->get_name() ) ) );
	}

	public function get_replacement_code() {
		return sprintf( '{superlink.%d}', $this->get_id() );
	}

	protected function post_setup() {
		$this->tags        = wp_parse_id_list( $this->tags );
		$this->remove_tags = wp_parse_id_list( $this->remove_tags );

		if ( ! is_array( $this->meta_changes ) ) {
			$this->meta_changes = [];
		}
	}

	/**
	 * @param $contact Contact
	 */
	public function process( $contact ) {

		$target = do_replacements( $this->get_target_url(), $contact );
		$target = no_and_amp( $target );

		if ( is_a_contact( $contact ) ) {

			$contact->apply_tag( $this->tags );
			$contact->remove_tag( $this->remove_tags );

			do_meta_changes( $contact, $this->meta_changes );

			track_live_activity( 'clicked_superlink', [
				'superlink' => $this->get_id()
			] );
		}

		die( wp_redirect( $target ) );
	}

	/**
	 * @return array
	 */
	public function get_tags() {
		return wp_parse_id_list( $this->tags );
	}

	/**
	 * @return string
	 */
	public function get_target_url() {
		return $this->target;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return absint( $this->ID );
	}

}
