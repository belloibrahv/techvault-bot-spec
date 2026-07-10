<?php
/**
 * Runs when an admin clicks "Delete" on the Plugins screen.
 * WP_UNINSTALL_PLUGIN must be defined — this guard is mandatory.
 *
 * We use Config::allOptionKeys() so the list of options to clean up
 * is maintained in one place and automatically stays current.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Bootstrap the autoloader so we can use Config.
require_once plugin_dir_path( __FILE__ ) . 'includes/Core/Autoloader.php';
\TechVaults\Chat\Core\Autoloader::register();

// ── Define constants (not set because this runs outside normal plugin load) ───
if ( ! defined( 'TVC_PATH' ) ) {
	define( 'TVC_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'TVC_BASENAME' ) ) {
	define( 'TVC_BASENAME', plugin_basename( __FILE__ ) );
}

global $wpdb;

// ── Drop DB tables ────────────────────────────────────────────────────────────
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tva_leads" );         // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tva_chat_events" );   // phpcs:ignore

// ── Delete options ────────────────────────────────────────────────────────────
foreach ( \TechVaults\Chat\Core\Config::allOptionKeys() as $key ) {
	delete_option( $key );
}

// ── Delete all Knowledge Base CPT posts and their meta ───────────────────────
$kbPosts = get_posts( [
	'post_type'   => 'tva_kb_entry',
	'post_status' => 'any',
	'numberposts' => -1,
	'fields'      => 'ids',
] );

foreach ( $kbPosts as $postId ) {
	wp_delete_post( $postId, true );
}
