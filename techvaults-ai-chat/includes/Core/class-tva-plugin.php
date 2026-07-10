<?php
/**
 * Main plugin bootstrap class.
 *
 * Singleton. Wires up every subsystem and owns the
 * script/style enqueue for the front-end widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Plugin {

	private static ?TVA_Plugin $instance = null;

	private function __construct() {}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		$this->load_textdomain();
		$this->register_subsystems();
		$this->register_hooks();
	}

	// ─── Text domain ──────────────────────────────────────────────────────────

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'tva-chat',
			false,
			dirname( TVA_CHAT_BASENAME ) . '/languages'
		);
	}

	// ─── Subsystem initialisation ─────────────────────────────────────────────

	private function register_subsystems(): void {
		TVA_Knowledge_Base::init();
		TVA_REST_API::init();
		TVA_Admin::init();
	}

	// ─── WordPress action hooks ───────────────────────────────────────────────

	private function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_widget_assets' ] );
	}

	/**
	 * Enqueue the chat widget CSS and JS on the public site only.
	 * All PHP-to-JS config is passed via wp_localize_script — never echoed directly.
	 */
	public function enqueue_widget_assets(): void {
		wp_enqueue_style(
			'tva-chat-widget',
			TVA_CHAT_URL . 'assets/css/tva-chat-widget.css',
			[],
			TVA_CHAT_VERSION
		);

		wp_enqueue_script(
			'tva-chat-widget',
			TVA_CHAT_URL . 'assets/js/tva-chat-widget.js',
			[],
			TVA_CHAT_VERSION,
			true // Load in footer.
		);

		// Pass config to JS safely. No secrets, no API keys — ever.
		wp_localize_script( 'tva-chat-widget', 'tvaChatConfig', [
			'restUrl'  => esc_url_raw( rest_url( 'tva/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'whatsapp' => get_option( 'tva_chat_whatsapp_number', '2348034048178' ),
			'greeting' => get_option(
				'tva_chat_greeting',
				"Hi, I'm the TechVaults assistant. Ask me about our services, pricing, or past projects."
			),
		] );
	}
}
