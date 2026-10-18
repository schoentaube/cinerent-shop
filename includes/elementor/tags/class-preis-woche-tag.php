<?php
/**
 * Dynamic Tag: Preis pro Woche.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Preis pro Woche des aktuellen Equipments.
 */
class Preis_Woche_Tag extends Base_Price_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-preis-woche';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Preis pro Woche', 'cinerent-shop' );
	}

	/**
	 * Feld.
	 *
	 * @return string
	 */
	protected function field() {
		return 'preis_woche_cent';
	}
}
