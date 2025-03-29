<?php

namespace GroundhoggPro;

use GroundhoggPro\Classes\Superlink;
use function Groundhogg\add_managed_rewrite_rule;
use function Groundhogg\get_contactdata;
use function Groundhogg\is_managed_page;
use function Groundhogg\isset_not_empty;

class Rewrites {
	/**
	 * Rewrites constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'groundhogg/install_custom_rewrites', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_filter( 'request', [ $this, 'parse_query' ] );
		add_action( 'template_redirect', [ $this, 'template_redirect' ] );
	}

	/**
	 * Add the rewrite rules required for the Preferences center.
	 */
	public function add_rewrite_rules() {

		// New tracking structure.
		add_managed_rewrite_rule(
			'superlinks/link/([^/]*)/?$',
			'subpage=superlink&superlink_id=$matches[1]'
		);

	}

	/**
	 * Add the query vars needed to manage the request.
	 *
	 * @param $vars
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'subpage';
		$vars[] = 'action';
		$vars[] = 'superlink_id';

		return $vars;
	}

	/**
	 * Maps a function to a specific query var.
	 *
	 * @param $query
	 *
	 * @return mixed
	 */
	public function parse_query( $query ) {
		return $query;
	}

	/**
	 * Perform Superlink/link click benchmark stuff.
	 *
	 * @param string $template
	 */
	public function template_redirect( $template = '' ) {

		if ( ! is_managed_page() ) {
			return;
		}

		$subpage = get_query_var( 'subpage' );

		switch ( $subpage ) {
			case 'superlink':

				$superlink_id = get_query_var( 'superlink_id' );

				$superlink    = new Superlink( $superlink_id );

				if ( $superlink->exists() ) {
					$superlink->process( get_contactdata() );
				}

				break;
		}
	}

	/**
	 * @param $array
	 * @param $key
	 * @param $func
	 */
	public function map_query_var( &$array, $key, $func ) {
		if ( ! function_exists( $func ) ) {
			return;
		}

		if ( isset_not_empty( $array, $key ) ) {
			$array[ $key ] = call_user_func( $func, $array[ $key ] );
		}
	}
}
