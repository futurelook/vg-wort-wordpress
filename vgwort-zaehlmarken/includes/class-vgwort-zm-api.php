<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client für die VG-Wort-REST-Schnittstelle "METIS für Verlage".
 *
 * Basis-URL und Endpunkte gemäß Integrationsbeschreibung METIS für Verlage
 * (Dokumentversion 2.29): https://tom.vgwort.de/api/external/metis/rest/...
 * Authentifizierung: HTTP Basic Auth über TLS (RFC 2617).
 */
class VGWort_ZM_Api {

	const BASE_URL = 'https://tom.vgwort.de/api/external/metis/rest';

	/** @var string */
	private $username;

	/** @var string */
	private $password;

	public function __construct( $username = '', $password = '' ) {
		$settings = VGWort_ZM_Settings::get();

		$this->username = '' !== $username ? $username : $settings['api_username'];
		$this->password = '' !== $password ? $password : $settings['api_password'];
	}

	public function has_credentials() {
		return '' !== $this->username && '' !== $this->password;
	}

	/**
	 * Bestellt neue Zählmarken (Zählmarken-Server: pixel/v1.0/order).
	 *
	 * @param int $count Anzahl der zu bestellenden Zählmarken.
	 * @return array|WP_Error {orderDateTime, domain, pixels:[{publicIdentificationId, privateIdentificationId}]}
	 */
	public function order_pixel( $count ) {
		return $this->request(
			'POST',
			'/pixel/v1.0/order',
			array( 'count' => (int) $count )
		);
	}

	/**
	 * Ruft Zählmarken ab, die den Mindestzugriff erreicht haben, aber noch
	 * nicht gemeldet wurden (pixel/v1.0/overview).
	 *
	 * @param int    $offset Wiederaufsetzpunkt (max. 100 Datensätze pro Abruf).
	 * @param string $type   MINDESTZUGRIFF oder ANTEILIGER_MINDESTZUGRIFF.
	 */
	public function pixel_overview( $offset = 0, $type = 'MINDESTZUGRIFF' ) {
		return $this->request(
			'POST',
			'/pixel/v1.0/overview',
			array(
				'offset' => (int) $offset,
				'type'   => $type,
			)
		);
	}

	/**
	 * Übermittelt eine Meldung zu einer Zählmarke (message/v1.0/newMessageRequest).
	 *
	 * @param array $payload Vollständiger Meldungs-Body gemäß Doku Kapitel 3.2.1.2.
	 */
	public function new_message( array $payload ) {
		return $this->request( 'POST', '/message/v1.0/newMessageRequest', $payload );
	}

	/**
	 * Prüft die Kombination Karteinummer/Nachname (message/v1.0/checkAuthorRequest).
	 */
	public function check_author( $card_number, $sur_name, $first_name = '' ) {
		$args = array(
			'cardNumber' => $card_number,
			'surName'    => $sur_name,
		);

		if ( '' !== $first_name ) {
			$args['firstName'] = $first_name;
		}

		return $this->request( 'GET', '/message/v1.0/checkAuthorRequest', $args );
	}

	/**
	 * Ruft die Qualitätskontrolle (Kennzahlen) ab (message/v1.0/qualityControlRequest).
	 */
	public function quality_control() {
		return $this->request( 'GET', '/message/v1.0/qualityControlRequest', array() );
	}

	/**
	 * Führt den eigentlichen HTTP-Request aus und normalisiert Erfolg/Fehler.
	 *
	 * @return array|WP_Error Bei Erfolg das dekodierte JSON-Array, sonst WP_Error
	 *                        mit den von VG Wort gelieferten Fehlercodes/-texten.
	 */
	private function request( $method, $path, array $args ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error(
				'vgwort_zm_no_credentials',
				__( 'Es sind keine VG-Wort-Zugangsdaten (T.O.M.-Benutzername/Passwort) hinterlegt.', 'vgwort-zaehlmarken' )
			);
		}

		$url = self::BASE_URL . $path;

		$request_args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
				'Accept'        => 'application/json',
			),
		);

		if ( 'GET' === $method ) {
			if ( ! empty( $args ) ) {
				$url = add_query_arg( array_map( 'rawurlencode', $args ), $url );
			}
		} else {
			$request_args['headers']['Content-Type'] = 'application/json';
			$request_args['body']                    = wp_json_encode( $args );
		}

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 401 === $code ) {
			return new WP_Error( 'vgwort_zm_auth', __( 'VG Wort hat die Zugangsdaten abgelehnt (401 – Authentifizierungsfehler).', 'vgwort-zaehlmarken' ) );
		}

		if ( 403 === $code ) {
			return new WP_Error( 'vgwort_zm_forbidden', __( 'Der Zugang hat nicht die nötigen Rechte für diese Aktion (403).', 'vgwort-zaehlmarken' ) );
		}

		$fault_code = $this->find_key( $data, array( 'errorcode', 'errorCode' ) );

		if ( null !== $fault_code || $code >= 400 ) {
			$fault_msg = $this->find_key( $data, array( 'errormsg', 'errorMsg', 'message' ) );

			return new WP_Error(
				'vgwort_zm_fault',
				sprintf(
					/* translators: 1: HTTP-Status, 2: Fehlercode, 3: Fehlermeldung */
					__( 'VG-Wort-Schnittstellenfehler (HTTP %1$s, Code %2$s): %3$s', 'vgwort-zaehlmarken' ),
					$code,
					null !== $fault_code ? $fault_code : '–',
					$fault_msg ? $fault_msg : __( 'Keine weitere Fehlermeldung erhalten.', 'vgwort-zaehlmarken' )
				),
				array(
					'http_code'  => $code,
					'error_code' => $fault_code,
					'raw_body'   => $body,
				)
			);
		}

		if ( null === $data ) {
			$data = array();
		}

		return $data;
	}

	/**
	 * Case-insensitive Suche nach einem von mehreren möglichen Schlüsseln,
	 * da die VG-Wort-Doku Feldnamen in Groß-/Kleinschreibung uneinheitlich angibt.
	 */
	private function find_key( $data, array $keys ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				return $data[ $key ];
			}
		}

		return null;
	}
}
