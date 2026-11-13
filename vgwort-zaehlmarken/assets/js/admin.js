( function ( $ ) {
	'use strict';

	$( function () {
		var $box = $( '.vgwort-zm-box' );

		if ( ! $box.length ) {
			return;
		}

		var postId = $box.data( 'post-id' );

		function ajax( action, data, $spinner ) {
			$spinner.addClass( 'is-active' );

			return $.post( vgwortZm.ajaxUrl, $.extend( { action: action, nonce: vgwortZm.nonce, post_id: postId }, data ) ).always( function () {
				$spinner.removeClass( 'is-active' );
			} );
		}

		$box.on( 'click', '.vgwort-zm-assign', function () {
			var $btn = $( this );
			var $msg = $box.find( '.vgwort-zm-assign-message' );
			var $spinner = $box.find( '.vgwort-zm-assign-spinner' );

			ajax( 'vgwort_zm_assign_mark', {}, $spinner ).done( function ( response ) {
				if ( response.success ) {
					window.location.reload();
				} else {
					$msg.text( response.data.message );
				}
			} );
		} );

		$box.on( 'click', '.vgwort-zm-unassign', function () {
			if ( ! window.confirm( vgwortZm.i18n.confirmUnassign ) ) {
				return;
			}

			var $spinner = $box.find( '.vgwort-zm-assign-spinner' );

			ajax( 'vgwort_zm_unassign_mark', {}, $spinner ).done( function ( response ) {
				if ( response.success ) {
					window.location.reload();
				} else {
					$box.find( '.vgwort-zm-assign-message' ).text( response.data.message );
				}
			} );
		} );

		$box.on( 'click', '.vgwort-zm-send-message', function () {
			if ( ! window.confirm( vgwortZm.i18n.confirmSend ) ) {
				return;
			}

			var $spinner = $box.find( '.vgwort-zm-message-spinner' );
			var $result = $box.find( '.vgwort-zm-message-result' );

			$result.removeClass( 'error success' ).text( '' );

			ajax( 'vgwort_zm_send_message', {}, $spinner ).done( function ( response ) {
				if ( response.success ) {
					$result.addClass( 'success' ).text( response.data.message );
					$box.find( '.vgwort-zm-send-message' ).prop( 'disabled', true );
				} else {
					$result.addClass( 'error' ).text( response.data.message );
				}
			} );
		} );

		$box.on( 'click', '.vgwort-zm-add-participant', function () {
			var $rows = $box.find( '.vgwort-zm-participants' );
			var index = $rows.find( '.vgwort-zm-participant-row' ).length;

			var row = '<div class="vgwort-zm-participant-row">' +
				'<input type="text" name="vgwort_participants[' + index + '][firstName]" placeholder="Vorname" class="widefat">' +
				'<input type="text" name="vgwort_participants[' + index + '][surName]" placeholder="Nachname" class="widefat">' +
				'<input type="text" name="vgwort_participants[' + index + '][cardNumber]" placeholder="Karteinummer (optional)" class="widefat">' +
				'<select name="vgwort_participants[' + index + '][involvement]">' +
					'<option value="AUTHOR">Autor</option>' +
					'<option value="TRANSLATOR">Übersetzer</option>' +
				'</select>' +
				'<button type="button" class="button-link vgwort-zm-remove-participant">&times;</button>' +
				'</div>';

			$rows.append( row );
		} );

		$box.on( 'click', '.vgwort-zm-remove-participant', function () {
			$( this ).closest( '.vgwort-zm-participant-row' ).remove();
		} );

		$box.on( 'change', '.vgwort-zm-publisher-involved', function () {
			$box.find( '.vgwort-zm-publisher-fields' ).toggle( $( this ).is( ':checked' ) );
		} );

		$box.on( 'change', 'input[name="vgwort_without_own_participation"]', function () {
			$box.find( '.vgwort-zm-rights-fields' ).toggle( ! $( this ).is( ':checked' ) );
		} );
	} );
} )( jQuery );
