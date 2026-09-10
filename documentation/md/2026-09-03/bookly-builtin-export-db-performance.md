# Speeding up Bookly's built-in appointments export at the database level

Scope: Bookly's own **Appointments → Export to CSV** (Bookly Pro), not our
`bookly-exports` (Simyatech Bookly Reports) plugin. The question was whether
indexes — or anything else purely at the DB level — can make it faster.

**Short answer:** yes, partly. The schema Bookly ships has a real, fixable index
gap on the one column the export actually filters and sorts by
(`bookly_appointments.start_date`), and adding a handful of indexes is safe and
worthwhile. But two of the three reasons the export is slow live in PHP, not in
the query plan, and no index touches them. Expect a solid improvement on the SQL
half and a hard floor set by the per-row PHP work.

---

## 1) What the export actually executes

`BooklyPro\Backend\Modules\Appointments\Ajax::exportAppointments()` calls
`Bookly\Backend\Modules\Appointments\Ajax::getAppointmentsTableData( $filter, array(), … , $export = true )`
— note the **empty `$limits`**, so there is no `LIMIT`; the whole filtered
history comes back in one result set.

That method runs **three** statements against the same 6-way join, plus one
statement per returned row.

```sql
-- (a) $total = $query->count();     <- runs BEFORE any WHERE is applied
SELECT COUNT(*)
  FROM r3fy6ztjv_bookly_appointments a
  LEFT JOIN r3fy6ztjv_bookly_customer_appointments ca ON a.id = ca.appointment_id
  LEFT JOIN r3fy6ztjv_bookly_services              s  ON s.id = a.service_id
  LEFT JOIN r3fy6ztjv_bookly_customers             c  ON c.id = ca.customer_id
  LEFT JOIN r3fy6ztjv_bookly_payments              p  ON p.id = ca.payment_id
  LEFT JOIN r3fy6ztjv_bookly_staff                 st ON st.id = a.staff_id
  LEFT JOIN r3fy6ztjv_bookly_staff_services        ss ON ss.staff_id = st.id
                                              AND ss.service_id = s.id
                                              AND ss.location_id = a.location_id;

-- (b) $filtered = $query->count();  <- same join, now with the WHERE
-- (c) the real fetch: same join + the full SELECT list + ORDER BY, no LIMIT
```

The WHERE that (b) and (c) carry:

```sql
WHERE a.start_date BETWEEN ? AND ?      -- or: a.start_date IS NOT NULL  (date = "any")
                                        -- or: a.start_date IS NULL      (date = "null")
  [ AND COALESCE(ca.created_at, a.created_at) BETWEEN ? AND ? ]
  [ AND a.staff_id = ? ]  [ AND ca.customer_id = ? ]  [ AND a.service_id = ? ]
  [ AND a.location_id = ? ]
  [ AND ca.status IN (…) ]
  [ AND ( a.id LIKE '%x%' OR ca.id LIKE '%x%' OR c.full_name LIKE '%x%'
          OR c.phone LIKE '%x%' OR c.email LIKE '%x%' OR st.full_name LIKE '%x%'
          OR COALESCE(s.title, a.custom_service_name) LIKE '%x%' ) ]
ORDER BY <saved list column> <dir>, a.id <dir>
```

Two things worth flagging right away, because they explain a lot of the wall
clock:

- Bookly issues `SET SQL_BIG_SELECTS=1` immediately before this — an explicit
  admission that the plan is expected to be scan-shaped.
- **`$total` and `$filtered` are computed and then thrown away.** The CSV writer
  only ever iterates `$data['data']`. So the export pays for two full `COUNT(*)`
  passes over the join for nothing, and query (a) has *no* `WHERE` at all — it
  counts the entire appointment history on every single export, no matter how
  narrow the filter.

---

## 2) The index inventory as Bookly ships it

Derived from `lib/Installer.php` plus InnoDB's rule that every foreign key gets
an index automatically.

### `r3fy6ztjv_bookly_appointments`

| Index | Source |
|---|---|
| `PRIMARY (id)` | declared |
| `(staff_id)` | auto, FK → `bookly_staff` |
| `(service_id)` | auto, FK → `bookly_services` |

**Missing: `start_date`, `created_at`, `location_id`.** `start_date` is the one
column the export filters on in every configuration and usually sorts by — and
it has no index at all. `location_id` has no FK in core (Locations is an
add-on), so it gets no index either.

### `r3fy6ztjv_bookly_customer_appointments`

| Index | Source |
|---|---|
| `PRIMARY (id)` | declared |
| `(customer_id)`, `(appointment_id)`, `(series_id)`, `(payment_id)`, `(order_id)` | auto, FKs |

The `a → ca` join itself is fine — `(appointment_id)` exists.
**Missing: anything on `status` or `created_at`**, both of which the export
filters on.

### The rest

