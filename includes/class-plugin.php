<?php
/**
 * Zentrale Plugin-Klasse.
 *
 * @package CineRent\Shop
 */

namespace CineRent\Shop;

use CineRent\Shop\Data\Data_Source;
use CineRent\Shop\Data\Local_Json_Source;
use CineRent\Shop\Data\Storybase_Api_Source;

defined( 'ABSPATH' ) || exit;

/**
 * Verdrahtet alle Bestandteile des Plugins.
 */
final class Plugin {

	/**
	 * Name des Cron-Hooks für den stündlichen Sync.
	 */
	const CRON_HOOK = 'cinerent_shop_sync';

	/**
	 * Singleton-Instanz.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Aktive Datenquelle (lazy).
	 *
	 * @var Data_Source|null
	 */
	private $source = null;

	/**
	 * Liefert die Instanz und startet das Plugin beim ersten Aufruf.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Registriert alle Hooks.
	 */
	private function boot() {
		load_plugin_textdomain( 'cinerent-shop', false, dirname( plugin_basename( CINERENT_SHOP_FILE ) ) . '/languages' );

		Post_Type::init();
		Settings::init();
		Sync::init();
		Readonly_Guard::init();
		Rest::init();
		Assets::init();
		Fonts::init();

		// Elementor-Integration erst, wenn Elementor geladen ist.
		add_action( 'elementor/loaded', array( Elementor\Integration::class, 'init' ) );
		if ( did_action( 'elementor/loaded' ) ) {
			Elementor\Integration::init();
		}

		add_action( 'admin_notices', array( $this, 'maybe_notice_missing_elementor' ) );
	}

	/**
	 * Liefert die in den Einstellungen gewählte Datenquelle.
	 *
	 * @return Data_Source
	 */
	public function source() {
		if ( null === $this->source ) {
			$source = 'storybase' === Settings::get( 'datenquelle' )
				? new Storybase_Api_Source( Settings::get( 'storybase_url' ), Settings::storybase_key() )
				: new Local_Json_Source( CINERENT_SHOP_DIR . 'data/katalog.json' );

			/**
			 * Erlaubt das Austauschen der Datenquelle, z. B. für Tests.
			 *
			 * @param Data_Source $source Aktive Datenquelle.
			 */
			$this->source = apply_filters( 'cinerent_shop_data_source', $source );
		}
		return $this->source;
	}

	/**
	 * Setzt die gecachte Datenquelle zurück (nach Änderung der Einstellungen).
	 */
	public function reset_source() {
		$this->source = null;
	}

	/**
	 * Hinweis, wenn Elementor Pro fehlt.
	 */
	public function maybe_notice_missing_elementor() {
		if ( ! current_user_can( 'activate_plugins' ) || defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ( 'plugins' !== $screen->id && Post_Type::NAME !== $screen->post_type ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'CineRent Shop: Dynamic Tags, Widgets und der Loop-Grid-Filter benötigen Elementor Pro. Der Katalog-Sync funktioniert auch ohne.', 'cinerent-shop' )
		);
	}

	/**
	 * Aktivierung: CPT registrieren, Rewrite-Regeln erneuern, Cron planen.
	 */
	public static function activate() {
		Post_Type::register();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}

		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}

	/**
	 * Deaktivierung: Cron entfernen, Rewrite-Regeln erneuern.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules();
	}
}
