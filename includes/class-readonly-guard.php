<?php
/**
 * Schreibschutz für gespiegelte Equipment-Beiträge.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Die Quelle ist führend: Inhalte lassen sich im Backend nicht ändern.
 * Meta-Boxen von Yoast SEO und Rank Math bleiben unberührt und damit bearbeitbar.
 */
final class Readonly_Guard {

	/**
	 * Vom Sync verwaltete Beitragsfelder.
	 *
	 * @var string[]
	 */
	private static $locked_fields = array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status', 'post_parent', 'post_password', 'menu_order' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'protect_post_data' ), 99, 2 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'protect_delete' ), 10, 4 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'protect_meta' ), 10, 3 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'protect_meta' ), 10, 3 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'protect_meta' ), 10, 3 );

		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'notice' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . Post_Type::NAME, '__return_empty_array' );
		add_action( Post_Type::TAXONOMY . '_pre_edit_form', array( __CLASS__, 'term_notice' ) );

		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Post_Type::NAME . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Klassischer Editor statt Gutenberg, damit Felder gezielt gesperrt werden können.
	 *
	 * @param bool   $use_block_editor Block-Editor nutzen.
	 * @param string $post_type        Beitragstyp.
	 * @return bool
	 */
	public static function disable_block_editor( $use_block_editor, $post_type ) {
		return Post_Type::NAME === $post_type ? false : $use_block_editor;
	}

	/**
	 * Stellt gesperrte Felder beim Speichern wieder her.
	 *
	 * @param array<string, mixed> $data    Bereinigte Beitragsdaten.
	 * @param array<string, mixed> $postarr Rohdaten.
	 * @return array<string, mixed>
	 */
	public static function protect_post_data( $data, $postarr ) {
		if ( Post_Type::NAME !== ( $data['post_type'] ?? '' ) || Sync::is_running() || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$current = get_post( (int) $postarr['ID'] );
		if ( ! $current instanceof WP_Post || 'auto-draft' === $current->post_status ) {
			return $data;
		}
		foreach ( self::$locked_fields as $field ) {
			$data[ $field ] = wp_slash( $current->$field );
		}
		return $data;
	}

	/**
	 * Equipment kann nicht gelöscht oder in den Papierkorb gelegt werden.
	 *
	 * @param string[] $caps    Benötigte Rechte.
	 * @param string   $cap     Geprüftes Recht.
	 * @param int      $user_id Benutzer.
	 * @param mixed[]  $args    Zusatzargumente.
	 * @return string[]
	 */
	public static function protect_delete( $caps, $cap, $user_id, $args ) {
		if ( 'delete_post' === $cap && ! empty( $args[0] ) && Post_Type::NAME === get_post_type( (int) $args[0] ) && ! Sync::is_running() ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * Blockiert Änderungen an Sync-Metadaten und Beitragsbild außerhalb des Syncs.
	 *
	 * @param mixed  $check    Null = weiter.
	 * @param int    $post_id  Beitrag.
	 * @param string $meta_key Schlüssel.
	 * @return mixed
	 */
	public static function protect_meta( $check, $post_id, $meta_key ) {
		if ( Sync::is_running() ) {
			return $check;
		}
		if ( 0 !== strpos( (string) $meta_key, '_cinerent_' ) && '_thumbnail_id' !== $meta_key ) {
			return $check;
		}
		if ( Post_Type::NAME !== get_post_type( $post_id ) ) {
			return $check;
		}
		return false;
	}

	/**
	 * Ersetzt Standard-Boxen durch eine schreibgeschützte Übersicht.
	 */
	public static function meta_boxes() {
		remove_meta_box( 'postimagediv', Post_Type::NAME, 'side' );
		remove_meta_box( 'slugdiv', Post_Type::NAME, 'normal' );
		add_meta_box( 'cinerent-daten', __( 'Katalogdaten', 'cinerent-shop' ), array( __CLASS__, 'render_box' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Hinweis über dem Editor.
	 *
	 * @param WP_Post $post Beitrag.
	 */
	public static function notice( $post ) {
		if ( Post_Type::NAME !== $post->post_type ) {
			return;
		}
		printf(
			'<div class="notice notice-info inline cinerent-readonly-notice"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Wird aus Storybase gepflegt.', 'cinerent-shop' ),
			esc_html(
				sprintf(
					/* translators: %s: Name der Datenquelle. */
					__( 'Änderungen hier werden nicht gespeichert (aktuelle Quelle: %s). Bearbeitbar sind nur die SEO-Felder.', 'cinerent-shop' ),
					Plugin::instance()->source()->get_label()
				)
			)
		);
	}

	/**
	 * Schreibgeschützte Datenübersicht.
	 *
	 * @param WP_Post $post Beitrag.
	 */
	public static function render_box( $post ) {
		$item = Post_Type::get_item( $post->ID );
		if ( ! $item ) {
			return;
		}
		$kategorien = wp_list_pluck( $item['kategorien'], 'name' );
		$rows       = array(
			__( 'Externe ID', 'cinerent-shop' )      => $item['id'],
			__( 'Kategorie', 'cinerent-shop' )       => implode( ', ', $kategorien ),
			__( 'Kurztext', 'cinerent-shop' )        => $item['kurztext'],
			__( 'Preis pro Tag', 'cinerent-shop' )   => Format::price( $item['preis_tag_cent'] ),
			__( 'Preis pro Woche', 'cinerent-shop' ) => Format::price( $item['preis_woche_cent'] ),
			__( 'Kaution', 'cinerent-shop' )         => Format::price( $item['kaution_cent'] ),
			__( 'Kit', 'cinerent-shop' )             => $item['ist_kit'] ? __( 'Ja', 'cinerent-shop' ) : __( 'Nein', 'cinerent-shop' ),
		);
		echo '<fieldset disabled class="cinerent-readonly"><table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			printf( '<tr><th scope="row">%s</th><td><input type="text" class="large-text" readonly value="%s"></td></tr>', esc_html( $label ), esc_attr( $value ) );
		}
		echo '<tr><th scope="row">' . esc_html__( 'Beschreibung', 'cinerent-shop' ) . '</th><td><div class="cinerent-readonly__content">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div></td></tr>';
		if ( $item['technische_daten'] ) {
			echo '<tr><th scope="row">' . esc_html__( 'Technische Daten', 'cinerent-shop' ) . '</th><td>' . wp_kses_post( Format::spec_list( $item['technische_daten'] ) ) . '</td></tr>';
		}
		if ( $item['kit_inhalt'] ) {
			echo '<tr><th scope="row">' . esc_html__( 'Kit-Inhalt', 'cinerent-shop' ) . '</th><td>' . wp_kses_post( Format::kit_list( $item['kit_inhalt'] ) ) . '</td></tr>';
		}
		$galerie = get_post_meta( $post->ID, Post_Type::META_GALERIE, true );
		if ( is_array( $galerie ) && $galerie ) {
			echo '<tr><th scope="row">' . esc_html__( 'Bilder', 'cinerent-shop' ) . '</th><td class="cinerent-readonly__bilder">';
			foreach ( $galerie as $attachment_id ) {
				echo wp_get_attachment_image( (int) $attachment_id, 'thumbnail' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></fieldset>';
	}

	/**
	 * Entfernt Schnellbearbeitung und Papierkorb aus der Liste.
	 *
	 * @param array<string, string> $actions Aktionen.
	 * @param WP_Post               $post    Beitrag.
	 * @return array<string, string>
	 */
	public static function row_actions( $actions, $post ) {
		if ( Post_Type::NAME === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'], $actions['trash'], $actions['delete'] );
		}
		return $actions;
	}

	/**
	 * Hinweis auf der Kategorie-Bearbeitungsseite.
	 */
	public static function term_notice() {
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Name und Slug werden aus Storybase gepflegt und beim nächsten Sync überschrieben. Beschreibung und SEO-Felder bleiben erhalten.', 'cinerent-shop' ) . '</p></div>';
	}

	/**
	 * Zusätzliche Spalten.
	 *
	 * @param array<string, string> $columns Spalten.
	 * @return array<string, string>
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['cinerent_id']    = __( 'Externe ID', 'cinerent-shop' );
				$new['cinerent_preis'] = __( 'Preis/Tag', 'cinerent-shop' );
			}
		}
		return $new;
	}

	/**
	 * Spalteninhalt.
	 *
	 * @param string $column  Spalte.
	 * @param int    $post_id Beitrag.
	 */
	public static function column( $column, $post_id ) {
		if ( 'cinerent_id' === $column ) {
			echo '<code>' . esc_html( (string) get_post_meta( $post_id, Post_Type::META_EXTERNAL_ID, true ) ) . '</code>';
		}
		if ( 'cinerent_preis' === $column ) {
			echo esc_html( Format::price( (int) get_post_meta( $post_id, Post_Type::META_PREIS_TAG, true ) ) );
		}
	}
}
