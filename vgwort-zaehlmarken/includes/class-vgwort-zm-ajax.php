<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX-Endpunkte für den Beitrags-Editor: Zählmarke aus dem Pool zuweisen/
 * aufheben und die Meldung an VG Wort übermitteln.
 */
class VGWort_ZM_Ajax {

	const NONCE_ACTION = 'vgwort_zm_ajax';

	public function __construct() {
		add_action( 'wp_ajax_vgwort_zm_assign_mark', array( $this, 'assign_mark' ) );
		add_action( 'wp_ajax_vgwort_zm_unassign_mark', array( $this, 'unassign_mark' ) );
		add_action( 'wp_ajax_vgwort_zm_send_message', array( $this, 'send_message' ) );
	}

	private function verify( $post_id ) {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'vgwort-zaehlmarken' ) ), 403 );
		}
	}

	public function assign_mark() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$this->verify( $post_id );

		$existing = VGWort_ZM_Pool::get_for_post( $post_id );
		if ( $existing ) {
			wp_send_json_error( array( 'message' => __( 'Diesem Beitrag ist bereits eine Zählmarke zugewiesen.', 'vgwort-zaehlmarken' ) ) );
		}

		$mark = VGWort_ZM_Pool::get_next_unused();

		if ( ! $mark ) {
			wp_send_json_error( array( 'message' => __( 'Der Marken-Pool ist leer. Bitte zuerst Zählmarken bestellen oder importieren.', 'vgwort-zaehlmarken' ) ) );
		}

		VGWort_ZM_Pool::assign_to_post( $mark['id'], $post_id );

		wp_send_json_success(
			array(
				'public_code' => $mark['public_code'],
				'domain'      => $mark['domain'],
				'message'     => __( 'Zählmarke zugewiesen.', 'vgwort-zaehlmarken' ),
			)
		);
	}

	public function unassign_mark() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$this->verify( $post_id );

		$mark = VGWort_ZM_Pool::get_for_post( $post_id );

		if ( ! $mark ) {
			wp_send_json_error( array( 'message' => __( 'Diesem Beitrag ist keine Pool-Zählmarke zugewiesen.', 'vgwort-zaehlmarken' ) ) );
		}

		VGWort_ZM_Pool::unassign( $mark['id'] );

		wp_send_json_success( array( 'message' => __( 'Zuweisung aufgehoben.', 'vgwort-zaehlmarken' ) ) );
	}

	public function send_message() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$this->verify( $post_id );

		$assignment = VGWort_ZM_Frontend::get_assignment( $post_id );

		if ( ! $assignment || '' === $assignment['private_code'] ) {
			wp_send_json_error( array( 'message' => __( 'Diesem Beitrag ist keine Zählmarke mit privatem Code zugewiesen.', 'vgwort-zaehlmarken' ) ) );
		}

		$participants_meta = get_post_meta( $post_id, '_vgwort_participants', true );
		$publisher_meta     = get_post_meta( $post_id, '_vgwort_publisher', true );
		$rights_meta        = get_post_meta( $post_id, '_vgwort_rights', true );

		if ( ! is_array( $participants_meta ) || empty( $participants_meta ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte mindestens einen Autor oder Übersetzer angeben und den Beitrag speichern.', 'vgwort-zaehlmarken' ) ) );
		}

		$rights_meta = is_array( $rights_meta ) ? $rights_meta : array();
		$without_own = ! empty( $rights_meta['withoutOwnParticipation'] );

		if ( $without_own && ! empty( $publisher_meta['involved'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Widerspruch: „Nur als Dienstleister melden“ und „Verlag ist selbst beteiligt“ können nicht gleichzeitig aktiv sein.', 'vgwort-zaehlmarken' ) ) );
		}

		$post = get_post( $post_id );

		$plain_text = trim( wp_strip_all_tags( $post->post_content ) );

		if ( mb_strlen( $plain_text ) < 1800 ) {
			wp_send_json_error(
				array(
					/* translators: %d: aktuelle Zeichenzahl */
					'message' => sprintf( __( 'Der Text hat nur %d Zeichen. VG Wort verlangt mindestens 1.800 Zeichen (inkl. Leerzeichen) für eine Meldung.', 'vgwort-zaehlmarken' ), mb_strlen( $plain_text ) ),
				)
			);
		}

		$participants = array();
		foreach ( $participants_meta as $p ) {
			$entry = array(
				'firstName'   => $p['firstName'],
				'surName'     => $p['surName'],
				'involvement' => $p['involvement'],
			);

			if ( ! empty( $p['cardNumber'] ) ) {
				$entry['cardNumber'] = $p['cardNumber'];
			}

			$participants[] = $entry;
		}

		$payload = array(
			'privateidentificationid'         => $assignment['private_code'],
			'reproductionRight'                => ! empty( $rights_meta['reproductionRight'] ),
			'distributionRight'                => ! empty( $rights_meta['distributionRight'] ),
			'publicAccessRight'                => ! empty( $rights_meta['publicAccessRight'] ),
			'otherRightsOfPublicReproduction'  => ! empty( $rights_meta['otherRightsOfPublicReproduction'] ),
			'rightsGrantedConfirmation'        => ! empty( $rights_meta['rightsGrantedConfirmation'] ),
			'withoutOwnParticipation'          => $without_own,
			'participants'                     => $participants,
			'messagetext'                      => array(
				'shorttext' => mb_substr( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ), 0, 100 ),
				'text'      => array(
					'plainText' => base64_encode( $plain_text ),
				),
			),
			'webranges'                        => array(
				array( 'urls' => array( get_permalink( $post_id ) ) ),
			),
		);

		if ( ! empty( $publisher_meta['involved'] ) && ! empty( $publisher_meta['name'] ) ) {
			$publisher = array( 'name' => $publisher_meta['name'] );

			if ( ! empty( $publisher_meta['cardNumber'] ) ) {
				$publisher['cardNumber'] = $publisher_meta['cardNumber'];
			}

			$payload['publisher'] = $publisher;
		}

		$api    = new VGWort_ZM_Api();
		$result = $api->new_message( $payload );

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_vgwort_report_error', $result->get_error_message() );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_post_meta( $post_id, '_vgwort_reported', true );
		update_post_meta( $post_id, '_vgwort_reported_at', current_time( 'mysql' ) );
		delete_post_meta( $post_id, '_vgwort_report_error' );

		$mark = VGWort_ZM_Pool::get_for_post( $post_id );
		if ( $mark ) {
			VGWort_ZM_Pool::mark_reported( $mark['id'] );
		}

		wp_send_json_success( array( 'message' => __( 'Meldung erfolgreich an VG Wort übermittelt.', 'vgwort-zaehlmarken' ) ) );
	}
}
