/**
 * TrustBio Author Box — Gutenberg Block Editor (Phase 18)
 *
 * No build step. Vanilla ES5 compatible.
 * Server-side rendered via render_callback in PHP.
 *
 * @package TrustBio
 */

/* global trustbioBlock, wp */

( function () {
	'use strict';

	// ── Safely resolve WP globals ─────────────────────────────
	// ServerSideRender changed export path between WP versions.
	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var ToggleControl     = wp.components.ToggleControl;
	var TextControl       = wp.components.TextControl;
	var Spinner           = wp.components.Spinner;
	var useState          = wp.element.useState;
	var useEffect         = wp.element.useEffect;
	var useRef            = wp.element.useRef;
	var __                = wp.i18n.__;

	// ServerSideRender: WP exposes it at wp.serverSideRender (the component itself).
	// In older WP it may be at wp.components.ServerSideRender — try both.
	var ServerSideRender = wp.serverSideRender
		|| ( wp.components && wp.components.ServerSideRender )
		|| null;

	var data = window.trustbioBlock || {};

	// Build regular box options (value must be string for SelectControl).
	var boxOptions = [ { label: __( '— Default layout —', 'trustbio' ), value: '0' } ];
	( data.boxes || [] ).forEach( function ( b ) {
		if ( b.value !== 0 ) {
			boxOptions.push( { label: b.label, value: String( b.value ) } );
		}
	} );

	// Build sidebar box options.
	var sidebarBoxOptions = [ { label: __( '— None (use Author Box above) —', 'trustbio' ), value: '0' } ];
	( data.sidebarBoxes || [] ).forEach( function ( b ) {
		if ( b.value !== 0 ) {
			sidebarBoxOptions.push( { label: b.label, value: String( b.value ) } );
		}
	} );

	var modeOptions = [
		{ label: __( "Show post's author (changes per post)", 'trustbio' ), value: 'current_post' },
		{ label: __( 'Always show a specific author',          'trustbio' ), value: 'fixed'        },
	];

	// ── Author Search component ───────────────────────────────
	function AuthorSearch( props ) {
		var onChange = props.onChange;
		var currentId = props.authorId;

		var queryState    = useState( '' );
		var query         = queryState[0];
		var setQuery      = queryState[1];

		var resultsState  = useState( [] );
		var results       = resultsState[0];
		var setResults    = resultsState[1];

		var searchingState = useState( false );
		var searching      = searchingState[0];
		var setSearching   = searchingState[1];

		var selectedState  = useState( null );
		var selected       = selectedState[0];
		var setSelected    = selectedState[1];

		var timerRef = useRef( null );

		// Load current author name when authorId is set.
		useEffect( function () {
			if ( ! currentId ) {
				setSelected( null );
				return;
			}
			// Fetch via TrustBio AJAX (works for both registered and guest authors).
			var url = data.ajaxUrl
				+ '?action=trustbio_get_author_detail'
				+ '&nonce=' + encodeURIComponent( data.nonce )
				+ '&id=' + encodeURIComponent( currentId );
			window.fetch( url )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( r.success && r.data ) {
						setSelected( r.data.display_name );
					}
				} )
				.catch( function () {} );
		}, [ currentId ] );

		function doSearch( q ) {
			setQuery( q );
			clearTimeout( timerRef.current );
			if ( q.length < 2 ) { setResults( [] ); return; }
			setSearching( true );
			timerRef.current = setTimeout( function () {
				var body = new URLSearchParams();
				body.append( 'action', 'trustbio_search_authors' );
				body.append( 'nonce',  data.nonce );
				body.append( 'q',      q );
				window.fetch( data.ajaxUrl, {
					method  : 'POST',
					headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
					body    : body.toString(),
				} )
					.then( function ( r ) { return r.json(); } )
					.then( function ( r ) {
						setSearching( false );
						setResults( r.success ? ( r.data.authors || [] ) : [] );
					} )
					.catch( function () { setSearching( false ); } );
			}, 400 );
		}

		function pick( author ) {
			setSelected( author.display_name );
			setQuery( '' );
			setResults( [] );
			onChange( author.id );
		}

		function clear() {
			setSelected( null );
			setQuery( '' );
			setResults( [] );
			onChange( 0 );
		}

		// Selected chip.
		if ( selected ) {
			return el( 'div', { style: { marginBottom: '12px' } },
				el( 'p', { style: { fontSize: '11px', fontWeight: 600, textTransform: 'uppercase', color: '#6b7280', marginBottom: '4px' } },
					__( 'Author', 'trustbio' )
				),
				el( 'div', {
					style: {
						display      : 'flex',
						alignItems   : 'center',
						gap          : '8px',
						padding      : '8px 10px',
						background   : '#f0f6fc',
						border       : '1px solid #c3d1e1',
						borderRadius : '4px',
						fontSize     : '13px',
					},
				},
					el( 'span', { style: { flex: 1, fontWeight: 600 } }, selected ),
					el( 'button', {
						type    : 'button',
						onClick : clear,
						style   : { background: 'none', border: 'none', cursor: 'pointer', color: '#6b7280', fontSize: '16px', lineHeight: 1, padding: '0 2px' },
					}, '✕' )
				)
			);
		}

		// Search input + results.
		return el( 'div', { style: { marginBottom: '12px', position: 'relative' } },
			el( TextControl, {
				label       : __( 'Search authors', 'trustbio' ),
				value       : query,
				onChange    : doSearch,
				placeholder : __( 'Type 2+ characters…', 'trustbio' ),
			} ),
			searching && el( 'div', { style: { padding: '8px', textAlign: 'center' } },
				el( Spinner )
			),
			! searching && results.length > 0 && el( 'div', {
				style: {
					position     : 'absolute',
					top          : '100%',
					left         : 0,
					right        : 0,
					background   : '#fff',
					border       : '1px solid #ddd',
					borderRadius : '4px',
					boxShadow    : '0 4px 12px rgba(0,0,0,.15)',
					zIndex       : 9999,
					maxHeight    : '180px',
					overflowY    : 'auto',
				},
			},
				results.map( function ( a ) {
					return el( 'div', {
						key          : String( a.id ),
						role         : 'button',
						tabIndex     : 0,
						onClick      : function () { pick( a ); },
						onKeyDown    : function ( e ) { if ( e.key === 'Enter' ) { pick( a ); } },
						onMouseEnter : function ( e ) { e.currentTarget.style.background = '#f0f6fc'; },
						onMouseLeave : function ( e ) { e.currentTarget.style.background = ''; },
						style        : { display: 'flex', alignItems: 'center', gap: '8px', padding: '7px 10px', cursor: 'pointer', fontSize: '13px', borderBottom: '1px solid #f0f0f0' },
					},
						el( 'img', {
							src    : a.avatar_url || 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=24',
							width  : 24, height: 24, alt: '',
							style  : { borderRadius: '50%', flexShrink: 0 },
						} ),
						el( 'div', null,
							el( 'strong', { style: { display: 'block', fontSize: '12px' } }, a.display_name ),
							a.category_name && el( 'span', { style: { fontSize: '11px', color: '#9ca3af' } }, a.category_name )
						)
					);
				} )
			),
			! searching && query.length >= 2 && results.length === 0 && el( 'p', {
				style: { fontSize: '12px', color: '#9ca3af', marginTop: '4px' },
			}, __( 'No authors found.', 'trustbio' ) )
		);
	}

	// ── Block edit component ──────────────────────────────────
	function TrustBioAuthorBoxEdit( props ) {
		var attrs    = props.attributes;
		var setAttrs = props.setAttributes;

		var blockProps = useBlockProps( {} );

		var preview = ServerSideRender
			? el( ServerSideRender, {
				block      : 'trustbio/author-box',
				attributes : attrs,
				LoadingResponsePlaceholder: function () {
					return el( 'div', { style: { padding: '20px', textAlign: 'center' } }, el( Spinner ) );
				},
			} )
			: el( 'div', {
				style: { border: '2px dashed #c3c4c7', borderRadius: '4px', padding: '20px', textAlign: 'center', color: '#6b7280', fontSize: '13px' },
			}, __( 'TrustBio Author Box — save the post to preview.', 'trustbio' ) );

		return el( 'div', blockProps,

			el( InspectorControls, null,

				el( PanelBody, { title: __( 'Author', 'trustbio' ), initialOpen: true },

					el( SelectControl, {
						label    : __( 'Display mode', 'trustbio' ),
						value    : attrs.mode,
						options  : modeOptions,
						onChange : function ( v ) {
							setAttrs( { mode: v, author_id: 0 } );
						},
					} ),

					attrs.mode === 'fixed' && el( AuthorSearch, {
						authorId : attrs.author_id,
						onChange : function ( id ) { setAttrs( { author_id: parseInt( id, 10 ) || 0 } ); },
					} )
				),

				el( PanelBody, { title: __( 'Layout', 'trustbio' ), initialOpen: false },

					el( SelectControl, {
						label    : __( 'Author Box design', 'trustbio' ),
						value    : String( attrs.box_id || 0 ),
						options  : boxOptions,
						onChange : function ( v ) {
							// Selecting a regular box clears any sidebar box selection.
							setAttrs( { box_id: parseInt( v, 10 ) || 0, sidebar_box_id: 0 } );
						},
					} ),

					sidebarBoxOptions.length > 1 && el( SelectControl, {
						label    : __( 'Or use a Sidebar Box', 'trustbio' ),
						value    : String( attrs.sidebar_box_id || 0 ),
						options  : sidebarBoxOptions,
						onChange : function ( v ) {
							// Selecting a sidebar box clears any regular box selection.
							setAttrs( { sidebar_box_id: parseInt( v, 10 ) || 0, box_id: 0 } );
						},
					} ),

					el( ToggleControl, {
						label    : __( 'Show author bio', 'trustbio' ),
						checked  : !! attrs.show_bio,
						onChange : function ( v ) { setAttrs( { show_bio: v } ); },
					} ),

					el( ToggleControl, {
						label    : __( 'Show social links', 'trustbio' ),
						checked  : !! attrs.show_social,
						onChange : function ( v ) { setAttrs( { show_social: v } ); },
					} )
				)
			),

			preview
		);
	}

	// ── Register the block ────────────────────────────────────
	registerBlockType( 'trustbio/author-box', {

		title      : __( 'TrustBio Author Box', 'trustbio' ),
		description: __( 'Display a TrustBio author profile box.', 'trustbio' ),
		category   : 'widgets',
		icon       : 'admin-users',
		supports   : { html: false },

		attributes: {
			mode           : { type: 'string',  default: 'current_post' },
			author_id      : { type: 'integer', default: 0 },
			box_id         : { type: 'integer', default: 0 },
			sidebar_box_id : { type: 'integer', default: 0 },
			show_bio       : { type: 'boolean', default: true },
			show_social    : { type: 'boolean', default: true },
		},

		edit: TrustBioAuthorBoxEdit,

		// Null save = server-side rendered.
		save: function () { return null; },
	} );

}() );
