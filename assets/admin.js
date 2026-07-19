( function () {
	'use strict';

	const button = document.getElementById( 'apmt-start-translation' );
	if ( ! button ) {
		return;
	}

	const statusBox = document.getElementById( 'apmt-status' );
	const statusText = document.getElementById( 'apmt-status-text' );
	const progress = document.getElementById( 'apmt-progress' );

	const setStatus = function ( message, value ) {
		statusBox.hidden = false;
		statusText.textContent = message;
		if ( typeof value === 'number' ) {
			progress.value = Math.max( 0, Math.min( 100, value ) );
		}
	};

	const post = async function ( data ) {
		const response = await fetch( apmtData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams( data ),
		} );
		const payload = await response.json();
		if ( ! response.ok || ! payload.success ) {
			const message = typeof payload.data === 'string' ? payload.data : apmtData.strings.failed;
			throw new Error( message );
		}
		return payload.data;
	};

	button.addEventListener( 'click', async function () {
		const menuId = document.getElementById( 'apmt-source-menu' ).value;
		const targetSelect = document.getElementById( 'apmt-target-language' );
		const targetLanguage = targetSelect.value;
		const translatorTargetLanguage = targetSelect.selectedOptions[ 0 ].dataset.translatorLanguage || targetLanguage;
		const sourceLanguage = document.getElementById( 'apmt-source-language' ).value.trim();
		let translator = null;

		button.disabled = true;
		setStatus( 'Checking Chrome translation support…', 0 );

		try {
			if ( ! ( 'Translator' in self ) ) {
				throw new Error( apmtData.strings.unsupported );
			}
			if ( ! sourceLanguage || sourceLanguage.toLowerCase() === translatorTargetLanguage.toLowerCase() ) {
				throw new Error( 'Choose different source and target language codes.' );
			}

			const availability = await Translator.availability( {
				sourceLanguage,
				targetLanguage: translatorTargetLanguage,
			} );
			if ( availability === 'unavailable' ) {
				throw new Error( apmtData.strings.unavailable );
			}

			setStatus( availability === 'downloadable' || availability === 'downloading' ? 'Downloading the on-device language model…' : 'Starting the on-device translator…', 5 );
			translator = await Translator.create( {
				sourceLanguage,
				targetLanguage: translatorTargetLanguage,
				monitor( monitor ) {
					monitor.addEventListener( 'downloadprogress', function ( event ) {
						setStatus( 'Downloading the on-device language model…', Math.round( event.loaded * 20 ) );
					} );
				},
			} );

			setStatus( 'Loading the source menu…', 20 );
			const menuItems = await post( {
				action: 'apmt_get_menu_data',
				nonce: apmtData.nonce,
				menu_id: menuId,
				target_lang: targetLanguage,
			} );

			for ( let index = 0; index < menuItems.length; index++ ) {
				setStatus( `Translating “${ menuItems[ index ].title }”…`, 20 + Math.round( ( index / menuItems.length ) * 70 ) );
				menuItems[ index ].translated_title = await translator.translate( menuItems[ index ].title );
			}

			setStatus( 'Saving the translated menu…', 92 );
			const result = await post( {
				action: 'apmt_save_translated_menu',
				nonce: apmtData.nonce,
				target_lang: targetLanguage,
				original_menu_id: menuId,
				items_json: JSON.stringify( menuItems ),
			} );

			setStatus( result.message, 100 );
		} catch ( error ) {
			setStatus( `Error: ${ error.message || apmtData.strings.failed }`, 0 );
		} finally {
			if ( translator && typeof translator.destroy === 'function' ) {
				translator.destroy();
			}
			button.disabled = false;
		}
	} );
}() );
