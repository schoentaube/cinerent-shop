<?php
/**
 * Übernahme von Katalogbildern in die Mediathek.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Importiert Bilder, verwendet unveränderte wieder und ersetzt geänderte.
 */
final class Media {

	const META_SOURCE      = '_cinerent_bild_quelle';
	const META_FINGERPRINT = '_cinerent_bild_fingerprint';

	/**
	 * Fingerabdruck einer Bildquelle: bei lokalen Dateien der Inhalt, sonst die URL.
	 *
	 * @param string $source URL oder Pfad relativ zum Plugin.
	 * @return string
	 */
	public static function fingerprint( $source ) {
		$local = self::local_path( $source );
		if ( $local ) {
			return (string) md5_file( $local );
		}
		return md5( $source );
	}

	/**
	 * Löst einen relativen Pfad im Plugin-Ordner auf. Nur Bilddateien innerhalb des Plugins.
	 *
	 * @param string $source Quelle.
	 * @return string|null Absoluter Pfad oder null, wenn es eine URL ist.
	 */
	private static function local_path( $source ) {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $source ) ) {
			return null;
		}
		$base = realpath( CINERENT_SHOP_DIR );
		$path = realpath( CINERENT_SHOP_DIR . ltrim( $source, '/' ) );
		if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) ) {
			return null;
		}
		if ( ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', $path ) ) {
			return null;
		}
		return $path;
	}

	/**
	 * Gleicht die Bilder eines Beitrags mit der Quelle ab.
	 *
	 * @param int      $post_id Beitrag.
	 * @param string[] $sources Bildquellen in Reihenfolge (erstes = Beitragsbild).
	 * @param string   $alt     Alternativtext.
	 * @return array{ids:int[], errors:string[]}
	 */
	public static function sync( $post_id, array $sources, $alt ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$existing = self::attachments_of( $post_id );
		$ids      = array();
		$errors   = array();

		foreach ( $sources as $source ) {
			$fingerprint = self::fingerprint( $source );
			$found       = 0;
			foreach ( $existing as $attachment_id => $meta ) {
				if ( $meta['source'] === $source && $meta['fingerprint'] === $fingerprint ) {
					$found = $attachment_id;
					break;
				}
			}

			if ( ! $found ) {
				$found = self::import( $post_id, $source, $alt );
				if ( is_wp_error( $found ) ) {
					$errors[] = $source . ': ' . $found->get_error_message();
					continue;
				}
				update_post_meta( $found, self::META_SOURCE, $source );
				update_post_meta( $found, self::META_FINGERPRINT, $fingerprint );
			}
			$ids[] = (int) $found;
		}

		// Nicht mehr verwendete, vom Plugin importierte Bilder entfernen (Ersetzen).
		foreach ( array_keys( $existing ) as $attachment_id ) {
			if ( ! in_array( $attachment_id, $ids, true ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}

		if ( $ids ) {
			set_post_thumbnail( $post_id, $ids[0] );
		} else {
			delete_post_thumbnail( $post_id );
		}
		update_post_meta( $post_id, Post_Type::META_GALERIE, $ids );

		return array(
			'ids'    => $ids,
			'errors' => $errors,
		);
	}

	/**
	 * Vom Plugin importierte Anhänge eines Beitrags.
	 *
	 * @param int $post_id Beitrag.
	 * @return array<int, array{source:string, fingerprint:string}>
	 */
	private static function attachments_of( $post_id ) {
		$out   = array();
		$posts = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'post_parent'      => $post_id,
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( $posts as $attachment_id ) {
			$out[ (int) $attachment_id ] = array(
				'source'      => (string) get_post_meta( $attachment_id, self::META_SOURCE, true ),
				'fingerprint' => (string) get_post_meta( $attachment_id, self::META_FINGERPRINT, true ),
			);
		}
		return $out;
	}

	/**
	 * Importiert ein Bild in die Mediathek.
	 *
	 * @param int    $post_id Beitrag.
	 * @param string $source  Quelle.
	 * @param string $alt     Alternativtext.
	 * @return int|WP_Error Anhang-ID.
	 */
	private static function import( $post_id, $source, $alt ) {
		$local = self::local_path( $source );

		if ( $local ) {
			$tmp = wp_tempnam( basename( $local ) );
			if ( ! $tmp || ! copy( $local, $tmp ) ) {
				return new WP_Error( 'cinerent_copy', __( 'Bild konnte nicht kopiert werden.', 'cinerent-shop' ) );
			}
			$name = basename( $local );
		} else {
			if ( ! wp_http_validate_url( $source ) ) {
				return new WP_Error( 'cinerent_url', __( 'Ungültige oder nicht erlaubte Bild-URL.', 'cinerent-shop' ) );
			}
			$tmp = download_url( $source, 30 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$name = basename( (string) wp_parse_url( $source, PHP_URL_PATH ) );
		}

		$file = array(
			'name'     => sanitize_file_name( $name ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id, $alt );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		return (int) $attachment_id;
	}
}
