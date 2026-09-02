<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates and versions the cache table.
 *
 * Columns are camelCase because they map one-to-one onto the CSV headings the
 * export produces; `caId` is the exception that isn't exported — it holds the
 * source bookly_customer_appointments row id and is what makes "cache only what
 * isn't cached yet" possible, and re-running the export idempotent.
 */
class Bookly_Exports_Installer {

    const VERSION_OPTION = 'bookly_exports_db_version';
    const DB_VERSION     = '1.1.0';

    /** Fully-qualified name of the cache table. */
    public static function table_name() {
        global $wpdb;

        return $wpdb->prefix . 'bookly_appointments_cached';
    }

    public static function activate() {
        self::install();
    }

    /** Install when the table has never been built, or the schema moved on. */
    public static function maybe_install() {
        if ( get_option( self::VERSION_OPTION ) !== self::DB_VERSION || ! self::table_exists() ) {
            self::install();
        }
    }

    public static function table_exists() {
        global $wpdb;

        $table = self::table_name();

        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::table_name();
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            caId BIGINT UNSIGNED NOT NULL,
            appointmentDate DATETIME DEFAULT NULL,
            therapist VARCHAR(255) DEFAULT NULL,
            customerName VARCHAR(255) DEFAULT NULL,
            customerPhone VARCHAR(64) DEFAULT NULL,
            created DATETIME DEFAULT NULL,
            customerEmail VARCHAR(255) DEFAULT NULL,
            services VARCHAR(255) DEFAULT NULL,
            duration INT DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY caId (caId),
            KEY appointmentDate (appointmentDate)
        ) {$collate};";

        dbDelta( $sql );

        // 1.1.0 narrowed the table to completed sessions only. The schema didn't
        // move, but anything an earlier version cached under the old rule (any
        // approved appointment, future ones included) has to go — running the
        // purge here means it happens once, on upgrade, rather than on a hot
        // path.
        if ( Bookly_Exports_Repository::bookly_installed() ) {
            Bookly_Exports_Repository::purge_uncompleted();
        }

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }
}
