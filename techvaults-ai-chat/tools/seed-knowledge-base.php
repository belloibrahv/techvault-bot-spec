<?php
/**
 * Knowledge Base Seeder — Sprint 2
 *
 * Creates the first 10 KB entries for TechVaults Limited.
 * Run this ONCE after plugin activation, either via WP-CLI:
 *
 *   wp eval-file wp-content/plugins/techvaults-ai-chat/tools/seed-knowledge-base.php
 *
 * Or place it temporarily in the plugin root and visit:
 *   https://your-staging-site.com/wp-content/plugins/techvaults-ai-chat/tools/seed-knowledge-base.php
 * (rename or delete it afterwards — it guards itself with the ABSPATH check below)
 *
 * Running it a second time is safe — it checks for duplicates by title.
 */

// Safety guard: only run inside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	// Called directly via browser without WP bootstrap — abort.
	die( 'Run via WP-CLI: wp eval-file <path-to-this-file>' );
}

// ── Entry definitions ─────────────────────────────────────────────────────────
$entries = [

	// ── Services ──────────────────────────────────────────────────────────────

	[
		'title'    => 'What services does TechVaults offer?',
		'category' => 'service',
		'answer'   => 'TechVaults Limited offers five core service areas: Website Development (business websites, e-commerce, landing pages), Cloud Services (hosting, migrations, infrastructure), Data Recovery & Security (data backup, recovery, cybersecurity audits), Business Tech Solutions (IT support, software setup, network management), and Training & Academy (practical tech training for individuals and businesses). Every engagement starts with a free consultation to scope your specific needs.',
	],

	[
		'title'    => 'Does TechVaults build e-commerce websites?',
		'category' => 'service',
		'answer'   => 'Yes. TechVaults builds e-commerce websites on WordPress/WooCommerce and can integrate payment gateways suited to the Nigerian market including Paystack, Flutterwave, and bank transfer options. We handle product catalogue setup, checkout flow, order management, and post-launch support.',
	],

	[
		'title'    => 'What is the TechVaults Academy?',
		'category' => 'service',
		'answer'   => 'The TechVaults Academy delivers practical technology training for individuals and corporate teams. Topics include web development fundamentals, cybersecurity basics, Microsoft Office productivity, cloud tools, and IT support skills. Training is available as in-person sessions at our Ikeja office, group workshops, and custom corporate programmes. Contact us to discuss a curriculum tailored to your team.',
	],

	// ── Pricing ───────────────────────────────────────────────────────────────

	[
		'title'    => 'How much does a website cost at TechVaults?',
		'category' => 'pricing',
		'answer'   => 'Website pricing at TechVaults starts from ₦150,000 for a standard business website (up to 5 pages, mobile-responsive, contact form, basic SEO setup). E-commerce sites, custom web applications, and larger projects are priced after a free scoping call because the cost depends on the number of features, integrations, and pages required. These are starting-from figures — your actual quote will come after we understand your project.',
	],

	[
		'title'    => 'What does cloud hosting or migration cost?',
		'category' => 'pricing',
		'answer'   => 'Cloud services are quoted based on the size of your infrastructure, the provider chosen (AWS, Google Cloud, or cPanel-based hosting), and whether ongoing management is required. Basic managed hosting for a small business website starts from ₦30,000 per year. Migration projects are scoped individually. Reach out for a free assessment and we will give you an accurate figure.',
	],

	// ── Process ───────────────────────────────────────────────────────────────

	[
		'title'    => 'How does TechVaults start a new project?',
		'category' => 'process',
		'answer'   => 'Our process has four stages: (1) Free consultation — we learn about your business, goals, and budget. (2) Proposal — we send a written scope, timeline, and price. (3) Build — we develop and review with you at each milestone. (4) Launch & support — we go live and provide post-launch support. Projects typically begin within one week of proposal acceptance. You can start by reaching us on WhatsApp or filling in the contact form on our website.',
	],

	[
		'title'    => 'How long does it take to build a website?',
		'category' => 'process',
		'answer'   => 'A standard 5-page business website takes 2–3 weeks from deposit to launch, assuming content (text and images) is provided promptly. E-commerce sites typically take 4–6 weeks. Custom web applications are scoped individually. Timelines are confirmed in writing in the project proposal.',
	],

	// ── FAQ ───────────────────────────────────────────────────────────────────

	[
		'title'    => 'Is TechVaults based in Lagos?',
		'category' => 'faq',
		'answer'   => 'Yes. TechVaults Limited is located at 17 Akinremi Street, Anifowoshe, Ikeja, Lagos, Nigeria. We work with clients across Nigeria and internationally. Remote collaboration is fully supported — most of our client interactions happen over WhatsApp, email, and video calls.',
	],

	[
		'title'    => 'Can TechVaults recover data from a damaged hard drive or phone?',
		'category' => 'service',
		'answer'   => 'Yes. Data recovery is one of our core services. We handle hard drives (mechanical and SSD), USB drives, memory cards, and mobile devices. The success rate depends on the type and extent of damage. We offer a free initial assessment before quoting a recovery fee — you only pay if we recover your data successfully. Bring the device to our Ikeja office or contact us first via WhatsApp.',
	],

	// ── Contact ───────────────────────────────────────────────────────────────

	[
		'title'    => 'How do I contact TechVaults?',
		'category' => 'contact',
		'answer'   => 'You can reach TechVaults Limited through any of these channels: WhatsApp / Phone: +234 803 404 8178 | Email: techvaults@gmail.com | Website: www.techvaults.com | Office: 17 Akinremi Street, Anifowoshe, Ikeja, Lagos. Office hours are Monday to Friday, 9 am – 6 pm WAT. WhatsApp messages are typically replied to within a few hours.',
	],

];

// ── Seeder ────────────────────────────────────────────────────────────────────

$created = 0;
$skipped = 0;

foreach ( $entries as $entry ) {

	// Duplicate check — find any published/draft post with this exact title.
	$existing = get_posts( [
		'post_type'   => 'tva_kb_entry',
		'post_status' => [ 'publish', 'draft' ],
		'title'       => $entry['title'],
		'numberposts' => 1,
		'fields'      => 'ids',
	] );

	if ( ! empty( $existing ) ) {
		echo "SKIP  (already exists): {$entry['title']}\n";
		$skipped++;
		continue;
	}

	$post_id = wp_insert_post( [
		'post_type'   => 'tva_kb_entry',
		'post_title'  => $entry['title'],
		'post_status' => 'publish',
	], true );

	if ( is_wp_error( $post_id ) ) {
		echo "ERROR creating: {$entry['title']} — " . $post_id->get_error_message() . "\n";
		continue;
	}

	update_post_meta( $post_id, '_tva_kb_answer',   $entry['answer'] );
	update_post_meta( $post_id, '_tva_kb_category', $entry['category'] );

	echo "OK    [{$entry['category']}] {$entry['title']}\n";
	$created++;
}

echo "\nDone. Created: {$created}  |  Skipped (duplicates): {$skipped}\n";
