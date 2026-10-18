<?php
/**
 * REST-Endpunkte für die Widgets.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Namespace cinerent/v1. Der Browser spricht nur mit WordPress, nie mit Storybase.
 */
final class Rest {

	const NAMESPACE_V1 = 'cinerent/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routen.
	 */
	public static function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/verfuegbarkeit',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'von' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'bis' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/kalender',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'calendar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'monat' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							return (bool) preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/formular',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'form_init' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/anfrage',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'inquiry' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Antwort ohne Caching.
	 *
	 * @param mixed $data   Daten.
	 * @param int   $status Status.
	 * @return WP_REST_Response
	 */
	private static function no_cache( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Live-Verfügbarkeit, z. B. für alle Badges einer Seite.
	 *
	 * @param WP_REST_Request $request Anfrage.
	 * @return WP_REST_Response
	 */
	public static function availability( WP_REST_Request $request ) {
		$ids    = array_slice( array_values( array_unique( array_filter( array_map( 'trim', explode( ',', (string) $request['ids'] ) ) ) ) ), 0, 100 );
		$period = Period::validate( $request['von'], $request['bis'] );
		$source = Plugin::instance()->source();

		if ( $period && $source->supports_availability() ) {
			$status = $source->get_availability( $ids, $period['von'], $period['bis'] );
		} else {
			$status = array_fill_keys( $ids, Data\Data_Source::ANFRAGE );
		}

		return self::no_cache(
			array(
				'zeitraum' => $period,
				'status'   => (object) $status,
			)
		);
	}

	/**
	 * Belegte Tage eines Monats.
	 *
	 * @param WP_REST_Request $request Anfrage.
	 * @return WP_REST_Response
	 */
	public static function calendar( WP_REST_Request $request ) {
		$data = Plugin::instance()->source()->get_calendar( (string) $request['id'], (string) $request['monat'] );
		return self::no_cache( $data );
	}

	/**
	 * Startdaten fürs Formular: Nonce, Zeit-Token und ALTCHA-Challenge.
	 * Wird beim Laden des Formulars abgerufen, damit gecachte Seiten funktionieren.
	 *
	 * @return WP_REST_Response
	 */
	public static function form_init() {
		return self::no_cache(
			array(
				'nonce'  => wp_create_nonce( Inquiry::NONCE_ACTION ),
				'token'  => Inquiry::create_token(),
				'altcha' => Altcha::create(),
			)
		);
	}

	/**
	 * Anfrage absenden.
	 *
	 * @param WP_REST_Request $request Anfrage.
	 * @return WP_REST_Response
	 */
	public static function inquiry( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$result = Inquiry::handle( is_array( $params ) ? $params : array() );

		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			return self::no_cache(
				array(
					'ok'      => false,
					'meldung' => $result->get_error_message(),
					'feld'    => $data['feld'] ?? '',
				),
				(int) ( $data['status'] ?? 400 )
			);
		}

		return self::no_cache(
			array(
				'ok'      => true,
				'meldung' => __( 'Vielen Dank! Ihre Anfrage ist eingegangen. Sie erhalten in Kürze eine Bestätigung per E-Mail.', 'cinerent-shop' ),
			)
		);
	}
}
