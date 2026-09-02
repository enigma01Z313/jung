=== Simyatech Bookly Reports ===
Author: Farzin Ahamadi
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.1.0

Caches Bookly's completed sessions into a flat table and exports them to CSV,
both in progress-tracked batches. The last file produced is kept for
re-download.

== What it does ==

Bookly keeps an appointment's status on `bookly_customer_appointments`, not on
`bookly_appointments` — one appointment can carry several customers, each with
its own status. A "session" therefore means one customer row, and this plugin
flattens them (with the therapist, customer, service and duration already joined
in) into one table so an export is a plain read.

**Menu:** a top-level *Bookly Exports* menu, whose *Completed Sessions* screen is
also linked from the Jung plugin's *Finances* menu. Both point at the same page.

== What counts as a completed session ==

A session is cached once **its date has passed** and its status doesn't say it
never happened — everything except `cancelled`, `rejected` and `waitlisted`
(adjustable through the `bookly_exports_excluded_statuses` filter). Future
bookings are not cached at all.

Earlier versions cached on `status = 'approved'` instead, which silently dropped
whole therapists from the report: Bookly moves a past booking on to `done`, and a
site running Bookly Pro's custom statuses can move it somewhere else again, so an
**archived therapist** — whose sessions are by definition all in the past — could
end up with nothing left in the export at all. Taking every status but the ones
that mean "didn't happen" fixes that, and keeps fixing it for any status added
later.

Staff visibility is never looked at, and the staff join is a LEFT JOIN, so an
archived (or removed) therapist's past sessions still make it into the file.

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

Upgrading to 1.1.0 purges anything the old rule left behind — future bookings and
sessions since cancelled — once, from the installer.

== How the export runs ==

One button. Pressing **Export CSV** runs two batched phases back to back, each
driven from the browser so no single request has to survive the whole history:

1. **Cache** — sessions completed since the last run are inserted 100 at a time.
   The bar tracks the count still outstanding, which the server reports back
   after each write, so it reflects rows actually stored.
2. **CSV** — the cached rows are appended to a file 1000 at a time, newest
   session first. The file is opened with a UTF-8 BOM so Excel reads Persian
   names correctly.

When the last slice is written the browser is sent to the download endpoint, and
the same file is offered as a **Download CSV** link on the page.

== The file ==

Files live in `uploads/bookly-exports/`, behind an `index.php` and a
`Deny from all` .htaccess, and are served through an endpoint that checks
`manage_options` and a nonce — never by their own URL.

The name carries the moment the export was taken, on the same Tehran clock the
dates inside it use:

    bookly-completed-sessions-2026-09-02_14-35-07.csv

**The last file is kept.** It stays in the folder, and its link stays on the
screen, so it can be fetched again later without re-running anything. A new
export replaces it — and only once the new one is complete, so a run that dies
partway through doesn't take the last good download with it.

== Re-running ==

Safe. Only uncached sessions are read, and `INSERT IGNORE` against the unique
`caId` means a retried or concurrent request cannot double-cache a row. Running
it again after more sessions have passed caches just those, then exports
everything.

== Changelog ==

= 1.1.0 =
* Fixed: appointments belonging to archived therapists were missing from the
  export — the scope was `approved` only, which excludes the statuses a past
  booking ends up in.
* Changed: the table now holds completed sessions only, i.e. those whose date has
  passed. Existing rows outside that scope are purged on upgrade.
* Changed: a single **Export CSV** button caches and exports in one press.
* Added: the finished file is kept in `uploads/bookly-exports/` and offered as a
  download link, alongside the automatic download.
* Added: the export's date and time are part of the file name.

= 1.0.0 =
* First release.
