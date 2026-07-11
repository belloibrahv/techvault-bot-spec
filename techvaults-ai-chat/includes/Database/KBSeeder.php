<?php
/**
 * Knowledge Base Seeder — built from live techvaults.com content.
 *
 * Trigger options:
 *   1. WP-CLI:  wp eval 'TechVaults\Chat\Database\KBSeeder::seed();'
 *   2. Admin:   TechVaults Chat → Settings → ⚡ Auto-seed button
 *
 * Safe to run multiple times — checks by title before inserting.
 */

declare( strict_types=1 );

namespace TechVaults\Chat\Database;

use TechVaults\Chat\KnowledgeBase\CPT;
use TechVaults\Chat\KnowledgeBase\MetaFields;

class KBSeeder {

	/** @return array<int, array{title: string, answer: string, category: string}> */
	private static function entries(): array {
		return [

			// ── COMPANY ───────────────────────────────────────────────────────

			[
				'category' => 'faq',
				'title'    => 'What is TechVaults?',
				'answer'   => 'TechVaults Limited is an ICT solutions company founded in 2016 and based in Ikeja, Lagos, Nigeria. We help businesses across Nigeria and Africa with website development, cloud services, data recovery, IT consulting, and tech training through our Academy. We are Microsoft Partners and Dell Partners.',
			],
			[
				'category' => 'faq',
				'title'    => 'When was TechVaults founded?',
				'answer'   => 'TechVaults Limited was founded in 2016. Since then we have been delivering ICT solutions covering web development, cloud computing, data recovery, business technology, and training.',
			],
			[
				'category' => 'faq',
				'title'    => 'Where is TechVaults located?',
				'answer'   => 'TechVaults is located at Connak Building, 17 Akinremi Street, Anifowoshe, Ikeja, Lagos, Nigeria. The TechVaults Academy is on the 2nd Floor of the same building.',
			],
			[
				'category' => 'contact',
				'title'    => 'How do I contact TechVaults?',
				'answer'   => "You can reach TechVaults through: WhatsApp / Phone: +234 803 404 8178 (fastest response) | Secondary: +234 812 267 7662 | Email: info@techvaults.com | Website: www.techvaults.com | Office: Connak Building, 17 Akinremi Street, Anifowoshe, Ikeja, Lagos. We are also on LinkedIn, Instagram (@techvaultsltd), and Facebook.",
			],
			[
				'category' => 'contact',
				'title'    => 'What is TechVaults WhatsApp number?',
				'answer'   => "TechVaults' WhatsApp number is +234 803 404 8178. It's the fastest way to reach the team for project enquiries, support, or pricing questions.",
			],
			[
				'category' => 'contact',
				'title'    => 'What is TechVaults email address?',
				'answer'   => 'The primary business email is info@techvaults.com. You can also reach us through the contact form at techvaults.com/contact.',
			],
			[
				'category' => 'faq',
				'title'    => 'Is TechVaults a Microsoft Partner?',
				'answer'   => 'Yes, TechVaults is a Microsoft Partner and a Dell Partner. This means we have certified expertise in Microsoft products and can supply and support Dell hardware.',
			],
			[
				'category' => 'faq',
				'title'    => 'What industries does TechVaults serve?',
				'answer'   => 'TechVaults serves a wide range of industries including finance, telecommunications, logistics, healthcare, education, real estate, NGOs, hospitality, and e-commerce. Our team has experience across these sectors both locally and internationally.',
			],
			[
				'category' => 'faq',
				'title'    => 'Can TechVaults work with clients outside Lagos or Nigeria?',
				'answer'   => 'Absolutely. We work with clients remotely across Nigeria and Africa. Most project communication, reviews, and support happen over WhatsApp, email, and video calls. Payment can be made via bank transfer or Paystack.',
			],

			// ── SERVICES ──────────────────────────────────────────────────────

			[
				'category' => 'service',
				'title'    => 'What services does TechVaults offer?',
				'answer'   => 'TechVaults offers five core service areas: (1) Website Development — custom sites, e-commerce, web apps; (2) Cloud Services — hosting, migration, infrastructure on AWS/GCP/Azure; (3) Data Recovery & Security — data retrieval and cybersecurity; (4) Business Tech Solutions — IT consulting, software, hardware, digital marketing; (5) Training — practical tech courses via Techvaults Academy.',
			],
			[
				'category' => 'service',
				'title'    => 'What kind of websites does TechVaults build?',
				'answer'   => 'We build all types of websites: corporate business sites, landing pages, e-commerce stores, portfolios, booking platforms, school/LMS sites, real estate listings, and custom web applications. We use WordPress, Webflow, React/Next.js, and Laravel depending on your needs. All sites are mobile-responsive, fast, and SEO-optimised.',
			],
			[
				'category' => 'service',
				'title'    => 'Does TechVaults build e-commerce websites?',
				'answer'   => 'Yes. We build e-commerce websites on WordPress/WooCommerce and integrate payment gateways for the Nigerian market — Paystack, Flutterwave, and bank transfer. We handle product catalogue, checkout, order management, and post-launch support.',
			],
			[
				'category' => 'service',
				'title'    => 'Does TechVaults do digital marketing?',
				'answer'   => 'Yes, digital marketing is part of our Business Tech Solutions. We offer social media management, SEO, content marketing, and paid advertising. Contact us on WhatsApp (+234 803 404 8178) to discuss your marketing goals.',
			],
			[
				'category' => 'service',
				'title'    => 'Does TechVaults do graphic design?',
				'answer'   => 'Yes, creative/graphic design is one of our services. We handle logo design, brand identity, social media graphics, and print materials. This can be bundled with a website project or ordered as a standalone service.',
			],
			[
				'category' => 'service',
				'title'    => 'Does TechVaults supply computer hardware?',
				'answer'   => 'Yes. As a Dell Partner, TechVaults supplies computer hardware and peripherals for businesses — laptops, desktops, servers, networking equipment, and accessories. Contact us for a hardware quote.',
			],
			[
				'category' => 'service',
				'title'    => 'What cloud platforms does TechVaults work with?',
				'answer'   => 'We work with Amazon Web Services (AWS), Google Cloud Platform (GCP), and Microsoft Azure. Services include cloud migration, server setup, containerisation (Docker/Kubernetes), CI/CD pipelines, backups, SSL, and 24/7 uptime monitoring.',
			],
			[
				'category' => 'service',
				'title'    => 'What is data recovery and how does TechVaults help?',
				'answer'   => "TechVaults recovers lost or inaccessible data from HDDs, SSDs, USB drives, memory cards, RAID arrays, and smartphones — whether from accidental deletion, physical damage, ransomware, or formatting. We do a free initial assessment before quoting. In most cases: no recovery, no fee. Bring the device to our Ikeja office or contact us on WhatsApp first.",
			],
			[
				'category' => 'service',
				'title'    => 'Can TechVaults recover data from a phone?',
				'answer'   => 'Yes. We recover data from Android and iOS devices. Contact us on WhatsApp (+234 803 404 8178) with details about the device and what happened, and we will advise on next steps.',
			],
			[
				'category' => 'service',
				'title'    => 'What is Business Tech Solutions at TechVaults?',
				'answer'   => "Business Tech Solutions covers: IT consulting and strategy, Microsoft 365 and Google Workspace setup, network infrastructure (LAN/WiFi), CCTV and access control, hardware procurement, custom software development, digital marketing, and ongoing IT helpdesk support. We act as your outsourced IT department.",
			],
			[
				'category' => 'service',
				'title'    => 'Does TechVaults do custom software development?',
				'answer'   => 'Yes. Custom software development is one of our core offerings under Business Tech Solutions. We build web applications, management systems, and enterprise software tailored to your business processes. Contact us for a scoping consultation.',
			],
			[
				'category' => 'service',
				'title'    => 'Does TechVaults offer IT support and consulting?',
				'answer'   => 'Yes. We provide IT planning, consulting, and ongoing support for businesses of all sizes. This includes Microsoft 365 / Google Workspace setup, network infrastructure, hardware procurement, and helpdesk support. We can act as your dedicated IT partner.',
			],
			[
				'category' => 'service',
				'title'    => 'Can TechVaults fix or improve my existing website?',
				'answer'   => 'Yes. We regularly take on redesigns, speed optimisation, security fixes, and adding new features to existing websites — whether or not we built the original. We start with a free audit to understand what needs fixing before quoting.',
			],

			// ── PRICING ───────────────────────────────────────────────────────

			[
				'category' => 'pricing',
				'title'    => 'How much does a website cost at TechVaults?',
				'answer'   => "Website pricing is not publicly listed — it depends on the scope, features, and complexity of your project. TechVaults will provide a custom quote after a free consultation. To get an estimate, reach out on WhatsApp (+234 803 404 8178) or email info@techvaults.com with a brief description of what you need.",
			],
			[
				'category' => 'pricing',
				'title'    => 'Does TechVaults publish its pricing?',
				'answer'   => 'TechVaults does not publish fixed prices because every project is scoped individually. The cost depends on the type, size, and features required. Contact us on WhatsApp (+234 803 404 8178) or via info@techvaults.com for a free consultation and custom quote.',
			],
			[
				'category' => 'pricing',
				'title'    => 'Do you offer payment plans?',
				'answer'   => 'Yes. For larger projects we typically structure payments in milestones — usually 50% upfront to begin and the balance on delivery. Some projects can be split into three payments. This is agreed in writing during the proposal stage.',
			],

			// ── PROCESS ───────────────────────────────────────────────────────

			[
				'category' => 'process',
				'title'    => 'How does TechVaults start a new project?',
				'answer'   => "Our process has four stages: (1) Discovery — we understand your goals, research requirements, define scope; (2) Planning — detailed project plan, timeline, and resource estimate; (3) Execute — build phase with progress meetings and quality checks; (4) Deliver — testing, documentation, launch, and client support. Start by reaching us on WhatsApp (+234 803 404 8178) or filling in the form at techvaults.com/contact.",
			],
			[
				'category' => 'process',
				'title'    => 'How long does it take to build a website?',
				'answer'   => 'Timelines depend on complexity. A standard business website typically takes 2–3 weeks from deposit to launch (assuming content is provided promptly). E-commerce sites take 4–6 weeks. Custom web applications are scoped individually. Timelines are confirmed in writing in the project proposal.',
			],
			[
				'category' => 'process',
				'title'    => 'What do I need to provide to build a website?',
				'answer'   => "You'll need: your business name and logo (or we design one), page content/copy, photos or images (or we source stock photos), preferred colour scheme, and examples of websites you like. We guide you through everything — no technical knowledge required.",
			],
			[
				'category' => 'process',
				'title'    => 'Does TechVaults offer website maintenance after launch?',
				'answer'   => 'Yes. All website projects include post-launch support. After the initial support period, we offer ongoing maintenance plans covering security updates, backups, uptime monitoring, and minor content changes. Contact us for current maintenance plan details.',
			],

			// ── PORTFOLIO / CLIENTS ───────────────────────────────────────────

			[
				'category' => 'project',
				'title'    => 'What kind of clients has TechVaults worked with?',
				'answer'   => 'TechVaults has delivered projects for 20+ organisations across diverse sectors: finance (Interstate Securities, Metroperilins Brokers), logistics (SAHCO Plc, Woodland Pacific Logistics), healthcare (Pentacare Hospital, Accucare UK), education (At-Tanzeel Schools), NGOs (MUSWEN, OSOAFoundation), hospitality (Skyway Premium Lounge, Amethyst Hall), real estate (Homes and Offices Pro), and more.',
			],
			[
				'category' => 'project',
				'title'    => 'Can I see TechVaults portfolio?',
				'answer'   => 'Yes. You can view our projects at techvaults.com/projects. We have built websites and platforms for clients in finance, logistics, healthcare, education, NGOs, hospitality, and e-commerce. During a consultation we can share relevant case studies for your industry.',
			],
			[
				'category' => 'project',
				'title'    => 'Does TechVaults sign NDAs?',
				'answer'   => 'Yes. We are happy to sign a Non-Disclosure Agreement before discussing sensitive project details. This is standard practice for us on projects involving proprietary business ideas or confidential information.',
			],

			// ── ACADEMY ───────────────────────────────────────────────────────

			[
				'category' => 'service',
				'title'    => 'What is the TechVaults Academy?',
				'answer'   => 'Techvaults Academy is TechVaults\' training arm. It offers expert-led, hands-on digital skills training in a world-class environment — empowering individuals and institutions to thrive in tech. It is located at the 2nd Floor, Connak Building, 17 Akinremi Street, Ikeja, Lagos. Classes are available both in-person and virtually.',
			],
			[
				'category' => 'service',
				'title'    => 'What courses does Techvaults Academy offer?',
				'answer'   => "Techvaults Academy offers: Tech Fundamentals (1–2 months), UI/UX Design (2–4 months), Graphics Design (2–4 months), Prompt Engineering & AI (2–4 months), Web Development (3–6 months), and Cloud Computing (3–6 months). All courses are available virtually and in-person, include certification, and come with career support and mentorship.",
			],
			[
				'category' => 'service',
				'title'    => 'What is the Web Development course at TechVaults Academy?',
				'answer'   => "The Web Development course (3–6 months) covers: HTML & CSS for responsive websites, JavaScript for interactivity, React basics, backend fundamentals, Git/GitHub, and deploying live websites. It's project-based — students build real websites and leave with a portfolio and certificate. Available virtually and in-person.",
			],
			[
				'category' => 'service',
				'title'    => 'What is the UI/UX Design course at TechVaults Academy?',
				'answer'   => 'The UI/UX Design course (2–4 months) covers: UI principles and visual hierarchy, UX fundamentals, wireframing and prototyping with Figma, user research, usability testing, and design systems. Students build a portfolio of design projects and receive a certificate on completion.',
			],
			[
				'category' => 'service',
				'title'    => 'What is the Graphics Design course at TechVaults Academy?',
				'answer'   => 'The Graphics Design course (2–4 months) covers: design principles (colour, typography, layout), Adobe Photoshop, Illustrator, CorelDRAW, logo and brand identity design, social media graphics, and print/digital preparation. Certificate on completion.',
			],
			[
				'category' => 'service',
				'title'    => 'What is the Prompt Engineering and AI course at TechVaults Academy?',
				'answer'   => 'The Prompt Engineering & AI course (2–4 months) covers: AI fundamentals, writing effective prompts, using AI for content creation and research, automating tasks, and optimising AI outputs for real-world business use. Ideal for professionals looking to boost productivity with AI tools.',
			],
			[
				'category' => 'service',
				'title'    => 'What is the Cloud Computing course at TechVaults Academy?',
				'answer'   => 'The Cloud Computing course (3–6 months) covers: core cloud concepts, cloud service models (IaaS, PaaS, SaaS), deploying and managing applications in the cloud, cloud storage and security fundamentals, and basic monitoring and cloud operations. Certificate on completion.',
			],
			[
				'category' => 'service',
				'title'    => 'What is the Tech Fundamentals course at TechVaults Academy?',
				'answer'   => 'Tech Fundamentals (1–2 months) is for complete beginners. It covers computer basics, internet safety, Microsoft Office (Word, Excel, PowerPoint), introduction to coding, and digital productivity tools. Perfect for anyone starting their tech journey.',
			],
			[
				'category' => 'faq',
				'title'    => 'Do I need experience to enroll at TechVaults Academy?',
				'answer'   => 'No prior tech or coding experience is needed. Courses cater to both beginners and professionals. Instructors guide students step by step, and the environment is supportive for learners at all levels.',
			],
			[
				'category' => 'faq',
				'title'    => 'Are TechVaults Academy classes virtual or in-person?',
				'answer'   => 'Both options are available. Virtual classes let you learn from anywhere with a flexible schedule and recorded sessions. Physical classes are held at the Academy in Ikeja and offer face-to-face interaction, hands-on practice, and immediate instructor feedback. Many courses offer a hybrid of both.',
			],
			[
				'category' => 'faq',
				'title'    => 'Does TechVaults Academy give certificates?',
				'answer'   => 'Yes. Every student who successfully completes a course receives a recognised certificate that validates their skills and boosts their career profile.',
			],
			[
				'category' => 'faq',
				'title'    => 'Does TechVaults Academy offer corporate training?',
				'answer'   => 'Yes. We offer custom corporate training packages for teams and organisations. Topics can be tailored to your team\'s needs. Contact us on WhatsApp (+234 803 404 8178) or email info@techvaults.com to discuss a programme.',
			],
			[
				'category' => 'faq',
				'title'    => 'What are TechVaults Academy success stats?',
				'answer'   => 'Techvaults Academy has trained 100+ students, achieved a 92% success rate, delivered 8+ hours of training content, and seen 60+ alumni go on to employment in tech roles.',
			],

			// ── TESTIMONIALS ──────────────────────────────────────────────────

			[
				'category' => 'faq',
				'title'    => 'What do TechVaults clients say?',
				'answer'   => "Here's what some clients have said: Gbenga Fashola (CEO, Squaremeter Concepts): \"TechVaults transformed our digital presence and streamlined our operations.\" | John Oladele (Online Service Provider): \"Their web development and cloud services modernized our business workflow.\" | Abayomi Oshin (CEO, Black Earth Organics): \"TechVaults provided scalable tech solutions that fueled our growth.\" | Tunde.A (Biogold Limited): \"TechVaults delivered beyond expectations, modernizing our workflows.\"",
			],

		];
	}