- `r3fy6ztjv_bookly_customers`, `r3fy6ztjv_bookly_services`, `r3fy6ztjv_bookly_staff`,
  `r3fy6ztjv_bookly_payments` are all joined on their **primary key** — already
  optimal, nothing to add.
- `r3fy6ztjv_bookly_staff_services` has `UNIQUE (staff_id, service_id, location_id)`,
  which exactly covers the `ss` join condition. Also optimal.
  (Side note: nothing from `ss` is selected in the core query — it is a dead
  join here — but it costs one index lookup per row, not a scan.)

So the entire index problem is concentrated in two tables.

---

## 3) The indexes worth adding

Replace `r3fy6ztjv_` with the site's actual `$wpdb->prefix`.

```sql
-- The big one: the date-range filter AND the default sort.
ALTER TABLE r3fy6ztjv_bookly_appointments
  ADD INDEX idx_bkly_appt_start_date (start_date);

-- Filter-by-staff / filter-by-service combined with a date range.
-- Leftmost prefix still satisfies the FK, so these supersede the auto indexes.
ALTER TABLE r3fy6ztjv_bookly_appointments
  ADD INDEX idx_bkly_appt_staff_start   (staff_id,   start_date),
  ADD INDEX idx_bkly_appt_service_start (service_id, start_date);

-- Only if the Locations add-on is in use and exports are filtered by location.
ALTER TABLE r3fy6ztjv_bookly_appointments
  ADD INDEX idx_bkly_appt_location (location_id);

-- Helps the status filter and lets the ca side of the join be filtered on index.
-- status is VARCHAR(255); a prefix keeps the index small — no real status is
-- longer than ~20 chars, including Bookly Pro's custom ones.
ALTER TABLE r3fy6ztjv_bookly_customer_appointments
  ADD INDEX idx_bkly_ca_status_appt (status(20), appointment_id);
```

Notes:

- **`(start_date)` is effectively `(start_date, id)`.** InnoDB appends the
  primary key to every secondary index, so this index also satisfies the
  export's `ORDER BY start_date <dir>, a.id <dir>` — MySQL scans a matching
  index backwards for the `DESC, DESC` case. That is the difference between
  reading rows in index order and materialising the entire result set into a
  temp table to sort it.
- The temp-table point matters more than usual here because the SELECT list
  pulls **five TEXT columns** (`ca.notes`, `ca.extras`, `ca.rating_comment`,
  `a.internal_note`, `a.online_meeting_data`). On MySQL before 8.0.13 (and on
  MariaDB), a temp table containing BLOB/TEXT is forced **on disk**, regardless
  of `tmp_table_size`. Avoiding the sort avoids that entirely.
- Cost: ~9 bytes per row per index on `bookly_appointments` (5-byte `DATETIME`
  + 4-byte PK). At 500k appointments that is single-digit MB. Negligible.
- All of these are `ALGORITHM=INPLACE, LOCK=NONE` on InnoDB (MySQL 5.6+ /
  MariaDB 10.0+), so they can be added on a live site. Append
  `, ALGORITHM=INPLACE, LOCK=NONE` explicitly if you want the statement to fail
  loudly rather than silently fall back to a blocking table copy.
- Bookly's own updater never adds these (`Updater.php` only ever adds
  `invoice_id_idx` on payments and a unique key on staff schedule items), so
  they will survive plugin updates — but they are **not** protected: a
  destructive `dbDelta` or a manual table rebuild could drop them. Re-check
  after major Bookly upgrades.

Afterwards:

```sql
ANALYZE TABLE r3fy6ztjv_bookly_appointments, r3fy6ztjv_bookly_customer_appointments;
```

---

## 4) What no index can fix

These are structural. Listing them so nobody spends a week hunting for the index
that makes them go away.

1. **`COALESCE(ca.created_at, a.created_at) BETWEEN ? AND ?`** — non-sargable,
   and the expression spans *two* tables, so it cannot even be rescued with an
   indexed generated column. Any export that sets a **Created date** filter
   gives up index access on that predicate entirely.
   → *Practical advice: leave "Created date" on **Any** and filter by
   appointment date instead.*
2. **The search box** — `LIKE '%x%'` with a leading wildcard, across seven
   columns in an `OR`. Never uses an index; a FULLTEXT index would not be picked
   up by this query either.
   → *Practical advice: clear the search field before exporting.*
3. **Sorting by a column on a joined table.** The export inherits whatever sort
   the Appointments list was last left in. If that is the **No.** column it maps
   to `ca.id`, i.e. the sort key lives on the joined table, which forces a temp
   table + filesort no matter what indexes exist on `a`.
   → *Practical advice: set the Appointments list sort to **Date** before
   exporting.* Free, and it lets the new `start_date` index do its job.
