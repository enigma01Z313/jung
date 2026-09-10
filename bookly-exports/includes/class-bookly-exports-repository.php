<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * All reads and writes for the export.
 *
 * Bookly keeps an appointment's status on bookly_customer_appointments, not on
 * bookly_appointments — one appointment can hold several customers, each with
 * its own status — so a "session" here means one customer row, and the cache is
 * keyed on their ids.
 *
 * What gets cached is every *completed* session: one whose start date is already
 * in the past and whose status doesn't say it never happened. Filtering on
 * `status = 'approved'` (what this used to do) quietly dropped whole therapists
 * from the report — Bookly moves a past booking on to `done`, and a site running
 * Bookly Pro's custom statuses can move it somewhere else again — so an archived
 * therapist, whose sessions are by definition all in the past, could end up with
 * nothing left in the export at all. Naming the statuses that mean "didn't
 * happen" and taking everything else keeps that from recurring for a status
 * added later.
 *
 * Sessions still ahead of us are read straight from Bookly at export time
 * (fetch_future) rather than cached: they are few, and a booking that has not
 * happened yet can still be moved or called off, so a cached copy would go
 * stale.
 *
 * Staff visibility is deliberately never used to *exclude* anyone: an archived
 * therapist's past sessions were still delivered, and the staff join stays a
 * LEFT JOIN so a session survives into the report even when its therapist row
 * has gone. It is only used to mark the name — see ARCHIVED_SUFFIX.
 */
class Bookly_Exports_Repository {

    /** Appended to the therapist's name when Bookly has archived them. */
    const ARCHIVED_SUFFIX = ' (Archived)';

    /** CSV headings, in order. The keys are the cache table's columns. */
    public static function csv_columns() {
        return array(
            'appointmentDate' => 'Appointment Date',
            'therapist'       => 'Therapist',
            'customerName'    => 'Customer Name',
            'customerPhone'   => 'Customer Phone',
            'created'         => 'Created',
            'customerEmail'   => 'Customer Email',
            'services'        => 'Services',
            'duration'        => 'Duration',
        );
    }

    /**
     * Statuses that mean the session did not take place, and so is not
     * "completed" however long ago its date was.
     */
    public static function excluded_statuses() {
        return apply_filters(
            'bookly_exports_excluded_statuses',
            array( 'cancelled', 'rejected', 'waitlisted' )
        );
    }

