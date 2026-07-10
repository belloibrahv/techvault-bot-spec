/**
 * TechVaults AI Chat Widget  v1.0.0
 *
 * Four classes, one file, no dependencies, no build step.
 *
 *   Storage      — session ID, history, lead flag (sessionStorage)
 *   ApiClient    — all fetch() calls to /tva/v1/* REST endpoints
 *   LeadCapture  — trigger detection, inline form, submission
 *   ChatUI       — DOM, animations, state, event wiring
 *
 * Config injected by wp_localize_script as window.tvaChatConfig:
 *   restUrl   — WP REST base URL  e.g. https://techvaults.com/wp-json/tva/v1/
 *   nonce     — wp_rest nonce
 *   whatsapp  — digits-only phone number
 *   greeting  — opening message text
 */

(function () {
  'use strict';

  const cfg = window.tvaChatConfig;
  if (!cfg) return;

  // ================================================================
  // Storage
  // ================================================================
  class Storage {
    #sessionKey = 'tva_sid';
    #historyKey = 'tva_hist';
    #openedKey  = 'tva_opened';

    sessionId() {
      let id = sessionStorage.getItem(this.#sessionKey);
      if (!id) {
        id = (typeof crypto.randomUUID === 'function')
          ? crypto.randomUUID()
          : Date.now().toString(36) + Math.random().toString(36).slice(2);
        sessionStorage.setItem(this.#sessionKey, id);
      }
      return id;
    }

    /** Last N turns for LLM context window. */
    history(limit = 8) {
      try {
        const raw = sessionStorage.getItem(this.#historyKey);
        return raw ? JSON.parse(raw).slice(-limit) : [];
      } catch { return []; }
    }

    pushTurn(role, content) {
      const all = this.history(100);
      all.push({ role, content });
      sessionStorage.setItem(this.#historyKey, JSON.stringify(all));
    }

    userMessages() {
      return this.history(100)
        .filter(t => t.role === 'user')
        .map(t => t.content)
        .join(' | ');
    }

    transcript() {
      return sessionStorage.getItem(this.#historyKey) || '[]';
    }

    hasOpened() {
      return !!sessionStorage.getItem(this.#openedKey);
    }

    markOpened() {
      sessionStorage.setItem(this.#openedKey, '1');
    }
  }

  // ================================================================
  // ApiClient
  // ================================================================
  class ApiClient {
    #storage;
    constructor(storage) { this.#storage = storage; }

    async sendMessage(text) {
      return this.#post('message', {
        session_id: this.#storage.sessionId(),
        message:    text,
        history:    this.#storage.history(),
        page_url:   location.href,
      });
    }

    async submitLead(name, phone) {
      return this.#post('lead', {
        session_id:  this.#storage.sessionId(),
        name,
        phone,
        stated_need: this.#storage.userMessages(),
        source_url:  location.href,
        transcript:  this.#storage.transcript(),
      });
    }

    logEvent(type, message) {
      this.#post('event', {
        session_id: this.#storage.sessionId(),
        event_type: type,
        page_url:   location.href,
        message:    message || '',
      }).catch(() => {});
    }

    async #post(path, body) {
      const res = await fetch(cfg.restUrl + path, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
        body:    JSON.stringify(body),
      });
      if (!res.ok) {
        const e = new Error('HTTP ' + res.status);
        e.status = res.status;
        throw e;
      }
      return res.json();
    }
  }

  // ================================================================
  // LeadCapture
  // ================================================================
  class LeadCapture {
    static #TRIGGERS = [
      'quote', 'price', 'pricing', 'cost', 'start a project',
      'hire', 'get started', 'consultation', 'build', 'develop',
    ];

    #api; #onDone; #captured = false;

    constructor(api, onDone) { this.#api = api; this.#onDone = onDone; }

    shouldTrigger(text) {
      if (this.#captured) return false;
      const lower = text.toLowerCase();
      return LeadCapture.#TRIGGERS.some(w => lower.includes(w));
    }

    /** Render the lead card into messagesEl and wire submission. */
    render(messagesEl) {
      const card = document.createElement('div');
      card.className = 'tva-msg-row tva-row-bot';
      card.style.width = '100%';

      const avatar = this.#makeAvatar();
      const inner  = document.createElement('div');
      inner.className = 'tva-lead-card';
      inner.style.flex = '1';

      const p = document.createElement('p');
      p.textContent = 'Happy to help. Can I get your name and WhatsApp number so the team can follow up with details?';

      const nameInput  = this.#field('text',  'Your name',       120);
      const phoneInput = this.#field('tel',   'WhatsApp number',  30);

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tva-lead-submit';
      btn.textContent = 'Send details →';

      inner.append(p, nameInput, phoneInput, btn);
      card.append(avatar, inner);
      messagesEl.appendChild(card);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      nameInput.focus();

      btn.addEventListener('click', async () => {
        const name  = nameInput.value.trim();
        const phone = phoneInput.value.trim();
        nameInput.classList.toggle('tva-error',  !name);
        phoneInput.classList.toggle('tva-error', !phone);
        if (!name || !phone) return;

        btn.disabled = true;
        btn.textContent = 'Sending…';

        try {
          await this.#api.submitLead(name, phone);
          this.#captured = true;
          card.remove();
          this.#onDone({ success: true, name });
          this.#api.logEvent('lead_form_submitted');
        } catch {
          btn.disabled = false;
          btn.textContent = 'Try again';
          this.#onDone({ success: false });
        }
      });
    }

    #field(type, placeholder, maxLength) {
      const el = document.createElement('input');
      el.type        = type;
      el.placeholder = placeholder;
      el.className   = 'tva-lead-field';
      el.maxLength   = maxLength;
      el.setAttribute('aria-label', placeholder);
      return el;
    }

    #makeAvatar() {
      const el = document.createElement('div');
      el.className = 'tva-msg-avatar';
      el.setAttribute('aria-hidden', 'true');
      el.textContent = '🤖';
      return el;
    }
  }

  // ================================================================
  // ChatUI
  // ================================================================
  class ChatUI {
    #storage; #api; #lead;
    #unresolved = 0;
    #lastRole   = null; // for avatar grouping

    // Quick replies shown after the greeting
    static #QUICK_REPLIES = [
      'What services do you offer?',
      'How much does a website cost?',
      'I have a project in mind',
      'How do I contact you?',
    ];

    constructor() {
      this.#storage = new Storage();
      this.#api     = new ApiClient(this.#storage);
      this.#build();
      this.#lead = new LeadCapture(this.#api, ({ success, name }) => {
        if (success) {
          this.#addBotMessage(`Thanks ${name}! Someone from TechVaults will reach out on WhatsApp shortly. 🎉`);
        } else {
          this.#addBotMessage("Sorry, I couldn't save your details right now. Please reach us on WhatsApp directly.");
        }
      });
      this.#wire();
      this.#setWaLink();

      // Show unread badge if this is a fresh session
      if (!this.#storage.hasOpened()) {
        this.#els.badge.hidden = false;
      }
    }

    // ── DOM construction ─────────────────────────────────────────

    #els = {};

    #build() {
      const root = document.createElement('div');
      root.id = 'tva-chat-root';
      document.body.appendChild(root);

      // Launcher
      const launcher = this.#el('button', {
        id:              'tva-chat-launcher',
        'aria-label':    'Open chat with TechVaults assistant',
        'aria-expanded': 'false',
        'aria-controls': 'tva-chat-panel',
      });
      launcher.innerHTML = `
        <span class="tva-icon-open" aria-hidden="true">💬</span>
        <span class="tva-icon-close" aria-hidden="true">✕</span>`;

      const badge = this.#el('span', { 'aria-hidden': 'true' });
      badge.className = 'tva-badge';
      badge.textContent = '1';
      badge.hidden = true;
      launcher.appendChild(badge);

      // Panel
      const panel = this.#el('div', {
        id:           'tva-chat-panel',
        role:         'dialog',
        'aria-label': 'TechVaults chat assistant',
        'aria-modal': 'true',
      });

      // Header
      const header = document.createElement('div');
      header.id = 'tva-chat-header';
      header.innerHTML = `
        <div class="tva-header-avatar" aria-hidden="true">🤖</div>
        <div class="tva-header-info">
          <div class="tva-header-name">TechVaults Assistant</div>
          <div class="tva-header-status">Online now</div>
        </div>`;

      const closeBtn = this.#el('button', {
        id:           'tva-chat-close',
        'aria-label': 'Close chat',
      });
      closeBtn.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>`;
      header.appendChild(closeBtn);

      // Messages
      const messages = this.#el('div', {
        id:           'tva-chat-messages',
        'aria-live':  'polite',
        'aria-atomic':'false',
      });

      // Quick replies
      const qr = document.createElement('div');
      qr.id = 'tva-chat-quickreplies';

      // Input form
      const form = document.createElement('form');
      form.id = 'tva-chat-form';
      form.setAttribute('novalidate', '');

      const label = document.createElement('label');
      label.htmlFor = 'tva-chat-input';
      label.className = 'tva-sr-only';
      label.textContent = 'Type your question';

      const input = this.#el('input', {
        id:             'tva-chat-input',
        type:           'text',
        placeholder:    'Ask me anything…',
        autocomplete:   'off',
        maxlength:      '500',
        'aria-label':   'Message input',
      });

      const sendBtn = this.#el('button', {
        id:           'tva-chat-send',
        type:         'submit',
        'aria-label': 'Send message',
      });
      sendBtn.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;

      form.append(label, input, sendBtn);

      // Footer / WhatsApp
      const footer = document.createElement('div');
      footer.id = 'tva-chat-footer';

      const waLink = this.#el('a', {
        id:     'tva-chat-whatsapp',
        target: '_blank',
        rel:    'noopener noreferrer',
      });
      waLink.innerHTML = `
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Chat on WhatsApp`;
      footer.appendChild(waLink);

      panel.append(header, messages, qr, form, footer);
      root.append(launcher, panel);

      // Cache
      this.#els = { root, launcher, badge, panel, header, closeBtn, messages, qr, form, input, sendBtn, waLink, footer };
    }

    // ── Wiring ───────────────────────────────────────────────────

    #wire() {
      const { launcher, closeBtn, panel, form, input, waLink } = this.#els;

      launcher.addEventListener('click', () => this.#open());
      closeBtn.addEventListener('click', () => this.#close());

      panel.addEventListener('keydown', e => {
        if (e.key === 'Escape') this.#close();
      });

      form.addEventListener('submit', e => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        this.#send(text);
      });

      waLink.addEventListener('click', () => this.#api.logEvent('whatsapp_handoff'));

      if (typeof window.gtag === 'function') {
        launcher.addEventListener('click', () => gtag('event', 'tva_chat_opened'));
        waLink.addEventListener('click',   () => gtag('event', 'tva_whatsapp_handoff'));
      }
    }

    // ── Open / close ─────────────────────────────────────────────

    #open() {
      const { root, launcher, badge, panel, input } = this.#els;
      root.classList.add('is-open', 'has-opened');
      launcher.setAttribute('aria-expanded', 'true');
      badge.hidden = true;

      this.#api.logEvent('widget_opened');
      this.#storage.markOpened();

      // Show greeting + quick replies on first open
      if (this.#els.messages.children.length === 0) {
        this.#addBotMessage(cfg.greeting);
        this.#showQuickReplies();
      }

      panel.removeAttribute('hidden');
      // Focus input after transition settles
      setTimeout(() => input.focus(), 320);
    }

    #close() {
      const { root, launcher } = this.#els;
      root.classList.remove('is-open');
      launcher.setAttribute('aria-expanded', 'false');
      launcher.focus();
    }

    // ── Quick replies ────────────────────────────────────────────

    #showQuickReplies() {
      const container = this.#els.qr;
      container.innerHTML = '';

      ChatUI.#QUICK_REPLIES.forEach(text => {
        const btn = document.createElement('button');
        btn.className   = 'tva-quick-reply';
        btn.textContent = text;
        btn.type        = 'button';
        btn.setAttribute('aria-label', `Quick reply: ${text}`);
        btn.addEventListener('click', () => {
          container.innerHTML = ''; // Remove chips once one is chosen
          this.#send(text);
        });
        container.appendChild(btn);
      });
    }

    #clearQuickReplies() {
      this.#els.qr.innerHTML = '';
    }

    // ── Send flow ────────────────────────────────────────────────

    async #send(text) {
      this.#clearQuickReplies();
      this.#addUserMessage(text);
      this.#storage.pushTurn('user', text);
      this.#api.logEvent('message_sent', text);

      // Disable input while waiting
      this.#els.input.disabled   = true;
      this.#els.sendBtn.disabled = true;

      const typing = this.#addTypingIndicator();

      let reply;
      try {
        const data = await this.#api.sendMessage(text);
        reply = data.reply || "Sorry, I couldn't process that. Try WhatsApp instead.";
      } catch (err) {
        reply = err.status === 429
          ? "You've sent a lot of messages. Please wait a moment, or reach us on WhatsApp."
          : "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
      } finally {
        this.#els.input.disabled   = false;
        this.#els.sendBtn.disabled = false;
        this.#els.input.focus();
      }

      typing.remove();
      this.#addBotMessage(reply);
      this.#storage.pushTurn('assistant', reply);

      this.#trackUnresolved(reply);
      if (this.#lead.shouldTrigger(text)) this.#lead.render(this.#els.messages);
      this.#setWaLink(text);
    }

    // ── Message rendering ────────────────────────────────────────

    #addBotMessage(text) {
      const row = document.createElement('div');
      row.className = 'tva-msg-row tva-row-bot';

      // Avatar — hide for consecutive bot messages (grouping)
      const avatar = document.createElement('div');
      avatar.className = 'tva-msg-avatar';
      avatar.setAttribute('aria-hidden', 'true');
      avatar.textContent = '🤖';
      if (this.#lastRole === 'bot') row.classList.add('tva-hide-avatar');

      const wrap = document.createElement('div');
      wrap.style.display = 'flex';
      wrap.style.flexDirection = 'column';
      wrap.style.alignItems = 'flex-start';
      wrap.style.maxWidth = '76%';

      const bubble = document.createElement('div');
      bubble.className = 'tva-msg-bubble';
      bubble.textContent = text; // textContent is XSS-safe

      const time = document.createElement('div');
      time.className = 'tva-msg-time';
      time.textContent = this.#timeNow();

      wrap.append(bubble, time);
      row.append(avatar, wrap);
      this.#els.messages.appendChild(row);
      this.#scroll();

      this.#lastRole = 'bot';
      return row;
    }

    #addUserMessage(text) {
      const row = document.createElement('div');
      row.className = 'tva-msg-row tva-row-user';

      const wrap = document.createElement('div');
      wrap.style.display = 'flex';
      wrap.style.flexDirection = 'column';
      wrap.style.alignItems = 'flex-end';
      wrap.style.maxWidth = '76%';

      const bubble = document.createElement('div');
      bubble.className = 'tva-msg-bubble';
      bubble.textContent = text;

      const time = document.createElement('div');
      time.className = 'tva-msg-time';
      time.textContent = this.#timeNow();

      wrap.append(bubble, time);
      row.appendChild(wrap);
      this.#els.messages.appendChild(row);
      this.#scroll();

      this.#lastRole = 'user';
    }

    #addTypingIndicator() {
      const row = document.createElement('div');
      row.className = 'tva-typing-row';

      const avatar = document.createElement('div');
      avatar.className = 'tva-msg-avatar';
      avatar.setAttribute('aria-hidden', 'true');
      avatar.textContent = '🤖';

      const bubble = document.createElement('div');
      bubble.className = 'tva-typing-bubble';
      bubble.setAttribute('aria-label', 'Assistant is typing');
      bubble.setAttribute('role', 'status');
      for (let i = 0; i < 3; i++) {
        const dot = document.createElement('span');
        dot.className = 'tva-typing-dot';
        bubble.appendChild(dot);
      }

      row.append(avatar, bubble);
      this.#els.messages.appendChild(row);
      this.#scroll();
      return row;
    }

    #scroll() {
      const m = this.#els.messages;
      m.scrollTop = m.scrollHeight;
    }

    // ── Unresolved tracking ──────────────────────────────────────

    #trackUnresolved(reply) {
      const lower = reply.toLowerCase();
      const bad = lower.includes("i'm not sure") ||
                  lower.includes('connect you')  ||
                  lower.includes("i'm having trouble");
      this.#unresolved = bad ? this.#unresolved + 1 : 0;
      if (this.#unresolved >= 2) {
        this.#addBotMessage('Want me to connect you to someone on WhatsApp instead?');
        this.#unresolved = 0;
      }
    }

    // ── WhatsApp link ────────────────────────────────────────────

    #setWaLink(prefill) {
      const text = encodeURIComponent(
        prefill || 'Hi TechVaults, I have a question from your website chatbot.'
      );
      this.#els.waLink.href = `https://wa.me/${cfg.whatsapp}?text=${text}`;
    }

    // ── Utilities ────────────────────────────────────────────────

    #timeNow() {
      return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    #el(tag, attrs = {}) {
      const el = document.createElement(tag);
      for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
      return el;
    }
  }

  // ── Boot ────────────────────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new ChatUI());
  } else {
    new ChatUI();
  }

})();
