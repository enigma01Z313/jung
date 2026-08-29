<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The batched export endpoints.
 *
 * Caching and CSV writing are both driven from the browser one slice at a time:
 * each request does a bounded amount of work and reports where it got to, so the
 * progress bar is real rather than a guess and no single request has to survive
 * the whole history.
 */
class Bookly_Exports_Ajax {

    const NONCE = 'bookly_exports';

    public static function register() {
        add_action( 'wp_ajax_bookly_exports_status', array( __CLASS__, 'status' ) );
        add_action( 'wp_ajax_bookly_exports_cache_batch', array( __CLASS__, 'cache_batch' ) );
        add_action( 'wp_ajax_bookly_exports_csv_start', array( __CLASS__, 'csv_start' ) );
        add_action( 'wp_ajax_bookly_exports_csv_batch', array( __CLASS__, 'csv_batch' ) );
        add_action( 'wp_ajax_bookly_exports_download', array( __CLASS__, 'download' ) );
    }

    /** Every endpoint is admin-only and nonce-checked. */
    private static function guard() {
        if ( ! current_user_can( Bookly_Exports_Admin::CAPABILITY ) ) {
            wp_send_json_error( array( 'message' => __( 'Not allowed.', 'bookly-exports' ) ), 403 );
        }

        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! Bookly_Exports_Repository::bookly_installed() ) {
            wp_send_json_error( array( 'message' => __( 'Bookly tables were not found.', 'bookly-exports' ) ), 400 );
        }

        Bookly_Exports_Installer::maybe_install();
    }

    public static function status() {
        self::guard();

        wp_send_json_success(
            array(
                'cached'  => Bookly_Exports_Repository::cached_count(),
                'pending' => Bookly_Exports_Repository::pending_count(),
            )
        );
    }

    /** Cache the next BOOKLY_EXPORTS_CACHE_BATCH approved appointments. */
    public static function cache_batch() {
        self::guard();

        $rows = Bookly_Exports_Repository::fetch_uncached( BOOKLY_EXPORTS_CACHE_BATCH );

        $inserted = 0;
        if ( ! empty( $rows ) ) {
            $inserted = Bookly_Exports_Repository::insert_batch(
                array_map( array( 'Bookly_Exports_Repository', 'to_cache_row' ), $rows )
            );
        }

        $pending = Bookly_Exports_Repository::pending_count();

        wp_send_json_success(
            array(
                'inserted' => $inserted,
                'cached'   => Bookly_Exports_Repository::cached_count(),
                'pending'  => $pending,
                // Stop on an empty read too, so a row that cannot be written can
                // never turn into an endless loop of identical requests.
                'done'     => empty( $rows ) || 0 === $pending,
            )
        );
    }

    // --- CSV ----------------------------------------------------------------

    /** uploads/bookly-exports, created and closed to direct browsing. */
    private static function export_dir() {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'bookly-exports';

        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        if ( ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
        }

        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        }

        return $dir;
    }

    /** Tokens come from the browser, so never let one walk out of the folder. */
    private static function file_for_token( $token ) {
        $token = preg_replace( '/[^a-f0-9]/', '', (string) $token );

        if ( 32 !== strlen( $token ) ) {
            return null;
        }

        return self::export_dir() . '/bookly-approved-appointments-' . $token . '.csv';
    }

    /**
     * Open a fresh file and write the heading row.
     *
     * The UTF-8 BOM is what makes Excel read Persian names and the Tehran dates
     * correctly instead of as mojibake.
     */
    public static function csv_start() {
        self::guard();

        $total = Bookly_Exports_Repository::cached_count();

        if ( 0 === $total ) {
            wp_send_json_error( array( 'message' => __( 'There is nothing to export yet.', 'bookly-exports' ) ), 400 );
        }

        $token = md5( uniqid( 'bookly-exports', true ) );
        $file  = self::file_for_token( $token );

        $handle = fopen( $file, 'w' );
        if ( false === $handle ) {
            wp_send_json_error( array( 'message' => __( 'The export file could not be created.', 'bookly-exports' ) ), 500 );
        }

        fwrite( $handle, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
        fputcsv( $handle, array_values( Bookly_Exports_Repository::csv_columns() ) );
        fclose( $handle );

        wp_send_json_success(
            array(
                'token'  => $token,
                'total'  => $total,
                'offset' => 0,
            )
        );
    }

    /** Append the next BOOKLY_EXPORTS_CSV_BATCH rows. */
    public static function csv_batch() {
        self::guard();

        $token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $file   = self::file_for_token( $token );

        if ( ! $file || ! file_exists( $file ) ) {
            wp_send_json_error( array( 'message' => __( 'The export file is no longer available. Please start again.', 'bookly-exports' ) ), 400 );
        }

        $rows = Bookly_Exports_Repository::fetch_cached( $offset, BOOKLY_EXPORTS_CSV_BATCH );

        $handle = fopen( $file, 'a' );
        if ( false === $handle ) {
            wp_send_json_error( array( 'message' => __( 'The export file could not be written to.', 'bookly-exports' ) ), 500 );
        }

        $columns = array_keys( Bookly_Exports_Repository::csv_columns() );
        foreach ( $rows as $row ) {
            $line = array();
            foreach ( $columns as $column ) {
                $line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
            }
            fputcsv( $handle, $line );
        }
        fclose( $handle );

        $written = $offset + count( $rows );
        $total   = Bookly_Exports_Repository::cached_count();
        $done    = count( $rows ) < BOOKLY_EXPORTS_CSV_BATCH || $written >= $total;

        wp_send_json_success(
            array(
                'offset'      => $written,
                'total'       => $total,
                'done'        => $done,
                'downloadUrl' => $done ? self::download_url( $token ) : null,
            )
        );
    }

    private static function download_url( $token ) {
        return add_query_arg(
            array(
                'action' => 'bookly_exports_download',
                'token'  => $token,
                'nonce'  => wp_create_nonce( self::NONCE ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Stream the finished file, then delete it — the cache table is the record
     * that matters, so a stale CSV left in uploads is only a liability.
     */
    public static function download() {
        if ( ! current_user_can( Bookly_Exports_Admin::CAPABILITY ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bookly-exports' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( self::NONCE, 'nonce' );

        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $file  = self::file_for_token( $token );

        if ( ! $file || ! file_exists( $file ) ) {
            wp_die( esc_html__( 'The export file is no longer available.', 'bookly-exports' ), '', array( 'response' => 404 ) );
        }

        $name = 'bookly-approved-appointments-' . gmdate( 'Ymd-His' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );
        header( 'Content-Length: ' . filesize( $file ) );

        readfile( $file );
        @unlink( $file );

        exit;
    }
}