	public static function seed(): int {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return 0;
		}

		$inserted = 0;

		foreach ( self::entries() as $entry ) {
			$existing = get_posts( [
				'post_type'   => CPT::POST_TYPE,
				'post_status' => [ 'publish', 'draft' ],
				'title'       => $entry['title'],
				'numberposts' => 1,
				'fields'      => 'ids',
			] );

			if ( ! empty( $existing ) ) {
				continue;
			}

			$postId = wp_insert_post( [
				'post_type'   => CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $entry['title'],
			] );

			if ( is_wp_error( $postId ) || ! $postId ) {
				continue;
			}

			update_post_meta( $postId, MetaFields::ANSWER_KEY,   $entry['answer'] );
			update_post_meta( $postId, MetaFields::CATEGORY_KEY, $entry['category'] );
			$inserted++;
		}

		return $inserted;
	}

	public static function registerAdminAction(): void {
		add_action( 'admin_post_tva_seed_kb', [ static::class, 'handleAdminAction' ] );
	}

	public static function handleAdminAction(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorised.' );
		}
		check_admin_referer( 'tva_seed_kb' );

		$count = self::seed();

		// Store the result in a short-lived transient keyed to this user
		// so the Settings page can display it without trusting a GET param.
		set_transient( 'tva_kb_seeded_' . get_current_user_id(), $count, 60 );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'tva-chat-settings' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
