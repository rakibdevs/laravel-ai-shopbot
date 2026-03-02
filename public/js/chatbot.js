/**
 * AI Shopbot Widget
 * Vanilla JS, zero dependencies
 */
(function () {
    'use strict';

    const C = window.__AiShopbot || {};

    // ── State ──────────────────────────────────────────────────────────────────
    let sessionId   = null;
    let isOpen      = false;
    let isLoading   = false;
    let suggestTimer = null;

    // ── DOM refs (lazy) ────────────────────────────────────────────────────────
    const $ = id => document.getElementById(id);

    const DOM = {
        root:       () => $('ai-shopbot-root'),
        launcher:   () => $('chatbot-launcher'),
        panel:      () => $('chatbot-panel'),
        messages:   () => $('cb-messages'),
        input:      () => $('cb-input'),
        send:       () => $('cb-send'),
        suggest:    () => $('cb-suggestions'),
        unread:     () => $('chatbot-unread'),
        iconOpen:   () => $('chatbot-icon-open'),
        iconClose:  () => $('chatbot-icon-close'),
    };

    // ── Boot ───────────────────────────────────────────────────────────────────
    async function init() {
        applyColor();
        bindEvents();
        await createSession();
    }

    function applyColor() {
        const root = DOM.root();
        if (root && C.color) {
            root.style.setProperty('--cb-color', C.color);
        }
    }

    async function createSession() {
        try {
            const data = await post(C.sessionUrl, {});
            sessionId  = data.session_id;
            appendBotMessage(data.greeting || C.greeting);
            if (data.featured?.length) renderProductCards(data.featured, false);
        } catch {
            sessionId = 'fallback-' + Math.random().toString(36).slice(2, 10);
            appendBotMessage(C.greeting);
        }
    }

    // ── Events ─────────────────────────────────────────────────────────────────
    function bindEvents() {
        DOM.launcher().addEventListener('click', togglePanel);
        DOM.send().addEventListener('click', sendMessage);
        DOM.input().addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
        DOM.input().addEventListener('input', onInputChange);

        // Close suggestions on outside click
        document.addEventListener('click', e => {
            if (!DOM.root().contains(e.target)) hideSuggestions();
        });
    }

    function togglePanel() {
        isOpen = !isOpen;
        const panel  = DOM.panel();
        const iconO  = DOM.iconOpen();
        const iconC  = DOM.iconClose();

        panel.style.display  = isOpen ? 'flex' : 'none';
        panel.setAttribute('aria-hidden', String(!isOpen));
        iconO.style.display  = isOpen ? 'none'  : '';
        iconC.style.display  = isOpen ? ''      : 'none';

        if (isOpen) {
            clearUnread();
            DOM.input().focus();
            scrollToBottom();
        }
    }

    // ── Messaging ──────────────────────────────────────────────────────────────
    async function sendMessage() {
        const input = DOM.input();
        const text  = input.value.trim();
        if (!text || isLoading || !sessionId) return;

        input.value = '';
        hideSuggestions();
        appendUserMessage(text);

        const typing = appendTyping();
        setLoading(true);

        try {
            const data = await post(C.messageUrl, { session_id: sessionId, message: text });
            typing.remove();
            appendBotMessage(data.message);
            // Only render product cards when the backend explicitly says to.
            // show_products is set by ChatbotService based on intent detection.
            if (data.show_products && data.products?.length) {
                renderProductCards(data.products, true);
            }

        } catch {
            typing.remove();
            appendBotMessage('⚠️ Unable to connect. Please try again.');
        } finally {
            setLoading(false);
            input.focus();
        }
    }

    function appendUserMessage(text) {
        const div = document.createElement('div');
        div.className = 'cb-msg cb-msg--user';
        div.innerHTML = `<div class="cb-bubble">${esc(text)}</div>`;
        DOM.messages().appendChild(div);
        scrollToBottom();
    }

    function appendBotMessage(text) {
        const div = document.createElement('div');
        div.className = 'cb-msg cb-msg--bot';
        div.innerHTML = `<div class="cb-bubble">${markdownToHtml(text)}</div>`;
        DOM.messages().appendChild(div);
        scrollToBottom();
        if (!isOpen) showUnread();
    }

    // ── Markdown → HTML converter ──────────────────────────────────────────────
    // Handles the common markdown patterns LLMs emit so they render cleanly.
    function markdownToHtml(text) {
        if (!text) return '';

        let s = text;

        // 1. Escape raw HTML entities first to prevent XSS
        s = s.replace(/&/g, '&amp;')
             .replace(/</g, '&lt;')
             .replace(/>/g, '&gt;');

        // 2. Extract and replace markdown links [label](url) → <a> before bold
        s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
            (_, label, url) => `<a href="${url}" target="_blank" rel="noopener" class="cb-link">${label}</a>`);

        // 3. Convert bare URLs to links (not already inside an <a>)
        s = s.replace(/(?<!href=")(https?:\/\/[^\s<"]+)/g,
            url => `<a href="${url}" target="_blank" rel="noopener" class="cb-link">${url}</a>`);

        // 4. Bold: **text** or __text__
        s = s.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/__([^_\n]+?)__/g, '<strong>$1</strong>');

        // 5. Italic: *text* or _text_ (single, not double)
        s = s.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
        s = s.replace(/_([^_\n]+?)_/g, '<em>$1</em>');

        // 6. Inline code: `code`
        s = s.replace(/`([^`]+?)`/g, '<code class="cb-code">$1</code>');

        // 7. Horizontal rules: --- or ***
        s = s.replace(/^[-*]{3,}\s*$/gm, '<hr class="cb-hr">');

        // 8. Headers: ## Heading → <strong> (LLMs sometimes emit these)
        s = s.replace(/^#{1,3}\s+(.+)$/gm, '<strong class="cb-heading">$1</strong>');

        // 9. Unordered lists: lines starting with - or * or •
        s = s.replace(/^[\-\*•]\s+(.+)$/gm, '<li>$1</li>');

        // 10. Numbered lists: lines starting with 1. 2. etc.
        s = s.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');

        // 11. Wrap consecutive <li> items in <ul>
        s = s.replace(/(<li>.*<\/li>\n?)+/gs, match => `<ul class="cb-list">${match}</ul>`);

        // 12. Newlines → <br> (but not inside lists)
        s = s.replace(/\n(?!<\/?(ul|li|hr))/g, '<br>');

        // 13. Clean up any leftover double <br><br> → single
        s = s.replace(/(<br>){2,}/g, '<br><br>');

        return s;
    }



    function appendTyping() {
        const div = document.createElement('div');
        div.className = 'cb-msg cb-msg--bot';
        div.innerHTML = `<div class="cb-bubble cb-typing"><span></span><span></span><span></span></div>`;
        DOM.messages().appendChild(div);
        scrollToBottom();
        return div;
    }

    // ── Product Cards ──────────────────────────────────────────────────────────
    function renderProductCards(products, showLabel) {
        if (!products.length) return;

        const wrap = document.createElement('div');
        wrap.className = 'cb-cards';

        if (showLabel) {
            const label = document.createElement('div');
            label.className = 'cb-cards-label';
            label.textContent = 'Matching Products';
            wrap.appendChild(label);
        }

        const row = document.createElement('div');
        row.className = 'cb-cards-row';

        products.slice(0, 4).forEach(p => {
            const inStock   = Boolean(p.in_stock);
            const hasDisc   = parseFloat(p.discounted_price) < parseFloat(p.price);
            const priceHtml = hasDisc
                ? `<span class="cb-price-new">${p.discounted_price}</span><span class="cb-price-old">${p.price}</span>`
                : `<span class="cb-price-new">${p.price}</span>`;

            const a     = document.createElement('a');
            a.href      = `/product/${p.slug}`;
            a.className = 'cb-card';
            a.innerHTML = `
                <img src="${esc(p.thumbnail || '')}" alt="${esc(p.name)}"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22%3E%3Crect width=%2260%22 height=%2260%22 fill=%22%23eee%22/%3E%3C/svg%3E'">
                <div class="cb-card-body">
                    <div class="cb-card-name">${esc(p.name)}</div>
                    <div class="cb-card-price">${priceHtml}</div>
                    <span class="cb-stock-badge ${inStock ? 'cb-in' : 'cb-out'}">${inStock ? `✓ ${p.stock} left` : '✗ Out of stock'}</span>
                </div>`;
            row.appendChild(a);
        });

        wrap.appendChild(row);
        DOM.messages().appendChild(wrap);
        scrollToBottom();
    }

    // ── Live Suggestions ───────────────────────────────────────────────────────
    function onInputChange() {
        clearTimeout(suggestTimer);
        const q = DOM.input().value.trim();
        
        if (q.length < 3 ) {
            hideSuggestions();
            return;
        }

        suggestTimer = setTimeout(() => fetchSuggestions(q), 400);
    }

    async function fetchSuggestions(q) {
        try {
            const res  = await fetch(`${C.suggestUrl}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf },
            });
            const data = await res.json();
            renderSuggestions(data.products || []);
        } catch { hideSuggestions(); }
    }

    function renderSuggestions(products) {
        const box = DOM.suggest();
        box.innerHTML = '';
        if (!products.length) { box.style.display = 'none'; return; }

        products.slice(0, 6).forEach(p => {
            const item  = document.createElement('div');
            item.className = 'cb-suggest-item';
            item.innerHTML = `
                <img src="${esc(p.thumbnail || '')}" alt=""
                     onerror="this.style.display='none'">
                <div class="cb-suggest-info">
                    <span class="cb-suggest-name">${esc(p.name)}</span>
                    <span class="cb-suggest-price">${p.discounted_price}</span>
                </div>
                <span class="cb-suggest-stock ${p.in_stock ? 'cb-in' : 'cb-out'}">${p.in_stock ? '✓' : '✗'}</span>`;
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                DOM.input().value = p.name;
                hideSuggestions();
                sendMessage();
            });
            box.appendChild(item);
        });

        box.style.display = 'block';
    }

    function hideSuggestions() { DOM.suggest().style.display = 'none'; }

    // ── Helpers ────────────────────────────────────────────────────────────────
    function scrollToBottom() {
        const m = DOM.messages();
        requestAnimationFrame(() => { m.scrollTop = m.scrollHeight; });
    }

    function setLoading(state) {
        isLoading = state;
        DOM.send().disabled = state;
        DOM.input().disabled = state;
    }

    function showUnread() {
        const u = DOM.unread();
        const n = parseInt(u.textContent || '0', 10) + 1;
        u.textContent = n > 9 ? '9+' : String(n);
        u.style.display = 'flex';
    }

    function clearUnread() {
        DOM.unread().style.display = 'none';
        DOM.unread().textContent   = '0';
    }

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function post(url, body) {
        const res = await fetch(url, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': C.csrf,
            },
            body: JSON.stringify(body),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ── Start ──────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
