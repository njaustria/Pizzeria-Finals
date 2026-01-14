<?php
?>

<div id="chatWidget" class="chat-widget">
    <div class="chat-toggle" id="chatToggle">
        <i class="fas fa-comments"></i>
        <span class="chat-badge" id="chatBadge">1</span>
    </div>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="chat-header-info">
                <div class="agent-avatar">
                    <img src="assets/images/pizzeria_boy.png" alt="PizzeriaBoy">
                </div>
                <div class="agent-details">
                    <h4>PizzeriaBoy</h4>
                    <span class="status-online">Online</span>
                </div>
            </div>
            <button class="chat-close" id="chatClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message">
                <div class="message-avatar">
                    <img src="assets/images/pizzeria_boy.png" alt="PizzeriaBoy">
                </div>
                <div class="message-content">
                    <p>Welcome to <?php echo SITE_NAME; ?>! I'm here to help you with your pizza needs.</p>
                    <span class="message-time" data-time="now">Just now</span>
                </div>
            </div>

            <div class="quick-actions">
                <h5>How can I help you today?</h5>
                <div class="action-buttons">
                    <button class="action-btn" data-action="menu">View Menu</button>
                    <button class="action-btn" data-action="hours">Store Hours</button>
                    <button class="action-btn" data-action="delivery">Delivery Info</button>
                    <button class="action-btn" data-action="contact">Contact Us</button>
                </div>
            </div>
        </div>

        <div class="chat-input-container">
            <div class="chat-input">
                <input type="text" id="messageInput" placeholder="Type your message...">
                <button id="sendMessage">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
    }

    .chat-toggle {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--glass-bg), rgba(255, 255, 255, 0.05));
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 32px var(--glass-shadow);
        transition: var(--transition-normal);
        position: relative;
        color: white;
    }

    .chat-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px var(--glass-shadow);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.1));
    }

    .chat-toggle i {
        font-size: 24px;
    }

    .chat-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4444;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    .chat-window {
        position: absolute;
        bottom: 80px;
        right: 0;
        width: 350px;
        max-width: 90vw;
        height: 500px;
        background: linear-gradient(135deg, var(--glass-bg), rgba(255, 255, 255, 0.05));
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: 0 20px 60px var(--glass-shadow);
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: slideInUp 0.3s ease;
    }

    .chat-window.active {
        display: flex;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chat-header {
        padding: 1rem;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border-bottom: 1px solid var(--glass-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
    }

    .chat-header-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .agent-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .agent-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .agent-details h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    .status-online {
        font-size: 0.8rem;
        color: #4caf50;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-online::before {
        content: '●';
        color: #4caf50;
        font-size: 0.8rem;
    }

    .chat-close {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: var(--transition-fast);
    }

    .chat-close:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .chat-messages {
        flex: 1;
        padding: 1rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .chat-messages::-webkit-scrollbar {
        width: 4px;
    }

    .chat-messages::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
    }

    .message {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .bot-message .message-avatar {
        background: none;
        border: 2px solid #2196f3;
        overflow: hidden;
    }

    .bot-message .message-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-message {
        flex-direction: row-reverse;
    }

    .user-message .message-avatar {
        background: linear-gradient(135deg, var(--glass-bg), rgba(255, 255, 255, 0.1));
        border: 1px solid var(--glass-border);
        color: white;
    }

    .message-content {
        flex: 1;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border: 1px solid var(--glass-border);
        padding: 0.75rem;
        border-radius: var(--radius-md);
        color: white;
    }

    .user-message .message-content {
        background: linear-gradient(135deg, #2196f3, #1976d2);
        border: none;
    }

    .message-content p {
        margin: 0;
        line-height: 1.4;
    }

    .message-time {
        font-size: 0.75rem;
        opacity: 0.7;
        margin-top: 0.5rem;
        display: block;
    }

    .quick-actions {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--glass-border);
    }

    .quick-actions h5 {
        color: white;
        margin-bottom: 0.75rem;
        font-size: 0.9rem;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .action-btn {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border: 1px solid var(--glass-border);
        color: white;
        padding: 0.5rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: var(--transition-fast);
        font-size: 0.8rem;
        text-align: center;
    }

    .action-btn:hover {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
        transform: translateY(-1px);
    }

    .chat-input-container {
        padding: 1rem;
        border-top: 1px solid var(--glass-border);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
    }

    .chat-input {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .chat-input input {
        flex: 1;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        color: white;
        font-size: 0.9rem;
    }

    .chat-input input::placeholder {
        color: rgba(255, 255, 255, 0.6);
    }

    .chat-input input:focus {
        outline: none;
        border-color: rgba(255, 255, 255, 0.4);
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
    }

    .chat-input button {
        background: linear-gradient(135deg, #2196f3, #1976d2);
        border: none;
        border-radius: var(--radius-sm);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: var(--transition-fast);
    }

    .chat-input button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
    }

    @media (max-width: 768px) {
        .chat-window {
            width: 300px;
            height: 450px;
            bottom: 80px;
            right: 10px;
        }

        .action-buttons {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .chat-widget {
            bottom: 10px;
            right: 10px;
        }

        .chat-window {
            width: calc(100vw - 20px);
            right: 0;
            height: 400px;
        }
    }

    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        color: rgba(255, 255, 255, 0.7);
        font-style: italic;
    }

    .typing-dots {
        display: flex;
        gap: 0.25rem;
    }

    .typing-dots span {
        width: 6px;
        height: 6px;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 50%;
        animation: typing 1.4s infinite ease-in-out;
    }

    .typing-dots span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dots span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {

        0%,
        60%,
        100% {
            transform: scale(1);
            opacity: 0.5;
        }

        30% {
            transform: scale(1.2);
            opacity: 1;
        }
    }
</style>

<script>
</script>