<?php
/**
 * Admin leads page — renders the captured leads table.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Admin;

class LeadsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
							<th><?php esc_html_e( 'Date',        'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Name',        'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Phone',       'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Email',       'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Need',        'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Source page', 'tva-chat' ); ?></th>
							<th><?php esc_html_e( 'Status',      'tva-chat' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $leads as $lead ) : ?>
							<tr>
								<td><?php echo esc_html( $lead->created_at ); ?></td>
								<td><?php echo esc_html( $lead->name ); ?></td>
								<td>
									<?php
									$clean = preg_replace( '/\D/', '', $lead->phone );
									if ( $clean ) :
									?>
										<a href="https://wa.me/<?php echo esc_attr( $clean ); ?>"
										   target="_blank" rel="noopener noreferrer">
											<?php echo esc_html( $lead->phone ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $lead->phone ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $lead->email ); ?></td>
								<td><?php echo esc_html( $lead->stated_need ); ?></td>
								<td>
									<?php
									$path = wp_parse_url( $lead->source_url, PHP_URL_PATH );
									?>
									<a href="<?php echo esc_url( $lead->source_url ); ?>"
									   target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $path ?: $lead->source_url ); ?>
									</a>
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
