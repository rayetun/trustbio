/**
 * TrustBio Review Bar Builder — Live Preview & Save
 *
 * @package TrustBio
 */

/* global trustbioReviewBarAdmin */

( function ( $ ) {
	'use strict';

	// ── Live Preview ─────────────────────────────────────────────
	const TBReviewBarPreview = {
		$preview : null,
		$spinner : null,
		$form    : null,
		timeout  : null,

		init() {
			this.$preview = $( '#tbr-preview-content' );
			this.$spinner = $( '#tbr-preview-spinner' );
			this.$form    = $( '#tbr-builder-form' );

			if ( ! this.$preview.length || ! this.$form.length ) {
				return;
			}

			this.bindEvents();
			this.refresh();
		},

		bindEvents() {
			const self = this;
			this.$form.on( 'change input', 'input, select, textarea', function () {
				clearTimeout( self.timeout );
				self.timeout = setTimeout( function () { self.refresh(); }, 400 );
			} );
		},

		refresh() {
			const self = this;
			self.$spinner.show();

			const formData = self.$form.serializeArray();
			self.$form.find( 'input[type="checkbox"]:not(:checked)' ).each( function () {
				formData.push( { name: $( this ).attr( 'name' ), value: '0' } );
			} );
			formData.push( { name: 'action', value: 'trustbio_preview_review_bar' } );
			formData.push( { name: 'nonce',  value: trustbioReviewBarAdmin.nonces.preview } );

			$.post( trustbioReviewBarAdmin.ajaxUrl, formData )
				.done( function ( r ) {
					if ( r.success && r.data && r.data.html ) {
						self.$preview.html( r.data.html );
					}
				} )
				.always( function () { self.$spinner.hide(); } );
		},
	};

	window.TBReviewBarPreview = TBReviewBarPreview;

	$( function () { TBReviewBarPreview.init(); } );

	// ── Layout card selection ────────────────────────────────────
	$( document ).on( 'click', '.tbr-layout-card', function () {
		$( '.tbr-layout-card' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		var $radio = $( this ).find( 'input[type="radio"]' );
		$radio.prop( 'checked', true ).trigger( 'change' );
		updateOptionVisibility( $radio.val() );
	} );

	// ── Option visibility ────────────────────────────────────────
	function updateOptionVisibility( layout ) {
		$( '.tbr-option-group[data-show-for]' ).each( function () {
			var showFor = $( this ).data( 'show-for' ).split( ' ' );
			if ( showFor.indexOf( layout ) !== -1 ) {
				$( this ).show();
			} else {
				$( this ).hide();
			}
		} );
	}

	// Initialise visibility on page load.
	$( function () {
		var initialLayout = $( 'input[name="layout"]:checked' ).val();
		if ( initialLayout ) {
			updateOptionVisibility( initialLayout );
		}
	} );

	// ── Color swatch sync ────────────────────────────────────────
	$( document ).on( 'change input', '.tb-color-row input[type="color"]', function () {
		$( this ).closest( '.tb-color-row' ).find( '.tb-color-text' )
			.val( $( this ).val() )
			.trigger( 'change' );
	} );

	$( document ).on( 'change input', '.tb-color-row .tb-color-text', function () {
		var val = $( this ).val();
		if ( /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test( val ) ) {
			$( this ).closest( '.tb-color-row' ).find( 'input[type="color"]' )
				.val( val )
				.trigger( 'change' );
		}
	} );

	// ── Bar card dropdown (⋯ button) ─────────────────────────────
	$( document ).on( 'click', '.tbr-bar-more', function ( e ) {
		e.stopPropagation();
		var id        = $( this ).data( 'id' );
		var $dropdown = $( '#tbr-dropdown-' + id );
		$( '.tb-box-dropdown' ).not( $dropdown ).hide();
		$dropdown.toggle();
	} );

	// Close dropdowns when clicking outside.
	$( document ).on( 'click', function () {
		$( '.tb-box-dropdown' ).hide();
	} );

	// ── Save ─────────────────────────────────────────────────────
	var isSaving = false;
	function saveReviewBar() {
		if ( isSaving ) { return; }
		isSaving = true;

		var $btn  = $( '#tbr-save-btn' );
		var $btn2 = $( '#tbr-save-bar-btn' );
		$btn.prop( 'disabled', true ).text( trustbioReviewBarAdmin.i18n.saving );
		$btn2.prop( 'disabled', true );

		var data = $( '#tbr-builder-form' ).serializeArray();
		$( '#tbr-builder-form' ).find( 'input[type="checkbox"]:not(:checked)' ).each( function () {
			data.push( { name: $( this ).attr( 'name' ), value: '0' } );
		} );
		data.push( { name: 'action', value: 'trustbio_save_review_bar' } );
		data.push( { name: 'nonce',  value: trustbioReviewBarAdmin.nonces.save } );

		$.post( trustbioReviewBarAdmin.ajaxUrl, data )
			.done( function ( r ) {
				if ( r.success ) {
					$( '#tbr-save-status' ).text( trustbioReviewBarAdmin.i18n.saved ).css( 'color', '#16a34a' );
					if ( r.data.id && ! $( 'input[name="id"]' ).val() ) {
						$( 'input[name="id"]' ).val( r.data.id );
						if ( history.replaceState ) {
							history.replaceState( null, '', '?page=trustbio-review-bars&action=edit&id=' + r.data.id );
						}
					}
				} else {
					alert( r.data ? r.data.message : trustbioReviewBarAdmin.i18n.error );
				}
			} )
			.fail( function () {
				alert( trustbioReviewBarAdmin.i18n.error );
			} )
			.always( function () {
				isSaving = false;
				$btn.prop( 'disabled', false ).text( trustbioReviewBarAdmin.i18n.save );
				$btn2.prop( 'disabled', false );
			} );
	}

	$( '#tbr-builder-form' ).on( 'submit', function ( e ) { e.preventDefault(); saveReviewBar(); } );
	$( document ).on( 'click', '#tbr-save-btn, #tbr-save-bar-btn', function ( e ) { e.preventDefault(); saveReviewBar(); } );

	// ── Delete ───────────────────────────────────────────────────
	$( document ).on( 'click', '.tbr-delete-bar', function () {
		if ( ! confirm( trustbioReviewBarAdmin.i18n.confirmDelete ) ) { return; }
		var id    = $( this ).data( 'id' );
		var $card = $( this ).closest( '.tb-box-card' );

		$.post( trustbioReviewBarAdmin.ajaxUrl, {
			action: 'trustbio_delete_review_bar',
			id:     id,
			nonce:  trustbioReviewBarAdmin.nonces.delete,
		} ).done( function ( r ) {
			if ( r.success ) {
				$card.fadeOut( 300, function () { $card.remove(); } );
			} else {
				alert( r.data ? r.data.message : trustbioReviewBarAdmin.i18n.error );
			}
		} );
	} );

	// ── Duplicate ────────────────────────────────────────────────
	$( document ).on( 'click', '.tbr-duplicate-bar', function () {
		var id = $( this ).data( 'id' );
		$( '.tb-box-dropdown' ).hide();

		$.post( trustbioReviewBarAdmin.ajaxUrl, {
			action: 'trustbio_duplicate_review_bar',
			id:     id,
			nonce:  trustbioReviewBarAdmin.nonces.duplicate,
		} ).done( function ( r ) {
			if ( r.success ) {
				location.reload();
			} else {
				alert( r.data ? r.data.message : trustbioReviewBarAdmin.i18n.error );
			}
		} );
	} );

	// ── Set default ──────────────────────────────────────────────
	$( document ).on( 'click', '.tbr-set-default', function () {
		var id = $( this ).data( 'id' );
		$.post( trustbioReviewBarAdmin.ajaxUrl, {
			action: 'trustbio_set_default_review_bar',
			id:     id,
			nonce:  trustbioReviewBarAdmin.nonces.setDefault,
		} ).done( function ( r ) {
			if ( r.success ) { location.reload(); }
		} );
	} );

}( jQuery ) );
