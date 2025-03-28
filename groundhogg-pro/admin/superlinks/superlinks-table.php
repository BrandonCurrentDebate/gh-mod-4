<?php

namespace GroundhoggPro\Admin\Superlinks;

use GroundhoggPro\Classes\Superlink;
use function Groundhogg\dashicon;
use function Groundhogg\do_replacements;
use function Groundhogg\get_db;
use function Groundhogg\get_request_query;
use function Groundhogg\get_screen_option;
use function Groundhogg\get_url_var;
use Groundhogg\Plugin;
use Groundhogg\Tag;
use VisualComposer\Modules\Settings\Traits\SubMenu;
use WP_List_Table;
use function Groundhogg\html;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Superlinks Table
 *
 * This is the Superlinks table, has basic actions and shows basic info about a superlink.
 *
 * @since       File available since Release 0.1
 * @subpackage  Admin/Supperlinks
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Admin
 */


// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Superlinks_Table extends WP_List_Table {
	/**
	 * TT_Example_List_Table constructor.
	 *
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 */
	public function __construct() {
		// Set parent defaults.
		parent::__construct( array(
			'singular' => 'superlink',     // Singular name of the listed records.
			'plural'   => 'superlinks',    // Plural name of the listed records.
			'ajax'     => false,       // Does this table support ajax?
		) );
	}

	/**
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information.
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />', // Render a checkbox instead of text.
			'name'        => _x( 'Name', 'Column label', 'groundhogg-pro' ),
			'source'      => _x( 'Source Url', 'Column label', 'groundhogg-pro' ),
			'replacement' => _x( 'Replacement Code', 'Column label', 'groundhogg-pro' ),
//			'tags'        => _x( 'Tags', 'Column label', 'groundhogg-pro' ),
			//'clicks' => _x( 'Clicks', 'Column label', 'groundhogg-pro' ),
			'target'      => _x( 'Target Url', 'Column label', 'groundhogg-pro' ),
		);

		return $columns;
	}

	/**
	 * @return array An associative array containing all the columns that should be sortable.
	 */
	protected function get_sortable_columns() {
		$sortable_columns = array(
			'name'        => array( 'name', false ),
//            'target'    => array( 'target', false ),
			'replacement' => array( 'replacement', false ),
			'tags'        => array( 'tags', false ),
			'clicks'      => array( 'clicks', false ),
		);

		return $sortable_columns;
	}


	/**
	 * @param $superlink Superlink
	 *
	 * @return string
	 */
	protected function column_name( $superlink ) {
		return "<a class='row-title edit-link' data-id='{$superlink->ID}' href='#'>" . esc_html( $superlink->get_name() ) . "</a>";	}

	/**
	 * @param $superlink Superlink
	 *
	 * @return string
	 */
	protected function column_target( $superlink ) {
		return html()->e( 'div', [
			'class' => 'gh-input-group'
		], [
			html()->input( [
				'class'    => 'code full-width',
				'readonly' => true,
				'onfocus'  => 'this.select()',
				'value'    => $superlink->get_target_url(),
			] ),
			html()->e( 'a', [
				'class' => 'gh-button secondary icon small',
				'href'  => do_replacements( $superlink->get_target_url() ),
			], dashicon( 'external' ) )
		] );
	}

	/**
	 * @param $superlink Superlink
	 *
	 * @return string
	 */
	protected function column_replacement( $superlink ) {

		return html()->input( [
			'class'    => 'code full-width',
			'readonly' => true,
			'onfocus'  => 'this.select()',
			'value'    => sprintf( '{superlink.%d}', $superlink->get_id() )
		] );

	}

	/**
	 * @param $superlink Superlink
	 *
	 * @return string
	 */
	protected function column_source( $superlink ) {

		return html()->input( [
			'class'    => 'code full-width',
			'readonly' => true,
			'onfocus'  => 'this.select()',
			'value'    => $superlink->get_source_url(),
		] );
	}

	/**
	 * @param $superlink Superlink
	 *
	 * @return string
	 */

	protected function column_tags( $superlink ) {

		?>
    <div class="gh-tags" title="<?php esc_attr_e( 'Tags' ); ?>">
		<?php
		$tags = $superlink->get_tags();

		foreach ( array_splice( $tags, 0, 10 ) as $tag ):
			$tag = new Tag( $tag ) ?><span
                class="gh-tag"><?php esc_html_e( $tag->get_name() ); ?></span><?php endforeach; ?>
		<?php if ( count( $tags ) > 0 ): ?>
			<?php printf( __( 'and %s more...', 'groundhogg' ), count( $tags ) ); ?>
		<?php endif; ?>
        </div><?php
	}

	/**
	 * Get default column value.
	 *
	 * @param object $superlink   A singular item (one full row's worth of data).
	 * @param string $column_name The name/slug of the column to be processed.
	 *
	 * @return string Text or HTML to be placed inside the column <td>.
	 */
	protected function column_default( $superlink, $column_name ) {

		return print_r( $superlink->$column_name, true );

	}

	/**
	 * @param  $superlink Superlink A singular item (one full row's worth of data).
	 *
	 * @return string Text to be placed inside the column <td>.
	 */
	protected function column_cb( $superlink ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
			$superlink->get_id()       // The value of the checkbox should be the record's ID.
		);
	}

	/**
	 * @return array An associative array containing all the bulk steps.
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'delete' => _x( 'Delete', 'List table bulk action', 'groundhogg-pro' ),
		);

		return apply_filters( 'wpgh_superlink_bulk_actions', $actions );
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 3.1.0
	 *
	 * @param object $item The current item
	 *
	 */
	public function single_row( $item ) {
		echo "<tr id=\"link-{$item->ID}\">";
		$this->single_row_columns( new Superlink( absint( $item->ID ) ) );
		echo '</tr>';
	}


	/**
	 * Prepares the list of items for displaying.
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 */
	function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = absint( get_url_var( 'limit', get_screen_option( 'per_page' ) ) );
		$paged    = $this->get_pagenum();
		$offset   = $per_page * ( $paged - 1 );
		$search   = get_url_var( 's' );
		$order    = get_url_var( 'order', 'DESC' );
		$orderby  = get_url_var( 'orderby', 'ID' );

		$args = array(
			'search'  => $search,
			'limit'   => $per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby,
		);

		$events = get_db( 'superlinks' )->query( $args );
		$total  = get_db( 'superlinks' )->count( $args );

		$this->items = $events;

		// Add condition to be sure we don't divide by zero.
		// If $this->per_page is 0, then set total pages to 1.
		$total_pages = $per_page ? ceil( (int) $total / (int) $per_page ) : 1;

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		) );
	}

	/**
	 * Generates and displays row action superlinks.
	 *
	 * @param        $superlink   Superlink Contact being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 *
	 * @return string Row steps output for posts.
	 */
	protected function handle_row_actions( $superlink, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = array();
		$title   = $superlink->get_name();

		$actions['edit'] = sprintf(
			'<a data-id="%d" href="%s" class="editinline edit-link" aria-label="%s">%s</a>',
			$superlink->get_id(),
            '#',
			esc_attr( sprintf( __( 'Edit' ), $title ) ),
			__( 'Edit' )
		);

		$actions['delete'] = sprintf(
			'<a data-id="%d" href="%s" class="submitdelete delete-link" aria-label="%s">%s</a>',
			$superlink->get_id(),
			'#',
			esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $title ) ),
			__( 'Delete' )
		);

		return $this->row_actions( $actions );
	}
}
