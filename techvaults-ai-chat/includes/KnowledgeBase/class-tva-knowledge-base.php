<?php
/**
 * Knowledge Base — Custom Post Type + keyword-scoring retrieval.
 *
 * Non-developers manage KB entries via the WordPress editor.
 * Retrieval in Phase 1 uses keyword overlap scoring — intentionally simple.
 * Upgrade path: swap retrieve() for embeddings-based nearest-neighbour
 * lookup when the KB grows past a few hundred entries. Nothing else changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVA_Knowledge_Base {

	public static function init(): void {
		add_action( 'init',                 [ __CLASS__, 'register_post_type' ] );
		add_action( 'add_meta_boxes',       [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post_tva_kb_entry', [ __CLASS__, 'save_meta' ] );
	}

	// ─── CPT Registration ─────────────────────────────────────────────────────

	public static function register_post_type(): void {
		register_post_type( 'tva_kb_entry', [
			'labels'       => [
				'name'          => __( 'Chatbot Knowledge Base', 'tva-chat' ),
				'singular_name' => __( 'KB Entry', 'tva-chat' ),
				'add_new_item'  => __( 'Add Service, Price, or FAQ Entry', 'tva-chat' ),
				'edit_item'     => __( 'Edit KB Entry', 'tva-chat' ),
				'all_items'     => __( 'All KB Entries', 'tva-chat' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'tva-chat-settings', // Nested under the plugin's admin menu.
			'supports'     => [ 'title' ],           // Title = the question or topic headline.
			'menu_icon'    => 'dashicons-format-chat',
		] );
	}

	// ─── Meta box ─────────────────────────────────────────────────────────────

	public static function add_meta_box(): void {
		add_meta_box(
			'tva_kb_answer',
			__( 'Approved Answer', 'tva-chat' ),
			[ __CLASS__, 'render_meta_box' ],
			'tva_kb_entry',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		$answer   = get_post_meta( $post->ID, '_tva_kb_answer',   true );
		$category = get_post_meta( $post->ID, '_tva_kb_category', true );
		$categories = [ 'service', 'pricing', 'project', 'process', 'faq', 'contact' ];

		wp_nonce_field( 'tva_kb_save', 'tva_kb_nonce' );
		?>
		<p>
			<label for="tva_kb_category"><?php esc_html_e( 'Category', 'tva-chat' ); ?></label><br />
			<select id="tva_kb_category" name="tva_kb_category" style="width:100%">
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $category, $cat ); ?>>
						<?php echo esc_html( ucfirst( $cat ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="tva_kb_answer">
				<?php esc_html_e( 'Answer (this is what the bot is allowed to say)', 'tva-chat' ); ?>
			</label>
		</p>
		<textarea id="tva_kb_answer" name="tva_kb_answer" style="width:100%;height:180px"><?php
			echo esc_textarea( $answer );
		?></textarea>
		<?php
	}

	public static function save_meta( int $post_id ): void {
		// Bail on autosave, revision, or missing/invalid nonce.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if (
			! isset( $_POST['tva_kb_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tva_kb_nonce'] ) ), 'tva_kb_save' )
		) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['tva_kb_answer'] ) ) {
			update_post_meta(
				$post_id,
				'_tva_kb_answer',
				sanitize_textarea_field( wp_unslash( $_POST['tva_kb_answer'] ) )
			);
		}
		if ( isset( $_POST['tva_kb_category'] ) ) {
			update_post_meta(
				$post_id,
				'_tva_kb_category',
				sanitize_text_field( wp_unslash( $_POST['tva_kb_category'] ) )
			);
		}
	}

	// ─── Retrieval ────────────────────────────────────────────────────────────

	/**
	 * Keyword-overlap scoring retrieval.
	 *
	 * Returns the top $limit KB entries most relevant to $user_message.
	 * Each result: [ 'score', 'title', 'answer', 'category' ]
	 *
	 * @param string $user_message
	 * @param int    $limit        Max entries to return (default 4).
	 * @return array
	 */
	public static function retrieve( string $user_message, int $limit = 4 ): array {
		$entries = get_posts( [
			'post_type'   => 'tva_kb_entry',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		if ( empty( $entries ) ) {
			return [];
		}

		$message_words = array_unique(
			preg_split( '/\W+/u', strtolower( $user_message ), -1, PREG_SPLIT_NO_EMPTY )
		);

		$scored = [];

		foreach ( $entries as $entry ) {
			$haystack = strtolower(
				$entry->post_title . ' ' .
				( get_post_meta( $entry->ID, '_tva_kb_answer', true ) ?? '' )
			);

			$score = 0;
			foreach ( $message_words as $word ) {
				if ( mb_strlen( $word ) < 3 ) {
					continue; // Skip noise / stop words.
				}
				if ( str_contains( $haystack, $word ) ) {
					$score++;
				}
			}

			if ( $score > 0 ) {
				$scored[] = [
					'score'    => $score,
					'title'    => $entry->post_title,
					'answer'   => get_post_meta( $entry->ID, '_tva_kb_answer', true ),
					'category' => get_post_meta( $entry->ID, '_tva_kb_category', true ),
				];
			}
		}

		usort( $scored, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		return array_slice( $scored, 0, $limit );
	}
}
