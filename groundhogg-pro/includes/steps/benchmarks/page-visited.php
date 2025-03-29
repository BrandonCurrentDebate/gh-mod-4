<?php

namespace GroundhoggPro\Steps\Benchmarks;

use Groundhogg\Classes\Page_Visit;
use Groundhogg\Contact;
use Groundhogg\Step;
use Groundhogg\Steps\Benchmarks\Benchmark;
use function Groundhogg\html;
use function Groundhogg\orList;
use function GroundhoggPro\check_url_matches;

/**
 * Page Visited
 *
 * This will run whenever a page is visited
 *
 * @since       File available since Release 0.9
 * @subpackage  Elements/Benchmarks
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Page_Visited extends Benchmark {

	public function get_help_article() {
		return 'https://docs.groundhogg.io/docs/builder/benchmarks/page-visited/';
	}

	/**
	 * Get the element name
	 *
	 * @return string
	 */
	public function get_name() {
		return _x( 'Page Visited', 'step_name', 'groundhogg-pro' );
	}

	/**
	 * Get the element type
	 *
	 * @return string
	 */
	public function get_type() {
		return 'page_visited';
	}

	public function get_sub_group() {
		return 'activity';
	}

	/**
	 * Get the description
	 *
	 * @return string
	 */
	public function get_description() {
		return _x( 'Runs whenever the specified page is visited.', 'step_description', 'groundhogg-pro' );
	}

	/**
	 * Get the icon URL
	 *
	 * @return string
	 */
	public function get_icon() {
//		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/page-visited.png';
		return GROUNDHOGG_PRO_ASSETS_URL . '/images/funnel-icons/page-visited.svg';
	}

	/**
	 * @param $step Step
	 */
	public function settings( $step ) {

		echo html()->e( 'p', [], html()->e( 'b', [], __( 'Run when contact visit pages matching the following rules...', 'groundhogg-pro' ) ) );

		echo html()->textarea( [
			'name'  => $this->setting_name_prefix( 'url_match' ),
			'id'    => $this->setting_id_prefix( 'url_match' ),
			'value' => $this->get_setting( 'url_match' ),
			'cols'  => null,
			'class' => 'code full-width'
		] );

		echo html()->description( __( 'URL paths matching the above rules will be tracked.', 'groundhogg-pro' ) );

		echo html()->description( sprintf( __( 'For example, adding <code>/my-page/</code> would match <code>%s/my-page/</code> and any child pages.', 'groundhogg-pro' ), site_url() ) );

		echo html()->description( __( 'To match an exact path use <code>^</code> at the beginning of a path <code>$</code> at the end.', 'groundhogg-pro' ) );

		echo html()->description( __( 'Query strings <code>?foo=bar</code> and fragments <code>#foo</code> are ignored.', 'groundhogg-pro' ) );
	}

	/**
	 * Save the step settings
	 *
	 * @param $step Step
	 */
	public function save( $step ) {
		$matches = sanitize_textarea_field( $this->get_posted_data( 'url_match' ) );

		$matches = explode( PHP_EOL, $matches );

		$matches = array_map( function ( $match ){

			$home_url = untrailingslashit( home_url() );
			$scheme   = wp_parse_url( $home_url, PHP_URL_SCHEME );

			if ( str_starts_with( $match, $scheme ) ){
				$match = str_replace( $home_url, '^', $match );
			}

			return $match;
		}, $matches );


		$this->save_setting( 'url_match', implode( PHP_EOL, $matches ) );
	}

	/**
	 * get the hook for which the benchmark will run
	 *
	 * @return int[]
	 */
	protected function get_complete_hooks() {
		return [ 'groundhogg/tracking/page_visit' => 2 ];
	}

	/**
	 * Hook handler
	 *
	 * @param $visit   Page_Visit
	 * @param $contact Contact
	 */
	public function setup( $visit, $contact ) {
		$this->add_data( 'visit', $visit );
		$this->add_data( 'contact', $contact );
	}

	public function generate_step_title( $step ) {

		$urls = explode( PHP_EOL, $this->get_setting( 'url_match' ) );

		return 'Visits ' . orList( array_map( '\Groundhogg\code_it', $urls ) );
	}

	/**
	 * Get the contact from the data set.
	 *
	 * @return Contact
	 */
	protected function get_the_contact() {
		return $this->get_data( 'contact' );
	}

	/**
	 * Based on the current step and contact,
	 *
	 * @return bool
	 */
	protected function can_complete_step() {

		/**
		 * @var $visit Page_Visit
		 */

		$visit     = $this->get_data( 'visit' );
		$match_url = $this->get_setting( 'url_match' );

		if ( ! $match_url || ! is_string( $match_url ) ) {
			return false;
		}

		return check_url_matches( $visit->path, $match_url );
	}
}
