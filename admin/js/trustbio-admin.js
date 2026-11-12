/**
 * TrustBio Admin JavaScript
 * Handles: tab navigation, notice system, toggle switches,
 * AJAX helpers, URL preview, and shared admin UI interactions.
 *
 * @package TrustBio
 */

/* global trustbioAdmin, jQuery */

( function ( $ ) {
	'use strict';

	// ============================================================
	// Notice System
	// ============================================================
	const TBNotice = {
		container: null,

		init() {
			this.container = $( '.tb-notice-area' );
		},

		show( message, type = 'success', duration = 4000 ) {
			const icons = {
				success : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
				error   : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
				warning : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
				info    : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>',
			};

			const $notice = $( `
				<div class="tb-notice tb-notice-${type}" role="alert">
					${icons[ type ] || icons.info}
					<span>${ $( '<span>' ).text( message ).html() }</span>
					<button class="tb-notice__close" aria-label="Dismiss">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
							<path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
						</svg>
					</button>
				</div>
			` );

			// If no dedicated container, prepend to the wrap.
			const $target = this.container && this.container.length
				? this.container
				: $( '.trustbio-admin-wrap' );

			$target.prepend( $notice );

			// Auto-dismiss.
			if ( duration > 0 ) {
				setTimeout( () => this.dismiss( $notice ), duration );
			}

			// Manual dismiss.
			$notice.find( '.tb-notice__close' ).on( 'click', () => this.dismiss( $notice ) );

			return $notice;
		},

		dismiss( $notice ) {
			$notice.css( 'transition', 'opacity 0.2s ease, transform 0.2s ease' );
			$notice.css( { opacity: 0, transform: 'translateY(-6px)' } );
			setTimeout( () => $notice.remove(), 220 );
		},

		success( msg ) { return this.show( msg, 'success' ); },
		error( msg )   { return this.show( msg, 'error', 6000 ); },
		warning( msg ) { return this.show( msg, 'warning', 5000 ); },
		info( msg )    { return this.show( msg, 'info' ); },
	};

	// ============================================================
	// Tab Navigation
	// ============================================================
	const TBTabs = {
		init() {
			$( document ).on( 'click', '.tb-tab[data-tab]', function ( e ) {
				e.preventDefault();
				const target    = $( this ).data( 'tab' );
				const wrapKey   = $( this ).data( 'tabs-wrap' );

				// Find the wrapping container two ways:
				// 1. A parent element that has data-tabs-wrap (not the button itself).
				// 2. Fallback: closest .tb-card or .tb-tabs-wrap container.
				let $wrap = $( this ).parents( '[data-tabs-wrap]' ).first();

				// If the only match is the button itself (shouldn't happen) fall back to card.
				if ( ! $wrap.length || $wrap.is( 'button' ) || $wrap.is( '.tb-tab' ) ) {
					$wrap = $( this ).closest( '.tb-card, .tb-tabs-container' );
				}

				if ( ! $wrap.length ) {
					return;
				}

				// Update tab buttons — only those sharing the same wrapKey.
				if ( wrapKey ) {
					$( `.tb-tab[data-tabs-wrap="${ wrapKey }"]` ).removeClass( 'is-active' );
				} else {
					$wrap.find( '.tb-tab' ).removeClass( 'is-active' );
				}
				$( this ).addClass( 'is-active' );

				// Update panes — search within the wrapping container.
				$wrap.find( '.tb-tab-pane' ).removeClass( 'is-active' );
				$wrap.find( `[data-tab-pane="${ target }"]` ).addClass( 'is-active' );

				// Persist active tab in URL hash (for settings page).
				if ( history.replaceState ) {
					history.replaceState( null, '', '#' + target );
				}
			} );

			// Restore tab from URL hash on page load.
			const hash = window.location.hash.replace( '#', '' );
			if ( hash ) {
				const $tabBtn = $( `.tb-tab[data-tab="${ hash }"]` );
				if ( $tabBtn.length ) {
					$tabBtn.trigger( 'click' );
				}
			}
		},
	};

	// ============================================================
	// URL Base Preview (Settings page)
	// ============================================================
	const TBUrlPreview = {
		init() {
			const $input   = $( '#tb-author-url-base' );
			const $preview = $( '#tb-url-preview-slug' );

			if ( ! $input.length ) {
				return;
			}

			$input.on( 'input', function () {
				const val = $( this ).val().trim() || 'authors';
				$preview.text( val );
			} );

			// Sanitize on blur (no uppercase, no spaces).
			$input.on( 'blur', function () {
				const sanitized = $( this ).val()
					.toLowerCase()
					.replace( /[^a-z0-9-]/g, '-' )
					.replace( /-+/g, '-' )
					.replace( /^-|-$/g, '' );
				$( this ).val( sanitized );
				$preview.text( sanitized || 'authors' );
			} );
		},
	};

	// ============================================================
	// Confirm Delete
	// ============================================================
	const TBConfirm = {
		init() {
			$( document ).on( 'click', '[data-confirm]', function ( e ) {
				const msg = $( this ).data( 'confirm' )
					|| trustbioAdmin.i18n.confirmDelete;
				if ( ! window.confirm( msg ) ) {
					e.preventDefault();
					e.stopImmediatePropagation();
				}
			} );
		},
	};

	// ============================================================
	// AJAX Helper
	// ============================================================
	const TBAjax = {
		/**
		 * Post an AJAX request to WordPress.
		 *
		 * @param {string} action   wp_ajax_{action} hook name.
		 * @param {Object} data     Additional POST data.
		 * @param {Object} options  Optional overrides.
		 * @returns {Promise}
		 */
		post( action, data = {}, options = {} ) {
			return new Promise( ( resolve, reject ) => {
				$.ajax( {
					url     : trustbioAdmin.ajaxUrl,
					method  : 'POST',
					data    : Object.assign( { action }, data ),
					...options,
					success : ( response ) => {
						if ( response.success ) {
							resolve( response.data );
						} else {
							reject( response.data );
						}
					},
					error   : ( xhr, status, err ) => reject( { message: err } ),
				} );
			} );
		},
	};

	// ============================================================
	// Button Loading State
	// ============================================================
	const TBButton = {
		/**
		 * Set a button to loading state.
		 *
		 * @param {jQuery} $btn
		 * @returns {string} Original button HTML, for restoring later.
		 */
		loading( $btn ) {
			const original = $btn.html();
			$btn.prop( 'disabled', true ).html(
				`<span class="tb-spinner"></span> ${ trustbioAdmin.i18n.saving }`
			);
			return original;
		},

		/**
		 * Restore button to its original state.
		 *
		 * @param {jQuery} $btn
		 * @param {string} original
		 */
		restore( $btn, original ) {
			$btn.prop( 'disabled', false ).html( original );
		},
	};

	// ============================================================
	// Row actions: Inline toggle (enable/disable)
	// ============================================================
	const TBRowActions = {
		init() {
			$( document ).on( 'click', '.tb-row-action-toggle-status', function ( e ) {
				e.preventDefault();
				const $btn    = $( this );
				const action  = $btn.data( 'action' );
				const id      = $btn.data( 'id' );
				const nonce   = $btn.data( 'nonce' );
				const $badge  = $btn.closest( 'tr' ).find( '.tb-badge' );

				TBAjax.post( action, { id, nonce } )
					.then( ( data ) => {
						// Toggle badge visual.
						if ( $badge.hasClass( 'tb-badge-success' ) ) {
							$badge.removeClass( 'tb-badge-success' ).addClass( 'tb-badge-danger' ).text( 'Disabled' );
							$btn.text( 'Enable' );
						} else {
							$badge.removeClass( 'tb-badge-danger' ).addClass( 'tb-badge-success' ).text( 'Enabled' );
							$btn.text( 'Disable' );
						}
						TBNotice.success( data.message || trustbioAdmin.i18n.saved );
					} )
					.catch( ( err ) => {
						TBNotice.error( err.message || trustbioAdmin.i18n.error );
					} );
			} );
		},
	};

	// ============================================================
	// Bulk Actions
	// ============================================================
	const TBBulkActions = {
		init() {
			// Select all checkbox.
			$( document ).on( 'change', '.tb-check-all', function () {
				const checked = $( this ).prop( 'checked' );
				$( this ).closest( '.tb-table-wrap, .tb-card' )
					.find( 'tbody input[type="checkbox"]' )
					.prop( 'checked', checked );
			} );

			// Deselect "all" if one row unchecked.
			$( document ).on( 'change', 'tbody input[type="checkbox"]', function () {
				const $table   = $( this ).closest( '.tb-table-wrap, .tb-card' );
				const total    = $table.find( 'tbody input[type="checkbox"]' ).length;
				const selected = $table.find( 'tbody input[type="checkbox"]:checked' ).length;
				$table.find( '.tb-check-all' ).prop( 'checked', total === selected );
			} );
		},
	};

	// ============================================================
	// Media Uploader (Avatar)
	// ============================================================
	const TBMediaUploader = {
		frame: null,

		init() {
			$( document ).on( 'click', '.tb-upload-avatar-btn', ( e ) => {
				e.preventDefault();
				const $btn       = $( e.currentTarget );
				const $preview   = $( $btn.data( 'preview' ) );
				const $idInput   = $( $btn.data( 'input' ) );

				if ( this.frame ) {
					this.frame.open();
					return;
				}

				this.frame = wp.media( {
					title   : 'Select Author Avatar',
					button  : { text: 'Use as Avatar' },
					library : { type: 'image' },
					multiple: false,
				} );

				this.frame.on( 'select', () => {
					const attachment = this.frame.state().get( 'selection' ).first().toJSON();
					const url = attachment.sizes?.thumbnail?.url || attachment.url;
					$preview.attr( 'src', url ).show();
					$idInput.val( attachment.id );
				} );

				this.frame.open();
			} );

			// Remove avatar.
			$( document ).on( 'click', '.tb-remove-avatar-btn', function () {
				const $btn = $( this );
				$( $btn.data( 'preview' ) ).attr( 'src', '' ).hide();
				$( $btn.data( 'input' ) ).val( '' );
			} );
		},
	};

	// ============================================================
	// Slug Generator (auto-generate from name input)
	// ============================================================
	const TBSlugGenerator = {
		init() {
			$( document ).on( 'input', '[data-slug-source]', function () {
				const $target = $( $( this ).data( 'slug-source' ) );
				if ( $target.length && ! $target.data( 'manual' ) ) {
					const slug = $( this ).val()
						.toLowerCase()
						.replace( /[^a-z0-9\s-]/g, '' )
						.replace( /\s+/g, '-' )
						.replace( /-+/g, '-' )
						.replace( /^-|-$/g, '' );
					$target.val( slug );
				}
			} );

			// Mark slug as manually edited.
			$( document ).on( 'input', '[data-slug-target]', function () {
				$( this ).data( 'manual', true );
			} );
		},
	};

	// ============================================================
	// Color Picker (for future box builder — Phase 6)
	// ============================================================
	const TBColorPicker = {
		init() {
			if ( $.fn.wpColorPicker ) {
				$( '.tb-color-picker' ).wpColorPicker( {
					change( event, ui ) {
						$( event.target ).trigger( 'tb:color-changed', [ ui.color.toString() ] );
					},
				} );
			}
		},
	};

	// ============================================================
	// Sticky Save Bar: show when form is dirty
	// ============================================================
	const TBSaveBar = {
		isDirty: false,

		init() {
			const $bar = $( '.tb-save-bar' );
			if ( ! $bar.length ) { return; }

			// Watch for any form change.
			$( '.trustbio-admin-wrap' ).on(
				'change input',
				'input, select, textarea',
				() => {
					if ( ! this.isDirty ) {
						this.isDirty = true;
						$bar.addClass( 'is-dirty' );
					}
				}
			);

			// Reset dirty state after save.
			$( document ).on( 'trustbio:saved', () => {
				this.isDirty = false;
				$bar.removeClass( 'is-dirty' );
			} );
		},
	};

	// ============================================================
	// Range Slider: sync value display
	// ============================================================
	const TBRangeSlider = {
		init() {
			$( document ).on( 'input', 'input[type="range"].tb-range', function () {
				const $display = $( '#' + $( this ).attr( 'id' ) + '-display' );
				if ( $display.length ) {
					$display.text( $( this ).val() );
				}
			} );
		},
	};

	// ============================================================
	// Init all modules on DOM ready
	// ============================================================
	$( function () {
		TBNotice.init();
		TBTabs.init();
		TBUrlPreview.init();
		TBConfirm.init();
		TBRowActions.init();
		TBBulkActions.init();
		TBMediaUploader.init();
		TBSlugGenerator.init();
		TBColorPicker.init();
		TBSaveBar.init();
		TBRangeSlider.init();
		TBTooltip.init();
	} );

	// ============================================================
	// Tooltip Component (.tb-tip)
	// ============================================================
	const TBTooltip = {
		init() {
			$( document ).on( 'click', '.tb-tip', function ( e ) {
				e.stopPropagation();
				const $tip = $( this );
				const wasOpen = $tip.hasClass( 'is-open' );
				$( '.tb-tip.is-open' ).removeClass( 'is-open' );
				if ( ! wasOpen ) {
					$tip.addClass( 'is-open' );
					TBTooltip.reposition( $tip );
				}
			} );

			$( document ).on( 'click', function () {
				$( '.tb-tip.is-open' ).removeClass( 'is-open' );
			} );

			$( document ).on( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					$( '.tb-tip.is-open' ).removeClass( 'is-open' );
				}
			} );
		},

		reposition( $tip ) {
			const rect = $tip[0].getBoundingClientRect();
			$tip.toggleClass( 'tip-below', rect.top < 120 );
			$tip.toggleClass( 'tip-left', rect.right > window.innerWidth - 260 );
		},
	};

	// Expose utilities to other scripts.
	window.TrustBio = {
		Notice : TBNotice,
		Ajax   : TBAjax,
		Button : TBButton,
	};

}( jQuery ) );
