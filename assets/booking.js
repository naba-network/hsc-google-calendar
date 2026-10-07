/**
 * Booking dialog for [HSC-Event-Booking-List].
 * Vanilla JS, no build step. The server validates everything again; this only gives fast feedback.
 */
( function () {
	'use strict';

	const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
	const PHONE_PATTERN = /^\+?[0-9][0-9 ()/-]{5,29}$/;
	const GENERAL_FIELD = '';

	/**
	 * @typedef {{bookingUrl: string, captchaUrl: string, maxParticipants: number, messages: Object<string, string>}} Config
	 */

	/**
	 * @param {HTMLElement} root Wrapper element of one shortcode instance.
	 */
	function init( root ) {
		/** @type {Config} */
		const config = JSON.parse( root.dataset.config || '{}' );
		const formTemplate = root.querySelector( '[data-hsc-form]' );
		const doneTemplate = root.querySelector( '[data-hsc-done]' );
		if ( ! formTemplate || ! doneTemplate || typeof HTMLDialogElement === 'undefined' ) {
			return;
		}

		const dialog = document.createElement( 'dialog' );
		dialog.className = 'hsc-dialog';
		root.appendChild( dialog );

		/** Closes on backdrop click (the click target is the dialog itself). */
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		root.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-hsc-book]' );
			if ( button ) {
				open( button );
			}
		} );

		/**
		 * @param {HTMLElement} button The clicked "Jetzt buchen" button.
		 */
		function open( button ) {
			const sheet = formTemplate.content.cloneNode( true );
			fill( sheet, button );
			sheet.querySelector( '[name="event"]' ).value = button.dataset.event;
			dialog.replaceChildren( sheet );

			const form = dialog.querySelector( 'form' );
			form.addEventListener( 'submit', ( event ) => submit( event, form, button ) );
			form.addEventListener( 'input', ( event ) => clearError( form, event.target.name ) );
			form.addEventListener( 'click', ( event ) => step( event, form ) );
			dialog.querySelectorAll( '[data-hsc-close]' ).forEach( ( el ) => el.addEventListener( 'click', () => dialog.close() ) );

			dialog.showModal();
			dialog.querySelector( '[data-hsc-title]' ).focus();
			loadCaptcha( form );
		}

		/**
		 * Writes date and time into the dialog header.
		 *
		 * @param {ParentNode} scope Cloned template content.
		 * @param {HTMLElement} button Trigger button holding the data attributes.
		 */
		function fill( scope, button ) {
			scope.querySelector( '[data-hsc-title]' ).textContent = button.dataset.date;
			scope.querySelector( '[data-hsc-time]' ).textContent = button.dataset.time;
		}

		/**
		 * @param {HTMLFormElement} form Booking form.
		 */
		async function loadCaptcha( form ) {
			try {
				const response = await fetch( config.captchaUrl, { cache: 'no-store' } );
				const data = await response.json();
				form.querySelector( '[data-hsc-question]' ).textContent = data.question;
				form.elements.token.value = data.token;
			} catch ( error ) {
				showErrors( form, { [ GENERAL_FIELD ]: config.messages.calendar_unavailable } );
			}
		}

		/**
		 * Plus/minus buttons of the number fields.
		 *
		 * @param {MouseEvent} event Click event.
		 * @param {HTMLFormElement} form Booking form.
		 */
		function step( event, form ) {
			const button = event.target.closest( '[data-step]' );
			if ( ! button ) {
				return;
			}
			const input = button.parentElement.querySelector( 'input' );
			const next = ( Number( input.value ) || 0 ) + Number( button.dataset.step );
			input.value = Math.min( Number( input.max ), Math.max( Number( input.min ), next ) );
			clearError( form, input.name );
		}

		/**
		 * @param {HTMLFormElement} form Booking form.
		 * @returns {Object<string, string>} Field name to message.
		 */
		function validate( form ) {
			const value = ( name ) => form.elements[ name ].value.trim();
			const messages = config.messages;
			const errors = {};
			const participants = Number( value( 'participants' ) );
			const rental = Number( value( 'rental' ) );

			if ( '' === value( 'name' ) ) {
				errors.name = messages.name_required;
			}
			if ( '' === value( 'email' ) ) {
				errors.email = messages.email_required;
			} else if ( ! EMAIL_PATTERN.test( value( 'email' ) ) ) {
				errors.email = messages.email_invalid;
			}
			if ( ! PHONE_PATTERN.test( value( 'phone' ) ) ) {
				errors.phone = messages.phone_invalid;
			}
			if ( ! Number.isInteger( participants ) || participants < 1 || participants > config.maxParticipants ) {
				errors.participants = messages.participants_range;
			}
			if ( ! Number.isInteger( rental ) || rental < 0 ) {
				errors.rental = messages.rental_range;
			} else if ( rental > participants ) {
				errors.rental = messages.rental_exceeds;
			}
			if ( '' === value( 'captcha' ) ) {
				errors.captcha = messages.captcha_wrong;
			}

			return errors;
		}

		/**
		 * @param {HTMLFormElement} form Booking form.
		 * @param {Object<string, string>} errors Field name to message, '' for a general error.
		 */
		function showErrors( form, errors ) {
			form.querySelectorAll( '[data-field]' ).forEach( ( field ) => {
				const message = errors[ field.dataset.field ];
				const output = field.querySelector( '[data-error]' );
				const input = field.querySelector( 'input, textarea' );
				field.classList.toggle( 'is-invalid', Boolean( message ) );
				if ( ! output ) {
					return;
				}
				output.hidden = ! message;
				output.textContent = message || '';
				if ( message ) {
					output.id = output.id || 'hsc-error-' + field.dataset.field;
					input.setAttribute( 'aria-invalid', 'true' );
					input.setAttribute( 'aria-describedby', output.id );
				} else {
					input.removeAttribute( 'aria-invalid' );
					input.removeAttribute( 'aria-describedby' );
				}
			} );

			const general = form.querySelector( '[data-error-general]' );
			general.hidden = ! errors[ GENERAL_FIELD ];
			general.textContent = errors[ GENERAL_FIELD ] || '';

			const first = form.querySelector( '.is-invalid input, .is-invalid textarea' );
			if ( first ) {
				first.focus();
			}
		}

		/**
		 * @param {HTMLFormElement} form Booking form.
		 * @param {string} name Field name whose error disappears while typing.
		 */
		function clearError( form, name ) {
			const field = form.querySelector( '[data-field="' + name + '"]' );
			if ( field && field.classList.contains( 'is-invalid' ) ) {
				field.classList.remove( 'is-invalid' );
				field.querySelector( '[data-error]' ).hidden = true;
				field.querySelector( 'input, textarea' ).removeAttribute( 'aria-invalid' );
			}
		}

		/**
		 * @param {SubmitEvent} event Submit event.
		 * @param {HTMLFormElement} form Booking form.
		 * @param {HTMLElement} button Trigger button, for the success header.
		 */
		async function submit( event, form, button ) {
			event.preventDefault();
			const errors = validate( form );
			showErrors( form, errors );
			if ( Object.keys( errors ).length > 0 ) {
				return;
			}

			const submitButton = form.querySelector( '[type="submit"]' );
			submitButton.disabled = true;
			try {
				const response = await fetch( config.bookingUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( Object.fromEntries( new FormData( form ) ) ),
				} );
				const data = await response.json();
				if ( data.ok ) {
					showDone( button );
					return;
				}
				showErrors( form, data.errors || { [ GENERAL_FIELD ]: config.messages.send_failed } );
			} catch ( error ) {
				showErrors( form, { [ GENERAL_FIELD ]: config.messages.send_failed } );
			}
			submitButton.disabled = false;
		}

		/**
		 * @param {HTMLElement} button Trigger button.
		 */
		function showDone( button ) {
			const sheet = doneTemplate.content.cloneNode( true );
			fill( sheet, button );
			dialog.replaceChildren( sheet );
			dialog.querySelectorAll( '[data-hsc-close]' ).forEach( ( el ) => el.addEventListener( 'click', () => dialog.close() ) );
			dialog.querySelector( '[data-hsc-title]' ).focus();
		}
	}

	document.querySelectorAll( '[data-hsc-booking]' ).forEach( init );
}() );
