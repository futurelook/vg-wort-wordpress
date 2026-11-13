<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zentrale Bootstrap-Klasse: instanziiert alle Komponenten und lädt Assets.
 */
class VGWort_ZM_Plugin {

	private static $instance = null;

	private function __construct() {
		load_plugin_textdomain( 'vgwort-zaehlmarken', false, dirname( plugin_basename( VGWORT_ZM_FILE ) ) . '/languages' );

		new VGWort_ZM_Settings();
		new VGWort_ZM_Admin_Pool();
		new VGWort_ZM_Post_Meta();
		new VGWort_ZM_Post_Column();
		new VGWort_ZM_Frontend();
		new VGWort_ZM_Ajax();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function enqueue_admin_assets( $hook ) {
		$settings = VGWort_ZM_Settings::get();
		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && in_array( $screen->post_type, $settings['post_types'], true ) ) {
			wp_enqueue_style( 'vgwort-zm-admin', VGWORT_ZM_URL . 'assets/css/admin.css', array(), VGWORT_ZM_VERSION );
			wp_enqueue_script( 'vgwort-zm-admin', VGWORT_ZM_URL . 'assets/js/admin.js', array( 'jquery' ), VGWORT_ZM_VERSION, true );
			wp_localize_script(
				'vgwort-zm-admin',
				'vgwortZm',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( VGWort_ZM_Ajax::NONCE_ACTION ),
					'i18n'    => array(
						'confirmUnassign' => __( 'Zuweisung wirklich aufheben? Die Zählmarke wird wieder in den Pool zurückgelegt.', 'vgwort-zaehlmarken' ),
						'confirmSend'     => __( 'Meldung jetzt an VG Wort senden? Dies kann nicht rückgängig gemacht werden.', 'vgwort-zaehlmarken' ),
					),
				)
			);
		}

		if ( 'tools_page_vgwort-zm-pool' === $hook ) {
			wp_enqueue_style( 'vgwort-zm-admin', VGWORT_ZM_URL . 'assets/css/admin.css', array(), VGWORT_ZM_VERSION );
		}
	}
}
