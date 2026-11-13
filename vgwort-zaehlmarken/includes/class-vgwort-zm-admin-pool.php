<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-Seite "Marken-Pool": Bestellung neuer Zählmarken über die
 * VG-Wort-Schnittstelle, CSV-Import und Übersicht/Verwaltung des Pools.
 */
class VGWort_ZM_Admin_Pool {

	const PAGE_SLUG = 'vgwort-zm-pool';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_vgwort_zm_order_pixel', array( $this, 'handle_order' ) );
		add_action( 'admin_post_vgwort_zm_import_csv', array( $this, 'handle_import' ) );
		add_action( 'admin_post_vgwort_zm_delete_mark', array( $this, 'handle_delete' ) );
	}

	public function register_menu() {
		add_management_page(
			__( 'VG Wort Marken-Pool', 'vgwort-zaehlmarken' ),
			__( 'VG Wort Marken-Pool', 'vgwort-zaehlmarken' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function handle_order() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vgwort-zaehlmarken' ) );
		}

		check_admin_referer( 'vgwort_zm_order_pixel' );

		$count = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 0;
		$count = max( 1, min( 4000, $count ) );

		$api    = new VGWort_ZM_Api();
		$result = $api->order_pixel( $count );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'error', $result->get_error_message() );
		}

		$pixels = array();
		foreach ( (array) $this->find( $result, array( 'pixels' ), array() ) as $pixel ) {
			$pixels[] = array(
				'public_code'  => $this->find( $pixel, array( 'publicIdentificationId' ), '' ),
				'private_code' => $this->find( $pixel, array( 'privateIdentificationId' ), '' ),
			);
		}

		$domain    = $this->find( $result, array( 'domain' ), '' );
		$inserted  = VGWort_ZM_Pool::add_pixels( $pixels, $domain, 'order' );

		$this->redirect_with_message(
			'success',
			sprintf(
				/* translators: %d: Anzahl */
				_n( '%d Zählmarke erfolgreich bestellt und in den Pool aufgenommen.', '%d Zählmarken erfolgreich bestellt und in den Pool aufgenommen.', $inserted, 'vgwort-zaehlmarken' ),
				$inserted
			)
		);
	}

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vgwort-zaehlmarken' ) );
		}

		check_admin_referer( 'vgwort_zm_import_csv' );

		$raw = '';

		if ( ! empty( $_FILES['csv_file']['tmp_name'] ) && is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			$raw = file_get_contents( $_FILES['csv_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
		} elseif ( ! empty( $_POST['csv_text'] ) ) {
			$raw = wp_unslash( $_POST['csv_text'] );
		}

		$domain = isset( $_POST['csv_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['csv_domain'] ) ) : '';
		$pixels = VGWort_ZM_Pool::parse_csv( $raw );
		$inserted = VGWort_ZM_Pool::add_pixels( $pixels, $domain, 'import' );

		$this->redirect_with_message(
			'success',
			sprintf(
				/* translators: %d: Anzahl */
				_n( '%d Zählmarke aus der CSV-Datei importiert.', '%d Zählmarken aus der CSV-Datei importiert.', $inserted, 'vgwort-zaehlmarken' ),
				$inserted
			)
		);
	}

	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'vgwort-zaehlmarken' ) );
		}

		check_admin_referer( 'vgwort_zm_delete_mark' );

		$id = isset( $_GET['mark_id'] ) ? absint( $_GET['mark_id'] ) : 0;
		VGWort_ZM_Pool::delete_unused( $id );

		$this->redirect_with_message( 'success', __( 'Zählmarke gelöscht.', 'vgwort-zaehlmarken' ) );
	}

	private function redirect_with_message( $type, $message ) {
		set_transient( 'vgwort_zm_admin_notice', array( 'type' => $type, 'message' => $message ), 60 );
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	private function find( $data, array $keys, $default ) {
		if ( ! is_array( $data ) ) {
			return $default;
		}

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				return $data[ $key ];
			}
		}

		return $default;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats    = VGWort_ZM_Pool::get_stats();
		$api      = new VGWort_ZM_Api();
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$listing  = VGWort_ZM_Pool::query( $status, $paged, 20 );
		$notice   = get_transient( 'vgwort_zm_admin_notice' );

		if ( $notice ) {
			delete_transient( 'vgwort_zm_admin_notice' );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'VG Wort – Marken-Pool', 'vgwort-zaehlmarken' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $api->has_credentials() ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: Link zu den Einstellungen */
							esc_html__( 'Es sind noch keine VG-Wort-Zugangsdaten hinterlegt. %s, um Zählmarken direkt über die Schnittstelle zu bestellen. Alternativ kannst du unten eine CSV-Datei aus dem Meldeportal importieren.', 'vgwort-zaehlmarken' ),
							'<a href="' . esc_url( admin_url( 'options-general.php?page=vgwort-zm-settings' ) ) . '">' . esc_html__( 'Jetzt eintragen', 'vgwort-zaehlmarken' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<ul class="vgwort-zm-stats">
				<li><strong><?php echo esc_html( $stats['total'] ); ?></strong> <?php esc_html_e( 'gesamt', 'vgwort-zaehlmarken' ); ?></li>
				<li><strong><?php echo esc_html( $stats['unused'] ); ?></strong> <?php esc_html_e( 'unbenutzt', 'vgwort-zaehlmarken' ); ?></li>
				<li><strong><?php echo esc_html( $stats['assigned'] ); ?></strong> <?php esc_html_e( 'zugewiesen', 'vgwort-zaehlmarken' ); ?></li>
				<li><strong><?php echo esc_html( $stats['reported'] ); ?></strong> <?php esc_html_e( 'gemeldet', 'vgwort-zaehlmarken' ); ?></li>
			</ul>

			<div style="display:flex; gap:2em; flex-wrap:wrap;">
				<div style="flex:1; min-width:320px;">
					<h2><?php esc_html_e( 'Zählmarken über Schnittstelle bestellen', 'vgwort-zaehlmarken' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'vgwort_zm_order_pixel' ); ?>
						<input type="hidden" name="action" value="vgwort_zm_order_pixel">
						<p>
							<label for="vgwort_count"><?php esc_html_e( 'Anzahl', 'vgwort-zaehlmarken' ); ?></label>
							<input type="number" id="vgwort_count" name="count" value="50" min="1" max="4000" required>
						</p>
						<p class="description"><?php esc_html_e( 'Pro Kalenderjahr können max. 4.000 Zählmarken bestellt werden. Bei höherem Bedarf: metis.support@vgwort.de.', 'vgwort-zaehlmarken' ); ?></p>
						<?php submit_button( __( 'Jetzt bestellen', 'vgwort-zaehlmarken' ), 'primary', 'submit', false, ! $api->has_credentials() ? array( 'disabled' => 'disabled' ) : array() ); ?>
					</form>
				</div>

				<div style="flex:1; min-width:320px;">
					<h2><?php esc_html_e( 'CSV aus dem Meldeportal importieren', 'vgwort-zaehlmarken' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'vgwort_zm_import_csv' ); ?>
						<input type="hidden" name="action" value="vgwort_zm_import_csv">
						<p>
							<label for="vgwort_csv_domain"><?php esc_html_e( 'Zähldomain dieser Marken', 'vgwort-zaehlmarken' ); ?></label><br>
							<input type="text" id="vgwort_csv_domain" name="csv_domain" placeholder="z. B. vg08.met.vgwort.de" class="regular-text">
						</p>
						<p>
							<label for="vgwort_csv_file"><?php esc_html_e( 'CSV-Datei', 'vgwort-zaehlmarken' ); ?></label><br>
							<input type="file" id="vgwort_csv_file" name="csv_file" accept=".csv,text/csv">
						</p>
						<p>
							<label for="vgwort_csv_text"><?php esc_html_e( 'oder Inhalt einfügen (öffentlicher;privater Code je Zeile)', 'vgwort-zaehlmarken' ); ?></label><br>
							<textarea id="vgwort_csv_text" name="csv_text" rows="5" class="large-text code"></textarea>
						</p>
						<?php submit_button( __( 'Importieren', 'vgwort-zaehlmarken' ) ); ?>
					</form>
				</div>
			</div>

			<h2><?php esc_html_e( 'Marken-Übersicht', 'vgwort-zaehlmarken' ); ?></h2>
			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Alle', 'vgwort-zaehlmarken' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::PAGE_SLUG . '&status=unused' ) ); ?>" class="<?php echo 'unused' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Unbenutzt', 'vgwort-zaehlmarken' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::PAGE_SLUG . '&status=assigned' ) ); ?>" class="<?php echo 'assigned' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Zugewiesen', 'vgwort-zaehlmarken' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::PAGE_SLUG . '&status=reported' ) ); ?>" class="<?php echo 'reported' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Gemeldet', 'vgwort-zaehlmarken' ); ?></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Öffentlicher Code', 'vgwort-zaehlmarken' ); ?></th>
						<th><?php esc_html_e( 'Privater Code', 'vgwort-zaehlmarken' ); ?></th>
						<th><?php esc_html_e( 'Domain', 'vgwort-zaehlmarken' ); ?></th>
						<th><?php esc_html_e( 'Status', 'vgwort-zaehlmarken' ); ?></th>
						<th><?php esc_html_e( 'Beitrag', 'vgwort-zaehlmarken' ); ?></th>
						<th><?php esc_html_e( 'Herkunft', 'vgwort-zaehlmarken' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $listing['rows'] ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Keine Zählmarken gefunden.', 'vgwort-zaehlmarken' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $listing['rows'] as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['public_code'] ); ?></code></td>
							<td><code><?php echo esc_html( $row['private_code'] ); ?></code></td>
							<td><?php echo esc_html( $row['domain'] ); ?></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
							<td>
								<?php if ( $row['post_id'] ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $row['post_id'] ) ); ?></a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['source'] ); ?></td>
							<td>
								<?php if ( 'unused' === $row['status'] ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vgwort_zm_delete_mark&mark_id=' . $row['id'] ), 'vgwort_zm_delete_mark' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Diese Zählmarke wirklich löschen?', 'vgwort-zaehlmarken' ) ); ?>');"><?php esc_html_e( 'Löschen', 'vgwort-zaehlmarken' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $listing['total'] / 20 );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}
}
