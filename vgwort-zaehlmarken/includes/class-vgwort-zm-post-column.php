<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zusätzliche Spalte in der Beitragsliste mit dem VG-Wort-Status.
 */
class VGWort_ZM_Post_Column {

	public function __construct() {
		$settings = VGWort_ZM_Settings::get();

		foreach ( $settings['post_types'] as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	public function add_column( $columns ) {
		$columns['vgwort_zm'] = __( 'VG Wort', 'vgwort-zaehlmarken' );

		return $columns;
	}

	public function render_column( $column, $post_id ) {
		if ( 'vgwort_zm' !== $column ) {
			return;
		}

		if ( ! empty( get_post_meta( $post_id, '_vgwort_disabled', true ) ) ) {
			echo '<span class="dashicons dashicons-hidden" title="' . esc_attr__( 'Deaktiviert', 'vgwort-zaehlmarken' ) . '"></span>';
			return;
		}

		$reported    = (bool) get_post_meta( $post_id, '_vgwort_reported', true );
		$public_code = get_post_meta( $post_id, '_vgwort_public_code', true );
		$mark        = VGWort_ZM_Pool::get_for_post( $post_id );

		if ( $reported ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" title="' . esc_attr__( 'Gemeldet', 'vgwort-zaehlmarken' ) . '"></span> ' . esc_html__( 'Gemeldet', 'vgwort-zaehlmarken' );
		} elseif ( $mark || $public_code ) {
			echo '<span class="dashicons dashicons-marker" style="color:#2271b1;" title="' . esc_attr__( 'Zugewiesen', 'vgwort-zaehlmarken' ) . '"></span> ' . esc_html__( 'Zugewiesen', 'vgwort-zaehlmarken' );
		} else {
			echo '<span class="dashicons dashicons-minus" style="color:#a7aaad;"></span> ' . esc_html__( 'Keine Zählmarke', 'vgwort-zaehlmarken' );
		}
	}
}
