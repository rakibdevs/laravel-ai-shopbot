{{--
    AI Chatbot Widget
    Include once in your main layout, before </body>:

    @include('ai_shopbot::widget.chatbot')
--}}
@if(config('ai_shopbot.widget.enabled', true))

<link rel="stylesheet" href="{{ asset('vendor/ai-shopbot/css/chatbot.css') }}">

<div id="ai-shopbot-root"
     data-position="{{ config('ai_shopbot.widget.position', 'bottom-right') }}"
     data-color="{{ config('ai_shopbot.widget.primary_color', '#007bff') }}">

    {{-- Launcher button --}}
    <button id="chatbot-launcher" aria-label="Open shopping assistant" title="{{ config('ai_shopbot.widget.title') }}">
        <svg id="chatbot-icon-open"  xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 00-2 2v18l4-4h14a2 2 0 002-2V4a2 2 0 00-2-2z"/></svg>
        <svg id="chatbot-icon-close" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24" style="display:none"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        <span id="chatbot-unread" aria-label="Unread messages" style="display:none"></span>
    </button>

    {{-- Chat panel --}}
    <div id="chatbot-panel" role="dialog" aria-label="{{ config('ai_shopbot.widget.title') }}" aria-hidden="true">

        <div class="cb-header">
            <div class="cb-header-left">
                <span class="cb-avatar">🛍️</span>
                <div>
                    <div class="cb-title">{{ config('ai_shopbot.widget.title') }}</div>
                    <div class="cb-status"><span class="cb-status-dot"></span> Online</div>
                </div>
            </div>
        </div>

        <div id="cb-messages" role="log" aria-live="polite" aria-relevant="additions"></div>

        <div id="cb-suggestions"></div>

        <div class="cb-footer">
            <input
                type="text"
                id="cb-input"
                placeholder="{{ config('ai_shopbot.widget.placeholder') }}"
                autocomplete="off"
                maxlength="500"
                aria-label="Type your message"
            >
            <button id="cb-send" aria-label="Send message">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
            </button>
        </div>

    </div>
</div>

<script>
window.__AiShopbot = {
    sessionUrl:  "{{ route('ai_shopbot.session') }}",
    messageUrl:  "{{ route('ai_shopbot.message') }}",
    suggestUrl:  "{{ route('ai_shopbot.suggest') }}",
    featuredUrl: "{{ route('ai_shopbot.featured') }}",
    csrf:        "{{ csrf_token() }}",
    color:       "{{ config('ai_shopbot.widget.primary_color', '#007bff') }}",
    greeting:    @json(config('ai_shopbot.widget.greeting')),
};
</script>
<script src="{{ asset('vendor/ai-shopbot/js/chatbot.js') }}" defer></script>
@endif
