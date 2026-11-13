<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fügt das VG-Wort-Zählpixel in den Frontend-Inhalt ein.
 */
class VGWort_ZM_Frontend {

	public function __construct() {
		add_filter( 'the_content', array( $this, 'inject_pixel' ) );
	}

	/**
	 * Ermittelt Code/Domain, die für einen Beitrag verwendet werden sollen.
	 * Priorität: manuell hinterlegte Codes > zugewiesene Pool-Marke >
	 * automatisch generierter verlagsinterner Schlüssel.
	 *
	 * @return array|null ['public_code'=>, 'private_code'=>, 'domain'=>, 'paywall'=>bool]
	 */
	public static function get_assignment( $post_id ) {
		$settings = VGWort_ZM_Settings::get();

		$public  = get_post_meta( $post_id, '_vgwort_public_code', true );
		$private = get_post_meta( $post_id, '_vgwort_private_code', true );
		$domain  = get_post_meta( $post_id, '_vgwort_domain', true );
		$paywall = (bool) get_post_meta( $post_id, '_vgwort_paywall', true );

		if ( '' === $public ) {
			$mark = VGWort_ZM_Pool::get_for_post( $post_id );

			if ( $mark ) {
				$public  = $mark['public_code'];
				$private = $mark['private_code'];
				$domain  = $domain ? $domain : $mark['domain'];
			}
		}

		if ( '' === $public && ! empty( $settings['use_internal_key'] ) && '' !== $settings['internal_key_card_no'] ) {
			$key     = self::sanitize_internal_key_part( 'post' . $post_id );
			$public  = 'vgzm.' . $settings['internal_key_card_no'] . '-' . $key;
			$private = $public;
		}

		if ( '' === $domain ) {
			$domain = $settings['fallback_domain'];
		}

		if ( '' === $public || '' === $domain ) {
			return null;
		}

		return array(
			'public_code'  => $public,
			'private_code' => $private,
			'domain'       => $domain,
			'paywall'      => $paywall,
		);
	}

	private static function sanitize_internal_key_part( $key ) {
		return preg_replace( '/[^a-zA-Z0-9._-]/', '', $key );
	}

	/**
	 * Formt aus einem Rohcode den tatsächlich in die URL einzubauenden Wert
	 * (inkl. Base64url-Kodierung bei unzulässigen Zeichen und pw-Präfix).
	 */
	public static function build_pixel_path( $public_code, $paywall ) {
		$allowed_pattern = '/^[a-zA-Z0-9._-]+$/';

		if ( ! preg_match( $allowed_pattern, $public_code ) ) {
			$encoded     = base64_encode( $public_code );
			$encoded     = rtrim( strtr( $encoded, '+/', '-_' ), '=' );
			$public_code = 'base64-' . $encoded;
		}

		return ( $paywall ? 'pw-' : '' ) . $public_code;
	}

	public function inject_pixel( $content ) {
		if ( is_feed() || is_preview() || post_password_required() ) {
			return $content;
		}

		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return $content;
		}

		$settings = VGWort_ZM_Settings::get();

		if ( ! in_array( get_post_type( $post_id ), $settings['post_types'], true ) ) {
			return $content;
		}

		if ( ! empty( get_post_meta( $post_id, '_vgwort_disabled', true ) ) ) {
			return $content;
		}

		$assignment = self::get_assignment( $post_id );

		if ( ! $assignment ) {
			return $content;
		}

		$path = self::build_pixel_path( $assignment['public_code'], $assignment['paywall'] );

		$pixel = sprintf(
			'<img src="https://%1$s/na/%2$s" width="1" height="1" alt="" style="border:0;position:absolute;visibility:hidden;" loading="eager">',
			esc_attr( $assignment['domain'] ),
			esc_attr( $path )
		);

		if ( 'prepend' === $settings['position'] ) {
			return $pixel . $content;
		}

		return $content . $pixel;
	}
}
