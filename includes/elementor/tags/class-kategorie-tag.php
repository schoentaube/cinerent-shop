<?php
/**
 * Dynamic Tag: Kategorie.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Kategorie(n) des aktuellen Equipments, optional verlinkt.
 */
class Kategorie_Tag extends Base_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-kategorie';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Kategorie', 'cinerent-shop' );
	}

	/**
	 * Steuerelemente.
	 */
	protected function register_controls() {
		$this->add_control(
			'link',
			array(
				'label'        => __( 'Mit Kategorieseite verlinken', 'cinerent-shop' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
	}

	/**
	 * Ausgabe.
	 */
	public function render() {
		$item = $this->item();
		if ( ! $item || ! $item['kategorien'] ) {
			return;
		}
		$out = array();
		foreach ( $item['kategorien'] as $term ) {
			$link  = 'yes' === $this->get_settings( 'link' ) ? get_term_link( $term ) : '';
			$out[] = ( $link && ! is_wp_error( $link ) )
				? '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>'
				: esc_html( $term->name );
		}
		echo wp_kses_post( implode( ', ', $out ) );
	}
}
