/**
 * ALMS — AI Assistant Chat Controller
 * Manages message rendering, typing states, and API communication
 */

class ALMSChat {
    constructor() {
        this.messagesContainer = document.getElementById('chat-messages');
        this.chatInput = document.getElementById('chat-input');
        this.sendBtn = document.getElementById('chat-send-btn');
        this.typingIndicator = document.getElementById('chat-typing');

        this.init();
    }

    init() {
        if (!this.messagesContainer) return;

        // Event listener: send button
        if (this.sendBtn) {
            this.sendBtn.addEventListener('click', () => this.handleSend());
        }

        // Event listener: keyboard Enter
        if (this.chatInput) {
            this.chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.handleSend();
                }
            });
        }

        // Quick prompts handler
        document.querySelectorAll('.quick-prompt-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const text = chip.dataset.prompt || chip.textContent.trim();
                this.handleQuickPrompt(text);
            });
        });

        // Load initial welcome message from AI
        this.sendInitialGreeting();
    }

    sendInitialGreeting() {
        const welcomeText = `Hello! I am your AI Study Assistant. I've tailored myself to your **${VARK_STYLE.toUpperCase()}** learning style and **${PACE_MODE.toUpperCase()}** pace.\n\nAsk me anything about your current courses or lecturers. How can I help you today?`;
        this.renderMessage('ai', welcomeText);
    }

    handleSend() {
        const text = this.chatInput.value.trim();
        if (!text) return;

        this.chatInput.value = '';
        this.renderMessage('user', text);
        this.sendMessage(text);
    }

    handleQuickPrompt(text) {
        // Remove quick prompts wrapper if visible
        const quickPromptContainer = document.getElementById('quick-prompts-container');
        if (quickPromptContainer) {
            quickPromptContainer.remove();
        }
        
        this.renderMessage('user', text);
        this.sendMessage(text);
    }

    renderMessage(role, content) {
        if (!this.messagesContainer) return;

        const time = new Intl.DateTimeFormat('en-NG', {
            timeStyle: 'short'
        }).format(new Date());

        const bubbleWrapper = document.createElement('div');
        bubbleWrapper.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'} w-full mb-4 animate-fade-in-up`;

        // Parse markdown bold and code references
        let formattedContent = this.escapeHtml(content)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/`(.*?)`/g, '<code class="font-mono-data">$1</code>')
            .replace(/\n/g, '<br>');

        bubbleWrapper.innerHTML = `
            <div class="flex gap-3 max-w-[80%] ${role === 'user' ? 'flex-row-reverse' : ''}">
                <div class="sidebar-avatar" style="${role === 'user' ? 'background: var(--clr-text-secondary)' : ''}">
                    ${role === 'user' ? 'ME' : 'AI'}
                </div>
                <div>
                    <div class="chat-bubble ${role === 'user' ? 'chat-bubble-user' : 'chat-bubble-ai'}">
                        ${formattedContent}
                    </div>
                    <div class="chat-bubble-time ${role === 'user' ? 'text-right' : ''}" style="margin-top: 4px;">
                        ${time}
                    </div>
                </div>
            </div>
        `;

        this.messagesContainer.appendChild(bubbleWrapper);
        this.scrollToBottom();
    }

    showTypingIndicator() {
        if (this.typingIndicator) {
            this.typingIndicator.style.display = 'inline-flex';
            this.scrollToBottom();
        }
    }

    hideTypingIndicator() {
        if (this.typingIndicator) {
            this.typingIndicator.style.display = 'none';
        }
    }

    sendMessage(message) {
        this.showTypingIndicator();

        fetch('/api/ai-chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ message })
        })
        .then(res => {
            if (!res.ok) throw new Error('API request failed');
            return res.json();
        })
        .then(data => {
            this.hideTypingIndicator();
            if (data.success && data.message) {
                this.renderMessage('ai', data.message);
            } else {
                this.renderMessage('ai', 'Oops! I encountered an error. Could you try asking that again?');
            }
        })
        .catch(err => {
            console.error('AI chat error:', err);
            this.hideTypingIndicator();
            this.renderMessage('ai', 'Connection error. Please check your network and try again.');
        });
    }

    scrollToBottom() {
        if (this.messagesContainer) {
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
}

// Instantiate on load
document.addEventListener('DOMContentLoaded', () => {
    window.almsChat = new ALMSChat();
});
