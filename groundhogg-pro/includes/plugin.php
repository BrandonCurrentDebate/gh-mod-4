<?php

namespace GroundhoggPro;

use Groundhogg\Api\V3\API_V3;
use Groundhogg\Api\V4\Base_Api;
use Groundhogg\DB\Manager;
use Groundhogg\Extension;
use GroundhoggPro\Admin\Broadcasts\Resend_To_Unopened;
use GroundhoggPro\Admin\Superlinks\Superlinks_Page;
use GroundhoggPro\Api\Plugin_Api as REST_Plugin_Api;
use GroundhoggPro\Api\Superlinks_Api;
use GroundhoggPro\Api\Webhooks_Api;
use GroundhoggPro\DB\Superlinks;
use GroundhoggPro\Steps\Actions\Advanced_Timer;
use GroundhoggPro\Steps\Actions\Apply_Owner;
use GroundhoggPro\Steps\Actions\Create_User;
use GroundhoggPro\Steps\Actions\Date_Timer;
use GroundhoggPro\Steps\Actions\Edit_Meta;
use GroundhoggPro\Steps\Actions\Field_Timer;
use GroundhoggPro\Steps\Actions\HTTP_Post;
use GroundhoggPro\Steps\Actions\Loop;
use GroundhoggPro\Steps\Actions\New_Activity;
use GroundhoggPro\Steps\Actions\Plugin_Action;
use GroundhoggPro\Steps\Actions\Skip;
use GroundhoggPro\Steps\Benchmarks\Custom_Activity;
use GroundhoggPro\Steps\Benchmarks\Email_Opened;
use GroundhoggPro\Steps\Benchmarks\Field_Changed;
use GroundhoggPro\Steps\Benchmarks\Login_Status;
use GroundhoggPro\Steps\Benchmarks\Page_Visited;
use GroundhoggPro\Steps\Benchmarks\Plugin_Api;
use GroundhoggPro\steps\benchmarks\Post_Published;
use GroundhoggPro\Steps\Benchmarks\Role_Changed;
use GroundhoggPro\Steps\Benchmarks\Webhook_Listener;
use function Groundhogg\is_option_enabled;

class Plugin extends Extension {

	const DOWNLOAD_ID = 22397;

	/**
	 * Override the parent instance.
	 *
	 * @var Plugin
	 */
	public static $instance;

	/**
	 * Include any files.
	 *
	 * @return void
	 */
	public function includes() {
		require dirname( __FILE__ ) . '/functions.php';
	}

	public function funnel_editor_scripts( $funnel ) {
		wp_enqueue_script( 'groundhogg-pro-funnel-steps' );
	}

	/**
	 * Init any components that need to be added.
	 *
	 * @return void
	 */
	public function init_components() {
		$this->installer = new Installer();
		$this->updater   = new Updater();
		$this->roles     = new Roles();

		new Rewrites();
		new Handle_Timers();

		if ( is_admin() ){
			new Resend_To_Unopened();
		}

		add_action( 'groundhogg/admin/contacts/edit/scripts', function () {
			wp_enqueue_script( 'groundhogg-pro-activity' );
		} );
	}

	/**
	 * Get the ID number for the download in EDD Store
	 *
	 * @return int
	 */
	public function get_download_id() {
		return self::DOWNLOAD_ID;
	}

	public function register_settings( $settings ) {

		if ( $this->license_is_valid() ){

			$settings[ 'gh_use_unsubscribe_me' ] = [
				'id'      => 'gh_use_unsubscribe_me',
				'section' => 'unsubscribe',
				'label'   => _x( 'Automatically handle email unsubscribe notifications', 'groundhogg-pro' ),
				'desc'    => _x( 'This feature will automatically unsubscribe contacts when the <b>List Unsubscribe</b> method is used in some inboxes. <a href="https://help.groundhogg.io/article/890-how-the-list-unsubscribe-header-works">More info.</a>', 'groundhogg-pro' ),
				'type'    => 'checkbox',
				'atts'    => [
					'label' => __( 'Enable' ),
					'name'  => 'gh_use_unsubscribe_me',
					'id'    => 'gh_use_unsubscribe_me',
					'value' => 'on',
				],
			];

			if ( is_option_enabled( 'gh_use_unsubscribe_me' ) ){
				unset( $settings[ 'gh_unsubscribe_email' ] );
			}
		}

		return $settings;
	}

