/**
 * WP Server Toolkit Pro — Admin dashboard polling.
 *
 * Polls the REST dashboard endpoint at the configured interval only
 * (never continuously per-second) and updates the "last updated" label
 * plus the server status table in place, so navigating away/back and
 * revisits don't stack up extra timers.
 *
 * Developed by: Saiful Islam (aThemeArt)
 */
( function () {
	'use strict';

	if ( typeof WPST === 'undefined' ) {
		return;
	}

	var intervalMs = Math.max( 30, parseInt( WPST.pollingSeconds, 10 ) || 60 ) * 1000;
	var timer = null;

	function statusClass( status ) {
		if ( status === 'online' ) return 'wpst-status--healthy';
		if ( status === 'offline' ) return 'wpst-status--critical';
		return 'wpst-status--warning';
	}

	function statusLabel( status ) {
		return status.charAt( 0 ).toUpperCase() + status.slice( 1 );
	}

	function updateLastUpdatedLabel() {
		var el = document.getElementById( 'wpst-last-updated' );
		if ( el ) {
			var now = new Date();
			el.textContent = '— updated ' + now.toLocaleTimeString();
		}
	}

	function updateServerTable( servers ) {
		var table = document.getElementById( 'wpst-server-overview-table' );
		if ( ! table || ! servers ) {
			return;
		}
		var rows = table.querySelectorAll( 'tbody tr' );
		rows.forEach( function ( row ) {
			var link = row.querySelector( 'td:first-child a' );
			if ( ! link ) {
				return;
			}
			var match = link.getAttribute( 'href' ).match( /server=(\d+)/ );
			if ( ! match ) {
				return;
			}
			var id = parseInt( match[1], 10 );
			var server = servers.find( function ( s ) { return s.id === id; } );
			if ( ! server ) {
				return;
			}
			var statusCell = row.querySelector( 'td:nth-child(2) .wpst-status' );
			if ( statusCell ) {
				statusCell.className = 'wpst-status ' + statusClass( server.status );
				statusCell.textContent = statusLabel( server.status );
			}
		} );
	}

	function poll() {
		fetch( WPST.restUrl + '/dashboard/overview', {
			headers: { 'X-WP-Nonce': WPST.nonce },
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Request failed: ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				updateServerTable( data.servers );
				updateLastUpdatedLabel();
			} )
			.catch( function () {
				// Network hiccups are expected occasionally; fail silently
				// and try again on the next scheduled tick rather than
				// spamming the console or retrying immediately.
			} );
	}

	function start() {
		poll();
		timer = window.setInterval( poll, intervalMs );
	}

	// Pause polling when the tab is hidden to avoid unnecessary load,
	// resume (with an immediate refresh) when it becomes visible again.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden && timer ) {
			window.clearInterval( timer );
			timer = null;
		} else if ( ! document.hidden && ! timer ) {
			start();
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
