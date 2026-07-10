/**
 * TechVaults AI Chat Widget
 * Vanilla JS — no build step, no framework dependency.
 * All API communication goes through WordPress REST endpoints (server-side).
 * No API keys, no secrets reach the browser.
 */
(function () {
  'use strict';

  const cfg = window.tvaChatConfig;
  if (!cfg) return; // Plugin not configured yet — bail silently.

  // ── Session identity ────────────────────────────────────────────────────────
  // sessionStorage is scoped to the browser tab; a new session starts when the
  // tab is closed, which is the correct granularity for conversation context.
  const sessionId =
    sessionStorage.getItem('tva_session_id') || crypto.randomUUID();
  sessionStorage.setItem('tva_session_id', sessionId);

  // ── State ───────────────────────────────────────────────────────────────────
  let history         = [];   // [ { role: 'user'|'assistant', content: string } ]
  let leadCaptured    = false;
  let unresolvedStreak = 0;

  // ── Build widget DOM ─────────────────────────────────────────────────────────
  const root = document.createElement('div');
  root.id = 'tva-chat-root';
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

      <a
        id="tva-chat-whatsapp"
        target="_blank"
        rel="noopener noreferrer"
      >Chat on WhatsApp instead</a>
    </div>`;

  document.body.appendChild(root);

  // Element references.
  const panel       = document.getElementById('tva-chat-panel');
  const messagesEl  = document.getElementById('tva-chat-messages');
  const form        = document.getElementById('tva-chat-form');
  const input       = document.getElementById('tva-chat-input');
  const launcher    = document.getElementById('tva-chat-launcher');
  const closeBtn    = document.getElementById('tva-chat-close');
  const whatsappLink = document.getElementById('tva-chat-whatsapp');

  // ── Helpers ──────────────────────────────────────────────────────────────────

  function addMessage(role, text) {
    const el = document.createElement('div');
    el.className = 'tva-msg tva-msg-' + role;
    // textContent is XSS-safe — no innerHTML for user/bot text.
    el.textContent = text;
    messagesEl.appendChild(el);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function setWhatsappLink(prefill) {
    const text = encodeURIComponent(
      prefill || 'Hi TechVaults, I have a question from your website chatbot.'
    );
    whatsappLink.href = `https://wa.me/${cfg.whatsapp}?text=${text}`;
  }

  async function postJSON(path, body) {
    const res = await fetch(cfg.restUrl + path, {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   cfg.nonce,
      },
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      const err = new Error('HTTP ' + res.status);
      err.status = res.status;
      throw err;
    }

    return res.json();
  }

  function logEvent(eventType, message) {
    postJSON('event', {
      session_id: sessionId,
      event_type: eventType,
      page_url:   location.href,
      message:    message || '',
    }).catch(() => {}); // Fire-and-forget; never block the UI.
  }

  // ── Lead capture ─────────────────────────────────────────────────────────────

  const LEAD_TRIGGER_WORDS = [
    'quote', 'price', 'pricing', 'cost', 'start a project',
    'hire', 'get started', 'consultation', 'build', 'develop',
  ];

  function maybeAskForLead(userText) {
    if (leadCaptured) return false;
    const lower = userText.toLowerCase();
    return LEAD_TRIGGER_WORDS.some((w) => lower.includes(w));
  }

  function showLeadForm() {
    const wrap = document.createElement('div');
    wrap.className = 'tva-lead-form';

    const prompt = document.createElement('p');
    prompt.textContent =
      'Happy to help. Can I get your name and WhatsApp number so someone from the team can follow up with details?';
    wrap.appendChild(prompt);

    const nameInput = document.createElement('input');
    nameInput.type        = 'text';
    nameInput.placeholder = 'Your name';
    nameInput.setAttribute('aria-label', 'Your name');
    nameInput.maxLength   = 120;
    wrap.appendChild(nameInput);

    const phoneInput = document.createElement('input');
    phoneInput.type        = 'text';
    phoneInput.placeholder = 'WhatsApp number';
    phoneInput.setAttribute('aria-label', 'WhatsApp number');
    phoneInput.maxLength   = 30;
    wrap.appendChild(phoneInput);

    const submitBtn = document.createElement('button');
    submitBtn.type        = 'button';
    submitBtn.textContent = 'Send';
    wrap.appendChild(submitBtn);

    messagesEl.appendChild(wrap);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    submitBtn.addEventListener('click', async () => {
      const name  = nameInput.value.trim();
      const phone = phoneInput.value.trim();

      if (!name || !phone) {
        nameInput.style.borderColor  = name  ? '' : '#BC0004';
        phoneInput.style.borderColor = phone ? '' : '#BC0004';
        return;
      }

      submitBtn.disabled = true;

      try {
        await postJSON('lead', {
          session_id:  sessionId,
          name,
          phone,
          stated_need: history
            .filter((h) => h.role === 'user')
            .map((h) => h.content)
            .join(' | '),
          source_url:  location.href,
          transcript:  JSON.stringify(history),
        });

        leadCaptured = true;
        wrap.remove();
        addMessage('bot', `Thanks ${name}, someone from TechVaults will reach out on WhatsApp shortly.`);
        logEvent('lead_form_submitted', name);
      } catch {
        submitBtn.disabled = false;
        addMessage('bot', "Sorry, I couldn't save your details. Please try WhatsApp directly.");
      }
    });
  }

  // ── Send flow ─────────────────────────────────────────────────────────────────

  async function sendMessage(text) {
    addMessage('user', text);
    history.push({ role: 'user', content: text });
    logEvent('message_sent', text);

    // Typing indicator.
    const typing = document.createElement('div');
    typing.className  = 'tva-msg tva-msg-bot tva-typing';
    typing.textContent = 'Typing…';
    typing.setAttribute('aria-label', 'Assistant is typing');
    messagesEl.appendChild(typing);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    let reply;

    try {
      const data = await postJSON('message', {
        session_id: sessionId,
        message:    text,
        history:    history.slice(-8), // Last 8 turns; enough context, keeps latency low.
        page_url:   location.href,
      });
      reply = data.reply || "Sorry, I couldn't process that. Try WhatsApp instead.";
    } catch (err) {
      if (err.status === 429) {
        reply = "You've sent a lot of messages. Please wait a moment and try again, or reach us on WhatsApp.";
      } else {
        reply = "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
      }
    }

    typing.remove();
    addMessage('bot', reply);
    history.push({ role: 'assistant', content: reply });

    // Track unresolved streak.
    const unresolved =
      reply.toLowerCase().includes("i'm not sure") ||
      reply.toLowerCase().includes('connect you') ||
      reply.toLowerCase().includes("i'm having trouble");

    unresolvedStreak = unresolved ? unresolvedStreak + 1 : 0;

    if (unresolvedStreak >= 2) {
      addMessage('bot', 'Want me to connect you to someone on WhatsApp instead?');
      unresolvedStreak = 0;
    }

    if (maybeAskForLead(text)) showLeadForm();
    setWhatsappLink(text);
  }

  // ── Event listeners ───────────────────────────────────────────────────────────

  launcher.addEventListener('click', () => {
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
    logEvent('widget_opened');

    if (messagesEl.children.length === 0) {
      addMessage('bot', cfg.greeting);
    }

    input.focus();
  });

  closeBtn.addEventListener('click', () => {
    panel.hidden = true;
    launcher.setAttribute('aria-expanded', 'false');
    launcher.focus();
  });

  // Close on Escape key.
  panel.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      panel.hidden = true;
      launcher.setAttribute('aria-expanded', 'false');
      launcher.focus();
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    sendMessage(text);
  });

  whatsappLink.addEventListener('click', () => logEvent('whatsapp_handoff'));

  // ── GA4 bridge ────────────────────────────────────────────────────────────────
  if (typeof window.gtag === 'function') {
    launcher.addEventListener('click', () => gtag('event', 'tva_chat_opened'));
    whatsappLink.addEventListener('click', () => gtag('event', 'tva_whatsapp_handoff'));
  }

  // ── Initialise WhatsApp link ──────────────────────────────────────────────────
  setWhatsappLink();
})();
