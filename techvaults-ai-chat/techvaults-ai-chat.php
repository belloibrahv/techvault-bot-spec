<?php
/**
 * Plugin Name: TechVaults AI Chat
 * Plugin URI:  https://www.techvaults.com
 * Description: Custom AI chatbot for techvaults.com — lead capture, WhatsApp handoff, knowledge-base grounded answers.
 * Version:     1.0.0
 * Author:      TechVaults Limited
 * Author URI:  https://www.techvaults.com
 * Text Domain: tva-chat
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'TVA_CHAT_VERSION',  '1.0.0' );
define( 'TVA_CHAT_PATH',     plugin_dir_path( __FILE__ ) );
define( 'TVA_CHAT_URL',      plugin_dir_url( __FILE__ ) );
define( 'TVA_CHAT_BASENAME', plugin_basename( __FILE__ ) );

// ─── Autoloader ───────────────────────────────────────────────────────────────
require_once TVA_CHAT_PATH . 'includes/Core/class-tva-autoloader.php';
TVA_Autoloader::register();

// ─── Activation / Deactivation hooks ─────────────────────────────────────────
register_activation_hook(   __FILE__, [ 'TVA_Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'TVA_Deactivator', 'deactivate' ] );

// ─── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	TVA_Plugin::get_instance()->init();
} );
