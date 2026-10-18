<?php
/**
 * Custom Post Type "equipment" und Taxonomie "equipment_kategorie".
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert CPT, Taxonomie und Meta-Felder.
 */
final class Post_Type {

	const NAME     = 'equipment';
	const TAXONOMY = 'equipment_kategorie';

	// Meta-Schlüssel.
	const META_EXTERNAL_ID = '_cinerent_external_id';
	const META_HASH        = '_cinerent_hash';
	const META_PREIS_TAG   = '_cinerent_preis_tag_cent';
	const META_PREIS_WOCHE = '_cinerent_preis_woche_cent';
	const META_KAUTION     = '_cinerent_kaution_cent';
	const META_TECHNIK     = '_cinerent_technische_daten';
	const META_IST_KIT     = '_cinerent_ist_kit';
	const META_KIT_INHALT  = '_cinerent_kit_inhalt';
	const META_GALERIE     = '_cinerent_galerie';
	const META_SYNC_ZEIT   = '_cinerent_sync_zeit';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registriert CPT und Taxonomie.
	 */
	public static function register() {
		register_post_type(
			self::NAME,
			array(
				'labels'          => array(
					'name'               => __( 'Equipment', 'cinerent-shop' ),
					'singular_name'      => __( 'Equipment', 'cinerent-shop' ),
					'menu_name'          => __( 'Equipment', 'cinerent-shop' ),
					'all_items'          => __( 'Alle Geräte', 'cinerent-shop' ),
					'edit_item'          => __( 'Equipment ansehen', 'cinerent-shop' ),
					'view_item'          => __( 'Equipment anzeigen', 'cinerent-shop' ),
					'search_items'       => __( 'Equipment durchsuchen', 'cinerent-shop' ),
					'not_found'          => __( 'Noch kein Equipment. Bitte unter Einstellungen synchronisieren.', 'cinerent-shop' ),
					'not_found_in_trash' => __( 'Kein Equipment im Papierkorb.', 'cinerent-shop' ),
				),
				'public'          => true,
				'has_archive'     => true,
				'rewrite'         => array(
					'slug'       => 'equipment',
					'with_front' => false,
				),
				'menu_icon'       => 'dashicons-video-alt2',
				'menu_position'   => 25,
				'show_in_rest'    => true,
				// Titel bleibt für Yoast/Rank Math erhalten, Inhalt wird schreibgeschützt angezeigt.
				'supports'        => array( 'title', 'thumbnail' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array(
					// Neue Geräte entstehen nur durch den Sync.
					'create_posts' => 'do_not_allow',
				),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::NAME,
			array(
				'labels'            => array(
					'name'          => __( 'Kategorien', 'cinerent-shop' ),
					'singular_name' => __( 'Kategorie', 'cinerent-shop' ),
					'menu_name'     => __( 'Kategorien', 'cinerent-shop' ),
					'edit_item'     => __( 'Kategorie bearbeiten', 'cinerent-shop' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				// Zuordnung nur per Sync, deshalb kein Auswahlfeld im Editor.
				'meta_box_cb'       => false,
				'rewrite'           => array(
					'slug'       => 'equipment-kategorie',
					'with_front' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'do_not_allow',
				),
			)
		);

		$readonly = static function () {
			return false;
		};

		$meta = array(
			self::META_EXTERNAL_ID => 'string',
			self::META_PREIS_TAG   => 'integer',
			self::META_PREIS_WOCHE => 'integer',
			self::META_KAUTION     => 'integer',
			self::META_IST_KIT     => 'boolean',
		);
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				self::NAME,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $readonly,
				)
			);
		}
	}

	/**
	 * Daten eines Equipment-Beitrags für Ausgabe und Anfrageliste.
	 *
	 * @param int|null $post_id Beitrags-ID, Standard: aktueller Beitrag (auch im Loop Grid).
	 * @return array<string, mixed>|null
	 */
	public static function get_item( $post_id = null ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		if ( ! $post_id || self::NAME !== get_post_type( $post_id ) ) {
			return null;
		}

		$technik = get_post_meta( $post_id, self::META_TECHNIK, true );
		$kit     = get_post_meta( $post_id, self::META_KIT_INHALT, true );
		$terms   = get_the_terms( $post_id, self::TAXONOMY );

		return array(
			'post_id'          => $post_id,
			'id'               => (string) get_post_meta( $post_id, self::META_EXTERNAL_ID, true ),
			'name'             => get_the_title( $post_id ),
			'url'              => get_permalink( $post_id ),
			'kurztext'         => (string) get_post_field( 'post_excerpt', $post_id ),
			'preis_tag_cent'   => (int) get_post_meta( $post_id, self::META_PREIS_TAG, true ),
			'preis_woche_cent' => (int) get_post_meta( $post_id, self::META_PREIS_WOCHE, true ),
			'kaution_cent'     => (int) get_post_meta( $post_id, self::META_KAUTION, true ),
			'technische_daten' => is_array( $technik ) ? $technik : array(),
			'ist_kit'          => (bool) get_post_meta( $post_id, self::META_IST_KIT, true ),
			'kit_inhalt'       => is_array( $kit ) ? $kit : array(),
			'kategorien'       => is_array( $terms ) ? $terms : array(),
			'bild'             => get_the_post_thumbnail_url( $post_id, 'thumbnail' ),
		);
	}

	/**
	 * Findet einen Beitrag anhand der externen ID (alle Status).
	 *
	 * @param string $external_id Externe ID.
	 * @return int Beitrags-ID oder 0.
	 */
	public static function find_by_external_id( $external_id ) {
		$ids = get_posts(
			array(
				'post_type'        => self::NAME,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'numberposts'      => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => self::META_EXTERNAL_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $external_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}
}
