<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'vgwort_zm_settings' );

if ( empty( $settings['delete_data_uninstall'] ) ) {
	return;
}

global $wpdb;

$table = $wpdb->prefix . 'vgwort_marks';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'vgwort_zm_settings' );
delete_option( 'vgwort_zm_db_version' );

$posts = get_posts(
	array(
		'post_type'   => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vgwort_public_code',
				'compare' => 'EXISTS',
			),
		),
	)
);

$meta_keys = array(
	'_vgwort_public_code',
	'_vgwort_private_code',
	'_vgwort_domain',
	'_vgwort_paywall',
	'_vgwort_disabled',
	'_vgwort_participants',
	'_vgwort_publisher',
	'_vgwort_rights',
	'_vgwort_reported',
	'_vgwort_reported_at',
	'_vgwort_report_error',
);

foreach ( $posts as $post_id ) {
	foreach ( $meta_keys as $meta_key ) {
		delete_post_meta( $post_id, $meta_key );
	}
}
