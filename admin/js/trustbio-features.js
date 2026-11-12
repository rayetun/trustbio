/**
 * TrustBio — Feature Modules screen.
 *
 * Toggles module cards and saves the enabled-feature list over AJAX.
 * Config comes from the trustbioFeaturesPage object localized in class-admin.php.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var cfg        = window.trustbioFeaturesPage || { ajaxUrl: '', nonce: '', i18n: {} };
		var saveBtn    = document.querySelector( '.tbf-save-btn' );
		var saveStatus = document.querySelector( '.tbf-save-status' );
		var dirty      = false;

		/*
		 * A toggle repaints its card immediately — the badge reads "Inactive"
		 * the moment you click it — but nothing is stored until Save Changes
		 * is pressed. So a reload put the module straight back, with the
		 * screen having given every impression that the change had taken.
		 * Say plainly that there is something unsaved, and ask before the
		 * page is left with changes outstanding.
		 */
		function markDirty() {
			if ( dirty ) {
				return;
			}
			dirty = true;
			if ( saveBtn ) {
				saveBtn.classList.add( 'is-dirty' );
			}
			if ( saveStatus ) {
				saveStatus.textContent = cfg.i18n.unsaved || 'Unsaved changes';
				saveStatus.className   = 'tbf-save-status is-visible is-dirty';
			}
		}

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( ! dirty ) {
				return undefined;
			}
			e.preventDefault();
			e.returnValue = '';
			return '';
		} );

		// Reflect the new state on the card as soon as the checkbox changes.
		document.querySelectorAll( '.tbf-toggle__input' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var card  = this.closest( '.tbf-card' );
				var badge = card.querySelector( '.tbf-card__badge' );
				var icon  = card.querySelector( '.tbf-card__icon' );
				var on    = this.checked;

				card.classList.toggle( 'is-enabled', on );
				icon.classList.toggle( 'tbf-card__icon--on', on );

				if ( badge ) {
					badge.textContent = on
						? ( cfg.i18n.active || 'Active' )
						: ( cfg.i18n.inactive || 'Inactive' );
					badge.className = 'tbf-card__badge ' + ( on ? 'tbf-card__badge--on' : 'tbf-card__badge--off' );
				}

				markDirty();
			} );
		} );

		if ( ! saveBtn ) {
			return;
		}

		saveBtn.addEventListener( 'click', function () {
			var features = [];
			document.querySelectorAll( '.tbf-toggle__input' ).forEach( function ( cb ) {
				if ( cb.checked ) {
					features.push( cb.dataset.feature );
				}
			} );

			saveBtn.disabled    = true;
			saveBtn.textContent = cfg.i18n.saving || 'Saving…';
			if ( saveStatus ) {
				saveStatus.textContent = '';
				saveStatus.className   = 'tbf-save-status';
			}

			var body = new URLSearchParams();
			body.append( 'action', 'trustbio_save_features' );
			body.append( 'nonce', cfg.nonce );
			features.forEach( function ( f ) {
				body.append( 'features[]', f );
			} );

			fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( resp ) {
					if ( ! resp || ! resp.success ) {
						throw new Error( ( resp && resp.data && resp.data.message ) || 'Error' );
					}
					// Cleared before the reload below, or beforeunload would
					// ask the user to confirm leaving a page we are reloading
					// precisely because the change has been stored.
					dirty = false;
					saveBtn.classList.remove( 'is-dirty' );
					saveBtn.textContent = cfg.i18n.saved || 'Saved!';
					if ( saveStatus ) {
						saveStatus.textContent = cfg.i18n.reload || 'Reloading…';
						saveStatus.className   = 'tbf-save-status is-visible';
					}
					window.setTimeout( function () {
						window.location.reload();
					}, 900 );
				} )
				.catch( function ( err ) {
					saveBtn.disabled    = false;
					saveBtn.textContent = cfg.i18n.save || 'Save Changes';
					if ( saveStatus ) {
						saveStatus.textContent = cfg.i18n.error || err.message || 'Save failed.';
						saveStatus.className   = 'tbf-save-status is-visible is-error';
						window.setTimeout( function () {
							saveStatus.className = 'tbf-save-status';
						}, 3500 );
					}
				} );
		} );
	} );
}() );
