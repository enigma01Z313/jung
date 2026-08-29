=== Bookly Exports ===
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.0

Caches Bookly's approved appointments into a flat table and exports them to CSV,
both in progress-tracked batches.

== What it does ==

Bookly keeps an appointment's status on `bookly_customer_appointments`, not on
`bookly_appointments` — one appointment can carry several customers, each with
its own status. "Approved appointments" therefore means approved customer rows,
and this plugin flattens them (with the therapist, customer, service and
duration already joined in) into one table so an export is a plain read.

**Menu:** a top-level *Bookly Exports* menu, whose *Approved Appointments*
screen is also linked from the Jung plugin's *Finances* menu. Both point at the
same page.

== The table ==

Created on activation (and re-checked on every admin load, so an in-place update
still ends up with it) as `{prefix}bookly_appointments_cached`:

| Column            | CSV heading      | Notes                                        |
|-------------------|------------------|----------------------------------------------|
| `caId`            | —                | Source `bookly_customer_appointments.id`; unique, and what makes "cache only what isn't cached" work |
| `appointmentDate` | Appointment Date | Tehran time                                  |
| `therapist`       | Therapist        | Staff full name                              |
| `customerName`    | Customer Name    | Full name, else first + last                 |
| `customerPhone`   | Customer Phone   |                                              |
| `created`         | Created          | Appointment creation, Tehran time            |
| `customerEmail`   | Customer Email   |                                              |
| `services`        | Services         | Service title, or the appointment's custom service name |
| `duration`        | Duration         | Minutes booked; falls back to the service's own duration, 0 when neither is known |

Bookly stores its datetimes in the site's own timezone, so the two date columns
are converted to Asia/Tehran rather than relabelled.

== How the export runs ==

Pressing **Export CSV** runs two batched phases, each driven from the browser so
no single request has to survive the whole history:

1. **Cache** — approved appointments that are not in the table yet are inserted
   100 at a time. The bar tracks the count still outstanding, which the server
   reports back after each write, so it reflects rows actually stored.
2. **CSV** — the cached rows are appended to a file 1000 at a time. The file is
   opened with a UTF-8 BOM so Excel reads Persian names correctly.

When the last slice is written the browser is sent to a download endpoint, which
streams the file and deletes it — the cache table is the record that matters, so
a stale CSV sitting in uploads is only a liability. While it exists it lives in
`uploads/bookly-exports/` under a random name, behind an `index.php` and a
`Deny from all` .htaccess.

Every endpoint checks `manage_options` and a nonce.

== Re-running ==

Safe. Only uncached appointments are read, and `INSERT IGNORE` against the unique
`caId` means a retried or concurrent request cannot double-cache a row. Running
it again after new bookings are approved caches just those, then exports
everything.

== Changelog ==

= 1.0.0 =
* First release.
