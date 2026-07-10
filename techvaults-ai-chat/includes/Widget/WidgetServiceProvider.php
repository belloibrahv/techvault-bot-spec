<?php
/**
 * Widget service provider — enqueues the chat widget on the public site.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Widget;

use TechVaults\Chat\Core\Config;
use TechVaults\Chat\Core\ServiceProviderInterface;

class WidgetServiceProvider implements ServiceProviderInterface {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function boot(): void {}

	public function enqueue(): void {
		// ── Google Fonts: Google Sans + Material Symbols Rounded ──────────────
		// Single request via the combined API — both families, display=swap for
		// performance, preconnect hints added below.
		wp_enqueue_style(
			'tva-google-fonts',
			'https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-50..200&display=swap',
			[],
			null // External CDN — no version hash.
		);

		// Preconnect hints so the font CDN handshake happens earlier.
		add_action( 'wp_head', static function () {
			echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
			echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		}, 1 );

		// ── Widget CSS (depends on Google Fonts) ──────────────────────────────
		wp_enqueue_style(
			'tva-chat-widget',
			TVC_URL . 'assets/css/tva-chat-widget.css',
			[ 'tva-google-fonts' ],
			TVC_VERSION
		);

		// ── Widget JS ─────────────────────────────────────────────────────────
		wp_enqueue_script(
			'tva-chat-widget',
			TVC_URL . 'assets/js/tva-chat-widget.js',
			[],
			TVC_VERSION,
			true
		);

		// Pass PHP config to JS safely — no secrets, no API keys.
		wp_localize_script( 'tva-chat-widget', 'tvaChatConfig', [
			'restUrl'  => esc_url_raw( rest_url( 'tva/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'whatsapp' => Config::whatsappNumber(),
			'greeting' => Config::greeting(),
		] );
	}
}
