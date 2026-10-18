<?php
/**
 * Schnittstelle für Katalog-Datenquellen.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Jede Datenquelle liefert Kategorien und Items im normalisierten Format
 * (siehe Catalog::normalize_item()) sowie Live-Verfügbarkeit.
 *
 * Verfügbarkeitsstatus: Data_Source::FREI, Data_Source::BELEGT, Data_Source::ANFRAGE.
 */
interface Data_Source {

	const FREI    = 'frei';
	const BELEGT  = 'belegt';
	const ANFRAGE = 'anfrage';

	/**
	 * Technischer Schlüssel der Quelle, z. B. "local_json".
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Lesbarer Name der Quelle.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Alle Kategorien.
	 *
	 * @return array<int, array{id:string, slug:string, name:string, reihenfolge:int}>|\WP_Error
	 */
	public function get_categories();

	/**
	 * Alle Items, normalisiert.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function get_items();

	/**
	 * Ob die Quelle echte Verfügbarkeit liefert. Ohne bleibt alles "auf Anfrage".
	 *
	 * @return bool
	 */
	public function supports_availability();

	/**
	 * Live-Verfügbarkeit mehrerer Items für einen Zeitraum.
	 *
	 * @param string[] $external_ids Externe IDs.
	 * @param string   $von          Abholung (Y-m-d).
	 * @param string   $bis          Rückgabe (Y-m-d).
	 * @return array<string, string> Externe ID => Status.
	 */
	public function get_availability( array $external_ids, $von, $bis );

	/**
	 * Belegte Tage eines Items in einem Monat.
	 *
	 * @param string $external_id Externe ID.
	 * @param string $monat       Monat (Y-m).
	 * @return array{status:string, belegt:string[]} Status "anfrage" oder "ok", belegte Tage als Y-m-d.
	 */
	public function get_calendar( $external_id, $monat );

	/**
	 * Ob die Quelle Anfragen selbst entgegennimmt. Sonst versendet das Plugin per wp_mail.
	 *
	 * @return bool
	 */
	public function supports_inquiries();

	/**
	 * Übergibt eine Anfrage an die Quelle.
	 *
	 * @param array<string, mixed> $inquiry Bereinigte Anfrage (siehe Inquiry::build()).
	 * @return true|\WP_Error
	 */
	public function submit_inquiry( array $inquiry );
}
