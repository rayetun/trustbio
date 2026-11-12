/* global trustbioSettings, jQuery */
( function ( $ ) {
	'use strict';

	var cfg    = typeof trustbioSettings !== 'undefined' ? trustbioSettings : {};
	var NONCES = cfg.nonces  || {};
	var AJAX   = cfg.ajaxUrl || ( typeof ajaxurl !== 'undefined' ? ajaxurl : '' );
	var SITE   = cfg.siteUrl || '';
	var STR    = cfg.strings || { saving: 'Saving…', saved: 'Saved!', error: 'Save failed. Please retry.' };

	// ── Tab switching ──────────────────────────────────────────────────────────

	function activateTab( id ) {
		var $btn = $( '.trustbio-sp-nav-item[data-tab="' + id + '"]' );

		/*
		 * The remembered tab can be gone: switching a feature module off
		 * removes its tab and its panel, and the last-used tab is restored
		 * from sessionStorage or ?tab=. Without this the screen came up with
		 * nothing selected and every panel hidden — a blank Settings page
		 * that only a click on another tab recovered from.
		 */
		if ( ! $btn.length ) {
			$btn = $( '.trustbio-sp-nav-item' ).first();
			id   = $btn.data( 'tab' );
			if ( ! id ) {
				return;
			}
		}

		$( '.trustbio-sp-nav-item' ).removeClass( 'is-active' );
		$btn.addClass( 'is-active' );
		$( '.trustbio-sp-panel' ).hide();
		$( '.trustbio-sp-panel[data-panel="' + id + '"]' ).show();
		try { sessionStorage.setItem( 'tbsp_active_tab', id ); } catch ( e ) { /* ignore */ }
	}

	$( '.trustbio-sp-nav-item' ).on( 'click', function () {
		activateTab( $( this ).data( 'tab' ) );
	} );

	var lastTab = 'overview';
	try { lastTab = sessionStorage.getItem( 'tbsp_active_tab' ) || 'overview'; } catch ( e ) { /* ignore */ }
	try {
		var urlTab = new URLSearchParams( window.location.search ).get( 'tab' );
		if ( urlTab ) { lastTab = urlTab; }
	} catch ( e ) { /* ignore */ }
	activateTab( lastTab );

	// ── +/- number steppers ────────────────────────────────────────────────────

	$( document ).on( 'click', '.trustbio-sp-num-btn', function () {
		var $btn   = $( this );
		var $input = $btn.siblings( '.trustbio-sp-number-input' );
		var dir    = parseInt( $btn.data( 'dir' ), 10 ) || 1;
		var val    = parseInt( $input.val(), 10 )       || 0;
		var min    = parseInt( $input.attr( 'min' ), 10 );
		var max    = parseInt( $input.attr( 'max' ), 10 );
		var next   = val + dir;
		if ( ! isNaN( min ) ) { next = Math.max( min, next ); }
		if ( ! isNaN( max ) ) { next = Math.min( max, next ); }
		$input.val( next );
	} );

	// ── Color pickers ──────────────────────────────────────────────────────────

	$( document ).on( 'input change', '.trustbio-sp-color-input', function () {
		var key = $( this ).data( 'tb-setting' );
		$( '[data-tb-color-text="' + key + '"]' ).val( $( this ).val() );
	} );

	$( document ).on( 'input', '[data-tb-color-text]', function () {
		var val = $( this ).val();
		if ( /^#[0-9A-Fa-f]{6}$/.test( val ) ) {
			var key = $( this ).data( 'tb-color-text' );
			$( '.trustbio-sp-color-input[data-tb-setting="' + key + '"]' ).val( val );
		}
	} );

	// ── URL base preview ───────────────────────────────────────────────────────

	function updateUrlPreview() {
		var base = ( $( '#tbsp-url-base-input' ).val() || 'authors' ).replace( /[^a-z0-9-]/gi, '' ) || 'authors';
		$( '#tbsp-url-preview' ).text( SITE + base + '/author-slug/' );
	}
	$( document ).on( 'input', '#tbsp-url-base-input', updateUrlPreview );
	updateUrlPreview();

	// ── Collect settings ───────────────────────────────────────────────────────

	function collectSettings() {
		var out = {};
		// Individual toggle checkboxes.
		$( '[data-tb-setting][type="checkbox"]' ).each( function () {
			out[ $( this ).data( 'tb-setting' ) ] = this.checked ? 1 : 0;
		} );
		// Selects, text inputs, number inputs (exclude colour-text mirrors).
		$( '[data-tb-setting]:not([type="checkbox"]):not([data-tb-color-text])' ).each( function () {
			out[ $( this ).data( 'tb-setting' ) ] = $( this ).val();
		} );
		// Checkbox groups → arrays.
		var groups = {};
		$( '[data-tb-group]' ).each( function () {
			var grp = $( this ).data( 'tb-group' );
			if ( ! groups[ grp ] ) { groups[ grp ] = []; }
			if ( this.checked ) { groups[ grp ].push( $( this ).val() ); }
		} );
		$.extend( out, groups );
		return out;
	}

	function collectFeatures() {
		var out = [];
		$( '[data-feature][type="checkbox"]' ).each( function () {
			if ( this.checked ) { out.push( $( this ).data( 'feature' ) ); }
		} );
		return out;
	}

	// ── Save button ────────────────────────────────────────────────────────────

	$( document ).on( 'click', '.trustbio-sp-save-btn', function () {
		var $btn    = $( this );
		var $status = $btn.siblings( '.trustbio-sp-save-status' );
		var type    = $btn.data( 'save-type' );

		$btn.prop( 'disabled', true ).addClass( 'is-loading' ).text( STR.saving );
		$status.removeClass( 'is-visible is-error' );

		var postData;
		if ( type === 'features' ) {
			postData = { action: 'trustbio_save_features', nonce: NONCES.saveFeatures, features: collectFeatures() };
		} else {
			postData = { action: 'trustbio_save_settings', nonce: NONCES.saveSettings, settings: collectSettings() };
		}

		$.post( AJAX, postData )
			.done( function ( resp ) {
				if ( resp && resp.success ) {
					$btn.removeClass( 'is-loading' ).addClass( 'is-success' ).text( STR.saved );
					$status.text( ( resp.data && resp.data.message ) ? resp.data.message : STR.saved ).addClass( 'is-visible' );
					setTimeout( function () {
						$btn.removeClass( 'is-success' ).text( 'Save Changes' ).prop( 'disabled', false );
						$status.removeClass( 'is-visible' );
					}, 2800 );
				} else {
					throw new Error( ( resp && resp.data && resp.data.message ) || STR.error );
				}
			} )
			.fail( function () {
				$btn.removeClass( 'is-loading is-success' ).text( 'Save Changes' ).prop( 'disabled', false );
				$status.text( STR.error ).addClass( 'is-visible is-error' );
				setTimeout( function () { $status.removeClass( 'is-visible is-error' ); }, 3000 );
			} );
	} );

	// ── Feature card class sync ────────────────────────────────────────────────

	$( document ).on( 'change', '[data-feature][type="checkbox"]', function () {
		$( this ).closest( '.trustbio-sp-module-card' ).toggleClass( 'is-enabled', this.checked );
	} );

	// ── Maintenance helpers ────────────────────────────────────────────────────

	function runAction( $btn, $status, data, onSuccess ) {
		var label = $btn.text();
		$btn.prop( 'disabled', true ).text( STR.saving );
		$.post( AJAX, data )
			.done( function ( resp ) {
				$btn.text( label ).prop( 'disabled', false );
				if ( resp && resp.success ) {
					$status.text( ( resp.data && resp.data.message ) || STR.saved ).addClass( 'is-visible' );
					if ( onSuccess ) { onSuccess( resp ); }
					setTimeout( function () { $status.removeClass( 'is-visible' ); }, 3500 );
				} else {
					$status.text( ( resp && resp.data && resp.data.message ) || STR.error ).addClass( 'is-visible is-error' );
					setTimeout( function () { $status.removeClass( 'is-visible is-error' ); }, 4000 );
				}
			} )
			.fail( function () {
				$btn.text( label ).prop( 'disabled', false );
				$status.text( STR.error ).addClass( 'is-visible is-error' );
				setTimeout( function () { $status.removeClass( 'is-visible is-error' ); }, 4000 );
			} );
	}

	$( '#tbsp-flush-rewrites' ).on( 'click', function () {
		runAction( $( this ), $( '#tbsp-flush-status' ), {
			action:          'trustbio_flush_rewrites',
			nonce:           NONCES.flushRewrites,
			author_url_base: $( '#tbsp-url-base-input' ).val() || 'authors',
		} );
	} );

	$( '#tbsp-clear-cache' ).on( 'click', function () {
		runAction( $( this ), $( '#tbsp-cache-status' ), {
			action: 'trustbio_clear_cache',
			nonce:  NONCES.clearCache,
		} );
	} );

	$( '#tbsp-export-data' ).on( 'click', function () {
		var $btn    = $( this );
		var $status = $( '#tbsp-export-status' );
		$btn.prop( 'disabled', true ).text( STR.saving );
		$.post( AJAX, { action: 'trustbio_export_data', nonce: NONCES.exportData } )
			.done( function ( resp ) {
				$btn.text( 'Export' ).prop( 'disabled', false );
				if ( resp && resp.success && resp.data && resp.data.data ) {
					var blob = new Blob( [ resp.data.data ], { type: 'application/json' } );
					var a    = document.createElement( 'a' );
					a.href   = URL.createObjectURL( blob );
					a.download = resp.data.filename || 'trustbio-export.json';
					a.click();
					$status.text( 'Downloaded.' ).addClass( 'is-visible' );
					setTimeout( function () { $status.removeClass( 'is-visible' ); }, 3000 );
				}
			} )
			.fail( function () { $btn.text( 'Export' ).prop( 'disabled', false ); } );
	} );

	$( '#tbsp-import-btn' ).on( 'click', function () {
		var json = $( '#tbsp-import-json' ).val().trim();
		if ( ! json ) { return; }
		runAction( $( this ), $( '#tbsp-import-status' ), {
			action:      'trustbio_import_data',
			nonce:       NONCES.saveSettings,
			import_json: json,
		} );
	} );

	// ── Shortcode copy buttons ─────────────────────────────────────────────────
	$( document ).on( 'click', '.trustbio-sp-sc-copy', function () {
		var $btn = $( this );
		var text = $btn.data( 'copy' );
		if ( ! text ) { return; }
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () {
				$btn.text( 'Copied!' ).addClass( 'copied' );
				setTimeout( function () { $btn.text( 'Copy' ).removeClass( 'copied' ); }, 1800 );
			} );
		} else {
			var $tmp = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
			document.execCommand( 'copy' );
			$tmp.remove();
			$btn.text( 'Copied!' ).addClass( 'copied' );
			setTimeout( function () { $btn.text( 'Copy' ).removeClass( 'copied' ); }, 1800 );
		}
	} );

} )( jQuery );
