<?php
/**
 * Knowledge Base — Custom Post Type registration.
 *
 * Single responsibility: register the CPT and its meta box in WordPress.
 * Storage (meta reads/writes) lives in Repository.
 * Scoring logic lives in Retriever.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\KnowledgeBase;

class CPT {

	public const POST_TYPE = 'tva_kb_entry';

	public const CATEGORIES = [
		'service',
		'pricing',
		'project',
		'process',
		'faq',
		'contact',
	];

	public function register(): void {
		add_action( 'init',                      [ $this, 'registerPostType' ] );
		add_action( 'add_meta_boxes',            [ $this, 'addMetaBox' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'saveMeta' ] );
	}

	public function registerPostType(): void {
		register_post_type( self::POST_TYPE, [
			'labels'       => [
				'name'          => __( 'Chatbot Knowledge Base', 'tva-chat' ),
				'singular_name' => __( 'KB Entry', 'tva-chat' ),
				'add_new_item'  => __( 'Add Service, Price, or FAQ Entry', 'tva-chat' ),
				'edit_item'     => __( 'Edit KB Entry', 'tva-chat' ),
				'all_items'     => __( 'All KB Entries', 'tva-chat' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'tva-chat-settings',
			'supports'     => [ 'title' ],
			'menu_icon'    => 'dashicons-format-chat',
		] );
	}

	public function addMetaBox(): void {
		add_meta_box(
			'tva_kb_answer',
			__( 'Approved Answer', 'tva-chat' ),
			[ $this, 'renderMetaBox' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function renderMetaBox( \WP_Post $post ): void {
		$answer   = get_post_meta( $post->ID, MetaFields::ANSWER_KEY,   true );
		$category = get_post_meta( $post->ID, MetaFields::CATEGORY_KEY, true );

		wp_nonce_field( 'tva_kb_save', 'tva_kb_nonce' );
		?>
		<p>
			<label for="tva_kb_category"><?php esc_html_e( 'Category', 'tva-chat' ); ?></label><br />
			<select id="tva_kb_category" name="tva_kb_category" style="width:100%">
				<?php foreach ( self::CATEGORIES as $cat ) : ?>
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
		<textarea id="tva_kb_answer" name="tva_kb_answer" style="width:100%;height:180px"
		><?php echo esc_textarea( (string) $answer ); ?></textarea>
		<?php
	}

	public function saveMeta( int $postId ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if (
			! isset( $_POST['tva_kb_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['tva_kb_nonce'] ) ),
				'tva_kb_save'
			)
		) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		if ( isset( $_POST['tva_kb_answer'] ) ) {
			update_post_meta(
				$postId,
				MetaFields::ANSWER_KEY,
				sanitize_textarea_field( wp_unslash( $_POST['tva_kb_answer'] ) )
			);
		}
		if ( isset( $_POST['tva_kb_category'] ) ) {
			update_post_meta(
				$postId,
				MetaFields::CATEGORY_KEY,
				sanitize_text_field( wp_unslash( $_POST['tva_kb_category'] ) )
			);
		}
	}
}
