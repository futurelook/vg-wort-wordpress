<?php
/**
 * Plugin Name:       VG Wort Zählmarken
 * Plugin URI:        https://example.com/vgwort-zaehlmarken
 * Description:       Verwaltet VG-Wort-Zählmarken: Import des Marken-Pools aus dem VG-Wort-Meldeportal, Zuweisung zu Beiträgen/Seiten und automatisches Einfügen des Zählpixels im Frontend.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            —
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vgwort-zaehlmarken
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Kein direkter Zugriff.
}

define( 'VGWORT_ZM_VERSION', '1.0.0' );
define( 'VGWORT_ZM_FILE', __FILE__ );
define( 'VGWORT_ZM_PATH', plugin_dir_path( __FILE__ ) );
define( 'VGWORT_ZM_URL', plugin_dir_url( __FILE__ ) );
define( 'VGWORT_ZM_TABLE', 'vgwort_marks' );

require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-install.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-api.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-pool.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-settings.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-post-meta.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-admin-pool.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-post-column.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-frontend.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-ajax.php';
require_once VGWORT_ZM_PATH . 'includes/class-vgwort-zm-plugin.php';

register_activation_hook( __FILE__, array( 'VGWort_ZM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VGWort_ZM_Install', 'deactivate' ) );

/**
 * Startet das Plugin nach dem Laden aller Plugins,
 * damit z. B. is_plugin_active() & Co. zuverlässig funktionieren.
 */
function vgwort_zm() {
	return VGWort_ZM_Plugin::instance();
}
add_action( 'plugins_loaded', 'vgwort_zm' );
