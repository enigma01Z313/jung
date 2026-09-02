<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin menu and the single «Completed Sessions» screen.
 */
class Bookly_Exports_Admin {

    const PAGE_SLUG  = 'bookly-exports';
    const CAPABILITY = 'manage_options';

    /** Parent menu of the Jung plugin's Finances section. */
    const FINANCES_PARENT = 'jung-invoices';

    public static function register_menus() {
        add_menu_page(
            __( 'Bookly Exports', 'bookly-exports' ),
            __( 'Bookly Exports', 'bookly-exports' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' ),
            'dashicons-database-export',
            3
        );

        // Without this the first child repeats the top-level label.
        add_submenu_page(
            self::PAGE_SLUG,
            __( 'Completed Sessions', 'bookly-exports' ),
            __( 'Completed Sessions', 'bookly-exports' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );

        // The same screen, also reachable from Finances. Registering an existing
        // slug under a second parent adds a link, not a second page.
        add_submenu_page(
            self::FINANCES_PARENT,
            __( 'Completed Sessions', 'bookly-exports' ),
            __( 'Completed Sessions', 'bookly-exports' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    public static function enqueue_assets( $hook ) {
        // The screen is registered under two parents, so match on the request
        // rather than on one hook suffix.
        if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
            return;
        }

        wp_enqueue_style(
            'bookly-exports-admin',
            BOOKLY_EXPORTS_URL . 'assets/admin.css',
            array(),
            BOOKLY_EXPORTS_VERSION
        );

        wp_enqueue_script(
            'bookly-exports-admin',
            BOOKLY_EXPORTS_URL . 'assets/admin.js',
            array(),
            BOOKLY_EXPORTS_VERSION,
            true
        );

        wp_localize_script(
            'bookly-exports-admin',
            'BooklyExports',
            array(
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( Bookly_Exports_Ajax::NONCE ),
                'cacheBatch' => BOOKLY_EXPORTS_CACHE_BATCH,
                'csvBatch'   => BOOKLY_EXPORTS_CSV_BATCH,
                'i18n'       => array(
                    'caching'    => __( 'Caching newly completed sessions…', 'bookly-exports' ),
                    'cached'     => __( 'Every completed session is cached.', 'bookly-exports' ),
                    'nothingNew' => __( 'Nothing new to cache — every completed session is already in the table.', 'bookly-exports' ),
                    'building'   => __( 'Building the CSV file…', 'bookly-exports' ),
                    'ready'      => __( 'The file is ready — your download should start automatically.', 'bookly-exports' ),
                    'empty'      => __( 'There is nothing to export yet.', 'bookly-exports' ),
                    'failed'     => __( 'Something went wrong. Please try again.', 'bookly-exports' ),
                    'ofRecords'  => __( '%1$s of %2$s records', 'bookly-exports' ),
                    'lastExport' => __( 'Last export: %1$s (%2$s records)', 'bookly-exports' ),
                    'download'   => __( 'Download CSV', 'bookly-exports' ),
                ),
            )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You are not allowed to view this page.', 'bookly-exports' ) );
        }

        $bookly_ready = Bookly_Exports_Repository::bookly_installed();
        $cached       = $bookly_ready ? Bookly_Exports_Repository::cached_count() : 0;
        $pending      = $bookly_ready ? Bookly_Exports_Repository::pending_count() : 0;
        $last         = $bookly_ready ? Bookly_Exports_Ajax::last_export() : null;
        ?>
        <div class="wrap bookly-exports">
            <h1><?php esc_html_e( 'Completed Sessions', 'bookly-exports' ); ?></h1>

            <?php if ( ! $bookly_ready ) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'Bookly tables were not found. Activate Bookly before running an export.', 'bookly-exports' ); ?></p>
                </div>
            <?php else : ?>

                <p class="bookly-exports__lead">
                    <?php esc_html_e( 'Only sessions whose date has already passed are reported, whatever therapist they belong to — archived ones included. Pressing Export CSV caches the sessions completed since the last run and then writes the whole table out, both in batches so a long history never trips the request timeout.', 'bookly-exports' ); ?>
                </p>

                <div class="bookly-exports__stats">
                    <div class="bookly-exports__stat">
                        <span class="bookly-exports__stat-label"><?php esc_html_e( 'Cached sessions', 'bookly-exports' ); ?></span>
                        <span class="bookly-exports__stat-value" id="bookly-exports-cached"><?php echo esc_html( number_format_i18n( $cached ) ); ?></span>
                    </div>
                    <div class="bookly-exports__stat">
                        <span class="bookly-exports__stat-label"><?php esc_html_e( 'Completed since the last run', 'bookly-exports' ); ?></span>
                        <span class="bookly-exports__stat-value" id="bookly-exports-pending"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span>
                    </div>
                </div>

                <p>
                    <?php // One button: caching whatever is newly completed is part of exporting, not a separate step to remember. ?>
                    <button type="button" class="button button-primary button-hero" id="bookly-exports-run">
                        <?php esc_html_e( 'Export CSV', 'bookly-exports' ); ?>
                    </button>
                </p>

                <div class="bookly-exports__progress" id="bookly-exports-progress" hidden>
                    <div class="bookly-exports__phase" id="bookly-exports-phase"></div>
                    <div class="bookly-exports__bar">
                        <div class="bookly-exports__bar-fill" id="bookly-exports-bar"></div>
                    </div>
                    <div class="bookly-exports__counts" id="bookly-exports-counts"></div>
                </div>

                <?php // The last file stays in uploads, so it can be fetched again without rebuilding anything. ?>
                <p class="bookly-exports__last" id="bookly-exports-last"<?php echo $last ? '' : ' hidden'; ?>>
                    <span class="bookly-exports__last-label" id="bookly-exports-last-label">
                        <?php
                        if ( $last ) {
                            printf(
                                /* translators: 1: date and time of the export, 2: number of records in it */
                                esc_html__( 'Last export: %1$s (%2$s records)', 'bookly-exports' ),
                                esc_html( $last['generatedAt'] ),
                                esc_html( number_format_i18n( $last['rows'] ) )
                            );
                        }
                        ?>
                    </span>
                    <a class="button" id="bookly-exports-last-link" href="<?php echo $last ? esc_url( $last['url'] ) : '#'; ?>">
                        <?php esc_html_e( 'Download CSV', 'bookly-exports' ); ?>
                    </a>
                </p>

                <div class="notice notice-success" id="bookly-exports-done" hidden><p></p></div>
                <div class="notice notice-error" id="bookly-exports-error" hidden><p></p></div>

            <?php endif; ?>
        </div>
        <?php
    }
}