    /** True when the Bookly tables this export reads are present. */
    public static function bookly_installed() {
        global $wpdb;

        $table = $wpdb->prefix . 'bookly_customer_appointments';

        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    /** The excluded-status half of the two conditions below, or ''. */
    private static function status_condition() {
        $statuses = array_map( 'esc_sql', (array) self::excluded_statuses() );

        if ( empty( $statuses ) ) {
            return '';
        }

        return " AND ca.status NOT IN ('" . implode( "', '", $statuses ) . "')";
    }

    /**
     * The «this session is completed» test, shared by every query below so the
     * counter and the reader can never disagree about what is in scope.
     *
     * Bookly writes start_date in the site's own timezone, so the cut-off is the
     * site's clock — the Tehran conversion happens on the way into the cache,
     * not here.
     *
     * Built by interpolation rather than through prepare() because it is spliced
     * into queries carrying placeholders of their own; both halves are escaped
     * and neither comes from a request.
     */
    private static function completed_condition() {
        $now = esc_sql( current_time( 'mysql' ) );

        return "a.start_date IS NOT NULL AND a.start_date < '{$now}'" . self::status_condition();
    }

    /**
     * The exact complement of completed_condition(): a session that has not
     * happened yet and has not been called off. The same site clock, for the
     * same reason — measuring against a Tehran "now" would slide the boundary
     * by the offset between the two clocks and land the sessions in that gap
     * in both halves of the export, or in neither.
     */
    private static function upcoming_condition() {
        $now = esc_sql( current_time( 'mysql' ) );

        return "a.start_date IS NOT NULL AND a.start_date >= '{$now}'" . self::status_condition();
    }

    /** Now, on the clock the cache table's dates are written in. */
    public static function now_in_export_timezone() {
        $date = new DateTime( 'now', new DateTimeZone( BOOKLY_EXPORTS_TIMEZONE ) );

        return $date->format( 'Y-m-d H:i:s' );
    }

    public static function cached_count() {
        global $wpdb;

        $table = Bookly_Exports_Installer::table_name();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /** Completed sessions that have not been cached yet. */
    public static function pending_count() {
        global $wpdb;

        $p         = $wpdb->prefix;
        $cached    = Bookly_Exports_Installer::table_name();
        $completed = self::completed_condition();

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
               FROM {$p}bookly_customer_appointments ca
               INNER JOIN {$p}bookly_appointments a ON a.id = ca.appointment_id
               LEFT JOIN {$cached} ch ON ch.caId = ca.id
              WHERE {$completed} AND ch.caId IS NULL"
        );
    }

    /**
     * The joined shape both readers below select, and to_cache_row() flattens.
     *
     * Keeping it in one place is what lets an upcoming session and a past one
     * go through exactly the same conversions — minutes rather than seconds,
     * Tehran dates, the customer-name fallback — instead of one of them
     * quietly exporting Bookly's raw columns.
     */
    private static function session_columns() {
        return 'ca.id AS caId,
                        ca.created_at AS createdAt,
                        a.start_date AS startDate,
                        a.end_date AS endDate,
                        a.staff_id AS staffId,
                        st.full_name AS therapist,
                        st.visibility AS staffVisibility,
                        c.full_name AS customerFullName,
                        c.first_name AS customerFirstName,
                        c.last_name AS customerLastName,
                        c.phone AS customerPhone,
                        c.email AS customerEmail,
                        a.custom_service_name AS customServiceName,
                        s.title AS serviceTitle,
                        s.duration AS serviceDuration';
    }

    /** The joins those columns are read from. */
    private static function session_joins() {
        global $wpdb;

        $p = $wpdb->prefix;

        return "FROM {$p}bookly_customer_appointments ca
                   INNER JOIN {$p}bookly_appointments a ON a.id = ca.appointment_id
                   LEFT JOIN {$p}bookly_staff st ON st.id = a.staff_id
                   LEFT JOIN {$p}bookly_customers c ON c.id = ca.customer_id
                   LEFT JOIN {$p}bookly_services s ON s.id = a.service_id";
    }

    /**
     * The next slice of uncached completed sessions.
     *
     * No OFFSET: every row this returns is cached before the next call, so the
     * "not in the cache" condition always walks the list forward on its own.
     */
    public static function fetch_uncached( $limit ) {
        global $wpdb;

        $cached    = Bookly_Exports_Installer::table_name();
        $completed = self::completed_condition();
        $columns   = self::session_columns();
        $joins     = self::session_joins();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$columns}
                   {$joins}
                   LEFT JOIN {$cached} ch ON ch.caId = ca.id
                  WHERE {$completed} AND ch.caId IS NULL
                  ORDER BY ca.id ASC
                  LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Sessions still ahead of us, already in the CSV's own shape.
     *
     * These are never cached — an upcoming booking can still be moved or called
     * off — so they are read live and flattened through the same to_cache_row()
     * the cached half goes through. Handing Bookly's own columns to the writer
     * is what made a 45-minute appointment export as «2700»: bookly_services
     * .duration is stored in seconds, and only duration_minutes() knows that.
     */
    public static function fetch_future() {
        global $wpdb;

        $columns  = self::session_columns();
        $joins    = self::session_joins();
        $upcoming = self::upcoming_condition();

        $rows = $wpdb->get_results(
            "SELECT {$columns}
               {$joins}
              WHERE {$upcoming}
              ORDER BY a.start_date DESC, ca.id DESC",
            ARRAY_A
        );

        $out = array();

        foreach ( (array) $rows as $row ) {
            $out[] = self::mark_archived( self::to_cache_row( $row ), $row );
        }

        return $out;
    }

    /**
     * Drop anything cached that is not a completed session.
     *
     * Nothing uncompleted is written any more, so in practice this clears out
     * what the old «approved, whenever it is» rule left behind — which is why
     * the installer runs it once on upgrade — but it also keeps the table
     * self-correcting when a cached session is later moved to a status saying it
     * never happened.
     *
     * @return int rows removed
     */
    public static function purge_uncompleted() {
        global $wpdb;

        $p       = $wpdb->prefix;
        $table   = Bookly_Exports_Installer::table_name();
        $removed = 0;

        // Dates in the cache are already Tehran time, so the cut-off is too.
        $removed += (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE appointmentDate IS NULL OR appointmentDate >= %s",
                self::now_in_export_timezone()
            )
        );

        $statuses = array_map( 'esc_sql', (array) self::excluded_statuses() );

        if ( ! empty( $statuses ) ) {
            $removed += (int) $wpdb->query(
                "DELETE ch FROM {$table} ch
                   INNER JOIN {$p}bookly_customer_appointments ca ON ca.id = ch.caId
                  WHERE ca.status IN ('" . implode( "', '", $statuses ) . "')"
            );
        }

        return $removed;
    }

    /**
     * Bookly stores datetimes in the site's own timezone; the export is asked
     * for Tehran time, so convert rather than relabel.
     */
    public static function to_tehran( $datetime ) {
        if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
            return null;
        }

