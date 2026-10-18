<?php
/**
 * Deinstallation: entfernt Einstellungen, Sync-Status und Cron.
 *
 * Equipment-Beiträge, Kategorien und Bilder bleiben erhalten, damit Seiten,
 * SEO-Daten und Mediathek bei einer Neuinstallation nicht verloren gehen.
 *
 * @package CineRent\Shop
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cinerent_shop_settings' );
delete_option( 'cinerent_shop_last_sync' );
delete_transient( 'cinerent_shop_sync_lock' );
wp_clear_scheduled_hook( 'cinerent_shop_sync' );
