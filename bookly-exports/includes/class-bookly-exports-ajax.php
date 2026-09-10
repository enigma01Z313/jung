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
 * the whole history. One button runs both phases back to back.
 */
class Bookly_Exports_Ajax {

    const NONCE = 'bookly_exports';

    /** Where the finished file's name and stamp are remembered between runs. */
    const LAST_FILE_OPTION = 'bookly_exports_last_file';

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

        // The run starts here, so this is the moment to drop anything the table
        // is holding that is no longer a completed session.
        Bookly_Exports_Repository::purge_uncompleted();

        wp_send_json_success(
            array(
                'cached'  => Bookly_Exports_Repository::cached_count(),
                'pending' => Bookly_Exports_Repository::pending_count(),
            )
        );
    }

    /** Cache the next BOOKLY_EXPORTS_CACHE_BATCH completed sessions. */
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

    // --- The export folder ---------------------------------------------------

    /**
     * uploads/bookly-exports, created and closed to direct browsing.
     *
     * The finished file stays here between runs — that is the whole point of the
     * folder — so it is served through the download endpoint, which checks the
     * capability and the nonce, rather than by its own URL.
     */
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

    /**
     * The name a run writes under: the moment the export was taken, on the same
     * Tehran clock the dates inside the file use.
     */
    private static function file_name_for( $stamp ) {
        return 'bookly-completed-sessions-' . $stamp . '.csv';
    }

    /**
     * Stamps come back from the browser between batches, so a stamp that isn't
     * exactly the shape this plugin writes is refused rather than sanitised —
     * nothing that isn't digits and separators can reach the filesystem.
     */
    private static function valid_stamp( $stamp ) {
        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', (string) $stamp );
    }

    /** The half-written file a run appends to before it is published. */
    private static function part_file( $stamp ) {
        if ( ! self::valid_stamp( $stamp ) ) {
            return null;
        }

        return self::export_dir() . '/' . self::file_name_for( $stamp ) . '.part';
    }

    /**
     * The last finished export, or null. Also the guard against the option
     * outliving the file it names (a manually emptied uploads folder, a restore).
     */
    public static function last_export() {
        $last = get_option( self::LAST_FILE_OPTION );

        if ( ! is_array( $last ) || empty( $last['file'] ) ) {
            return null;
        }

        $path = self::export_dir() . '/' . basename( $last['file'] );

        if ( ! file_exists( $path ) ) {
            return null;
        }

        return array(
            'file'        => basename( $last['file'] ),
            'generatedAt' => isset( $last['generated_at'] ) ? $last['generated_at'] : '',
            'rows'        => isset( $last['rows'] ) ? (int) $last['rows'] : 0,
            'url'         => self::download_url(),
        );
    }

    // --- CSV -----------------------------------------------------------------

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

        $dir = self::export_dir();

        // Only half-written files go now; the previous finished export is kept
        // until this one is complete, so a run that dies partway through doesn't
        // take the last good download with it.
        foreach ( (array) glob( $dir . '/*.part' ) as $stale ) {
            @unlink( $stale );
        }

        $stamp = str_replace( ' ', '_', str_replace( ':', '-', Bookly_Exports_Repository::now_in_export_timezone() ) );
        $file  = self::part_file( $stamp );

        $handle = $file ? fopen( $file, 'w' ) : false;
        if ( false === $handle ) {
            wp_send_json_error( array( 'message' => __( 'The export file could not be created.', 'bookly-exports' ) ), 500 );
        }

        fwrite( $handle, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
        fputcsv( $handle, array_values( Bookly_Exports_Repository::csv_columns() ) );
        fclose( $handle );

        wp_send_json_success(
            array(
                'stamp'  => $stamp,
                'total'  => $total,
                'offset' => 0,
            )
        );
    }

    /** Append the next BOOKLY_EXPORTS_CSV_BATCH rows. */
    public static function csv_batch() {
        self::guard();

        $stamp  = isset( $_POST['stamp'] ) ? sanitize_text_field( wp_unslash( $_POST['stamp'] ) ) : '';
        $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $file   = self::part_file( $stamp );

        if ( ! $file || ! file_exists( $file ) ) {
            wp_send_json_error( array( 'message' => __( 'The export file is no longer available. Please start again.', 'bookly-exports' ) ), 400 );
        }

        $rows = Bookly_Exports_Repository::fetch_cached( $offset, BOOKLY_EXPORTS_CSV_BATCH );

        if($offset == 0){
            $future_rows = Bookly_Exports_Repository::fetch_future();

            $handle = fopen( $file, 'a' );
            $columns = array_keys( Bookly_Exports_Repository::csv_columns() );
            foreach ( $future_rows as $row ) {
                $line = array();
                foreach ( $columns as $column ) {
                    $line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
                }
                fputcsv( $handle, $line );
            }
            fclose( $handle );
        }

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

        $response = array(
            'offset' => $written,
            'total'  => $total,
            'done'   => $done,
        );

        if ( $done ) {
            $response['last'] = self::publish( $stamp, $written );
        }

        wp_send_json_success( $response );
    }

    /**
     * Turn the finished .part into *the* export: it takes the run's name, the
     * previous one is removed, and the option remembers it so the screen can
     * offer the download again later without rebuilding anything.
     *
     * @return array the same shape last_export() returns
     */
    private static function publish( $stamp, $rows ) {
        $dir   = self::export_dir();
        $name  = self::file_name_for( $stamp );
        $part  = self::part_file( $stamp );
        $final = $dir . '/' . $name;

        if ( ! @rename( $part, $final ) ) {
            // Stop before the old file is cleared away — an export that cannot
            // be published must not cost the one that is already there.
            wp_send_json_error( array( 'message' => __( 'The export file could not be finished.', 'bookly-exports' ) ), 500 );
        }

        foreach ( (array) glob( $dir . '/*.csv' ) as $old ) {
            if ( basename( $old ) !== $name ) {
                @unlink( $old );
            }
        }

        update_option(
            self::LAST_FILE_OPTION,
            array(
                'file'         => $name,
                // Stored formatted rather than as a timestamp: it is only ever
                // shown, and it is already on the file's own Tehran clock.
                'generated_at' => substr( $stamp, 0, 10 ) . ' ' . str_replace( '-', ':', substr( $stamp, 11 ) ),
                'rows'         => (int) $rows,
            ),
            false
        );

        return self::last_export();
    }

    private static function download_url() {
        return add_query_arg(
            array(
                'action' => 'bookly_exports_download',
                'nonce'  => wp_create_nonce( self::NONCE ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Stream the last finished file — and keep it. Re-downloading yesterday's
     * export shouldn't mean caching and rebuilding the whole history again, so
     * the file lives in uploads until the next export replaces it.
     */
    public static function download() {
        if ( ! current_user_can( Bookly_Exports_Admin::CAPABILITY ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bookly-exports' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( self::NONCE, 'nonce' );

        $last = self::last_export();

        if ( ! $last ) {
            wp_die( esc_html__( 'There is no export file yet. Run an export first.', 'bookly-exports' ), '', array( 'response' => 404 ) );
        }

        $file = self::export_dir() . '/' . $last['file'];

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $last['file'] . '"' );
        header( 'Content-Length: ' . filesize( $file ) );

        readfile( $file );

        exit;
    }
}
