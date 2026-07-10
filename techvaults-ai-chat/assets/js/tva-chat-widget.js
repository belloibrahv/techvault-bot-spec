/**
 * TechVaults AI Chat Widget
 *
 * Architecture: four concerns, each isolated in its own class.
 *   Storage     — session identity, conversation history
 *   ApiClient   — all fetch() calls to the WP REST endpoints
 *   LeadCapture — lead form state machine
 *   ChatUI      — DOM construction, message rendering, event wiring
 *
 * One file, no build step, no framework, no external dependencies.
 * When a bundler is added later, each class moves to its own module
 * with zero logic changes.
 *
 * The config object (tvaChatConfig) is injected by wp_localize_script:
 *   restUrl  — WP REST base URL, e.g. https://techvaults.com/wp-json/tva/v1/
 *   nonce    — wp_rest nonce for authenticated requests
 *   whatsapp — WhatsApp number, digits only
 *   greeting — Opening message text
 */

(function () {
  'use strict';

  const cfg = window.tvaChatConfig;
  if (!cfg) return; // Plugin not configured — bail silently.

  // ============================================================
  // Storage
  // Owns: session ID, conversation history, lead-captured flag.
  // ============================================================
  class Storage {
    constructor() {
      this._sessionKey = 'tva_session_id';
      this._historyKey = 'tva_history';
    }

    getSessionId() {
      let id = sessionStorage.getItem(this._sessionKey);
      if (!id) {
        id = crypto.randomUUID();
        sessionStorage.setItem(this._sessionKey, id);
      }
      return id;
    }

    /**
     * Returns the last N turns from history.
     * Keeping only the last 8 turns limits payload size and LLM latency.
     * @param {number} limit
     * @returns {{ role: string, content: string }[]}
     */
    getHistory(limit = 8) {
      try {
        const raw = sessionStorage.getItem(this._historyKey);
        const all = raw ? JSON.parse(raw) : [];
        return all.slice(-limit);
      } catch {
        return [];
      }
    }

    pushTurn(role, content) {
      const all = this.getHistory(100); // Full history for storage.
      all.push({ role, content });
      sessionStorage.setItem(this._historyKey, JSON.stringify(all));
    }

    /** Returns all user messages joined — used as stated_need in leads. */
    getUserMessages() {
      return this.getHistory(100)
        .filter((t) => t.role === 'user')
        .map((t) => t.content)
        .join(' | ');
    }

    /** Full history as JSON string — stored in the lead transcript column. */
    getTranscript() {
      return sessionStorage.getItem(this._historyKey) || '[]';
    }
  }

  // ============================================================
  // ApiClient
  // Owns: all fetch() calls. Never touches the DOM.
  // ============================================================
  class ApiClient {
    constructor(storage) {
      this._storage = storage;
    }

    async sendMessage(text) {
      return this._post('message', {
        session_id: this._storage.getSessionId(),
        message:    text,
        history:    this._storage.getHistory(),
        page_url:   location.href,
      });
    }

    async submitLead(name, phone) {
      return this._post('lead', {
        session_id:  this._storage.getSessionId(),
        name,
        phone,
        stated_need: this._storage.getUserMessages(),
        source_url:  location.href,
        transcript:  this._storage.getTranscript(),
      });
    }

    logEvent(eventType, message) {
      // Fire-and-forget. Never awaited — must not block the UI.
      this._post('event', {
        session_id: this._storage.getSessionId(),
        event_type: eventType,
        page_url:   location.href,
        message:    message || '',
      }).catch(() => {});
    }

    async _post(path, body) {
      const res = await fetch(cfg.restUrl + path, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce':   cfg.nonce,
        },
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        const err    = new Error('HTTP ' + res.status);
        err.status   = res.status;
        throw err;
      }

      return res.json();
    }
  }

  // ============================================================
  // LeadCapture
  // Owns: trigger detection, form rendering, form submission.
  // Never touches the conversation flow — calls back via onCaptured().
  // ============================================================
  class LeadCapture {
    static TRIGGER_WORDS = [
      'quote', 'price', 'pricing', 'cost', 'start a project',
      'hire', 'get started', 'consultation', 'build', 'develop',
    ];

    constructor(api, onCaptured) {
      this._api         = api;
      this._onCaptured  = onCaptured; // Callback: (name) => void
      this._captured    = false;
    }

    shouldTrigger(text) {
      if (this._captured) return false;
      const lower = text.toLowerCase();
      return LeadCapture.TRIGGER_WORDS.some((w) => lower.includes(w));
    }

    /**
     * Render the inline lead form into messagesEl and wire up submission.
     * @param {HTMLElement} messagesEl
     */
    renderForm(messagesEl) {
      const wrap = document.createElement('div');
      wrap.className = 'tva-lead-form';

      const prompt = document.createElement('p');
      prompt.textContent =
        'Happy to help. Can I get your name and WhatsApp number so someone from the team can follow up?';

      const nameInput  = this._input('text',  'Your name',       'tva-lead-name',  120);
      const phoneInput = this._input('text',  'WhatsApp number', 'tva-lead-phone',  30);
      const submitBtn  = document.createElement('button');
      submitBtn.type        = 'button';
      submitBtn.textContent = 'Send';

      wrap.append(prompt, nameInput, phoneInput, submitBtn);
      messagesEl.appendChild(wrap);
      messagesEl.scrollTop = messagesEl.scrollHeight;

      submitBtn.addEventListener('click', async () => {
        const name  = nameInput.value.trim();
        const phone = phoneInput.value.trim();

        nameInput.style.borderColor  = name  ? '' : '#BC0004';
        phoneInput.style.borderColor = phone ? '' : '#BC0004';
        if (!name || !phone) return;

        submitBtn.disabled    = true;
        submitBtn.textContent = 'Sending…';

        try {
          await this._api.submitLead(name, phone);
          this._captured = true;
          wrap.remove();
          this._onCaptured(name);
          this._api.logEvent('lead_form_submitted');
        } catch {
          submitBtn.disabled    = false;
          submitBtn.textContent = 'Send';
          // Surface the error via the normal message flow.
          this._onCaptured(null);
        }
      });
    }

    _input(type, placeholder, id, maxLength) {
      const el       = document.createElement('input');
      el.type        = type;
      el.placeholder = placeholder;
      el.id          = id;
      el.setAttribute('aria-label', placeholder);
      el.maxLength   = maxLength;
      return el;
    }
  }

  // ============================================================
  // ChatUI
  // Owns: DOM, event listeners, send flow, WhatsApp link.
  // Composes the three classes above — this is the only entry point.
  // ============================================================
  class ChatUI {
    constructor() {
      this._storage      = new Storage();
      this._api          = new ApiClient(this._storage);
      this._unresolvedStreak = 0;

      this._build();

      this._lead = new LeadCapture(this._api, (name) => {
        if (name) {
          this._addMessage('bot', `Thanks ${name}, someone from TechVaults will reach out on WhatsApp shortly.`);
        } else {
          this._addMessage('bot', "Sorry, I couldn't save your details. Please reach us on WhatsApp directly.");
        }
      });

      this._wire();
      this._setWhatsappLink();
    }

    // ── DOM construction ────────────────────────────────────────

    _build() {
      const root = document.createElement('div');
      root.id    = 'tva-chat-root';
      root.innerHTML = `
        <button
          id="tva-chat-launcher"
          aria-label="Open chat with TechVaults assistant"
          aria-expanded="false"
          aria-controls="tva-chat-panel"
        >💬</button>

        <div
          id="tva-chat-panel"
          role="dialog"
          aria-label="TechVaults chat"
          aria-modal="true"
          hidden
        >
          <div id="tva-chat-header">
            <span>TechVaults Assistant</span>
            <button id="tva-chat-close" aria-label="Close chat">×</button>
          </div>

          <div id="tva-chat-messages" aria-live="polite" aria-atomic="false"></div>

          <form id="tva-chat-form" novalidate>
            <label for="tva-chat-input" class="tva-sr-only">Type your question</label>
            <input
              id="tva-chat-input"
              type="text"
              placeholder="Type your question…"
              autocomplete="off"
              maxlength="500"
            />
            <button type="submit" aria-label="Send message">➤</button>
          </form>

          <a id="tva-chat-whatsapp" target="_blank" rel="noopener noreferrer">
            Chat on WhatsApp instead
          </a>
        </div>`;

      document.body.appendChild(root);

      // Cache element references.
      this._panel       = document.getElementById('tva-chat-panel');
      this._messages    = document.getElementById('tva-chat-messages');
      this._form        = document.getElementById('tva-chat-form');
      this._input       = document.getElementById('tva-chat-input');
      this._launcher    = document.getElementById('tva-chat-launcher');
      this._closeBtn    = document.getElementById('tva-chat-close');
      this._waLink      = document.getElementById('tva-chat-whatsapp');
    }

    // ── Event wiring ────────────────────────────────────────────

    _wire() {
      this._launcher.addEventListener('click', () => this._open());
      this._closeBtn.addEventListener('click', () => this._close());

      // Close on Escape.
      this._panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') this._close();
      });

      this._form.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = this._input.value.trim();
        if (!text) return;
        this._input.value = '';
        this._send(text);
      });

      this._waLink.addEventListener('click', () =>
        this._api.logEvent('whatsapp_handoff')
      );

      // GA4 bridge — only fires if gtag is present on the page.
      if (typeof window.gtag === 'function') {
        this._launcher.addEventListener('click', () =>
          gtag('event', 'tva_chat_opened')
        );
        this._waLink.addEventListener('click', () =>
          gtag('event', 'tva_whatsapp_handoff')
        );
      }
    }

    // ── Panel open / close ──────────────────────────────────────

    _open() {
      this._panel.hidden = false;
      this._launcher.setAttribute('aria-expanded', 'true');
      this._api.logEvent('widget_opened');

      // Show greeting only on first open.
      if (this._messages.children.length === 0) {
        this._addMessage('bot', cfg.greeting);
      }

      this._input.focus();
    }

    _close() {
      this._panel.hidden = true;
      this._launcher.setAttribute('aria-expanded', 'false');
      this._launcher.focus();
    }

    // ── Send flow ───────────────────────────────────────────────

    async _send(text) {
      this._addMessage('user', text);
      this._storage.pushTurn('user', text);
      this._api.logEvent('message_sent', text);

      const typing = this._addTypingIndicator();

      let reply;
      try {
        const data = await this._api.sendMessage(text);
        reply = data.reply || "Sorry, I couldn't process that. Try WhatsApp instead.";
      } catch (err) {
        reply = err.status === 429
          ? "You've sent a lot of messages. Please wait a moment, or reach us on WhatsApp."
          : "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
      }

      typing.remove();
      this._addMessage('bot', reply);
      this._storage.pushTurn('assistant', reply);

      this._updateUnresolvedStreak(reply);
      if (this._lead.shouldTrigger(text)) {
        this._lead.renderForm(this._messages);
      }
      this._setWhatsappLink(text);
    }

    // ── Message rendering ───────────────────────────────────────

    _addMessage(role, text) {
      const el       = document.createElement('div');
      el.className   = `tva-msg tva-msg-${role}`;
      el.textContent = text; // textContent is XSS-safe.
      this._messages.appendChild(el);
      this._messages.scrollTop = this._messages.scrollHeight;
      return el;
    }

    _addTypingIndicator() {
      const el       = document.createElement('div');
      el.className   = 'tva-msg tva-msg-bot tva-typing';
      el.textContent = 'Typing…';
      el.setAttribute('aria-label', 'Assistant is typing');
      this._messages.appendChild(el);
      this._messages.scrollTop = this._messages.scrollHeight;
      return el;
    }

    // ── Unresolved streak ───────────────────────────────────────

    _updateUnresolvedStreak(reply) {
      const lower = reply.toLowerCase();
      const unresolved =
        lower.includes("i'm not sure") ||
        lower.includes('connect you')  ||
        lower.includes("i'm having trouble");

      this._unresolvedStreak = unresolved ? this._unresolvedStreak + 1 : 0;

      if (this._unresolvedStreak >= 2) {
        this._addMessage('bot', 'Want me to connect you to someone on WhatsApp instead?');
        this._unresolvedStreak = 0;
      }
    }

    // ── WhatsApp link ───────────────────────────────────────────

    _setWhatsappLink(prefill) {
      const text     = encodeURIComponent(
        prefill || 'Hi TechVaults, I have a question from your website chatbot.'
      );
      this._waLink.href = `https://wa.me/${cfg.whatsapp}?text=${text}`;
    }
  }

  // ── Boot ──────────────────────────────────────────────────────────────────
  new ChatUI();

})();
