@extends('layouts.institute')

@section('title', 'AI Assistant — AccumenAI')

@section('content')

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-robot me-2"></i>AI Assistant</h4>
        <p class="page-header-desc">Ask about {{ $institute->name ?? 'your institute' }} — students, courses, batches, results, fees and more.</p>
    </div>
</div>

<div class="admin-card ai-chat-card">
    <div class="ai-chat-body" id="aiChatBody" aria-live="polite">
        <div class="ai-empty" id="aiChatEmpty">
            <div class="ai-empty-icon"><i class="bi bi-stars"></i></div>
            <p>Ask me anything about your institute.<br><small>Example: how many students enrolled this month?</small></p>
            <div class="ai-suggestions">
                <button type="button" class="btn btn-sm btn-outline-primary ai-suggestion">How many active students do we have?</button>
                <button type="button" class="btn btn-sm btn-outline-primary ai-suggestion">Show me the running batches</button>
                <button type="button" class="btn btn-sm btn-outline-primary ai-suggestion">What is the average exam result percentage?</button>
                <button type="button" class="btn btn-sm btn-outline-primary ai-suggestion">How much fee is still due?</button>
            </div>
        </div>
    </div>

    <div class="ai-chat-input">
        <label class="visually-hidden" for="aiPrompt">Ask the AI Assistant a question</label>
        <textarea id="aiPrompt" class="form-control ai-prompt" rows="1" placeholder="Type your question…" maxlength="4000"></textarea>
        <button type="button" class="btn btn-outline-secondary ai-cancel" id="aiCancelBtn" title="Stop" aria-label="Stop the AI request" hidden>
            <i class="bi bi-x-lg"></i>
        </button>
        <button type="button" class="btn btn-primary ai-send" id="aiSendBtn" title="Send" aria-label="Send message">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

@endsection

@push('scripts')
<script data-ajax-page-script>
(function () {
    var body = document.getElementById('aiChatBody');
    var empty = document.getElementById('aiChatEmpty');
    var prompt = document.getElementById('aiPrompt');
    var sendBtn = document.getElementById('aiSendBtn');
    var cancelBtn = document.getElementById('aiCancelBtn');
    var history = [];
    var busy = false;
    var controller = null;
    var lastMessage = '';
    var thinkingEl = null;

    // Escape everything the model returns, then apply a tiny, safe subset of
    // formatting. Only our own generated tags are ever inserted, never raw AI
    // HTML, so model output can never become an XSS vector.
    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function renderMarkdown(text) {
        var escaped = escapeHtml(text);
        var blocks = escaped.split(/\n{2,}/);
        var html = '';
        for (var i = 0; i < blocks.length; i++) {
            var block = blocks[i].trim();
            if (!block) { continue; }
            if (/^(?:[-*]|\d+\.)\s/m.test(block)) {
                var items = block.split(/\n/);
                html += '<ul class="ai-list">';
                for (var j = 0; j < items.length; j++) {
                    var item = items[j].replace(/^(?:[-*]|\d+\.)\s+/, '').trim();
                    if (item) { html += '<li>' + item + '</li>'; }
                }
                html += '</ul>';
            } else {
                html += '<p>' + block.replace(/\n/g, '<br>') + '</p>';
            }
        }
        return html;
    }

    function addMessage(role, text) {
        if (empty) { empty.style.display = 'none'; }
        var wrap = document.createElement('div');
        wrap.className = 'ai-msg ai-msg-' + role;
        var bubble = document.createElement('div');
        bubble.className = 'ai-bubble';
        if (role === 'assistant') {
            bubble.innerHTML = renderMarkdown(text);
        } else {
            bubble.textContent = text;
        }
        wrap.appendChild(bubble);
        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
        history.push({ role: role, content: text });
        if (history.length > 20) { history = history.slice(-20); }
    }

    function addNote(text) {
        if (empty) { empty.style.display = 'none'; }
        var note = document.createElement('div');
        note.className = 'ai-note';
        note.textContent = text;
        body.appendChild(note);
        body.scrollTop = body.scrollHeight;
        return note;
    }

    function addRetry() {
        var wrap = document.createElement('div');
        wrap.className = 'ai-retry';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Try Again';
        btn.addEventListener('click', function () {
            if (wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
            prompt.value = lastMessage;
            autoGrow();
            send();
        });
        wrap.appendChild(btn);
        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
    }

    function send() {
        if (busy) { return; }
        var message = prompt.value.trim();
        if (!message) { return; }

        lastMessage = message;
        prompt.value = '';
        autoGrow();
        addMessage('user', message);
        busy = true;
        sendBtn.disabled = true;
        sendBtn.querySelector('i').className = 'bi bi-hourglass-split';
        cancelBtn.hidden = false;
        thinkingEl = addNote('AI is thinking…');
        controller = new AbortController();

        Monetix.request('{{ route('ai.assistant.send') }}', {
            method: 'POST',
            body: JSON.stringify({ message: message, history: history.slice(-10) }),
            signal: controller.signal,
        })
        .then(function (data) {
            if (thinkingEl && thinkingEl.parentNode) { thinkingEl.parentNode.removeChild(thinkingEl); }
            thinkingEl = null;

            if (data._status === 401 || data._status === 419) { return; }

            if (data.success === false) {
                addMessage('assistant', data.message || 'The AI could not complete your request.');
                addRetry();
                return;
            }

            var answer = data.data && data.data.answer;
            addMessage('assistant', answer || 'I could not answer that. Please try rephrasing.');
        })
        .catch(function (err) {
            if (err && err.name === 'AbortError') { return; }
            if (thinkingEl && thinkingEl.parentNode) { thinkingEl.parentNode.removeChild(thinkingEl); }
            thinkingEl = null;
            addMessage('assistant', 'Network error — please check your connection and try again.');
            addRetry();
        })
        .finally(function () {
            busy = false;
            sendBtn.disabled = false;
            sendBtn.querySelector('i').className = 'bi bi-send-fill';
            cancelBtn.hidden = true;
            controller = null;
            prompt.focus();
        });
    }

    cancelBtn.addEventListener('click', function () {
        if (controller) { controller.abort(); }
    });

    function autoGrow() {
        prompt.style.height = 'auto';
        prompt.style.height = Math.min(prompt.scrollHeight, 140) + 'px';
    }

    prompt.addEventListener('input', autoGrow);
    prompt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });
    sendBtn.addEventListener('click', send);

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.ai-suggestion');
        if (!btn) { return; }
        prompt.value = btn.textContent.trim();
        autoGrow();
        send();
    });
})();
</script>
@endpush
