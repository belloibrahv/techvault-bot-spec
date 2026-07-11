<?php
/**
 * Runs when an admin clicks "Delete" on the Plugins screen.
 * WP_UNINSTALL_PLUGIN must be defined — WordPress sets this automatically.
 *
 * Security:
 *  - WP_UNINSTALL_PLUGIN guard is the first and only check needed here.
 *  - No user input is processed — all values come from WordPress core globals.
 *  - On multisite, each site's own tables are cleaned up individually.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Must be called through WordPress uninstall flow.
}

// Bootstrap the autoloader so we can use Config.
require_once plugin_dir_path( __FILE__ ) . 'includes/Core/Autoloader.php';
\TechVaults\Chat\Core\Autoloader::register();

// Define constants that aren't available during uninstall.
if ( ! defined( 'TVC_PATH' ) ) {
	define( 'TVC_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'TVC_BASENAME' ) ) {
	define( 'TVC_BASENAME', plugin_basename( __FILE__ ) );
}

global $wpdb;

/**
 * Perform cleanup for a single site (by $wpdb prefix).
 * Called once on single-site, or per-site on multisite.
 */
function tva_uninstall_site(): void {
	global $wpdb;

	// ── Drop DB tables ────────────────────────────────────────────────────────
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'tva_leads' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'tva_chat_events' );
	// phpcs:enable

	// ── Delete plugin options ─────────────────────────────────────────────────
	foreach ( \TechVaults\Chat\Core\Config::allOptionKeys() as $key ) {
		delete_option( $key );
	}

	// ── Delete all seeder transients for all users ────────────────────────────
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_tva_kb_seeded_' ) . '%'
		)
	);

	// ── Delete all Knowledge Base CPT posts and their meta ───────────────────
	$postIds = get_posts( [
		'post_type'      => 'tva_kb_entry',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	foreach ( $postIds as $postId ) {
		wp_delete_post( (int) $postId, true ); // force-delete (no trash)
	}
}

// ── Multisite: clean up every site in the network ────────────────────────────
if ( is_multisite() ) {
	$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
	foreach ( $sites as $siteId ) {
		switch_to_blog( (int) $siteId );
		tva_uninstall_site();
		restore_current_blog();
	}
} else {
	tva_uninstall_site();
}