4. **The per-row N+1.** Inside the row loop:

   ```php
   $customer_appointment = new Lib\Entities\CustomerAppointment();
   $customer_appointment->load( $row['ca_id'] );   // SELECT * … WHERE id = ? LIMIT 1
   ```

   `Entity::load()` issues one query per row, purely to hand the object to the
   Custom Fields proxy — even when every column it fetches was already selected
   by the main query, and even when the Custom Fields add-on is not installed
   (it is not, on this install — only the proxy stubs are present). A 50,000-row
   export therefore issues **50,001** queries. They are primary-key lookups, so
   each is fast; 50,000 PHP↔MySQL round trips are not.
   This is the single largest remaining cost after indexing, and it is a code
   fix, not a DB fix.
5. **The two discarded `COUNT(*)` passes** described in §1. Indexing shrinks
   them but cannot remove them; only a code change can.
6. **No `LIMIT`, no streaming.** The full result set is materialised into a PHP
   array before the first CSV line is written, so peak memory scales with the
   export size — this is what produces the timeouts and 500s on large ranges,
   and it is unaffected by any index.

---

## 5) Server-level knobs that are also "DB level"

- **`innodb_buffer_pool_size`** — the biggest non-index lever. If
  `bookly_appointments` + `bookly_customer_appointments` + `bookly_customers`
  and their indexes do not fit, every export re-reads from disk. Size it to
  comfortably hold those tables.
- **`tmp_table_size` / `max_heap_table_size`** — worth raising, but see the TEXT
  caveat above: on MySQL < 8.0.13 and on MariaDB these do not help a temp table
  containing TEXT columns, because it goes to disk regardless. Removing the sort
  (indexing `start_date` + sorting the list by Date) is the real fix.
- **`tmpdir`** — if disk temp tables are unavoidable, having `tmpdir` on fast
  local storage is a direct win.
- **Confirm the engine is actually InnoDB.** Bookly declares `ENGINE = INNODB`,
  but tables migrated from very old installs or restored from a mangled dump can
  end up MyISAM — which would also mean **no FK indexes at all**, turning every
  join into a scan:

  ```sql
  SELECT table_name, engine, table_rows
    FROM information_schema.tables
   WHERE table_schema = DATABASE()
     AND table_name LIKE '%bookly%';
  ```
- **`innodb_stats_persistent = ON`** (default on modern versions) so the
  optimizer's cardinality estimates for the new indexes stay stable.

---

## 6) How to verify

```sql
-- Before/after inventory
SHOW INDEX FROM r3fy6ztjv_bookly_appointments;
SHOW INDEX FROM r3fy6ztjv_bookly_customer_appointments;

-- Plan for the export's fetch query (paste the real one from the slow log)
EXPLAIN SELECT a.id, ca.id, a.start_date
  FROM r3fy6ztjv_bookly_appointments a
  LEFT JOIN r3fy6ztjv_bookly_customer_appointments ca ON a.id = ca.appointment_id
 WHERE a.start_date BETWEEN '2025-01-01 00:00:00' AND '2025-12-31 23:59:59'
 ORDER BY a.start_date DESC, a.id DESC;
```

What to look for on the `a` row: `type` moving from **`ALL`** to **`range`**,
`key` showing `idx_bkly_appt_start_date`, and `Extra` losing **`Using temporary;
Using filesort`**.

For the real thing, capture it end to end:

```sql
SET profiling = 1;          -- or: enable the slow log with long_query_time = 0
-- run the export from the admin UI
SHOW PROFILES;
```

---

## 7) Bottom line

| Cost | Fixable with indexes? |
|---|---|
| Unfiltered `COUNT(*)` over the whole join | Partly — shrunk, not removed |
| Filtered `COUNT(*)` over the join | Partly — same |
| Full scan of `bookly_appointments` for the date range | **Yes** |
| Temp table + filesort for the `ORDER BY` | **Yes**, if sorting by Date |
| `COALESCE(created_at)` filter | No |
| `LIKE '%…%'` search | No |
| One `SELECT` per exported row (N+1) | No — code fix |
| Whole result set held in PHP memory, no `LIMIT` | No — code fix |

Adding `idx_bkly_appt_start_date` and running the export sorted by **Date**,
with **Created date = Any** and an empty search box, is the whole DB-level
lever — and on a large history it is a real one: it converts the dominant access
path from a full table scan plus an on-disk sort into an index range scan in
output order. What it will not do is make the export *scale*, because the
per-row query and the unbounded result set stay linear in the number of rows no
matter how good the plan is.

That gap is precisely what our **`bookly-exports`** plugin already routes
around: a flat `r3fy6ztjv_bookly_appointments_cached` table (`UNIQUE (caId)`,
`KEY (appointmentDate)`), populated incrementally in 100-row batches and read
back in 1000-row batches, so the CSV never depends on the 6-way join or on a
single unbounded request. At our data volume that remains the right path; the
indexes above are for when someone reaches for Bookly's own export button
anyway.
