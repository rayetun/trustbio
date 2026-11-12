/**
 * TrustBio Box Builder — Live Preview (single source of truth)
 *
 * The server render is authoritative: every change still goes to
 * trustbio_preview_box and the returned HTML replaces the panel. What changed
 * is that it no longer *waits* for that.
 *
 * Three things were making the panel feel slow:
 *
 *   1. One 400ms debounce for everything. Picking a radio or a select is a
 *      single, final act — there is nothing to debounce — yet it sat behind
 *      the same timer as typing.
 *   2. Requests were never cancelled, so dragging a slider queued a line of
 *      them and the panel settled on whichever happened to land last.
 *   3. Nothing was painted locally, so even a colour swatch cost a round trip.
 *
 * The local paint below is a latency mask, not a second renderer. Every entry
 * mirrors something trustbio_build_wrapper_style() emits, and the next AJAX
 * response overwrites it wholesale — so if an entry is ever wrong, the server
 * corrects it a moment later rather than the preview staying wrong.
 *
 * @package TrustBio
 */

/* global trustbioAdmin */

( function ( $ ) {
	'use strict';

	// Must match trustbio_build_wrapper_style() in includes/helpers.php.
	const SHADOW = '0 30px 60px -30px rgba(20,20,20,.18), 0 8px 20px -10px rgba(20,20,20,.08)';
	const AVATAR_RADIUS = { circle: '50%', rounded: '14px', square: '0' };
	const ICON_CHROME = {
		color:      { bg: '#ece8e1',    border: '0 solid transparent' },
		monochrome: { bg: null,         border: '0 solid transparent' }, // bg = icon colour
		outline:    { bg: 'transparent', border: '1.5px solid #e5e7eb' },
	};

	const TBBoxPreview = {
		$preview : null,
		$spinner : null,
		$form    : null,
		timeout  : null,
		spinTimer: null,
		xhr      : null,

		init() {
			this.$preview = $( '#tb-preview-content' );
			this.$spinner = $( '#tb-preview-spinner' );
			this.$form    = $( '#tb-box-builder-form' );

			if ( ! this.$preview.length || ! this.$form.length ) {
				return;
			}

			this.bindEvents();
			this.refresh(); // Initial render on page load.
		},

		bindEvents() {
			const self = this;

			/*
			 * Two bindings over the same wide selector, not a list of input
			 * types. An earlier attempt enumerated the types — radio,
			 * checkbox, select for change; everything for input — and quietly
			 * dropped `color` and `range` out of the change path. Browsers
			 * fire both events on those, so it still worked by luck. Keep the
			 * selector broad and let the two events mean what they mean.
			 */

			// input: the value is still moving. Paint now, ask the server once
			// the hand stops.
			this.$form.on( 'input', 'input, select, textarea', function () {
				self.paint( this );
				self.schedule( 140 );
			} );

			// change: the value is final — a radio picked, a select chosen, a
			// field left. Nothing to wait for.
			this.$form.on( 'change', 'input, select, textarea', function () {
				self.paint( this );
				self.schedule( 0 );
			} );

			$( '#tb-refresh-preview' ).on( 'click', function () {
				self.schedule( 0 );
			} );
		},

		schedule( delay ) {
			const self = this;
			clearTimeout( self.timeout );
			if ( ! delay ) {
				self.refresh();
				return;
			}
			self.timeout = setTimeout( function () { self.refresh(); }, delay );
		},

		/**
		 * Which element inside the preview a setting addresses, and what to set.
		 *
		 * Returns false for anything that changes markup rather than style
		 * (layout, bio_limit, show_review_date, custom_class) — those can only
		 * come from the server.
		 */
		paint( field ) {
			const box = this.$preview[0] && this.$preview[0].querySelector( '.trustbio-box' );
			if ( ! box ) {
				return;
			}

			/*
			 * Each colour row is a pair: a swatch and a text box, only one of
			 * which carries the name — and which one differs between rows.
			 * Whichever half the user touched, its value is the colour, so
			 * borrow the key from its partner rather than waiting for the
			 * two to sync.
			 */
			let name = field.name;
			if ( ! name ) {
				const row = field.parentElement;
				const partner = row && row.querySelector( '[name^="settings["]' );
				if ( ! partner ) {
					return;
				}
				name = partner.name;
			}

			const m = name.match( /^settings\[([a-z_]+)\]$/ );
			if ( ! m ) {
				return;
			}

			const key = m[1];
			const val = 'checkbox' === field.type ? field.checked : field.value;

			const form = this.$form;
			const get = ( n ) => {
				// The field in hand is ahead of its partner, so trust it.
				if ( n === key ) {
					return val;
				}
				const el = form.find( '[name="settings[' + n + ']"]' ).filter( function () {
					return 'radio' !== this.type || this.checked;
				} ).get( 0 );
				return el ? ( 'checkbox' === el.type ? el.checked : el.value ) : '';
			};
			const setVar = ( name, v ) => box.style.setProperty( name, v );
			const each = ( sel, fn ) => box.querySelectorAll( sel ).forEach( fn );

			switch ( key ) {

				// ---- wrapper -------------------------------------------------
				case 'bg_color':
					box.style.background = val || '';
					break;
				case 'border_radius':
					box.style.borderRadius = ( parseInt( val, 10 ) || 0 ) + 'px';
					break;
				case 'padding':
					box.style.padding = ( parseInt( val, 10 ) || 0 ) + 'px';
					break;
				case 'box_shadow':
					setVar( '--tb-shadow', val ? SHADOW : 'none' );
					break;
				case 'border_color':
				case 'border_style':
				case 'border_width':
				case 'border_top':
				case 'border_right':
				case 'border_bottom':
				case 'border_left': {
					const style = get( 'border_style' ) || 'none';
					if ( 'none' === style ) {
						box.style.border = '';
						break;
					}
					const colour = get( 'border_color' ) || '#e5e7eb';
					const wide = parseInt( get( 'border_width' ), 10 ) || 0;
					const side = ( n ) => {
						const v = get( n );
						return ( '' === v ? wide : ( parseInt( v, 10 ) || 0 ) ) + 'px ' + style + ' ' + colour;
					};
					box.style.borderTop    = side( 'border_top' );
					box.style.borderRight  = side( 'border_right' );
					box.style.borderBottom = side( 'border_bottom' );
					box.style.borderLeft   = side( 'border_left' );
					break;
				}

				// ---- avatar --------------------------------------------------
				case 'avatar_shape':
					setVar( '--tb-avatar-radius', AVATAR_RADIUS[ val ] || '50%' );
					break;
				case 'avatar_size':
					setVar( '--tb-avatar-size', ( parseInt( val, 10 ) || 96 ) + 'px' );
					break;

				// ---- social icons --------------------------------------------
				case 'icon_style':
				case 'icon_color': {
					const style = get( 'icon_style' ) || 'color';
					const colour = get( 'icon_color' ) || '#374151';
					const chrome = ICON_CHROME[ style ] || ICON_CHROME.color;
					setVar( '--tb-icon-bg', chrome.bg || colour );
					setVar( '--tb-icon-border', chrome.border );
					// Monochrome inverts: chip takes the colour, glyph goes white.
					const fill = 'monochrome' === style ? '#ffffff' : colour;
					each( '.tb-social-link svg', ( s ) => s.setAttribute( 'fill', fill ) );
					break;
				}
				case 'icon_size': {
					const n = parseInt( val, 10 ) || 13;
					setVar( '--tb-icon-size', n + 'px' );
					setVar( '--tb-icon-chip', ( n + 17 ) + 'px' );
					break;
				}

				// ---- type ----------------------------------------------------
				case 'name_color':
					each( '.tb-name', ( e ) => { e.style.color = val; } );
					break;
				case 'name_size':
					each( '.tb-name', ( e ) => { e.style.fontSize = ( parseInt( val, 10 ) || 18 ) + 'px'; } );
					break;
				case 'name_weight':
					each( '.tb-name', ( e ) => { e.style.fontWeight = val; } );
					break;
				case 'bio_color':
					each( '.tb-bio', ( e ) => { e.style.color = val; } );
					break;
				case 'bio_size':
					each( '.tb-bio', ( e ) => { e.style.fontSize = ( parseInt( val, 10 ) || 14 ) + 'px'; } );
					break;
				case 'label_color':
					setVar( '--tb-label-color', val );
					break;
				case 'label_size':
					setVar( '--tb-label-size', ( parseInt( val, 10 ) || 10 ) + 'px' );
					break;
				case 'label_weight':
					setVar( '--tb-label-weight', val );
					break;
				case 'jobtitle_size':
					setVar( '--tb-jobtitle-size', ( parseInt( val, 10 ) || 12 ) + 'px' );
					break;
				case 'font_heading':
					setVar( '--tb-font-heading', val );
					break;
				case 'font_label':
					setVar( '--tb-font-label', val );
					break;

				// bio_limit, show_review_date, custom_class and the layout
				// change the markup, so they wait for the server.
				default:
					break;
			}
		},

		refresh() {
			const self = this;

			// The newest request is the only one whose answer is still true.
			if ( self.xhr ) {
				self.xhr.abort();
				self.xhr = null;
			}

			// Only show the spinner if the wait is long enough to notice;
			// otherwise it strobes on every keystroke.
			clearTimeout( self.spinTimer );
			self.spinTimer = setTimeout( function () { self.$spinner.show(); }, 250 );

			// Use raw serializeArray so PHP receives settings[key] as a proper array.
			const formData = self.$form.serializeArray();
			formData.push( { name: 'action', value: 'trustbio_preview_box' } );
			formData.push( { name: 'nonce',  value: trustbioAdmin.nonces.previewBox } );

			self.xhr = $.ajax( {
				url: trustbioAdmin.ajaxUrl,
				type: 'POST',
				data: formData,
			} )
				.done( function ( r ) {
					if ( r && r.success && r.data && r.data.html ) {
						self.$preview.html( r.data.html );
					}
				} )
				.always( function ( _, status ) {
					if ( 'abort' === status ) {
						return; // A newer request owns the spinner now.
					}
					self.xhr = null;
					clearTimeout( self.spinTimer );
					self.$spinner.hide();
				} );
		},
	};

	// Expose globally so trustbio-box-builder.js can trigger a refresh
	// after applying layout defaults or loading a saved template.
	window.TBBoxPreview = TBBoxPreview;

	$( function () {
		TBBoxPreview.init();
	} );

}( jQuery ) );
