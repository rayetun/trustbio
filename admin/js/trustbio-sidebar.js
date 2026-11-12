/**
 * TrustBio Gutenberg Sidebar Panel
 *
 * No jQuery. All AJAX via fetch() with POST method.
 * wp.apiFetch used only for REST API saves.
 *
 * @package TrustBio
 */

/* global trustbioSidebar, wp */

( function () {
	'use strict';

	// ── WP globals ────────────────────────────────────────────
	var el                       = wp.element.createElement;
	var Component                = wp.element.Component;
	var Fragment                 = wp.element.Fragment;
	var TextControl              = wp.components.TextControl;
	var SelectControl            = wp.components.SelectControl;
	var CheckboxControl          = wp.components.CheckboxControl;
	var Spinner                  = wp.components.Spinner;
	var registerPlugin           = wp.plugins.registerPlugin;
	var PluginSidebar            = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var apiFetch                 = wp.apiFetch;

	var data = trustbioSidebar;

	// ── Author detail cache ───────────────────────────────────
	// Pre-populated from PHP-rendered assignedDetails (names/avatars ready on first paint).
	var authorCache = {};
	( data.assignedDetails || [] ).forEach( function ( a ) {
		if ( a && a.id ) {
			authorCache[ String( a.id ) ] = a;
		}
	} );

	// ── Global state + micro pub/sub ──────────────────────────
	var state = {
		searchQuery   : '',
		searchResults : [],
		searching     : false,
		assigned      : ( data.assigned || [] ).slice(), // copy
		boxId         : parseInt( data.boxId, 10 ) || 0,
		hideBox       : data.hideBox === 1 || data.hideBox === '1' || data.hideBox === true,
		saving        : false,
		notice        : '',
		noticeType    : 'success',
	};

	var listeners = [];
	function setState( patch ) {
		Object.assign( state, patch );
		listeners.forEach( function ( fn ) { fn(); } );
	}

	// ── AJAX helper — always POST so handlers read $_POST ─────
	function ajaxPost( params ) {
		var body = new URLSearchParams();
		Object.keys( params ).forEach( function ( k ) {
			body.append( k, params[ k ] );
		} );
		return window.fetch( data.ajaxUrl, {
			method  : 'POST',
			headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
			body    : body.toString(),
		} ).then( function ( res ) {
			if ( ! res.ok ) { throw new Error( 'HTTP ' + res.status ); }
			return res.json();
		} );
	}

	// ── Fetch single author detail ────────────────────────────
	var fetchingIds = {};
	function fetchAuthorDetail( authorId ) {
		var key = String( authorId );
		if ( authorCache[ key ] && authorCache[ key ].display_name ) { return; }
		if ( fetchingIds[ key ] ) { return; }
		fetchingIds[ key ] = true;

		ajaxPost( {
			action : 'trustbio_get_author_detail',
			nonce  : data.nonce,
			id     : authorId,
		} ).then( function ( r ) {
			if ( r.success && r.data ) {
				authorCache[ key ] = r.data;
				setState( {} );
			}
		} ).catch( function () {
			fetchingIds[ key ] = false; // allow retry
		} );
	}

	// ── Author search ─────────────────────────────────────────
	var searchTimer;
	function searchAuthors( q ) {
		clearTimeout( searchTimer );
		if ( q.length < 2 ) {
			setState( { searchResults: [], searching: false } );
			return;
		}
		setState( { searching: true } );
		searchTimer = setTimeout( function () {
			ajaxPost( {
				action : 'trustbio_search_authors',
				nonce  : data.nonce,
				q      : q,
			} ).then( function ( r ) {
				setState( {
					searching     : false,
					searchResults : r.success ? ( r.data.authors || [] ) : [],
				} );
			} ).catch( function () {
				setState( { searching: false, searchResults: [] } );
			} );
		}, 350 );
	}

	// ── Save to post meta (REST) ──────────────────────────────
	function saveToMeta() {
		setState( { saving: true, notice: '' } );

		var restBase = data.restBase || ( data.postType + 's' );
		apiFetch( {
			path   : '/wp/v2/' + restBase + '/' + data.postId,
			method : 'POST',
			data   : {
				trustbio_authors  : state.assigned,
				trustbio_hide_box : state.hideBox ? 1 : 0,
				trustbio_box_id   : state.boxId,
			},
		} ).then( function () {
			setState( { saving: false, notice: data.i18n.saved || 'Saved.', noticeType: 'success' } );
			setTimeout( function () { setState( { notice: '' } ); }, 3000 );
		} ).catch( function ( err ) {
			var msg = ( err && err.message ) ? err.message : 'Save failed.';
			setState( { saving: false, notice: msg, noticeType: 'error' } );
		} );
	}

	// ── Author assignment actions ─────────────────────────────
	function assignAuthor( author ) {
		if ( state.assigned.find( function ( a ) {
			return String( a.author_id ) === String( author.id );
		} ) ) { return; }

		authorCache[ String( author.id ) ] = {
			id           : author.id,
			display_name : author.display_name,
			avatar_url   : author.avatar_url || '',
			credentials  : author.credentials || '',
		};

		setState( {
			assigned      : state.assigned.concat( [ {
				author_id   : author.id,
				category_id : author.category_id || null,
				sort_order  : state.assigned.length,
			} ] ),
			searchQuery   : '',
			searchResults : [],
		} );
		saveToMeta();
	}

	function removeAuthor( authorId ) {
		setState( {
			assigned: state.assigned
				.filter( function ( a ) { return String( a.author_id ) !== String( authorId ); } )
				.map( function ( a, i ) { return Object.assign( {}, a, { sort_order: i } ); } ),
		} );
		saveToMeta();
	}

	function updateCategory( authorId, categoryId ) {
		setState( {
			assigned: state.assigned.map( function ( a ) {
				if ( String( a.author_id ) !== String( authorId ) ) { return a; }
				return Object.assign( {}, a, {
					category_id: categoryId ? parseInt( categoryId, 10 ) : null,
				} );
			} ),
		} );
		saveToMeta();
	}

	// ── Option builders ───────────────────────────────────────
	function getCategoryOptions() {
		var opts = [ { value: '', label: '— Category —' } ];
		( data.categories || [] ).forEach( function ( c ) {
			opts.push( { value: String( c.id ), label: c.singular } );
		} );
		return opts;
	}

	function getBoxOptions() {
		var opts = [ { value: '0', label: data.i18n.defaultLayout || '— Default —' } ];
		( data.boxes || [] ).forEach( function ( b ) {
			opts.push( { value: String( b.id ), label: b.title } );
		} );
		return opts;
	}

	// ── Panel Component ───────────────────────────────────────
	function TrustBioPanel( props ) {
		Component.call( this, props );
		this.state  = { tick: 0 };
		this._unsub = null;
	}
	TrustBioPanel.prototype = Object.create( Component.prototype );
	TrustBioPanel.prototype.constructor = TrustBioPanel;

	TrustBioPanel.prototype.componentDidMount = function () {
		var self = this;
		self._unsub = function () { self.setState( { tick: self.state.tick + 1 } ); };
		listeners.push( self._unsub );
		// Fetch details for any assigned author not already cached.
		state.assigned.forEach( function ( entry ) {
			fetchAuthorDetail( entry.author_id );
		} );
	};

	TrustBioPanel.prototype.componentWillUnmount = function () {
		var self = this;
		listeners = listeners.filter( function ( fn ) { return fn !== self._unsub; } );
	};

	TrustBioPanel.prototype.render = function () {
		var s       = state;
		var catOpts = getCategoryOptions();
		var boxOpts = getBoxOptions();
		var GRAVATAR = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y&s=56';

		// ── Author rows ───────────────────────────────────────
		var assignedRows = s.assigned.map( function ( entry ) {
			var authorId  = entry.author_id;
			var catId     = entry.category_id ? String( entry.category_id ) : '';
			var cached    = authorCache[ String( authorId ) ] || {};
			var name      = cached.display_name || ( 'Author #' + authorId );
			var avatarUrl = ( cached.avatar_url && cached.avatar_url.length > 0 ) ? cached.avatar_url : GRAVATAR;

			return el( 'div', {
				key       : String( authorId ),
				className : 'tb-sb-author-row',
				style     : {
					display        : 'flex',
					flexDirection  : 'row',
					alignItems     : 'center',
					gap            : '8px',
					padding        : '8px 10px',
					background     : '#f8f9fa',
					border         : '1px solid #e9ecef',
					borderRadius   : '6px',
					marginBottom   : '6px',
					boxSizing      : 'border-box',
				},
			},
				// Drag handle.
				el( 'span', {
					style : { cursor: 'grab', color: '#c0c0c0', fontSize: '16px', flexShrink: 0 },
					title : data.i18n.dragToReorder || 'Drag to reorder',
				}, '⠿' ),

				// Avatar.
				el( 'img', {
					src    : avatarUrl,
					width  : 28,
					height : 28,
					alt    : '',
					style  : {
						borderRadius : '50%',
						flexShrink   : 0,
						border       : '2px solid #fff',
						boxShadow    : '0 1px 3px rgba(0,0,0,.15)',
						width        : '28px',
						height       : '28px',
						display      : 'block',
					},
				} ),

				// Name + category.
				el( 'div', {
					style : {
						flex          : '1',
						minWidth      : 0,
						display       : 'flex',
						flexDirection : 'column',
						gap           : '4px',
					},
				},
					el( 'span', {
						style : {
							fontWeight    : 600,
							fontSize      : '12px',
							color         : '#1e1e1e',
							overflow      : 'hidden',
							textOverflow  : 'ellipsis',
							whiteSpace    : 'nowrap',
							display       : 'block',
						},
					}, name ),

					el( 'select', {
						value    : catId,
						style    : { width: '100%', fontSize: '11px', border: '1px solid #ddd', borderRadius: '4px', padding: '2px 4px' },
						onChange : function ( e ) { updateCategory( authorId, e.target.value ); },
					}, catOpts.map( function ( opt ) {
						return el( 'option', { key: opt.value, value: opt.value }, opt.label );
					} ) )
				),

				// Remove.
				el( 'button', {
					type    : 'button',
					onClick : function () { removeAuthor( authorId ); },
					title   : data.i18n.remove || 'Remove',
					style   : {
						background   : 'none',
						border       : 'none',
						cursor       : 'pointer',
						color        : '#cc1818',
						marginLeft   : 'auto',
						flexShrink   : 0,
						fontSize     : '16px',
						lineHeight   : 1,
						padding      : '2px 5px',
						borderRadius : '3px',
					},
				}, '✕' )
			);
		} );

		// ── Search results dropdown ───────────────────────────
		var resultsList = null;
		if ( s.searchQuery.length >= 2 ) {
			var dropStyle = {
				position    : 'absolute',
				left        : 0,
				right       : 0,
				top         : '100%',
				background  : '#fff',
				border      : '1px solid #e5e7eb',
				borderRadius: '6px',
				boxShadow   : '0 4px 16px rgba(0,0,0,.12)',
				zIndex      : 99999,
				maxHeight   : '220px',
				overflowY   : 'auto',
			};

			if ( s.searching ) {
				resultsList = el( 'div', { style: Object.assign( {}, dropStyle, { padding: '12px', textAlign: 'center' } ) },
					el( Spinner )
				);
			} else if ( s.searchResults.length === 0 ) {
				resultsList = el( 'div', { style: Object.assign( {}, dropStyle, { padding: '12px', fontSize: '12px', color: '#9ca3af', textAlign: 'center' } ) },
					data.i18n.noAuthors || 'No authors found.'
				);
			} else {
				resultsList = el( 'div', { style: dropStyle },
					s.searchResults.map( function ( author ) {
						return el( 'div', {
							key          : author.id,
							onClick      : function () { assignAuthor( author ); },
							onMouseEnter : function ( e ) { e.currentTarget.style.background = '#f0f4ff'; },
							onMouseLeave : function ( e ) { e.currentTarget.style.background = ''; },
							style        : { display: 'flex', alignItems: 'center', gap: '8px', padding: '9px 12px', cursor: 'pointer', fontSize: '12px', borderBottom: '1px solid #f3f4f6' },
						},
							el( 'img', {
								src    : author.avatar_url || GRAVATAR,
								width  : 24,
								height : 24,
								alt    : '',
								style  : { borderRadius: '50%', flexShrink: 0 },
							} ),
							el( 'div', null,
								el( 'strong', { style: { display: 'block', fontSize: '12px' } }, author.display_name ),
								author.category_name && el( 'span', { style: { fontSize: '10px', color: '#6b7280' } }, author.category_name )
							)
						);
					} )
				);
			}
		}

		// ── Full panel render ─────────────────────────────────
		return el( Fragment, null,

			// Notice.
			s.notice && el( 'div', {
				style: {
					padding     : '10px 16px',
					fontSize    : '12px',
					borderLeft  : '3px solid ' + ( s.noticeType === 'success' ? '#10b981' : '#ef4444' ),
					background  : s.noticeType === 'success' ? '#ecfdf5' : '#fef2f2',
					color       : s.noticeType === 'success' ? '#065f46' : '#991b1b',
					marginBottom: '4px',
				},
			}, s.notice ),

			// Author rows.
			el( 'div', { style: { padding: '14px 16px', borderBottom: '1px solid #f0f0f0' } },
				assignedRows.length
					? assignedRows
					: el( 'p', { style: { color: '#9ca3af', fontSize: '12px', margin: 0, fontStyle: 'italic' } },
						'No authors assigned. Search below to add.'
					)
			),

			// Search.
			el( 'div', { style: { padding: '14px 16px', borderBottom: '1px solid #f0f0f0', position: 'relative' } },
				el( TextControl, {
					placeholder : data.i18n.searchPlaceholder || 'Search authors…',
					value       : s.searchQuery,
					onChange    : function ( v ) {
						setState( { searchQuery: v } );
						searchAuthors( v );
					},
					style: { marginBottom: 0 },
				} ),
				resultsList
			),

			el( 'hr', { style: { border: 'none', borderTop: '1px solid #f0f0f0', margin: '4px 0' } } ),

			// Regular author box select.
			el( 'div', { style: { padding: '4px 16px' } },
				el( SelectControl, {
					label    : data.i18n.boxLayout || 'Regular Author Box',
					value    : String( s.boxId ),
					options  : boxOpts,
					onChange : function ( v ) {
						setState( { boxId: parseInt( v, 10 ) || 0 } );
						saveToMeta();
					},
				} )
			),

			// Hide box checkbox.
			el( 'div', { style: { padding: '0 16px 14px' } },
				el( CheckboxControl, {
					label    : data.i18n.hideBox || 'Hide author box on this post',
					checked  : !! s.hideBox,
					onChange : function ( v ) {
						setState( { hideBox: v } );
						saveToMeta();
					},
				} )
			),

			// Saving spinner.
			s.saving && el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', color: '#6b7280', padding: '8px 16px' } },
				el( Spinner ), ' Saving…'
			)
		);
	};

	// ── Register plugin sidebar ───────────────────────────────
	registerPlugin( 'trustbio-sidebar', {
		render: function () {
			return el( Fragment, null,
				el( PluginSidebarMoreMenuItem, { target: 'trustbio-sidebar' },
					data.i18n.panel || 'TrustBio Authors'
				),
				el( PluginSidebar, {
					name  : 'trustbio-sidebar',
					title : data.i18n.panel || 'TrustBio Authors',
					icon  : el( 'svg', {
						xmlns   : 'http://www.w3.org/2000/svg',
						viewBox : '0 0 20 20',
						fill    : 'currentColor',
						width   : 20,
						height  : 20,
					},
						el( 'path', {
							fillRule : 'evenodd',
							d        : 'M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
							clipRule : 'evenodd',
						} )
					),
				},
					el( TrustBioPanel, null )
				)
			);
		},
	} );

}() );
