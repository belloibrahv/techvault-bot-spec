<?php
/**
 * Plugin Name:       TechVaults AI Chat
 * Plugin URI:        https://www.techvaults.com
 * Description:       Custom AI chatbot for techvaults.com — knowledge-base grounded answers, lead capture, and WhatsApp handoff.
 * Version:           1.0.1
 * Author:            TechVaults Limited
 * Author URI:        https://www.techvaults.com
 * Text Domain:       tva-chat
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access — must be loaded through WordPress.
}

// ── PHP version gate ──────────────────────────────────────────────────────────
// Deactivate gracefully rather than throwing a fatal on old PHP.
if ( version_compare( PHP_VERSION, '8.0.0', '<' ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p><strong>TechVaults AI Chat</strong> requires PHP 8.0 or higher. '
			. 'You are running PHP ' . esc_html( PHP_VERSION ) . '. Please upgrade.</p></div>';
	} );
	return;
}

// ── Plugin constants ──────────────────────────────────────────────────────────
define( 'TVC_VERSION',  '1.0.1' );
define( 'TVC_PATH',     plugin_dir_path( __FILE__ ) );
define( 'TVC_URL',      plugin_dir_url( __FILE__ ) );
define( 'TVC_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoloader ────────────────────────────────────────────────────────────────
require_once TVC_PATH . 'includes/Core/Autoloader.php';
\TechVaults\Chat\Core\Autoloader::register();

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook(
	__FILE__,
	[ \TechVaults\Chat\Database\Activator::class, 'activate' ]
);

register_deactivation_hook(
	__FILE__,
	[ \TechVaults\Chat\Database\Deactivator::class, 'deactivate' ]
);

// ── Security headers on REST responses ───────────────────────────────────────
// Prevent REST API responses from being framed or sniffed.
add_filter( 'rest_pre_serve_request', static function ( bool $served ): bool {
	if ( ! headers_sent() ) {
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: DENY' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
	return $served;
} );

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', static function () {
	\TechVaults\Chat\Core\Plugin::getInstance()->run();
} );
