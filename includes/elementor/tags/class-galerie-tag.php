<?php
/**
 * Dynamic Tag: Bildergalerie.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use CineRent\Shop\Elementor\Integration;
use CineRent\Shop\Post_Type;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Alle aus der Quelle übernommenen Bilder, z. B. für das Galerie-Widget.
 */
class Galerie_Tag extends Data_Tag {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cinerent-galerie';
	}

	/**
	 * Titel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Bildergalerie', 'cinerent-shop' );
	}

	/**
	 * Gruppe.
	 *
	 * @return string
	 */
	public function get_group() {
		return Integration::GROUP;
	}

	/**
	 * Kategorie: Galerie.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Module::GALLERY_CATEGORY );
	}

	/**
	 * Wert.
	 *
	 * @param array<string, mixed> $options Optionen.
	 * @return array<int, array{id:int}>
	 */
	public function get_value( array $options = array() ) {
		$ids = get_post_meta( (int) get_the_ID(), Post_Type::META_GALERIE, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_map(
			static function ( $id ) {
				return array( 'id' => (int) $id );
			},
			$ids
		);
	}
}
