<?php
/**
 * Elementor-Integration.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Elementor;

use CineRent\Shop\Data\Data_Source;
use CineRent\Shop\Period;
use CineRent\Shop\Plugin;
use CineRent\Shop\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert Widget-Kategorie, Widgets, Dynamic Tags und den Loop-Grid-Filter.
 */
final class Integration {

	const GROUP    = 'cinerent';
	const QUERY_ID = 'cinerent_frei';

	/**
	 * Verhindert doppelte Registrierung.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'tags' ) );
		add_action( 'elementor/query/' . self::QUERY_ID, array( __CLASS__, 'filter_free' ) );
	}

	/**
	 * Widget-Kategorie "CineRent".
	 *
	 * @param \Elementor\Elements_Manager $manager Manager.
	 */
	public static function category( $manager ) {
		$manager->add_category(
			self::GROUP,
			array(
				'title' => __( 'CineRent', 'cinerent-shop' ),
				'icon'  => 'eicon-video-camera',
			)
		);
	}

	/**
	 * Widgets.
	 *
	 * @param \Elementor\Widgets_Manager $manager Manager.
	 */
	public static function widgets( $manager ) {
		$manager->register( new Widgets\Zeitraum_Widget() );
		$manager->register( new Widgets\Verfuegbarkeit_Widget() );
		$manager->register( new Widgets\Kalender_Widget() );
		$manager->register( new Widgets\Anfrage_Button_Widget() );
		$manager->register( new Widgets\Anfrageliste_Widget() );
		$manager->register( new Widgets\Anfrageformular_Widget() );
	}

	/**
	 * Dynamic Tags in der Gruppe "CineRent".
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $manager Manager.
	 */
	public static function tags( $manager ) {
		$manager->register_group( self::GROUP, array( 'title' => __( 'CineRent', 'cinerent-shop' ) ) );

		$manager->register( new Tags\Preis_Tag_Tag() );
		$manager->register( new Tags\Preis_Woche_Tag() );
		$manager->register( new Tags\Kaution_Tag() );
		$manager->register( new Tags\Kurztext_Tag() );
		$manager->register( new Tags\Technische_Daten_Tag() );
		$manager->register( new Tags\Kit_Inhalt_Tag() );
		$manager->register( new Tags\Ist_Kit_Tag() );
		$manager->register( new Tags\Kategorie_Tag() );
		$manager->register( new Tags\Galerie_Tag() );
	}

	/**
	 * Query-ID "cinerent_frei": Bei gewähltem Zeitraum nur freie Geräte.
	 * Ohne Verfügbarkeitsdaten (JSON-Phase) bleibt die Abfrage unverändert.
	 *
	 * @param \WP_Query $query Abfrage des Loop Grids.
	 */
	public static function filter_free( $query ) {
		$period = Period::current();
		$source = Plugin::instance()->source();
		if ( ! $period || ! $source->supports_availability() ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'        => Post_Type::NAME,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		$map   = array();
		foreach ( $posts as $post_id ) {
			$external_id = (string) get_post_meta( $post_id, Post_Type::META_EXTERNAL_ID, true );
			if ( '' !== $external_id ) {
				$map[ $external_id ] = (int) $post_id;
			}
		}

		$status = $source->get_availability( array_keys( $map ), $period['von'], $period['bis'] );
		$free   = array();
		foreach ( $status as $external_id => $value ) {
			if ( Data_Source::FREI === $value && isset( $map[ $external_id ] ) ) {
				$free[] = $map[ $external_id ];
			}
		}

		$existing = (array) $query->get( 'post__in' );
		if ( $existing ) {
			$free = array_values( array_intersect( $free, array_map( 'intval', $existing ) ) );
		}
		$query->set( 'post__in', $free ? $free : array( 0 ) );
	}
}
