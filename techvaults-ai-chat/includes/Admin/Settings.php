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
		$kbCount = wp_count_posts( 'tva_kb_entry' )->publish ?? 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TechVaults Chat — Settings', 'tva-chat' ); ?></h1>

			<?php
			// Only show the seeded notice when the value came from our own
			// admin-post redirect — verify the referring action via a transient
			// rather than trusting a raw GET parameter anyone could craft.
			$seeded_count = get_transient( 'tva_kb_seeded_' . get_current_user_id() );
			if ( $seeded_count !== false ) {
				delete_transient( 'tva_kb_seeded_' . get_current_user_id() );
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						$seeded = (int) $seeded_count;
						if ( $seeded > 0 ) {
							printf(
								esc_html__( '✓ Knowledge Base seeded! %d entries added. The chatbot can now answer detailed questions about TechVaults.', 'tva-chat' ),
								$seeded
							);
						} else {
							esc_html_e( 'Knowledge Base already up to date — no new entries were needed.', 'tva-chat' );
						}
						?>
					</p>
				</div>
				<?php
			}
			?>

			<?php if ( (int) $kbCount === 0 ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Knowledge Base is empty.', 'tva-chat' ); ?></strong>
						<?php esc_html_e( 'The chatbot will use general knowledge only until you add KB entries.', 'tva-chat' ); ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=tva_kb_entry' ) ); ?>">
							<?php esc_html_e( 'Add an entry manually →', 'tva-chat' ); ?>
						</a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tva_seed_kb' ), 'tva_seed_kb' ) ); ?>"
						   style="font-weight:600;color:#bc0004;">
							<?php esc_html_e( '⚡ Auto-seed with TechVaults content →', 'tva-chat' ); ?>
						</a>
					</p>
				</div>
			<?php elseif ( (int) $kbCount < 5 ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							esc_html__( 'Knowledge Base has %d entries. Add at least 5 for reliable responses.', 'tva-chat' ),
							(int) $kbCount
						);
						?>
						&nbsp;
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tva_seed_kb' ), 'tva_seed_kb' ) ); ?>">
							<?php esc_html_e( '⚡ Auto-seed with TechVaults content →', 'tva-chat' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-success inline">
					<p>
						<?php
						printf(
							esc_html__( '✓ Knowledge Base: %d entries ready.', 'tva-chat' ),
							(int) $kbCount
						);
						?>
					</p>
				</div>
			<?php endif; ?>

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
									Google Gemini (direct)
								</option>
								<option value="agentrouter" <?php selected( Config::llmProvider(), 'agentrouter' ); ?>>
									AgentRouter (OpenAI-compatible gateway)
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'AgentRouter is an OpenAI-compatible proxy giving access to Gemini, Claude, GPT, DeepSeek and more via a single API key.', 'tva-chat' ); ?>
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
								<?php
								if ( Config::llmProvider() === 'agentrouter' ) {
									esc_html_e( 'Your AgentRouter API key (sk-...) from agentrouter.org/console/token. Never share or commit this.', 'tva-chat' );
								} else {
									esc_html_e( 'Your Gemini API key from Google AI Studio. Never share this or commit it to version control.', 'tva-chat' );
								}
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tva_llm_model"><?php esc_html_e( 'Model', 'tva-chat' ); ?></label>
						</th>
						<td>
							<?php
							$current_model    = Config::llmModel();
							$current_provider = Config::llmProvider();

							$gemini_models = [
								'gemini-flash-lite-latest' => 'Gemini 3.1 Flash Lite — fast, recommended ✓',
								'gemini-flash-latest'      => 'Gemini 3.5 Flash — latest (higher quota needed)',
								'gemini-2.0-flash'         => 'Gemini 2.0 Flash (older keys)',
								'gemini-2.5-flash'         => 'Gemini 2.5 Flash (older keys only)',
							];

							$agentrouter_models = [
								'gemini-2.0-pro'               => 'Gemini 2.0 Pro (via AgentRouter)',
								'gemini-2.5-flash'             => 'Gemini 2.5 Flash (via AgentRouter)',
								'claude-sonnet-4-5-20250929'   => 'Claude Sonnet 4.5 (via AgentRouter)',
								'claude-haiku-3-5-20241022'    => 'Claude Haiku 3.5 — fast & cheap (via AgentRouter)',
								'gpt-4o'                       => 'GPT-4o (via AgentRouter)',
								'gpt-4o-mini'                  => 'GPT-4o Mini — cost-efficient (via AgentRouter)',
								'deepseek-r1'                  => 'DeepSeek R1 — near-free (via AgentRouter)',
								'glm-4.5-air'                  => 'GLM-4.5 Air — FREE (via AgentRouter)',
							];

							$models = $current_provider === 'agentrouter' ? $agentrouter_models : $gemini_models;
							?>
							<select id="tva_llm_model" name="tva_chat_llm_model" style="width:420px">
								<?php foreach ( $models as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_model, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php
								if ( $current_provider === 'agentrouter' ) {
									esc_html_e( 'Using AgentRouter: change provider first to switch between model families. gemini-2.0-pro is recommended for chat quality.', 'tva-chat' );
								} else {
									esc_html_e( 'gemini-flash-latest is recommended — it resolves to Gemini 3.5 Flash on new API keys. Use gemini-2.0-flash for older keys.', 'tva-chat' );
								}
								?>
							</p>
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
