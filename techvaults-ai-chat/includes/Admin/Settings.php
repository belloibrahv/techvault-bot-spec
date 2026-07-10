<?php
/**
 * Admin settings page — registers options and renders the settings form.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Admin;

use TechVaults\Chat\Analytics\EventRepository;
use TechVaults\Chat\Core\Config;

class Settings {

	public function registerSettings(): void {
		$sanitizers = [
			'tva_chat_llm_provider'    => 'sanitize_text_field',
			'tva_chat_llm_api_key'     => 'sanitize_text_field',
			'tva_chat_llm_model'       => 'sanitize_text_field',
			'tva_chat_whatsapp_number' => 'sanitize_text_field',
			'tva_chat_greeting'        => 'sanitize_textarea_field',
			'tva_chat_notify_email'    => 'sanitize_email',
		];

		foreach ( $sanitizers as $option => $callback ) {
			register_setting( 'tva_chat_settings', $option, [
				'sanitize_callback' => $callback,
			] );
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary = ( new EventRepository() )->weeklySummary();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TechVaults Chat — Settings', 'tva-chat' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'tva_chat_settings' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">
							<label for="tva_llm_provider"><?php esc_html_e( 'LLM Provider', 'tva-chat' ); ?></label>
						</th>
						<td>
							<select id="tva_llm_provider" name="tva_chat_llm_provider">
								<option value="gemini" <?php selected( Config::llmProvider(), 'gemini' ); ?>>
									Google Gemini
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'AI provider for chat responses. More providers can be added in future releases.', 'tva-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_api_key"><?php esc_html_e( 'API Key', 'tva-chat' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="tva_api_key"
								name="tva_chat_llm_api_key"
								value="<?php echo esc_attr( Config::llmApiKey() ); ?>"
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
							<label for="tva_llm_model"><?php esc_html_e( 'Model', 'tva-chat' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="tva_llm_model"
								name="tva_chat_llm_model"
								value="<?php echo esc_attr( Config::llmModel() ); ?>"
								style="width:300px"
							/>
							<p class="description">e.g. gemini-1.5-flash, gemini-1.5-pro</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_whatsapp"><?php esc_html_e( 'WhatsApp Number', 'tva-chat' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="tva_whatsapp"
								name="tva_chat_whatsapp_number"
								value="<?php echo esc_attr( Config::whatsappNumber() ); ?>"
								style="width:220px"
							/>
							<p class="description">
								<?php esc_html_e( 'International format, no + or spaces. Example: 2348034048178', 'tva-chat' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_greeting"><?php esc_html_e( 'Greeting Message', 'tva-chat' ); ?></label>
						</th>
						<td>
							<textarea
								id="tva_greeting"
								name="tva_chat_greeting"
								rows="3"
								style="width:400px"
							><?php echo esc_textarea( Config::greeting() ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_notify_email"><?php esc_html_e( 'Lead Notification Email', 'tva-chat' ); ?></label>
						</th>
						<td>
							<input
								type="email"
								id="tva_notify_email"
								name="tva_chat_notify_email"
								value="<?php echo esc_attr( Config::notifyEmail() ); ?>"
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
				<strong><?php echo esc_html( (string) $summary['total_conversations'] ); ?></strong>
				<?php esc_html_e( 'conversations', 'tva-chat' ); ?>
			</p>

			<?php if ( ! empty( $summary['unresolved_messages'] ) ) : ?>
				<h3><?php esc_html_e( 'Unresolved questions — add these to the knowledge base', 'tva-chat' ); ?></h3>
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
}
