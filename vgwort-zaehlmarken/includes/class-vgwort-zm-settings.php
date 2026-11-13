<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Einstellungsseite: Zugangsdaten für die VG-Wort-REST-Schnittstelle,
 * Einbau-Verhalten und Verlagsinterner-Schlüssel-Modus.
 */
class VGWort_ZM_Settings {

	const OPTION_KEY = 'vgwort_zm_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function get_defaults() {
		return array(
			'api_username'          => '',
			'api_password'          => '',
			'use_internal_key'      => false,
			'internal_key_card_no'  => '',
			'fallback_domain'       => '',
			'post_types'            => array( 'post' ),
			'position'              => 'append',
			'delete_data_uninstall' => false,
		);
	}

	public static function get() {
		$settings = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( $settings, self::get_defaults() );
	}

	public function register_menu() {
		add_options_page(
			__( 'VG Wort Zählmarken', 'vgwort-zaehlmarken' ),
			__( 'VG Wort Zählmarken', 'vgwort-zaehlmarken' ),
			'manage_options',
			'vgwort-zm-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'vgwort_zm_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		$defaults = self::get_defaults();
		$output   = array();

		$output['api_username']         = isset( $input['api_username'] ) ? sanitize_text_field( $input['api_username'] ) : '';
		$output['api_password']         = isset( $input['api_password'] ) ? trim( (string) $input['api_password'] ) : '';
		$output['use_internal_key']     = ! empty( $input['use_internal_key'] );
		$output['internal_key_card_no'] = isset( $input['internal_key_card_no'] ) ? preg_replace( '/[^0-9]/', '', $input['internal_key_card_no'] ) : '';
		$output['fallback_domain']      = isset( $input['fallback_domain'] ) ? preg_replace( '#^https?://#', '', sanitize_text_field( $input['fallback_domain'] ) ) : '';
		$output['fallback_domain']      = rtrim( $output['fallback_domain'], '/' );

		$allowed_post_types    = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		$output['post_types']  = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_values( array_intersect( $input['post_types'], $allowed_post_types ) )
			: array();

		$output['position'] = isset( $input['position'] ) && 'prepend' === $input['position'] ? 'prepend' : 'append';

		$output['delete_data_uninstall'] = ! empty( $input['delete_data_uninstall'] );

		// Falls das Passwort-Feld beim Speichern leer angezeigt wurde (z. B. weil der
		// Nutzer es nicht ändern wollte), das bestehende Passwort behalten.
		if ( '' === $output['api_password'] ) {
			$existing = self::get();
			$output['api_password'] = $existing['api_password'];
		}

		return wp_parse_args( $output, $defaults );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings    = self::get();
		$post_types  = get_post_types( array( 'public' => true ), 'objects' );
		$test_result = null;

		if ( isset( $_POST['vgwort_zm_test_connection'] ) && check_admin_referer( 'vgwort_zm_test_connection' ) ) {
			$api         = new VGWort_ZM_Api();
			$test_result = $api->quality_control();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'VG Wort Zählmarken – Einstellungen', 'vgwort-zaehlmarken' ); ?></h1>

			<?php if ( null !== $test_result ) : ?>
				<?php if ( is_wp_error( $test_result ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $test_result->get_error_message() ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-success"><p><?php esc_html_e( 'Verbindung erfolgreich – die Zugangsdaten wurden von VG Wort akzeptiert.', 'vgwort-zaehlmarken' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'vgwort_zm_settings_group' ); ?>

				<h2><?php esc_html_e( 'Zugangsdaten (T.O.M.-Meldeportal)', 'vgwort-zaehlmarken' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Dieselben Zugangsdaten, mit denen du dich unter tom.vgwort.de anmeldest. Die Schnittstelle nutzt HTTP Basic Auth über TLS.', 'vgwort-zaehlmarken' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="vgwort_api_username"><?php esc_html_e( 'Benutzername', 'vgwort-zaehlmarken' ); ?></label></th>
						<td><input type="text" id="vgwort_api_username" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_username]" value="<?php echo esc_attr( $settings['api_username'] ); ?>" class="regular-text" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="vgwort_api_password"><?php esc_html_e( 'Passwort', 'vgwort-zaehlmarken' ); ?></label></th>
						<td>
							<input type="password" id="vgwort_api_password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_password]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $settings['api_password'] ? esc_attr__( '•••••••• (unverändert lassen)', 'vgwort-zaehlmarken' ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'Leer lassen, um das gespeicherte Passwort beizubehalten.', 'vgwort-zaehlmarken' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Zähldomäne', 'vgwort-zaehlmarken' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="vgwort_fallback_domain"><?php esc_html_e( 'Fallback-Domain', 'vgwort-zaehlmarken' ); ?></label></th>
						<td>
							<input type="text" id="vgwort_fallback_domain" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fallback_domain]" value="<?php echo esc_attr( $settings['fallback_domain'] ); ?>" class="regular-text" placeholder="z. B. vg08.met.vgwort.de">
							<p class="description"><?php esc_html_e( 'Wird für per CSV importierte oder verlagsinterne Zählmarken verwendet, falls keine Domain hinterlegt ist. Bei über die Schnittstelle bestellten Marken liefert VG Wort die Domain automatisch mit.', 'vgwort-zaehlmarken' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Verlagsinterner Schlüssel', 'vgwort-zaehlmarken' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Nur aktivieren, wenn VG Wort dir die Nutzung eigener Schlüssel als Zählmarke per E-Mail (metis.support@vgwort.de) bestätigt hat.', 'vgwort-zaehlmarken' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Aktivieren', 'vgwort-zaehlmarken' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[use_internal_key]" value="1" <?php checked( $settings['use_internal_key'] ); ?>>
								<?php esc_html_e( 'Verlagsinternen Schlüssel statt bestellter VG-Wort-Zählmarken verwenden', 'vgwort-zaehlmarken' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vgwort_internal_key_card_no"><?php esc_html_e( 'VG-Wort-Karteinummer', 'vgwort-zaehlmarken' ); ?></label></th>
						<td><input type="text" id="vgwort_internal_key_card_no" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[internal_key_card_no]" value="<?php echo esc_attr( $settings['internal_key_card_no'] ); ?>" class="regular-text"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Einbau', 'vgwort-zaehlmarken' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Beitragstypen', 'vgwort-zaehlmarken' ); ?></th>
						<td>
							<?php foreach ( $post_types as $pt ) : ?>
								<label style="display:block;">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $settings['post_types'], true ) ); ?>>
									<?php echo esc_html( $pt->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Position der Zählmarke', 'vgwort-zaehlmarken' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[position]">
								<option value="append" <?php selected( $settings['position'], 'append' ); ?>><?php esc_html_e( 'Am Ende des Inhalts', 'vgwort-zaehlmarken' ); ?></option>
								<option value="prepend" <?php selected( $settings['position'], 'prepend' ); ?>><?php esc_html_e( 'Am Anfang des Inhalts', 'vgwort-zaehlmarken' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Deinstallation', 'vgwort-zaehlmarken' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Daten löschen', 'vgwort-zaehlmarken' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_data_uninstall]" value="1" <?php checked( $settings['delete_data_uninstall'] ); ?>>
								<?php esc_html_e( 'Zählmarken-Tabelle und Einstellungen bei Deinstallation vollständig löschen', 'vgwort-zaehlmarken' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Verbindung testen', 'vgwort-zaehlmarken' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'vgwort_zm_test_connection' ); ?>
				<p><?php submit_button( __( 'Verbindung zu VG Wort testen', 'vgwort-zaehlmarken' ), 'secondary', 'vgwort_zm_test_connection', false ); ?></p>
			</form>
		</div>
		<?php
	}
}
