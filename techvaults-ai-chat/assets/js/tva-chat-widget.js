/**
 * TechVaults AI Chat Widget  v1.2.0  — Material Design 3
 *
 * Icons:  Material Symbols Rounded (loaded via Google Fonts CDN)
 * Font:   Google Sans (loaded via Google Fonts CDN)
 * Uses:   <span class="ms">icon_name</span> pattern throughout
 *
 * Four classes, one file, zero dependencies, zero build step.
 *   Storage     — sessionStorage: session ID, history, opened flag
 *   ApiClient   — all fetch() calls to /tva/v1/* REST endpoints
 *   LeadCapture — trigger detection, MD3 card form, submission
 *   ChatUI      — full DOM construction, MD3 components, event wiring
 */
(function () {
  'use strict';

  const cfg = window.tvaChatConfig;
  if (!cfg) return;

  /* ─────────────────────────────────────────────────────────────────────────
     Storage
  ───────────────────────────────────────────────────────────────────────── */
  class Storage {
    #S = 'tva_sid';
    #H = 'tva_hist';
    #O = 'tva_opened';

    sessionId() {
      let id = sessionStorage.getItem(this.#S);
      if (!id) {
        id = typeof crypto.randomUUID === 'function'
          ? crypto.randomUUID()
          : Date.now().toString(36) + Math.random().toString(36).slice(2);
        sessionStorage.setItem(this.#S, id);
      }
      return id;
    }

    history(n = 8) {
      try {
        return (JSON.parse(sessionStorage.getItem(this.#H)) || []).slice(-n);
      } catch { return []; }
    }

    push(role, content) {
      const a = this.history(100);
      a.push({ role, content });
      sessionStorage.setItem(this.#H, JSON.stringify(a));
    }

    userText()   { return this.history(100).filter(t => t.role === 'user').map(t => t.content).join(' | '); }
    transcript() { return sessionStorage.getItem(this.#H) || '[]'; }
    hasOpened()  { return !!sessionStorage.getItem(this.#O); }
    markOpened() { sessionStorage.setItem(this.#O, '1'); }
  }

  /* ─────────────────────────────────────────────────────────────────────────
     ApiClient
  ───────────────────────────────────────────────────────────────────────── */
  class ApiClient {
    #s;
    constructor(s) { this.#s = s; }

    send(text) {
      return this.#post('message', {
        session_id: this.#s.sessionId(),
        message:    text,
        history:    this.#s.history(),
        page_url:   location.href,
      });
    }

    lead(name, phone) {
      return this.#post('lead', {
        session_id:  this.#s.sessionId(),
        name, phone,
        stated_need: this.#s.userText(),
        source_url:  location.href,
        transcript:  this.#s.transcript(),
      });
    }

    log(type, msg) {
      this.#post('event', {
        session_id: this.#s.sessionId(),
        event_type: type,
        page_url:   location.href,
        message:    msg || '',
      }).catch(() => {});
    }

    async #post(path, body) {
      const r = await fetch(cfg.restUrl + path, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
        body:    JSON.stringify(body),
      });
      if (!r.ok) { const e = new Error('HTTP ' + r.status); e.status = r.status; throw e; }
      return r.json();
    }
  }

  /* ─────────────────────────────────────────────────────────────────────────
     Helpers
  ───────────────────────────────────────────────────────────────────────── */

  /** Create element with optional class. */
  function mk(tag, cls) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    return el;
  }

  /** Material Symbol icon span. */
  function icon(name, extraClass) {
    const s = mk('span', 'ms' + (extraClass ? ' ' + extraClass : ''));
    s.setAttribute('aria-hidden', 'true');
    s.textContent = name;
    return s;
  }

  /** Time string hh:mm. */
  function hhMM() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  /* ─────────────────────────────────────────────────────────────────────────
     LeadCapture
  ───────────────────────────────────────────────────────────────────────── */
  class LeadCapture {
    static #T = [
      'quote','price','pricing','cost','start a project',
      'hire','get started','consultation','build','develop',
    ];

    #api; #cb; #done = false;
    constructor(api, cb) { this.#api = api; this.#cb = cb; }

    triggered(text) {
      return !this.#done && LeadCapture.#T.some(w => text.toLowerCase().includes(w));
    }

    /** Render MD3 filled card lead form into the messages container. */
    render(container) {
      // Wrap in a bot-row so it left-aligns like bot messages
      const row  = mk('div', 'tva-row tva-row-bot');
      const av   = this.#avatar();
      const wrap = mk('div', 'tva-bub-wrap');
      const card = mk('div', 'tva-lead-card');

      const p = mk('p');
      p.textContent = 'Happy to help! Can I get your name and WhatsApp number so the team can follow up?';

      const ni = this.#field('text', 'Your name',       120);
      const pi = this.#field('tel',  'WhatsApp number',  30);

      const btn = mk('button', 'tva-lead-btn');
      btn.type = 'button';
      btn.appendChild(icon('send', 'ms-send'));
      btn.appendChild(Object.assign(mk('span'), { textContent: 'Send details' }));

      card.append(p, ni, pi, btn);
      wrap.appendChild(card);
      row.append(av, wrap);
      container.appendChild(row);
      container.scrollTop = container.scrollHeight;
      ni.focus();

      btn.addEventListener('click', async () => {
        const name  = ni.value.trim();
        const phone = pi.value.trim();
        ni.classList.toggle('tva-err',  !name);
        pi.classList.toggle('tva-err', !phone);
        if (!name || !phone) return;

        btn.disabled = true;
        btn.querySelector('span:not(.ms)').textContent = 'Sending…';

        try {
          await this.#api.lead(name, phone);
          this.#done = true;
          row.remove();
          this.#cb(true, name);
          this.#api.log('lead_form_submitted');
        } catch {
          btn.disabled = false;
          btn.querySelector('span:not(.ms)').textContent = 'Try again';
          this.#cb(false);
        }
      });
    }

    #field(type, ph, max) {
      const el = mk('input', 'tva-lead-field');
      el.type = type; el.placeholder = ph; el.maxLength = max;
      el.setAttribute('aria-label', ph);
      return el;
    }

    #avatar() {
      const av = mk('div', 'tva-avatar');
      av.setAttribute('aria-hidden', 'true');
      av.appendChild(icon('smart_toy'));
      return av;
    }
  }

  /* ─────────────────────────────────────────────────────────────────────────
     ChatUI
  ───────────────────────────────────────────────────────────────────────── */
  class ChatUI {
    #s; #api; #lead;
    #streak = 0;
    #lastRole = null;
    #el = {};

    static #QR = [
      'What services do you offer?',
      'How much does a website cost?',
      'I have a project in mind',
      'How do I contact you?',
    ];

    constructor() {
      this.#s   = new Storage();
      this.#api  = new ApiClient(this.#s);
      this.#dom();
      this.#lead = new LeadCapture(this.#api, (ok, name) => {
        this.#bot(ok
          ? `Thanks ${name}! Someone from TechVaults will reach out on WhatsApp shortly. 🎉`
          : "Sorry, I couldn't save your details. Please reach us on WhatsApp directly.");
      });
      this.#wire();
      this.#waHref();
      if (!this.#s.hasOpened()) {
        this.#el.badge.style.display = 'flex';
      }
    }

    /* ── Build DOM ─────────────────────────────────────────────────────── */
    #dom() {
      const root = mk('div');
      root.id = 'tva-root';
      document.body.appendChild(root);

      /* ── FAB Launcher ── */
      const btn = mk('button');
      btn.id = 'tva-btn';
      btn.setAttribute('aria-label', 'Open TechVaults chat assistant');
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-controls', 'tva-panel');

      // Open icon: chat_bubble  Close icon: close
      const icoOpen  = icon('chat_bubble', 'tva-ico tva-ico-open');
      const icoClose = icon('close',       'tva-ico tva-ico-close');
      btn.append(icoOpen, icoClose);

      // MD3 badge
      const badge = mk('span', 'tva-badge');
      badge.setAttribute('aria-label', '1 unread message');
      badge.textContent = '1';
      badge.style.display = 'none';
      btn.appendChild(badge);

      /* ── Panel ── */
      const panel = mk('div');
      panel.id = 'tva-panel';
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', 'TechVaults chat assistant');
      panel.setAttribute('aria-modal', 'true');

      /* ── Header (MD3 Top App Bar) ── */
      const hdr = mk('div');
      hdr.id = 'tva-hdr';

      const av = mk('div', 'tva-hdr-av');
      av.setAttribute('aria-hidden', 'true');
      av.textContent = 'TV';

      const info = mk('div', 'tva-hdr-info');
      const name = mk('span', 'tva-hdr-name'); name.textContent = 'TechVaults Assistant';
      const stat = mk('span', 'tva-hdr-status');
      const dot  = mk('i', 'tva-dot'); dot.setAttribute('aria-hidden', 'true');
      stat.append(dot, document.createTextNode('Online now'));
      info.append(name, stat);

      // MD3 Icon button — close
      const closeBtn = mk('button', 'tva-close');
      closeBtn.setAttribute('aria-label', 'Close chat');
      closeBtn.appendChild(icon('close'));

      hdr.append(av, info, closeBtn);

      /* ── Messages ── */
      const msgs = mk('div');
      msgs.id = 'tva-msgs';
      msgs.setAttribute('aria-live', 'polite');
      msgs.setAttribute('aria-atomic', 'false');

      /* ── Quick replies ── */
      const qr = mk('div');
      qr.id = 'tva-qr';

      /* ── Input form ── */
      const form = mk('form');
      form.id = 'tva-form';
      form.setAttribute('novalidate', '');

      const lbl = mk('label', 'tva-sr');
      lbl.htmlFor = 'tva-inp';
      lbl.textContent = 'Message';

      const inp = mk('input');
      inp.id = 'tva-inp';
      inp.type = 'text';
      inp.placeholder = 'Ask me anything…';
      inp.autocomplete = 'off';
      inp.maxLength = 500;
      inp.setAttribute('aria-label', 'Message');

      // MD3 small FAB send button
      const sendBtn = mk('button', 'tva-send');
      sendBtn.type = 'submit';
      sendBtn.setAttribute('aria-label', 'Send message');
      sendBtn.appendChild(icon('send'));

      form.append(lbl, inp, sendBtn);

      /* ── Footer / WhatsApp ── */
      const foot = mk('div');
      foot.id = 'tva-foot';

      const wa = mk('a', 'tva-wa');
      wa.target = '_blank';
      wa.rel = 'noopener noreferrer';
      // WhatsApp SVG logo (official green)
      wa.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="#25d366" aria-hidden="true">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>`;
      const waText = mk('span', 'tva-wa-text');
      waText.textContent = 'Chat on WhatsApp';
      wa.appendChild(waText);
      foot.appendChild(wa);

      panel.append(hdr, msgs, qr, form, foot);
      root.append(btn, panel);

      this.#el = { root, btn, badge, panel, closeBtn, msgs, qr, form, inp, sendBtn, wa };
    }

    /* ── Wire events ─────────────────────────────────────────────────── */
    #wire() {
      const { btn, closeBtn, panel, form, inp, wa } = this.#el;
      btn.addEventListener('click',      () => this.#open());
      closeBtn.addEventListener('click', () => this.#close());
      panel.addEventListener('keydown',  e  => { if (e.key === 'Escape') this.#close(); });
      form.addEventListener('submit',    e  => {
        e.preventDefault();
        const t = inp.value.trim();
        if (t) { inp.value = ''; this.#send(t); }
      });
      wa.addEventListener('click', () => this.#api.log('whatsapp_handoff'));
      if (typeof window.gtag === 'function') {
        btn.addEventListener('click', () => gtag('event', 'tva_chat_opened'));
        wa.addEventListener('click',  () => gtag('event', 'tva_whatsapp_handoff'));
      }
    }

    /* ── Open / close ────────────────────────────────────────────────── */
    #open() {
      const { root, btn, badge, msgs, inp } = this.#el;
      root.classList.add('tva-open');
      btn.setAttribute('aria-expanded', 'true');
      badge.style.display = 'none';
      this.#api.log('widget_opened');
      this.#s.markOpened();
      if (!msgs.children.length) {
        this.#bot(cfg.greeting);
        this.#showQR();
      }
      setTimeout(() => inp.focus(), 280);
    }

    #close() {
      this.#el.root.classList.remove('tva-open');
      this.#el.btn.setAttribute('aria-expanded', 'false');
      this.#el.btn.focus();
    }

    /* ── Quick replies ───────────────────────────────────────────────── */
    #showQR() {
      const c = this.#el.qr;
      c.innerHTML = '';
      ChatUI.#QR.forEach(text => {
        const b = mk('button', 'tva-qr-btn');
        b.type = 'button';
        b.textContent = text;
        b.addEventListener('click', () => { c.innerHTML = ''; this.#send(text); });
        c.appendChild(b);
      });
    }

    /* ── Send flow ───────────────────────────────────────────────────── */
    async #send(text) {
      this.#el.qr.innerHTML = '';
      this.#user(text);
      this.#s.push('user', text);
      this.#api.log('message_sent', text);

      this.#el.inp.disabled     = true;
      this.#el.sendBtn.disabled = true;
      const ty = this.#typingRow();

      let reply;
      try {
        const d = await this.#api.send(text);
        reply = d.reply || "Sorry, I couldn't process that. Try WhatsApp instead.";
      } catch (e) {
        reply = e.status === 429
          ? "You've sent a lot of messages. Please wait a moment or reach us on WhatsApp."
          : "I'm having trouble connecting right now. Please reach us directly on WhatsApp.";
      } finally {
        this.#el.inp.disabled     = false;
        this.#el.sendBtn.disabled = false;
        this.#el.inp.focus();
      }

      ty.remove();
      this.#bot(reply);
      this.#s.push('assistant', reply);
      this.#checkStreak(reply);
      if (this.#lead.triggered(text)) this.#lead.render(this.#el.msgs);
      this.#waHref(text);
    }

    /* ── Message builders ────────────────────────────────────────────── */
    #bot(text) {
      const row  = mk('div', 'tva-row tva-row-bot');
      const av   = mk('div', 'tva-avatar');
      av.setAttribute('aria-hidden', 'true');
      av.appendChild(icon('smart_toy'));
      if (this.#lastRole === 'bot') av.classList.add('tva-av-hide');

      const wrap = mk('div', 'tva-bub-wrap');
      const bub  = mk('div', 'tva-bub tva-bub-bot');
      bub.textContent = text;
      const ts = mk('div', 'tva-time');
      ts.textContent = hhMM();
      wrap.append(bub, ts);
      row.append(av, wrap);
      this.#el.msgs.appendChild(row);
      this.#scroll();
      this.#lastRole = 'bot';
    }

    #user(text) {
      const row  = mk('div', 'tva-row tva-row-usr');
      const wrap = mk('div', 'tva-bub-wrap');
      const bub  = mk('div', 'tva-bub tva-bub-usr');
      bub.textContent = text;
      const ts = mk('div', 'tva-time');
      ts.textContent = hhMM();
      wrap.append(bub, ts);
      row.appendChild(wrap);
      this.#el.msgs.appendChild(row);
      this.#scroll();
      this.#lastRole = 'user';
    }

    #typingRow() {
      const row = mk('div', 'tva-row tva-row-bot');
      const av  = mk('div', 'tva-avatar');
      av.setAttribute('aria-hidden', 'true');
      av.appendChild(icon('smart_toy'));

      const bub = mk('div', 'tva-typing');
      bub.setAttribute('role', 'status');
      bub.setAttribute('aria-label', 'Assistant is typing');
      for (let i = 0; i < 3; i++) bub.appendChild(mk('span', 'tva-dot'));

      row.append(av, bub);
      this.#el.msgs.appendChild(row);
      this.#scroll();
      return row;
    }

    #scroll() {
      const m = this.#el.msgs;
      m.scrollTop = m.scrollHeight;
    }

    /* ── Unresolved streak ───────────────────────────────────────────── */
    #checkStreak(reply) {
      const l = reply.toLowerCase();
      const bad = l.includes("i'm not sure") || l.includes('connect you') || l.includes("i'm having trouble");
      this.#streak = bad ? this.#streak + 1 : 0;
      if (this.#streak >= 2) {
        this.#bot('Want me to connect you to someone on WhatsApp instead?');
        this.#streak = 0;
      }
    }

    /* ── WhatsApp href ───────────────────────────────────────────────── */
    #waHref(pre) {
      const t = encodeURIComponent(pre || 'Hi TechVaults, I have a question from your website chatbot.');
      this.#el.wa.href = `https://wa.me/${cfg.whatsapp}?text=${t}`;
    }
  }

  /* Boot */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new ChatUI());
  } else {
    new ChatUI();
  }
})();
