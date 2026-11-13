<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta-Box im Beitrags-Editor: Zuweisung der Zählmarke sowie Erfassung der
 * für die VG-Wort-Meldung nötigen Angaben (Beteiligte, Rechteeinräumung).
 */
class VGWort_ZM_Post_Meta {

	const NONCE_ACTION = 'vgwort_zm_save_post_meta';
	const NONCE_NAME    = 'vgwort_zm_post_meta_nonce';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
	}

	public function register_meta_box() {
		$settings = VGWort_ZM_Settings::get();

		foreach ( $settings['post_types'] as $post_type ) {
			add_meta_box(
				'vgwort-zm-meta-box',
				__( 'VG Wort Zählmarke', 'vgwort-zaehlmarken' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$mark          = VGWort_ZM_Pool::get_for_post( $post->ID );
		$public_code   = get_post_meta( $post->ID, '_vgwort_public_code', true );
		$private_code  = get_post_meta( $post->ID, '_vgwort_private_code', true );
		$domain        = get_post_meta( $post->ID, '_vgwort_domain', true );
		$paywall       = (bool) get_post_meta( $post->ID, '_vgwort_paywall', true );
		$disabled      = (bool) get_post_meta( $post->ID, '_vgwort_disabled', true );
		$reported      = (bool) get_post_meta( $post->ID, '_vgwort_reported', true );
		$reported_at   = get_post_meta( $post->ID, '_vgwort_reported_at', true );
		$report_error  = get_post_meta( $post->ID, '_vgwort_report_error', true );
		$participants  = get_post_meta( $post->ID, '_vgwort_participants', true );
		$rights        = get_post_meta( $post->ID, '_vgwort_rights', true );
		$publisher     = get_post_meta( $post->ID, '_vgwort_publisher', true );

		if ( ! is_array( $participants ) || empty( $participants ) ) {
			$participants = array( array( 'firstName' => '', 'surName' => '', 'cardNumber' => '', 'involvement' => 'AUTHOR' ) );
		}

		if ( ! is_array( $rights ) ) {
			$rights = array();
		}
		if ( ! is_array( $publisher ) ) {
			$publisher = array( 'name' => '', 'cardNumber' => '', 'involved' => false );
		}

		$has_mark = $mark || ( $public_code && $private_code );
		?>
		<div class="vgwort-zm-box" data-post-id="<?php echo esc_attr( $post->ID ); ?>">

			<p><strong><?php esc_html_e( 'Zählmarke', 'vgwort-zaehlmarken' ); ?></strong></p>

			<div class="vgwort-zm-assignment">
				<?php if ( $mark ) : ?>
					<p>
						<?php esc_html_e( 'Öffentlicher Code:', 'vgwort-zaehlmarken' ); ?> <code><?php echo esc_html( $mark['public_code'] ); ?></code><br>
						<?php esc_html_e( 'Domain:', 'vgwort-zaehlmarken' ); ?> <code><?php echo esc_html( $mark['domain'] ); ?></code>
					</p>
					<button type="button" class="button vgwort-zm-unassign" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Zuweisung aufheben', 'vgwort-zaehlmarken' ); ?></button>
				<?php else : ?>
					<p><em><?php esc_html_e( 'Noch keine Zählmarke aus dem Pool zugewiesen.', 'vgwort-zaehlmarken' ); ?></em></p>
					<button type="button" class="button button-primary vgwort-zm-assign" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Aus Pool zuweisen', 'vgwort-zaehlmarken' ); ?></button>
				<?php endif; ?>
				<span class="vgwort-zm-assign-spinner spinner" style="float:none;"></span>
				<p class="vgwort-zm-assign-message"></p>
			</div>

			<p class="description"><?php esc_html_e( 'Alternativ manuell eintragen (z. B. verlagsinterner Schlüssel):', 'vgwort-zaehlmarken' ); ?></p>
			<p>
				<label><?php esc_html_e( 'Öffentlicher Code', 'vgwort-zaehlmarken' ); ?><br>
					<input type="text" class="widefat" name="vgwort_public_code" value="<?php echo esc_attr( $public_code ); ?>"></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Privater Code', 'vgwort-zaehlmarken' ); ?><br>
					<input type="text" class="widefat" name="vgwort_private_code" value="<?php echo esc_attr( $private_code ); ?>"></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Domain', 'vgwort-zaehlmarken' ); ?><br>
					<input type="text" class="widefat" name="vgwort_domain" value="<?php echo esc_attr( $domain ); ?>" placeholder="vg08.met.vgwort.de"></label>
			</p>

			<p>
				<label><input type="checkbox" name="vgwort_paywall" value="1" <?php checked( $paywall ); ?>> <?php esc_html_e( 'Hinter Bezahlschranke (Präfix „pw-“)', 'vgwort-zaehlmarken' ); ?></label>
			</p>
			<p>
				<label><input type="checkbox" name="vgwort_disabled" value="1" <?php checked( $disabled ); ?>> <?php esc_html_e( 'Zählmarke für diesen Beitrag deaktivieren', 'vgwort-zaehlmarken' ); ?></label>
			</p>

			<hr>
			<p><strong><?php esc_html_e( 'Meldung an VG Wort', 'vgwort-zaehlmarken' ); ?></strong></p>

			<?php if ( $reported ) : ?>
				<p class="notice notice-success" style="padding:6px;"><?php printf( esc_html__( 'Gemeldet am %s.', 'vgwort-zaehlmarken' ), esc_html( $reported_at ) ); ?></p>
			<?php elseif ( $report_error ) : ?>
				<p class="notice notice-error" style="padding:6px;"><?php echo esc_html( $report_error ); ?></p>
			<?php endif; ?>

			<p class="description"><?php esc_html_e( 'Beteiligte (Autor/Übersetzer). Mindestens eine Person ist erforderlich.', 'vgwort-zaehlmarken' ); ?></p>
			<div class="vgwort-zm-participants">
				<?php foreach ( $participants as $i => $p ) : ?>
					<div class="vgwort-zm-participant-row">
						<input type="text" name="vgwort_participants[<?php echo (int) $i; ?>][firstName]" value="<?php echo esc_attr( $p['firstName'] ); ?>" placeholder="<?php esc_attr_e( 'Vorname', 'vgwort-zaehlmarken' ); ?>" class="widefat">
						<input type="text" name="vgwort_participants[<?php echo (int) $i; ?>][surName]" value="<?php echo esc_attr( $p['surName'] ); ?>" placeholder="<?php esc_attr_e( 'Nachname', 'vgwort-zaehlmarken' ); ?>" class="widefat">
						<input type="text" name="vgwort_participants[<?php echo (int) $i; ?>][cardNumber]" value="<?php echo esc_attr( $p['cardNumber'] ); ?>" placeholder="<?php esc_attr_e( 'Karteinummer (optional)', 'vgwort-zaehlmarken' ); ?>" class="widefat">
						<select name="vgwort_participants[<?php echo (int) $i; ?>][involvement]">
							<option value="AUTHOR" <?php selected( $p['involvement'], 'AUTHOR' ); ?>><?php esc_html_e( 'Autor', 'vgwort-zaehlmarken' ); ?></option>
							<option value="TRANSLATOR" <?php selected( $p['involvement'], 'TRANSLATOR' ); ?>><?php esc_html_e( 'Übersetzer', 'vgwort-zaehlmarken' ); ?></option>
						</select>
						<button type="button" class="button-link vgwort-zm-remove-participant">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button vgwort-zm-add-participant"><?php esc_html_e( '+ Beteiligten hinzufügen', 'vgwort-zaehlmarken' ); ?></button>

			<p style="margin-top:1em;">
				<label><input type="checkbox" class="vgwort-zm-publisher-involved" name="vgwort_publisher[involved]" value="1" <?php checked( ! empty( $publisher['involved'] ) ); ?>> <?php esc_html_e( 'Verlag ist selbst als Beteiligter zu melden', 'vgwort-zaehlmarken' ); ?></label>
			</p>
			<div class="vgwort-zm-publisher-fields" style="<?php echo empty( $publisher['involved'] ) ? 'display:none;' : ''; ?>">
				<input type="text" name="vgwort_publisher[name]" value="<?php echo esc_attr( $publisher['name'] ); ?>" placeholder="<?php esc_attr_e( 'Verlagsname', 'vgwort-zaehlmarken' ); ?>" class="widefat">
				<input type="text" name="vgwort_publisher[cardNumber]" value="<?php echo esc_attr( $publisher['cardNumber'] ); ?>" placeholder="<?php esc_attr_e( 'Karteinummer (optional)', 'vgwort-zaehlmarken' ); ?>" class="widefat">
			</div>

			<p style="margin-top:1em;">
				<label><input type="checkbox" name="vgwort_without_own_participation" value="1" <?php checked( ! empty( $rights['withoutOwnParticipation'] ) ); ?>> <?php esc_html_e( 'Verlag meldet nur als Dienstleister (keine Rechteeinräumung, keine Ausschüttung an den Verlag)', 'vgwort-zaehlmarken' ); ?></label>
			</p>

			<div class="vgwort-zm-rights-fields" style="<?php echo ! empty( $rights['withoutOwnParticipation'] ) ? 'display:none;' : ''; ?>">
				<p><label><input type="checkbox" name="vgwort_rights[reproductionRight]" value="1" <?php checked( ! empty( $rights['reproductionRight'] ) ); ?>> <?php esc_html_e( 'Vervielfältigungsrecht (§ 16 UrhG) eingeräumt', 'vgwort-zaehlmarken' ); ?></label></p>
				<p><label><input type="checkbox" name="vgwort_rights[distributionRight]" value="1" <?php checked( ! empty( $rights['distributionRight'] ) ); ?>> <?php esc_html_e( 'Verbreitungsrecht (§ 17 UrhG) eingeräumt', 'vgwort-zaehlmarken' ); ?></label></p>
				<p><label><input type="checkbox" name="vgwort_rights[publicAccessRight]" value="1" <?php checked( ! empty( $rights['publicAccessRight'] ) ); ?>> <?php esc_html_e( 'Recht der öffentlichen Zugänglichmachung (§ 19a UrhG) eingeräumt', 'vgwort-zaehlmarken' ); ?></label></p>
				<p><label><input type="checkbox" name="vgwort_rights[otherRightsOfPublicReproduction]" value="1" <?php checked( ! empty( $rights['otherRightsOfPublicReproduction'] ) ); ?>> <?php esc_html_e( 'Sonstige Rechte der öffentlichen Wiedergabe (§§ 19,20,21,22 UrhG) eingeräumt', 'vgwort-zaehlmarken' ); ?></label></p>
				<p><label><input type="checkbox" name="vgwort_rights[rightsGrantedConfirmation]" value="1" <?php checked( ! empty( $rights['rightsGrantedConfirmation'] ) ); ?>> <?php esc_html_e( 'Ich bestätige die Rechteeinräumung durch alle Beteiligten', 'vgwort-zaehlmarken' ); ?></label></p>
			</div>

			<p>
				<button type="button" class="button button-primary vgwort-zm-send-message" data-post-id="<?php echo esc_attr( $post->ID ); ?>" <?php disabled( ! $has_mark || $reported ); ?>><?php esc_html_e( 'Meldung an VG Wort senden', 'vgwort-zaehlmarken' ); ?></button>
				<span class="vgwort-zm-message-spinner spinner" style="float:none;"></span>
			</p>
			<p class="vgwort-zm-message-result"></p>
			<p class="description"><?php esc_html_e( 'Hinweis: Der Text muss mindestens 1.800 Zeichen umfassen und sollte erst gemeldet werden, wenn er nicht mehr wesentlich geändert wird.', 'vgwort-zaehlmarken' ); ?></p>
		</div>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$public_code  = isset( $_POST['vgwort_public_code'] ) ? sanitize_text_field( wp_unslash( $_POST['vgwort_public_code'] ) ) : '';
		$private_code = isset( $_POST['vgwort_private_code'] ) ? sanitize_text_field( wp_unslash( $_POST['vgwort_private_code'] ) ) : '';
		$domain       = isset( $_POST['vgwort_domain'] ) ? preg_replace( '#^https?://#', '', sanitize_text_field( wp_unslash( $_POST['vgwort_domain'] ) ) ) : '';

		update_post_meta( $post_id, '_vgwort_public_code', $public_code );
		update_post_meta( $post_id, '_vgwort_private_code', $private_code );
		update_post_meta( $post_id, '_vgwort_domain', $domain );
		update_post_meta( $post_id, '_vgwort_paywall', ! empty( $_POST['vgwort_paywall'] ) );
		update_post_meta( $post_id, '_vgwort_disabled', ! empty( $_POST['vgwort_disabled'] ) );

		$participants = array();
		if ( ! empty( $_POST['vgwort_participants'] ) && is_array( $_POST['vgwort_participants'] ) ) {
			foreach ( wp_unslash( $_POST['vgwort_participants'] ) as $p ) {
				$first_name = sanitize_text_field( $p['firstName'] ?? '' );
				$sur_name   = sanitize_text_field( $p['surName'] ?? '' );

				if ( '' === $first_name && '' === $sur_name ) {
					continue;
				}

				$participants[] = array(
					'firstName'   => $first_name,
					'surName'     => $sur_name,
					'cardNumber'  => preg_replace( '/[^0-9]/', '', $p['cardNumber'] ?? '' ),
					'involvement' => 'TRANSLATOR' === ( $p['involvement'] ?? '' ) ? 'TRANSLATOR' : 'AUTHOR',
				);
			}
		}
		update_post_meta( $post_id, '_vgwort_participants', $participants );

		$publisher = array(
			'involved'   => ! empty( $_POST['vgwort_publisher']['involved'] ),
			'name'       => isset( $_POST['vgwort_publisher']['name'] ) ? sanitize_text_field( wp_unslash( $_POST['vgwort_publisher']['name'] ) ) : '',
			'cardNumber' => isset( $_POST['vgwort_publisher']['cardNumber'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['vgwort_publisher']['cardNumber'] ) ) : '',
		);
		update_post_meta( $post_id, '_vgwort_publisher', $publisher );

		$rights = array(
			'reproductionRight'               => ! empty( $_POST['vgwort_rights']['reproductionRight'] ),
			'distributionRight'                => ! empty( $_POST['vgwort_rights']['distributionRight'] ),
			'publicAccessRight'                 => ! empty( $_POST['vgwort_rights']['publicAccessRight'] ),
			'otherRightsOfPublicReproduction'   => ! empty( $_POST['vgwort_rights']['otherRightsOfPublicReproduction'] ),
			'rightsGrantedConfirmation'         => ! empty( $_POST['vgwort_rights']['rightsGrantedConfirmation'] ),
			'withoutOwnParticipation'           => ! empty( $_POST['vgwort_without_own_participation'] ),
		);
		update_post_meta( $post_id, '_vgwort_rights', $rights );
	}
}