	/**
	 * register the new benchmark.
	 *
	 * @param \Groundhogg\Steps\Manager $manager
	 */
	public function register_funnel_steps( $manager ) {
		/*ACTIONS*/
		$manager->add_step( new Date_Timer() );
		$manager->add_step( new Field_Timer() );
		$manager->add_step( new Advanced_Timer() );
		$manager->add_step( new Apply_Owner() );
		$manager->add_step( new Create_User() );
		$manager->add_step( new Edit_Meta() );
		$manager->add_step( new HTTP_Post() );
		$manager->add_step( new Plugin_Action() );
		$manager->add_step( new Loop() );
		$manager->add_step( new Skip() );
		$manager->add_step( new New_Activity() );

		/* BENCHMARKS */
		$manager->add_step( new Page_Visited() );
		$manager->add_step( new Email_Opened() );
		$manager->add_step( new Role_Changed() );
		$manager->add_step( new Login_Status() );
		$manager->add_step( new Plugin_Api() );
		$manager->add_step( new Field_Changed() );
		$manager->add_step( new Webhook_Listener() );
		$manager->add_step( new Custom_Activity() );
		$manager->add_step( new Post_Published() );
	}

	public function register_admin_pages( $admin_menu ) {
		$admin_menu->superlinks = new Superlinks_Page();
	}

	/**
	 * Register the new DB.
	 *
	 * @param Manager $db_manager
	 */
	public function register_dbs( $db_manager ) {
		$db_manager->superlinks = new Superlinks();
	}

	public function register_admin_scripts( $is_minified, $IS_MINIFIED ) {

		wp_register_script( 'groundhogg-pro-funnel-steps', GROUNDHOGG_PRO_ASSETS_URL . 'js/admin/funnel-steps' . $IS_MINIFIED . '.js', [
			'jquery',
			'groundhogg-email-block-editor'
		], GROUNDHOGG_PRO_VERSION, true );

		wp_register_script( 'groundhogg-pro-data', GROUNDHOGG_PRO_ASSETS_URL . '/js/admin/data' . $IS_MINIFIED . '.js', [
			'groundhogg-admin-data',
		] );

		wp_register_script( 'groundhogg-pro-superlinks', GROUNDHOGG_PRO_ASSETS_URL . '/js/admin/superlinks' . $IS_MINIFIED . '.js', [
			'groundhogg-admin-element',
			'groundhogg-pro-data',
			'jquery-ui-sortable'
		] );

		wp_register_script( 'groundhogg-pro-activity', GROUNDHOGG_PRO_ASSETS_URL . '/js/admin/activity' . $IS_MINIFIED . '.js', [
			'groundhogg-pro-data',
			'groundhogg-admin-contact-editor',
		] );

		wp_localize_script( 'groundhogg-pro-data', 'GroundhoggPro', [
			'routes' => [
				'superlinks' => rest_url( Base_Api::NAME_SPACE . '/superlinks' ),
			]
		] );

		wp_register_script( 'groundhogg-resend-broadcast', GROUNDHOGG_PRO_ASSETS_URL . '/js/admin/resend-to-unopened' . $IS_MINIFIED . '.js', [
			'groundhogg-make-el',
			'groundhogg-admin',
			'groundhogg-admin-data',
			'groundhogg-admin-send-broadcast'
		] );
	}

	public function register_admin_styles() {

	}

	/**
	 * @param $api_manager API_V3
	 */
	public function register_apis( $api_manager ) {
		$api_manager->webhooks        = new Webhooks_Api();
		$api_manager->rest_plugin_api = new REST_Plugin_Api();
	}

	/**
	 * @param $api_manager
	 *
	 * @return void
	 */
	public function register_v4_apis( $api_manager ) {
		$api_manager->webhooks   = new Api\V4\Webhooks_Api();
		$api_manager->superlinks = new Superlinks_Api();
	}

	/**
	 * Get the version #
	 *
	 * @return mixed
	 */
	public function get_version() {
		return GROUNDHOGG_PRO_VERSION;
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return GROUNDHOGG_PRO__FILE__;
	}

	/**
	 * Register autoloader.
	 *
	 * Groundhogg autoloader loads all the classes needed to run the plugin.
	 *
	 * @since  1.6.0
	 * @access private
	 */
	protected function register_autoloader() {
		require dirname( __FILE__ ) . '/autoloader.php';
		Autoloader::run();
	}

	/**
	 * Register the superlink replacement
	 *
	 * @param \Groundhogg\Replacements $replacements
	 */
	public function add_replacements( $replacements ) {

		$replacements->add_group( 'superlinks', 'Superlinks' );

		$replacements->add( 'superlink', __NAMESPACE__ . '\replacement_superlink', _x( 'A superlink code. Usage: {superlink.id}', 'replacement', 'groundhogg-pro' ), 'Superlink', 'superlinks' );
	}
}

Plugin::instance();
