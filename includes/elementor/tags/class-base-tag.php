<?php
/**
 * Basis für alle CineRent-Dynamic-Tags.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor\Tags;

use CineRent\Shop\Elementor\Integration;
use CineRent\Shop\Post_Type;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Liest die Daten des aktuellen Equipment-Beitrags. get_the_ID() liefert auch
 * im Loop Grid den jeweiligen Eintrag.
 */
abstract class Base_Tag extends Tag {

	/**
	 * Gruppe "CineRent".
	 *
	 * @return string
	 */
	public function get_group() {
		return Integration::GROUP;
	}

	/**
	 * Kategorie: Text.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	/**
	 * Daten des aktuellen Equipment-Beitrags.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function item() {
		return Post_Type::get_item();
	}
}
