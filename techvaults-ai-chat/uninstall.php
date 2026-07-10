<?php
/**
 * Runs when an admin clicks "Delete" on the plugin in wp-admin.
 * Only executes if WP_UNINSTALL_PLUGIN is defined — this guard is required.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove database tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tva_leads" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tva_chat_events" );

// Remove all plugin options.
$options = [
	'tva_chat_llm_api_key',
	'tva_chat_llm_model',
	'tva_chat_llm_provider',
	'tva_chat_whatsapp_number',
	'tva_chat_greeting',
	'tva_chat_notify_email',
	'tva_chat_db_version',
];
foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove all Knowledge Base posts and their meta.
$kb_posts = get_posts( [
	'post_type'   => 'tva_kb_entry',
	'post_status' => 'any',
	'numberposts' => -1,
	'fields'      => 'ids',
] );
foreach ( $kb_posts as $post_id ) {
	wp_delete_post( $post_id, true );
}
