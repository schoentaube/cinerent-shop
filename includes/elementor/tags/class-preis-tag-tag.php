<?php
/**
 * Dynamic Tag: Preis pro Tag.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Preis pro Tag des aktuellen Equipments.
 */
class Preis_Tag_Tag extends Base_Price_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-preis-tag';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Preis pro Tag', 'cinerent-shop' );
	}

	/**
	 * Feld.
	 *
	 * @return string
	 */
	protected function field() {
		return 'preis_tag_cent';
	}
}
