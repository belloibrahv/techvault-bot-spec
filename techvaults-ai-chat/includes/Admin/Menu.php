<?php
/**
 * Admin menu registration.
 *
 * Single responsibility: register pages in wp-admin and map them to renderers.
 * Page content lives in the Settings and LeadsPage classes.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Admin;

class Menu {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'addPages' ] );
	}

	public function addPages(): void {
		add_menu_page(
			__( 'TechVaults Chat', 'tva-chat' ),
			__( 'TechVaults Chat', 'tva-chat' ),
			'manage_options',
			'tva-chat-settings',
			[ new Settings(), 'render' ],
			'dashicons-format-chat',
			58
		);

		// First submenu item shares the parent slug — becomes the "Settings" tab.
		add_submenu_page(
			'tva-chat-settings',
			__( 'Settings', 'tva-chat' ),
			__( 'Settings', 'tva-chat' ),
			'manage_options',
			'tva-chat-settings',
			[ new Settings(), 'render' ]
		);

		add_submenu_page(
			'tva-chat-settings',
			__( 'Leads', 'tva-chat' ),
			__( 'Leads', 'tva-chat' ),
			'manage_options',
			'tva-chat-leads',
			[ new LeadsPage(), 'render' ]
		);
	}
}
