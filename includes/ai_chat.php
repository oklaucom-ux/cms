<!-- Floating AI Chat Widget -->
<style>
    .ai-chat-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #6366f1, #3b82f6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        cursor: pointer;
        z-index: 9999;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .ai-chat-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
    }
    
    .ai-chat-window {
        position: fixed;
        bottom: 100px;
        right: 30px;
        width: 350px;
        height: 500px;
        background: var(--bg-card);
        border: 1px solid var(--border-card);
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        z-index: 9998;
        overflow: hidden;
        transform: translateY(20px);
        opacity: 0;
        pointer-events: none;
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s;
    }
    .ai-chat-window.active {
        transform: translateY(0);
        opacity: 1;
        pointer-events: all;
    }
    
    .ai-chat-header {
        background: linear-gradient(135deg, #6366f1, #3b82f6);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
    }
    .ai-chat-header .close-ai {
        cursor: pointer;
        opacity: 0.8;
    }
    .ai-chat-header .close-ai:hover {
        opacity: 1;
    }
    
    .ai-chat-body {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: var(--bg-body);
    }
    
    .ai-msg {
        max-width: 85%;
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.4;
    }
    .ai-msg.system {
        background: var(--bg-card);
        border: 1px solid var(--border-card);
        color: var(--text-body);
        align-self: flex-start;
        border-bottom-left-radius: 4px;
    }
    .ai-msg.user {
        background: #6366f1;
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
    }
    
    .ai-chat-input-area {
        padding: 15px;
        background: var(--bg-card);
        border-top: 1px solid var(--border-card);
        display: flex;
        gap: 10px;
    }
    .ai-chat-input-area input {
        flex: 1;
        background: var(--input-bg);
        border: 1px solid var(--border-card);
        color: var(--text-body);
        border-radius: 20px;
        padding: 8px 15px;
        outline: none;
        font-size: 13px;
    }
    .ai-chat-input-area button {
        background: #6366f1;
        color: white;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
    }
    .ai-chat-input-area button:hover {
        background: #4f46e5;
    }
    .typing-indicator {
        display: none;
        align-self: flex-start;
        background: var(--bg-card);
        padding: 10px 14px;
        border-radius: 12px;
        border-bottom-left-radius: 4px;
        font-size: 12px;
        color: var(--text-muted);
        border: 1px solid var(--border-card);
    }
</style>

<div class="ai-chat-btn" id="aiToggleBtn" onclick="toggleAIChat()">
    <i class="fas fa-robot"></i>
</div>

<div class="ai-chat-window" id="aiChatWindow">
    <div class="ai-chat-header">
        <div><i class="fas fa-sparkles me-2"></i> Cyno Assistant</div>
        <i class="fas fa-times close-ai" onclick="toggleAIChat()"></i>
    </div>
    <div class="ai-chat-body" id="aiChatBody">
        <div class="ai-msg system">
            Hello! I'm your AI HR & Ops assistant. Ask me anything about policies, onboarding, or general queries!
        </div>
        <div class="typing-indicator" id="aiTyping">
            <i class="fas fa-ellipsis-h fa-fade"></i> AI is thinking...
        </div>
    </div>
    <form class="ai-chat-input-area" id="aiChatForm" onsubmit="handleAISubmit(event)">
        <input type="text" id="aiChatInput" placeholder="Ask a question..." autocomplete="off">
        <button type="submit"><i class="fas fa-paper-plane"></i></button>
    </form>
</div>

<script>
    function toggleAIChat() {
        document.getElementById('aiChatWindow').classList.toggle('active');
        if(document.getElementById('aiChatWindow').classList.contains('active')) {
            document.getElementById('aiChatInput').focus();
        }
    }

    async function handleAISubmit(e) {
        e.preventDefault();
        const input = document.getElementById('aiChatInput');
        const text = input.value.trim();
        if(!text) return;
        
        appendMessage('user', text);
        input.value = '';
        
        const typing = document.getElementById('aiTyping');
        const body = document.getElementById('aiChatBody');
        
        // Show typing indicator
        body.appendChild(typing); 
        typing.style.display = 'block';
        body.scrollTop = body.scrollHeight;

        try {
            const fd = new FormData();
            fd.append('query', text);
            fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

            const res = await fetch('controllers/ai_api.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            typing.style.display = 'none';
            if (data.status === 'success') {
                appendMessage('system', data.reply);
            } else {
                appendMessage('system', 'Error: ' + data.message);
            }
        } catch (err) {
            typing.style.display = 'none';
            appendMessage('system', 'Connection error. Please try again later.');
        }
    }

    function appendMessage(sender, text) {
        const body = document.getElementById('aiChatBody');
        const div = document.createElement('div');
        div.className = 'ai-msg ' + sender;
        // Basic escaping to prevent XSS while allowing basic markdown like line breaks
        let escapedText = text.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
        div.innerHTML = escapedText;
        
        const typing = document.getElementById('aiTyping');
        body.insertBefore(div, typing);
        body.scrollTop = body.scrollHeight;
    }
</script>
