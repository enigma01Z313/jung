<?php
/**
 * Plugin Name: Simyatech Bookly Reports
 * Description: Caches Bookly's completed sessions into a flat table and exports them to CSV, both in progress-tracked batches so a large history never times out.
 * Version:     1.1.0
 * Author:      Farzin Ahamadi
 * Text Domain: bookly-exports
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BOOKLY_EXPORTS_VERSION', '1.1.0' );
define( 'BOOKLY_EXPORTS_FILE', __FILE__ );
define( 'BOOKLY_EXPORTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BOOKLY_EXPORTS_URL', plugin_dir_url( __FILE__ ) );

/** Everything is written in Tehran time, whatever the site's own timezone is. */
define( 'BOOKLY_EXPORTS_TIMEZONE', 'Asia/Tehran' );

/** Rows cached per AJAX round-trip. */
define( 'BOOKLY_EXPORTS_CACHE_BATCH', 100 );

/** Rows written to the CSV per AJAX round-trip. */
define( 'BOOKLY_EXPORTS_CSV_BATCH', 1000 );

require_once BOOKLY_EXPORTS_PATH . 'includes/class-bookly-exports-installer.php';
require_once BOOKLY_EXPORTS_PATH . 'includes/class-bookly-exports-repository.php';
require_once BOOKLY_EXPORTS_PATH . 'includes/class-bookly-exports-admin.php';
require_once BOOKLY_EXPORTS_PATH . 'includes/class-bookly-exports-ajax.php';

register_activation_hook( __FILE__, array( 'Bookly_Exports_Installer', 'activate' ) );

// The table is also checked on every admin load, so a plugin updated in place
// (rather than deactivated and reactivated) still ends up with it.
add_action( 'admin_init', array( 'Bookly_Exports_Installer', 'maybe_install' ) );

// Priority 20: the «Completed Sessions» entry is also hung under the Jung
// plugin's Finances menu, which registers itself at the default priority.
add_action( 'admin_menu', array( 'Bookly_Exports_Admin', 'register_menus' ), 20 );
add_action( 'admin_enqueue_scripts', array( 'Bookly_Exports_Admin', 'enqueue_assets' ) );

Bookly_Exports_Ajax::register();
