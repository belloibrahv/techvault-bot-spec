# TechVaults AI Chat — WordPress Plugin

Custom AI chatbot for [techvaults.com](https://www.techvaults.com).  
Provides lead capture, WhatsApp handoff, and knowledge-base grounded answers.

---

## Architecture

```
Browser
  │
  ▼
Chat Widget (Vanilla JS)          assets/js/tva-chat-widget.js
  │
  ▼ REST API (POST /wp-json/tva/v1/...)
WordPress Backend
  │
  ├── Knowledge Base retrieval    includes/KnowledgeBase/
  │       └── keyword-overlap scoring (Phase 1)
  │
  ├── LLM Provider Interface      includes/LLM/Interfaces/
  │       └── Gemini (v1 default) includes/LLM/Providers/
  │
  ├── Lead storage + email notify includes/Leads/
  │
  └── Analytics event log         includes/Analytics/
```

---

## Folder structure

```
techvaults-ai-chat/
├── techvaults-ai-chat.php          # Bootstrap: constants, autoloader, hooks
├── uninstall.php                   # Cleanup on plugin deletion
├── .gitignore
│
├── assets/
│   ├── css/tva-chat-widget.css
│   ├── js/tva-chat-widget.js
│   └── images/
│
├── includes/
│   ├── Admin/
│   │   └── class-tva-admin.php           # Settings page + leads list
│   ├── Analytics/
│   │   └── class-tva-analytics.php       # Event logging + weekly summary
│   ├── API/
│   │   └── class-tva-rest-api.php        # /message, /lead, /event endpoints
│   ├── Core/
│   │   ├── class-tva-autoloader.php      # spl_autoload_register
│   │   └── class-tva-plugin.php          # Singleton bootstrap
│   ├── Database/
│   │   ├── class-tva-activator.php       # dbDelta table creation
│   │   └── class-tva-deactivator.php     # flush_rewrite_rules
│   ├── KnowledgeBase/
│   │   └── class-tva-knowledge-base.php  # CPT + retrieve()
│   ├── Leads/
│   │   └── class-tva-lead-store.php      # Insert + email notify
│   └── LLM/
│       ├── class-tva-llm-client.php      # Provider factory
│       ├── Interfaces/
│       │   └── interface-tva-llm-provider.php
│       └── Providers/
│           └── class-tva-gemini-provider.php
│
├── languages/
└── templates/
```

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Google Gemini API key ([get one here](https://aistudio.google.com/app/apikey))

---

## Installation (staging)

1. Copy the `techvaults-ai-chat/` folder to `wp-content/plugins/` via SFTP.
2. Activate in **Plugins → Installed Plugins**.
3. Go to **TechVaults Chat → Settings** and add your Gemini API key.
4. Set the WhatsApp number and greeting message.
5. Add Knowledge Base entries under **TechVaults Chat → Chatbot Knowledge Base → Add New**.
6. Test using the smoke test checklist in the spec.

---

## Development decisions

| Decision | Choice | Reason |
|---|---|---|
| LLM provider | Google Gemini | Chosen for v1; switchable via provider interface |
| Retrieval | Keyword overlap scoring | Simple, reliable for 20–80 KB entries |
| Auth | WP REST nonce + IP rate limit | No external auth dependency |
| Lead storage | Custom DB table | Fast queries as volume grows |
| Widget | Vanilla JS, no framework | No build step, no bundle, no dependency risk |

---

## Deferred to Phase 2+

- Embeddings-based retrieval  
- WhatsApp Business API two-way messaging  
- Calendar sync for preferred time  
- CRM sync  

---

*TechVaults Limited · Ikeja, Lagos, Nigeria · techvaults@gmail.com*
