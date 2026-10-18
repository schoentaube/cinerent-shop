<?php
/**
 * Skripte und Styles.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert alle Assets. Die Widgets laden nur, was sie brauchen
 * (get_script_depends / get_style_depends). Alles liegt lokal im Plugin.
 */
final class Assets {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
	}

	/**
	 * URL und Version eines Assets.
	 *
	 * @param string $path Pfad relativ zu assets/.
	 * @return array{0:string, 1:string}
	 */
	private static function asset( $path ) {
		$file    = CINERENT_SHOP_DIR . 'assets/' . $path;
		$version = is_readable( $file ) ? CINERENT_SHOP_VERSION . '.' . filemtime( $file ) : CINERENT_SHOP_VERSION;
		return array( CINERENT_SHOP_URL . 'assets/' . $path, $version );
	}

	/**
	 * Frontend-Assets registrieren.
	 */
	public static function register() {
		if ( wp_script_is( 'cinerent-frontend', 'registered' ) ) {
			return;
		}

		list( $url, $ver ) = self::asset( 'css/frontend.css' );
		wp_register_style( 'cinerent-frontend', $url, array(), $ver );

		list( $url, $ver ) = self::asset( 'js/frontend.js' );
		wp_register_script(
			'cinerent-frontend',
			$url,
			array(),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		list( $url, $ver ) = self::asset( 'js/altcha.js' );
		wp_register_script( 'cinerent-altcha', $url, array(), $ver, array( 'in_footer' => true ) );

		list( $url, $ver ) = self::asset( 'js/anfrage.js' );
		wp_register_script( 'cinerent-anfrage', $url, array( 'cinerent-frontend', 'cinerent-altcha' ), $ver, array( 'in_footer' => true ) );

		wp_add_inline_script( 'cinerent-frontend', 'window.cinerentConfig = ' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/**
	 * Konfiguration für JavaScript. Enthält keine Geheimnisse.
	 *
	 * @return array<string, mixed>
	 */
	private static function config() {
		return array(
			'rest'           => esc_url_raw( rest_url( Rest::NAMESPACE_V1 . '/' ) ),
			'cookie'         => Period::COOKIE,
			'cookieTage'     => 30,
			'storageKey'     => 'cinerent_anfrageliste',
			'heute'          => wp_date( 'Y-m-d' ),
			'verfuegbarkeit' => (bool) Plugin::instance()->source()->supports_availability(),
			'i18n'           => array(
				'frei'             => __( 'Frei', 'cinerent-shop' ),
				'belegt'           => __( 'Belegt', 'cinerent-shop' ),
				'anfrage'          => __( 'Auf Anfrage', 'cinerent-shop' ),
				'tag'              => __( 'Miettag', 'cinerent-shop' ),
				'tage'             => __( 'Miettage', 'cinerent-shop' ),
				'keinZeitraum'     => __( 'Kein Zeitraum gewählt', 'cinerent-shop' ),
				'ungueltig'        => __( 'Die Rückgabe muss nach der Abholung liegen.', 'cinerent-shop' ),
				'hinzugefuegt'     => __( 'Auf der Anfrageliste', 'cinerent-shop' ),
				'entfernen'        => __( 'Entfernen', 'cinerent-shop' ),
				'leer'             => __( 'Ihre Anfrageliste ist noch leer.', 'cinerent-shop' ),
				'menge'            => __( 'Menge', 'cinerent-shop' ),
				'summe'            => __( 'Richtwert gesamt', 'cinerent-shop' ),
				'richtwertHinweis' => __( 'Unverbindlicher Richtwert für den gewählten Zeitraum. Das verbindliche Angebot folgt nach Prüfung der Verfügbarkeit.', 'cinerent-shop' ),
				'zeitraumWaehlen'  => __( 'Bitte zuerst einen Mietzeitraum wählen.', 'cinerent-shop' ),
				'kalenderAnfrage'  => __( 'Die Verfügbarkeit nennen wir Ihnen auf Anfrage.', 'cinerent-shop' ),
				'vorMonat'         => __( 'Vorheriger Monat', 'cinerent-shop' ),
				'naechsterMonat'   => __( 'Nächster Monat', 'cinerent-shop' ),
				'wochentage'       => array( __( 'Mo', 'cinerent-shop' ), __( 'Di', 'cinerent-shop' ), __( 'Mi', 'cinerent-shop' ), __( 'Do', 'cinerent-shop' ), __( 'Fr', 'cinerent-shop' ), __( 'Sa', 'cinerent-shop' ), __( 'So', 'cinerent-shop' ) ),
				'senden'           => __( 'Wird gesendet …', 'cinerent-shop' ),
				'pruefen'          => __( 'Spamschutz wird berechnet …', 'cinerent-shop' ),
				'fehler'           => __( 'Es ist ein Fehler aufgetreten. Bitte erneut versuchen.', 'cinerent-shop' ),
				'abholung'         => __( 'Abholung', 'cinerent-shop' ),
				'rueckgabe'        => __( 'Rückgabe', 'cinerent-shop' ),
				'abbrechen'        => __( 'Abbrechen', 'cinerent-shop' ),
				'uebernehmen'      => __( 'Übernehmen', 'cinerent-shop' ),
			),
		);
	}

	/**
	 * Admin-Assets nur auf Equipment- und Einstellungsseiten.
	 *
	 * @param string $hook Seiten-Hook.
	 */
	public static function admin( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ( Post_Type::NAME !== $screen->post_type ) ) {
			return;
		}
		list( $url, $ver ) = self::asset( 'css/admin.css' );
		wp_enqueue_style( 'cinerent-admin', $url, array(), $ver );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			list( $url, $ver ) = self::asset( 'js/admin.js' );
			wp_enqueue_script( 'cinerent-admin', $url, array(), $ver, array( 'in_footer' => true ) );
		}
	}
}
