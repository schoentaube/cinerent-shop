<?php
/**
 * Spiegelt den Katalog der Datenquelle in WordPress.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

use CineRent\Shop\Data\Catalog;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Abgleich über die externe ID: anlegen, aktualisieren, fehlende auf Entwurf setzen.
 */
final class Sync {

	const STATUS_OPTION = 'cinerent_shop_last_sync';
	const LOCK          = 'cinerent_shop_sync_lock';
	const TERM_META_ID  = '_cinerent_external_id';

	/**
	 * Läuft gerade ein Sync? Der Schreibschutz lässt dann Änderungen zu.
	 *
	 * @var bool
	 */
	private static $running = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( Plugin::CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'admin_post_cinerent_sync', array( __CLASS__, 'handle_button' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
	}

	/**
	 * Plant den Cron neu, falls er verloren gegangen ist.
	 */
	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( Plugin::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', Plugin::CRON_HOOK );
		}
	}

	/**
	 * Ob gerade synchronisiert wird.
	 *
	 * @return bool
	 */
	public static function is_running() {
		return self::$running;
	}

	/**
	 * Button "Jetzt synchronisieren".
	 */
	public static function handle_button() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'cinerent-shop' ), 403 );
		}
		check_admin_referer( 'cinerent_sync' );

		self::run();

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'       => Post_Type::NAME,
					'page'            => Settings::PAGE_SLUG,
					'cinerent-synced' => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Ergebnis nach manuellem Sync anzeigen.
	 */
	public static function notice() {
		// Nur Anzeige eines Hinweises, keine Verarbeitung von Eingaben.
		if ( empty( $_GET['cinerent-synced'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$status = get_option( self::STATUS_OPTION );
		if ( ! is_array( $status ) ) {
			return;
		}
		$class = empty( $status['fehler'] ) ? 'notice-success' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( self::describe( $status ) ) . '</p>';
		if ( ! empty( $status['fehler'] ) ) {
			echo '<ul>';
			foreach ( array_slice( (array) $status['fehler'], 0, 10 ) as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * Kurzbeschreibung eines Sync-Ergebnisses.
	 *
	 * @param array<string, mixed> $status Ergebnis.
	 * @return string
	 */
	public static function describe( array $status ) {
		if ( ! empty( $status['abbruch'] ) ) {
			/* translators: %s: Fehlermeldung. */
			return sprintf( __( 'Abgebrochen: %s', 'cinerent-shop' ), $status['abbruch'] );
		}
		return sprintf(
			/* translators: 1: neu, 2: aktualisiert, 3: unverändert, 4: auf Entwurf gesetzt, 5: Fehler. */
			__( '%1$d neu, %2$d aktualisiert, %3$d unverändert, %4$d auf Entwurf gesetzt, %5$d Fehler', 'cinerent-shop' ),
			(int) $status['erstellt'],
			(int) $status['aktualisiert'],
			(int) $status['unveraendert'],
			(int) $status['entwurf'],
			count( (array) $status['fehler'] )
		);
	}

	/**
	 * Führt den Sync aus.
	 *
	 * @return array<string, mixed> Ergebnis.
	 */
	public static function run() {
		$status = array(
			'zeit'         => time(),
			'quelle'       => '',
			'erstellt'     => 0,
			'aktualisiert' => 0,
			'unveraendert' => 0,
			'entwurf'      => 0,
			'fehler'       => array(),
			'abbruch'      => '',
		);

		if ( get_transient( self::LOCK ) ) {
			$status['abbruch'] = __( 'Ein Sync läuft bereits.', 'cinerent-shop' );
			return self::finish( $status, false );
		}
		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );

		$source           = Plugin::instance()->source();
		$status['quelle'] = $source->get_id();

		$categories = $source->get_categories();
		$items      = $source->get_items();

		foreach ( array( $categories, $items ) as $result ) {
			if ( is_wp_error( $result ) ) {
				$status['abbruch'] = $result->get_error_message();
				return self::finish( $status );
			}
		}
		// Schutz vor versehentlichem Leeren des Katalogs bei fehlerhafter Quelle.
		if ( empty( $items ) ) {
			$status['abbruch'] = __( 'Die Quelle lieferte keine Einträge. Es wurde nichts verändert.', 'cinerent-shop' );
			return self::finish( $status );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Bildimporte können dauern.
		}

		self::$running = true;

		$term_map = self::sync_categories( $categories, $status );
		$seen     = array();

		foreach ( $items as $item ) {
			$seen[] = $item['id'];
			$result = self::sync_item( $item, $term_map );
			if ( is_wp_error( $result ) ) {
				$status['fehler'][] = $item['id'] . ': ' . $result->get_error_message();
				continue;
			}
			++$status[ $result['ergebnis'] ];
			foreach ( $result['fehler'] as $error ) {
				$status['fehler'][] = $item['id'] . ': ' . $error;
			}
		}

		$status['entwurf'] = self::draft_missing( $seen );

		self::$running = false;
		return self::finish( $status );
	}

	/**
	 * Speichert das Ergebnis und gibt die Sperre frei.
	 *
	 * @param array<string, mixed> $status Ergebnis.
	 * @param bool                 $unlock Sperre freigeben.
	 * @return array<string, mixed>
	 */
	private static function finish( array $status, $unlock = true ) {
		if ( $unlock ) {
			delete_transient( self::LOCK );
		}
		self::$running = false;
		update_option( self::STATUS_OPTION, $status, false );

		/**
		 * Nach jedem Sync.
		 *
		 * @param array<string, mixed> $status Ergebnis.
		 */
		do_action( 'cinerent_shop_synced', $status );
		return $status;
	}

	/**
	 * Legt Kategorien an oder aktualisiert sie.
	 *
	 * @param array<int, array<string, mixed>> $categories Kategorien.
	 * @param array<string, mixed>             $status     Ergebnis (Fehler werden ergänzt).
	 * @return array<string, int> Externe ID und Slug => Term-ID.
	 */
	private static function sync_categories( array $categories, array &$status ) {
		$map = array();
		foreach ( $categories as $category ) {
			$terms   = get_terms(
				array(
					'taxonomy'   => Post_Type::TAXONOMY,
					'hide_empty' => false,
					'number'     => 1,
					'fields'     => 'ids',
					'meta_key'   => self::TERM_META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $category['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			$term_id = ( is_array( $terms ) && $terms ) ? (int) $terms[0] : 0;

			if ( ! $term_id ) {
				$by_slug = get_term_by( 'slug', $category['slug'], Post_Type::TAXONOMY );
				$term_id = $by_slug ? (int) $by_slug->term_id : 0;
			}

			$args   = array(
				'name' => $category['name'],
				'slug' => $category['slug'],
			);
			$result = $term_id
				? wp_update_term( $term_id, Post_Type::TAXONOMY, $args )
				: wp_insert_term( $category['name'], Post_Type::TAXONOMY, $args );

			if ( is_wp_error( $result ) ) {
				$status['fehler'][] = $category['id'] . ': ' . $result->get_error_message();
				continue;
			}

			$term_id = (int) $result['term_id'];
			update_term_meta( $term_id, self::TERM_META_ID, $category['id'] );
			update_term_meta( $term_id, '_cinerent_reihenfolge', (int) $category['reihenfolge'] );

			$map[ $category['id'] ]   = $term_id;
			$map[ $category['slug'] ] = $term_id;
		}
		return $map;
	}

	/**
	 * Legt ein Item an oder aktualisiert es.
	 *
	 * @param array<string, mixed> $item     Normalisiertes Item.
	 * @param array<string, int>   $term_map Kategorie-Zuordnung.
	 * @return array{ergebnis:string, fehler:string[]}|WP_Error
	 */
	private static function sync_item( array $item, array $term_map ) {
		$post_id = Post_Type::find_by_external_id( $item['id'] );

		// Lokale Bilddateien fließen mit ihrem Inhalt in den Fingerabdruck ein.
		$hash = Catalog::fingerprint( $item + array( 'bild_fingerprints' => array_map( array( Media::class, 'fingerprint' ), $item['bilder'] ) ) );

		if ( $post_id && get_post_meta( $post_id, Post_Type::META_HASH, true ) === $hash && 'publish' === get_post_status( $post_id ) ) {
			return array(
				'ergebnis' => 'unveraendert',
				'fehler'   => array(),
			);
		}

		$postarr = array(
			'post_type'    => Post_Type::NAME,
			'post_status'  => 'publish',
			'post_title'   => $item['name'],
			'post_name'    => $item['slug'],
			'post_content' => $item['beschreibung'],
			'post_excerpt' => $item['kurztext'],
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
			$ergebnis      = 'aktualisiert';
		} else {
			$result   = wp_insert_post( wp_slash( $postarr ), true );
			$ergebnis = 'erstellt';
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id = (int) $result;

		update_post_meta( $post_id, Post_Type::META_EXTERNAL_ID, $item['id'] );
		update_post_meta( $post_id, Post_Type::META_PREIS_TAG, $item['preis_tag_cent'] );
		update_post_meta( $post_id, Post_Type::META_PREIS_WOCHE, $item['preis_woche_cent'] );
		update_post_meta( $post_id, Post_Type::META_KAUTION, $item['kaution_cent'] );
		update_post_meta( $post_id, Post_Type::META_TECHNIK, $item['technische_daten'] );
		update_post_meta( $post_id, Post_Type::META_IST_KIT, $item['ist_kit'] ? 1 : 0 );
		update_post_meta( $post_id, Post_Type::META_KIT_INHALT, $item['kit_inhalt'] );
		update_post_meta( $post_id, Post_Type::META_SYNC_ZEIT, time() );

		$term_ids = isset( $term_map[ $item['kategorie'] ] ) ? array( $term_map[ $item['kategorie'] ] ) : array();
		wp_set_object_terms( $post_id, $term_ids, Post_Type::TAXONOMY );

		$media = Media::sync( $post_id, $item['bilder'], $item['name'] );

		// Bei Bildfehlern beim nächsten Lauf erneut versuchen.
		update_post_meta( $post_id, Post_Type::META_HASH, $media['errors'] ? '' : $hash );

		return array(
			'ergebnis' => $ergebnis,
			'fehler'   => $media['errors'],
		);
	}

	/**
	 * Setzt veröffentlichte Beiträge, die in der Quelle fehlen, auf Entwurf.
	 *
	 * @param string[] $seen Externe IDs aus der Quelle.
	 * @return int Anzahl.
	 */
	private static function draft_missing( array $seen ) {
		$count = 0;
		$ids   = get_posts(
			array(
				'post_type'        => Post_Type::NAME,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $post_id ) {
			$external_id = (string) get_post_meta( $post_id, Post_Type::META_EXTERNAL_ID, true );
			if ( ! in_array( $external_id, $seen, true ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				++$count;
			}
		}
		return $count;
	}
}
