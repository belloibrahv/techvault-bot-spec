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
		wp_enqueue_style(
			'tva-chat-widget',
			TVC_URL . 'assets/css/tva-chat-widget.css',
			[],
			TVC_VERSION
		);

		wp_enqueue_script(
			'tva-chat-widget',
			TVC_URL . 'assets/js/tva-chat-widget.js',
			[],
			TVC_VERSION,
			true // Load in footer — never block page render.
		);

		// Hand PHP config to JS safely via wp_localize_script.
		// Rule: no secrets, no API keys, ever.
		wp_localize_script( 'tva-chat-widget', 'tvaChatConfig', [
			'restUrl'  => esc_url_raw( rest_url( 'tva/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'whatsapp' => Config::whatsappNumber(),
			'greeting' => Config::greeting(),
		] );
	}
}