        try {
            $date = new DateTime( $datetime, wp_timezone() );
            $date->setTimezone( new DateTimeZone( BOOKLY_EXPORTS_TIMEZONE ) );

            return $date->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            return null;
        }
    }

    /**
     * Minutes actually booked, falling back to the service's own duration.
     * The column is an INT, so an appointment with neither is stored as 0.
     */
    private static function duration_minutes( $row ) {
        if ( ! empty( $row['startDate'] ) && ! empty( $row['endDate'] ) ) {
            $start = strtotime( $row['startDate'] );
            $end   = strtotime( $row['endDate'] );
            if ( $start && $end && $end > $start ) {
                return (int) round( ( $end - $start ) / 60 );
            }
        }

        // bookly_services.duration is in seconds.
        return isset( $row['serviceDuration'] ) ? (int) round( (int) $row['serviceDuration'] / 60 ) : 0;
    }

    private static function customer_name( $row ) {
        if ( ! empty( $row['customerFullName'] ) ) {
            return $row['customerFullName'];
        }

        $name = trim( (string) $row['customerFirstName'] . ' ' . (string) $row['customerLastName'] );

        return '' === $name ? null : $name;
    }

    /**
     * Mark an export row whose therapist Bookly has archived, so a reader can
     * still tell the two apart in a report that deliberately keeps archived
     * therapists' sessions in.
     *
     * $source is the joined row the visibility was read from, which is why the
     * marker is never stored: it is decided when the file is written, so a
     * therapist archived long after their sessions were cached still comes out
     * marked on the next export.
     */
    private static function mark_archived( array $export, $source ) {
        $archived = isset( $source['staffVisibility'] ) && 'archive' === $source['staffVisibility'];

        if ( $archived && ! empty( $export['therapist'] ) ) {
            $export['therapist'] .= self::ARCHIVED_SUFFIX;
        }

        return $export;
    }

    /** Flatten a joined row into the cache table's shape. */
    public static function to_cache_row( $row ) {
        $service = ! empty( $row['customServiceName'] ) ? $row['customServiceName'] : $row['serviceTitle'];

        return array(
            'caId'            => (int) $row['caId'],
            // 0 when the appointment names no staff, or the staff row has been
            // deleted outright: no staff id matches it, so such a row simply
            // never picks the archived marker up.
            'staffId'         => isset( $row['staffId'] ) ? (int) $row['staffId'] : 0,
            'appointmentDate' => self::to_tehran( $row['startDate'] ),
            'therapist'       => $row['therapist'],
            'customerName'    => self::customer_name( $row ),
            'customerPhone'   => $row['customerPhone'],
            'created'         => self::to_tehran( $row['createdAt'] ),
            'customerEmail'   => $row['customerEmail'],
            'services'        => $service,
            'duration'        => self::duration_minutes( $row ),
        );
    }

    /**
     * Insert a batch in one statement. IGNORE plus the unique caId key means a
     * concurrent run (or a retried request) can never double-cache a row.
     *
     * @return int rows written
     */
    public static function insert_batch( array $rows ) {
        global $wpdb;

        if ( empty( $rows ) ) {
            return 0;
        }

        $table   = Bookly_Exports_Installer::table_name();
        $columns = array( 'caId', 'staffId', 'appointmentDate', 'therapist', 'customerName', 'customerPhone', 'created', 'customerEmail', 'services', 'duration' );

        $placeholders = array();
        $values       = array();

        foreach ( $rows as $row ) {
            $placeholders[] = '(%d, %d, %s, %s, %s, %s, %s, %s, %s, %d)';
            foreach ( $columns as $column ) {
                $values[] = $row[ $column ];
            }
        }

        $sql = "INSERT IGNORE INTO {$table} (`" . implode( '`, `', $columns ) . '`) VALUES '
            . implode( ', ', $placeholders );

        $affected = $wpdb->query( $wpdb->prepare( $sql, $values ) );

        return false === $affected ? 0 : (int) $affected;
    }

    /**
     * One page of cached rows, for the CSV writer: newest appointment first.
     *
     * The id breaks ties so the ordering is total — without it, rows sharing an
     * appointmentDate could come back in a different order for two different
     * OFFSETs, and the paged write would duplicate some rows while dropping
     * others. Nothing is cached while the CSV is being written, so the set
     * itself stays stable across the batches.
     *
     * The staff join is there only to read the therapist's visibility now, at
     * export time; the marker is not part of the cached name.
     */
    public static function fetch_cached( $offset, $limit ) {
        global $wpdb;

        $p     = $wpdb->prefix;
        $table = Bookly_Exports_Installer::table_name();

        $select = array();
        foreach ( array_keys( self::csv_columns() ) as $column ) {
            $select[] = "ch.`{$column}`";
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . implode( ', ', $select ) . ", st.visibility AS staffVisibility
                   FROM {$table} ch
                   LEFT JOIN {$p}bookly_staff st ON st.id = ch.staffId
                  ORDER BY ch.appointmentDate DESC, ch.id DESC
                  LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        $out = array();

        foreach ( (array) $rows as $row ) {
            $out[] = self::mark_archived( $row, $row );
        }

        return $out;
    }
}
