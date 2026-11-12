/**
 * TrustBio Author Search
 * Live search + author assignment for post editor sidebar.
 * Used by both the Classic meta box and the Gutenberg sidebar.
 *
 * @package TrustBio
 */

/* global trustbioAdmin, TrustBio */

( function ( $ ) {
	'use strict';

	const TBAuthorSearch = {
		$wrap     : null,
		$input    : null,
		$results  : null,
		$assigned : null,
		timeout   : null,

		init( $context ) {
			this.$wrap     = $context || $( '.tb-author-search-wrap' );
			this.$input    = this.$wrap.find( '.tb-author-search-input' );
			this.$results  = this.$wrap.find( '.tb-author-search-results' );
			this.$assigned = this.$wrap.find( '.tb-assigned-authors' );

			this.bindEvents();
		},

		bindEvents() {
			// Live search on input.
			this.$input.on( 'input', ( e ) => {
				clearTimeout( this.timeout );
				const q = e.target.value.trim();
				if ( q.length < 2 ) {
					this.$results.empty().hide();
					return;
				}
				this.timeout = setTimeout( () => this.search( q ), 300 );
			} );

			// Hide results on outside click.
			$( document ).on( 'click', ( e ) => {
				if ( ! $( e.target ).closest( '.tb-author-search-wrap' ).length ) {
					this.$results.hide();
				}
			} );

			// Select result.
			this.$results.on( 'click', '.tb-search-result-item', ( e ) => {
				const $item   = $( e.currentTarget );
				const id      = $item.data( 'id' );
				const name    = $item.data( 'name' );
				const avatar  = $item.data( 'avatar' );
				const catId   = $item.data( 'category-id' );
				const catName = $item.data( 'category-name' );
				this.assignAuthor( id, name, avatar, catId, catName );
				this.$input.val( '' );
				this.$results.empty().hide();
			} );

			// Remove assigned author.
			this.$assigned.on( 'click', '.tb-assigned-remove', ( e ) => {
				$( e.currentTarget ).closest( '.tb-assigned-author' ).remove();
				this.updateHiddenInput();
			} );

			// Drag to reorder.
			if ( $.fn.sortable ) {
				this.$assigned.sortable( {
					handle    : '.tb-assigned-handle',
					tolerance : 'pointer',
					update    : () => this.updateHiddenInput(),
				} );
			}
		},

		search( q ) {
			this.$results.html( '<div class="tb-search-loading"><span class="tb-spinner"></span></div>' ).show();

			TrustBio.Ajax.post( 'trustbio_search_authors', {
				nonce : trustbioAdmin.nonces.searchAuthors,
				q,
			} )
				.then( ( data ) => {
					if ( ! data.authors || data.authors.length === 0 ) {
						this.$results.html(
							`<div class="tb-search-empty">${ trustbioAdmin.i18n.noAuthorsFound }</div>`
						);
						return;
					}
					const html = data.authors.map( ( a ) => `
						<div class="tb-search-result-item"
							data-id="${ a.id }"
							data-name="${ $( '<span>' ).text( a.display_name ).html() }"
							data-avatar="${ $( '<span>' ).text( a.avatar_url ).html() }"
							data-category-id="${ a.category_id || '' }"
							data-category-name="${ $( '<span>' ).text( a.category_name || '' ).html() }">
							<img class="tb-search-result-avatar" src="${ $( '<span>' ).text( a.avatar_url ).html() }" alt="" width="28" height="28">
							<div class="tb-search-result-info">
								<span class="tb-search-result-name">${ $( '<span>' ).text( a.display_name ).html() }</span>
								<span class="tb-search-result-meta">${ $( '<span>' ).text( a.category_name || a.author_type ).html() }</span>
							</div>
						</div>
					` ).join( '' );
					this.$results.html( html );
				} )
				.catch( () => {
					this.$results.html( '<div class="tb-search-empty">Error loading authors.</div>' );
				} );
		},

		assignAuthor( id, name, avatar, catId, catName ) {
			// Check if already assigned.
			if ( this.$assigned.find( `[data-author-id="${ id }"]` ).length ) {
				TrustBio.Notice.warning( `${ name } is already assigned to this post.` );
				return;
			}

			// Get category options HTML.
			const catOptions = this.getCategoryOptions( catId );

			const $card = $( `
				<div class="tb-assigned-author" data-author-id="${ id }">
					<div class="tb-assigned-handle" title="Drag to reorder">
						<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
							<path d="M7 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm6-16a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm0 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
						</svg>
					</div>
					<img class="tb-assigned-avatar" src="${ $( '<span>' ).text( avatar ).html() }" alt="" width="32" height="32">
					<div class="tb-assigned-info">
						<span class="tb-assigned-name">${ $( '<span>' ).text( name ).html() }</span>
						<select class="tb-assigned-category" data-author-id="${ id }">
							${ catOptions }
						</select>
					</div>
					<button type="button" class="tb-assigned-remove" aria-label="Remove author" title="Remove">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
							<path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
						</svg>
					</button>
					<input type="hidden" name="trustbio_author_ids[]" value="${ id }">
					<input type="hidden" name="trustbio_category_ids[${id}]" value="${ catId || '' }">
				</div>
			` );

			// Set selected category.
			if ( catId ) {
				$card.find( 'select' ).val( catId );
			}

			// Update hidden input when category changes.
			$card.find( 'select' ).on( 'change', function () {
				$card.find( `input[name="trustbio_category_ids[${ id }]"]` ).val( $( this ).val() );
			} );

			this.$assigned.append( $card );
			this.updateHiddenInput();
		},

		getCategoryOptions( selectedId ) {
			// Will be populated from wp_localize_script in a later phase.
			const categories = trustbioAdmin.categories || [];
			const options = categories.map( ( c ) => {
				const sel = String( c.id ) === String( selectedId ) ? ' selected' : '';
				return `<option value="${ c.id }"${ sel }>${ $( '<span>' ).text( c.singular ).html() }</option>`;
			} );
			return `<option value="">— Select Category —</option>` + options.join( '' );
		},

		updateHiddenInput() {
			// Collect current state as JSON for save_post meta.
			const authors = [];
			this.$assigned.find( '.tb-assigned-author' ).each( function ( i ) {
				const $card = $( this );
				authors.push( {
					author_id   : $card.data( 'author-id' ),
					category_id : $card.find( 'select' ).val(),
					sort_order  : i,
				} );
			} );
			$( '#tb-post-authors-json' ).val( JSON.stringify( authors ) );
		},
	};

	$( function () {
		if ( $( '.tb-author-search-wrap' ).length ) {
			TBAuthorSearch.init();
		}
	} );

	window.TrustBio = window.TrustBio || {};
	window.TrustBio.AuthorSearch = TBAuthorSearch;

}( jQuery ) );
