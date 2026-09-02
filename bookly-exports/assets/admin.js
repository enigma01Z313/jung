/* global BooklyExports */
( function () {
    'use strict';

    var cfg = window.BooklyExports;
    if ( ! cfg ) {
        return;
    }

    var runBtn    = document.getElementById( 'bookly-exports-run' );
    var progress  = document.getElementById( 'bookly-exports-progress' );
    var phaseEl   = document.getElementById( 'bookly-exports-phase' );
    var barEl     = document.getElementById( 'bookly-exports-bar' );
    var countsEl  = document.getElementById( 'bookly-exports-counts' );
    var doneEl    = document.getElementById( 'bookly-exports-done' );
    var errorEl   = document.getElementById( 'bookly-exports-error' );
    var cachedEl  = document.getElementById( 'bookly-exports-cached' );
    var pendEl    = document.getElementById( 'bookly-exports-pending' );
    var lastEl    = document.getElementById( 'bookly-exports-last' );
    var lastLabel = document.getElementById( 'bookly-exports-last-label' );
    var lastLink  = document.getElementById( 'bookly-exports-last-link' );

    if ( ! runBtn ) {
        return;
    }

    function post( action, data ) {
        var body = new FormData();
        body.append( 'action', action );
        body.append( 'nonce', cfg.nonce );
        Object.keys( data || {} ).forEach( function ( key ) {
            body.append( key, data[ key ] );
        } );

        return fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
            .then( function ( res ) { return res.json(); } )
            .then( function ( json ) {
                if ( ! json || ! json.success ) {
                    throw new Error( ( json && json.data && json.data.message ) || cfg.i18n.failed );
                }
                return json.data;
            } );
    }

    function num( value ) {
        return Number( value || 0 ).toLocaleString();
    }

    function setPhase( label ) {
        phaseEl.textContent = label;
    }

    // A batch is only "done" once it is written, so the bar is driven by counts
    // the server reports back rather than by how many requests have been sent.
    function setProgress( done, total ) {
        var pct = total > 0 ? Math.min( 100, Math.round( ( done / total ) * 100 ) ) : 100;
        barEl.style.width = pct + '%';
        countsEl.textContent = cfg.i18n.ofRecords
            .replace( '%1$s', num( done ) )
            .replace( '%2$s', num( total ) );
    }

    function showError( message ) {
        errorEl.hidden = false;
        errorEl.querySelector( 'p' ).textContent = message || cfg.i18n.failed;
    }

    function showDone( message ) {
        doneEl.hidden = false;
        doneEl.querySelector( 'p' ).textContent = message;
    }

    function reset() {
        doneEl.hidden = true;
        errorEl.hidden = true;
        progress.hidden = false;
        barEl.style.width = '0%';
        countsEl.textContent = '';
    }

    // The finished file is kept, so the link stays usable long after the run —
    // this only moves it on to whatever the latest run produced.
    function setLastExport( last ) {
        if ( ! last || ! last.url ) {
            return;
        }

        lastLabel.textContent = cfg.i18n.lastExport
            .replace( '%1$s', last.generatedAt )
            .replace( '%2$s', num( last.rows ) );
        lastLink.href = last.url;
        lastEl.hidden = false;
    }

    function refreshCounts() {
        return post( 'bookly_exports_status', {} ).then( function ( status ) {
            cachedEl.textContent = num( status.cached );
            pendEl.textContent = num( status.pending );
            return status;
        } );
    }

    /**
     * Phase 1 — cache every completed session that is not in the table yet,
     * BooklyExports.cacheBatch rows per request.
     */
    function cacheAll() {
        return refreshCounts().then( function ( status ) {
            var total = status.pending;

            if ( total === 0 ) {
                setPhase( cfg.i18n.nothingNew );
                setProgress( 1, 1 );
                return status;
            }

            setPhase( cfg.i18n.caching );
            setProgress( 0, total );

            function step() {
                return post( 'bookly_exports_cache_batch', {} ).then( function ( data ) {
                    cachedEl.textContent = num( data.cached );
                    pendEl.textContent = num( data.pending );
                    setProgress( total - data.pending, total );

                    if ( data.done ) {
                        setPhase( cfg.i18n.cached );
                        return data;
                    }
                    return step();
                } );
            }

            return step();
        } );
    }

    /**
     * Phase 2 — write the CSV incrementally, BooklyExports.csvBatch rows per
     * request, then hand the finished file to the browser.
     */
    function buildCsv() {
        return post( 'bookly_exports_csv_start', {} ).then( function ( start ) {
            setPhase( cfg.i18n.building );
            setProgress( 0, start.total );

            function step( offset ) {
                return post( 'bookly_exports_csv_batch', { stamp: start.stamp, offset: offset } )
                    .then( function ( data ) {
                        setProgress( data.offset, data.total );

                        if ( data.done ) {
                            return data;
                        }
                        return step( data.offset );
                    } );
            }

            return step( 0 );
        } );
    }

    // One press does both phases: catch the table up on what has completed since
    // last time, then export all of it.
    runBtn.addEventListener( 'click', function () {
        runBtn.disabled = true;
        reset();

        cacheAll()
            .then( buildCsv )
            .then( function ( result ) {
                setPhase( cfg.i18n.ready );
                showDone( cfg.i18n.ready );

                var last = result && result.last;
                setLastExport( last );

                if ( last && last.url ) {
                    // Content-Disposition: attachment, so this downloads rather
                    // than navigating away from the page.
                    window.location.href = last.url;
                }
            } )
            .catch( function ( err ) {
                showError( err && err.message );
            } )
            .then( refreshCounts )
            .catch( function () {} )
            .then( function () {
                runBtn.disabled = false;
            } );
    } );
}() );
