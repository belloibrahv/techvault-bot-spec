<?php
/**
 * Plugin Name:       TechVaults AI Chat
 * Plugin URI:        https://www.techvaults.com
 * Description:       Custom AI chatbot for techvaults.com — knowledge-base grounded answers, lead capture, and WhatsApp handoff.
 * Version:           1.0.0
 * Author:            TechVaults Limited
 * Author URI:        https://www.techvaults.com
 * Text Domain:       tva-chat
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

// ── Plugin constants ──────────────────────────────────────────────────────────
// Prefixed TVC_ (TechVaults Chat) to keep them short and unambiguous.
define( 'TVC_VERSION',  '1.0.0' );
define( 'TVC_PATH',     plugin_dir_path( __FILE__ ) );
define( 'TVC_URL',      plugin_dir_url( __FILE__ ) );
define( 'TVC_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoloader ────────────────────────────────────────────────────────────────
// The autoloader is the only file we require manually.
// Every class in the TechVaults\Chat namespace is resolved from here.
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

// ── Bootstrap ─────────────────────────────────────────────────────────────────
// plugins_loaded is the correct hook — all WordPress APIs are available,
// and other plugins that we might depend on (e.g. WooCommerce) are loaded.
add_action( 'plugins_loaded', function () {
	\TechVaults\Chat\Core\Plugin::getInstance()->run();
} );
