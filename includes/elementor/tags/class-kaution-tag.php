<?php
/**
 * Dynamic Tag: Kaution.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Kaution des aktuellen Equipments.
 */
class Kaution_Tag extends Base_Price_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-kaution';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Kaution', 'cinerent-shop' );
	}

	/**
	 * Feld.
	 *
	 * @return string
	 */
	protected function field() {
		return 'kaution_cent';
	}
}
