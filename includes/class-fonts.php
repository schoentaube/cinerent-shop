<?php
/**
 * Mitgelieferte Schriften und Elementor-Schriftliste.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Schibsted Grotesk und IBM Plex Mono (SIL Open Font License) lokal aus dem Plugin.
 */
final class Fonts {

	const GROUP = 'cinerent';

	/**
	 * Schriftfamilien.
	 *
	 * @var string[]
	 */
	const FAMILIES = array( 'Schibsted Grotesk', 'IBM Plex Mono' );

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( Settings::get( 'google_fonts_blockieren' ) ) {
			add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
		}

		if ( ! Settings::get( 'schriften_laden' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'enqueue' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue' ) );

		add_filter( 'elementor/fonts/groups', array( __CLASS__, 'groups' ) );
		add_filter( 'elementor/fonts/additional_fonts', array( __CLASS__, 'fonts' ) );
	}

	/**
	 * Lädt die @font-face-Regeln.
	 */
	public static function enqueue() {
		$file = CINERENT_SHOP_DIR . 'assets/css/fonts.css';
		wp_enqueue_style( 'cinerent-fonts', CINERENT_SHOP_URL . 'assets/css/fonts.css', array(), CINERENT_SHOP_VERSION . '.' . filemtime( $file ) );
	}

	/**
	 * Eigene Gruppe in der Elementor-Schriftauswahl.
	 *
	 * @param array<string, string> $groups Gruppen.
	 * @return array<string, string>
	 */
	public static function groups( $groups ) {
		return array( self::GROUP => __( 'CineRent (lokal)', 'cinerent-shop' ) ) + $groups;
	}

	/**
	 * Schriften in der Gruppe. Elementor lädt für diesen Typ nichts von Google.
	 *
	 * @param array<string, string> $fonts Schriften.
	 * @return array<string, string>
	 */
	public static function fonts( $fonts ) {
		foreach ( self::FAMILIES as $family ) {
			$fonts[ $family ] = self::GROUP;
		}
		return $fonts;
	}
}
