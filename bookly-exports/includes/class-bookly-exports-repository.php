<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * All reads and writes for the export.
 *
 * Bookly keeps an appointment's status on bookly_customer_appointments, not on
 * bookly_appointments — one appointment can hold several customers, each with
 * its own status — so "approved appointments" means approved customer rows, and
 * the cache is keyed on their ids.
 */
class Bookly_Exports_Repository {

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

    /** True when the Bookly tables this export reads are present. */
    public static function bookly_installed() {
        global $wpdb;

        $table = $wpdb->prefix . 'bookly_customer_appointments';

        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    public static function cached_count() {
        global $wpdb;

        $table = Bookly_Exports_Installer::table_name();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /** Approved appointments that have not been cached yet. */
    public static function pending_count() {
        global $wpdb;

        $p      = $wpdb->prefix;
        $cached = Bookly_Exports_Installer::table_name();

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
               FROM {$p}bookly_customer_appointments ca
               INNER JOIN {$p}bookly_appointments a ON a.id = ca.appointment_id
               LEFT JOIN {$cached} ch ON ch.caId = ca.id
              WHERE ca.status = 'approved' AND ch.caId IS NULL"
        );
    }

    /**
     * The next slice of uncached approved appointments.
     *
     * No OFFSET: every row this returns is cached before the next call, so the
     * "not in the cache" condition always walks the list forward on its own.
     */
    public static function fetch_uncached( $limit ) {
        global $wpdb;

        $p      = $wpdb->prefix;
        $cached = Bookly_Exports_Installer::table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ca.id AS caId,
                        ca.created_at AS createdAt,
                        a.start_date AS startDate,
                        a.end_date AS endDate,
                        st.full_name AS therapist,
                        c.full_name AS customerFullName,
                        c.first_name AS customerFirstName,
                        c.last_name AS customerLastName,
                        c.phone AS customerPhone,
                        c.email AS customerEmail,
                        a.custom_service_name AS customServiceName,
                        s.title AS serviceTitle,
                        s.duration AS serviceDuration
                   FROM {$p}bookly_customer_appointments ca
                   INNER JOIN {$p}bookly_appointments a ON a.id = ca.appointment_id
                   LEFT JOIN {$p}bookly_staff st ON st.id = a.staff_id
                   LEFT JOIN {$p}bookly_customers c ON c.id = ca.customer_id
                   LEFT JOIN {$p}bookly_services s ON s.id = a.service_id
                   LEFT JOIN {$cached} ch ON ch.caId = ca.id
                  WHERE ca.status = 'approved' AND ch.caId IS NULL
                  ORDER BY ca.id ASC
                  LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
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

    /** Flatten a joined row into the cache table's shape. */
    public static function to_cache_row( $row ) {
        $service = ! empty( $row['customServiceName'] ) ? $row['customServiceName'] : $row['serviceTitle'];

        return array(
            'caId'            => (int) $row['caId'],
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
        $columns = array( 'caId', 'appointmentDate', 'therapist', 'customerName', 'customerPhone', 'created', 'customerEmail', 'services', 'duration' );

        $placeholders = array();
        $values       = array();

        foreach ( $rows as $row ) {
            $placeholders[] = '(%d, %s, %s, %s, %s, %s, %s, %s, %d)';
            foreach ( $columns as $column ) {
                $values[] = $row[ $column ];
            }
        }

        $sql = "INSERT IGNORE INTO {$table} (`" . implode( '`, `', $columns ) . '`) VALUES '
            . implode( ', ', $placeholders );

        $affected = $wpdb->query( $wpdb->prepare( $sql, $values ) );

        return false === $affected ? 0 : (int) $affected;
    }

    /** One page of cached rows, for the CSV writer. */
    public static function fetch_cached( $offset, $limit ) {
        global $wpdb;

        $table   = Bookly_Exports_Installer::table_name();
        $columns = implode( '`, `', array_keys( self::csv_columns() ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `{$columns}` FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }
}
