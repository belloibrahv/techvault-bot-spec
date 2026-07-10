<?php
/**
 * Admin — settings page, leads list, and analytics summary in wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Admin {

	public static function init(): void {
		add_action( 'admin_menu',  [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init',  [ __CLASS__, 'register_settings' ] );
	}

	// ─── Menu ─────────────────────────────────────────────────────────────────

	public static function add_menu(): void {
		add_menu_page(
			__( 'TechVaults Chat', 'tva-chat' ),
			__( 'TechVaults Chat', 'tva-chat' ),
			'manage_options',
			'tva-chat-settings',
			[ __CLASS__, 'render_settings_page' ],
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'tva-chat-settings',
			__( 'Settings', 'tva-chat' ),
			__( 'Settings', 'tva-chat' ),
			'manage_options',
			'tva-chat-settings',      // Same slug — this becomes the first submenu item.
			[ __CLASS__, 'render_settings_page' ]
		);

		add_submenu_page(
			'tva-chat-settings',
			__( 'Leads', 'tva-chat' ),
			__( 'Leads', 'tva-chat' ),
			'manage_options',
			'tva-chat-leads',
			[ __CLASS__, 'render_leads_page' ]
		);
	}

	// ─── Settings Registration ────────────────────────────────────────────────

	public static function register_settings(): void {
		$settings = [
			'tva_chat_llm_api_key'      => 'sanitize_text_field',
			'tva_chat_llm_model'        => 'sanitize_text_field',
			'tva_chat_llm_provider'     => 'sanitize_text_field',
			'tva_chat_whatsapp_number'  => 'sanitize_text_field',
			'tva_chat_greeting'         => 'sanitize_textarea_field',
			'tva_chat_notify_email'     => 'sanitize_email',
		];

		foreach ( $settings as $option => $callback ) {
			register_setting( 'tva_chat_settings', $option, [
				'sanitize_callback' => $callback,
			] );
		}
	}

	// ─── Settings Page ────────────────────────────────────────────────────────

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary = TVA_Analytics::weekly_summary();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TechVaults Chat — Settings', 'tva-chat' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'tva_chat_settings' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							<label for="tva_chat_llm_provider">
								<?php esc_html_e( 'LLM Provider', 'tva-chat' ); ?>
							</label>
						</th>
						<td>
							<select id="tva_chat_llm_provider" name="tva_chat_llm_provider">
								<option value="gemini" <?php selected( get_option( 'tva_chat_llm_provider', 'gemini' ), 'gemini' ); ?>>
									Google Gemini
								</option>
								<!-- Future providers go here as they are implemented -->
							</select>
							<p class="description">
								<?php esc_html_e( 'Select which AI provider to use for chat responses.', 'tva-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_chat_llm_api_key">
								<?php esc_html_e( 'API Key', 'tva-chat' ); ?>
							</label>
						</th>
						<td>
							<input
								type="password"
								id="tva_chat_llm_api_key"
								name="tva_chat_llm_api_key"
								value="<?php echo esc_attr( get_option( 'tva_chat_llm_api_key' ) ); ?>"
								style="width:400px"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php esc_html_e( 'Your Gemini API key from Google AI Studio. Never share this or commit it to version control.', 'tva-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_chat_llm_model">
								<?php esc_html_e( 'Model', 'tva-chat' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="tva_chat_llm_model"
								name="tva_chat_llm_model"
								value="<?php echo esc_attr( get_option( 'tva_chat_llm_model', 'gemini-1.5-flash' ) ); ?>"
								style="width:400px"
							/>
							<p class="description">
								<?php esc_html_e( 'e.g. gemini-1.5-flash, gemini-1.5-pro', 'tva-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_chat_whatsapp_number">
								<?php esc_html_e( 'WhatsApp Number', 'tva-chat' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="tva_chat_whatsapp_number"
								name="tva_chat_whatsapp_number"
								value="<?php echo esc_attr( get_option( 'tva_chat_whatsapp_number', '2348034048178' ) ); ?>"
								style="width:220px"
							/>
							<p class="description">
								<?php esc_html_e( 'International format, no + or spaces. Example: 2348034048178', 'tva-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_chat_greeting">
								<?php esc_html_e( 'Greeting Message', 'tva-chat' ); ?>
							</label>
						</th>
						<td>
							<textarea
								id="tva_chat_greeting"
								name="tva_chat_greeting"
								rows="3"
								style="width:400px"
							><?php echo esc_textarea( get_option( 'tva_chat_greeting', "Hi, I'm the TechVaults assistant. Ask me about our services, pricing, or past projects." ) ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_chat_notify_email">
								<?php esc_html_e( 'Lead Notification Email', 'tva-chat' ); ?>
							</label>
						</th>
						<td>
							<input
								type="email"
								id="tva_chat_notify_email"
								name="tva_chat_notify_email"
								value="<?php echo esc_attr( get_option( 'tva_chat_notify_email', get_option( 'admin_email' ) ) ); ?>"
								style="width:300px"
							/>
						</td>
					</tr>

				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Last 7 days', 'tva-chat' ); ?></h2>
			<p>
				<strong><?php echo esc_html( $summary['total_conversations'] ); ?></strong>
				<?php esc_html_e( 'conversations', 'tva-chat' ); ?>
			</p>

			<?php if ( ! empty( $summary['unresolved_messages'] ) ) : ?>
				<h3><?php esc_html_e( 'Unresolved questions — review these to grow the knowledge base', 'tva-chat' ); ?></h3>
				<ul>
					<?php foreach ( $summary['unresolved_messages'] as $q ) : ?>
						<li><?php echo esc_html( $q ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No unresolved questions this week.', 'tva-chat' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Leads Page ───────────────────────────────────────────────────────────

	public static function render_leads_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$leads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tva_leads ORDER BY created_at DESC LIMIT %d",
				100
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chatbot Leads', 'tva-chat' ); ?></h1>

			<?php if ( empty( $leads ) ) : ?>
				<p><?php esc_html_e( 'No leads captured yet.', 'tva-chat' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date',       'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Name',       'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Phone',      'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Email',      'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Need',       'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Source page','tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Status',     'tva-chat' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $leads as $lead ) : ?>
							<tr>
								<td><?php echo esc_html( $lead->created_at ); ?></td>
								<td><?php echo esc_html( $lead->name ); ?></td>
								<td>
									<?php
									$clean_phone = preg_replace( '/\D/', '', $lead->phone );
									if ( $clean_phone ) :
									?>
										<a
											href="https://wa.me/<?php echo esc_attr( $clean_phone ); ?>"
											target="_blank"
											rel="noopener noreferrer"
										><?php echo esc_html( $lead->phone ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $lead->phone ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $lead->email ); ?></td>
								<td><?php echo esc_html( $lead->stated_need ); ?></td>
								<td>
									<a
										href="<?php echo esc_url( $lead->source_url ); ?>"
										target="_blank"
										rel="noopener noreferrer"
									><?php echo esc_html( wp_parse_url( $lead->source_url, PHP_URL_PATH ) ?: $lead->source_url ); ?></a>
								</td>
								<td><?php echo esc_html( $lead->status ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
