class PizzeriaChatbot {
    constructor() {
        this.isOpen = false;
        this.sessionId = null;
        this.responses = this.initializeResponses();
        this.init();
    }

    init() {
        this.bindEvents();
        this.hideNotification();
        setTimeout(() => this.showNotification(), 3000);
    }

    bindEvents() {
        const chatToggle = document.getElementById('chatToggle');
        const chatClose = document.getElementById('chatClose');
        const chatWindow = document.getElementById('chatWindow');
        const messageInput = document.getElementById('messageInput');
        const sendMessage = document.getElementById('sendMessage');

        if (chatToggle) {
            chatToggle.addEventListener('click', () => this.toggleChat());
        }

        if (chatClose) {
            chatClose.addEventListener('click', () => this.closeChat());
        }

        if (sendMessage) {
            sendMessage.addEventListener('click', () => this.sendUserMessage());
        }

        if (messageInput) {
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.sendUserMessage();
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('action-btn')) {
                const action = e.target.getAttribute('data-action');
                this.handleQuickAction(action);
            }
        });

        document.addEventListener('click', (e) => {
            if (this.isOpen && !e.target.closest('#chatWidget')) {
                this.closeChat();
            }
        });
    }

    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }

    openChat() {
        const chatWindow = document.getElementById('chatWindow');
        const chatBadge = document.getElementById('chatBadge');

        if (chatWindow) {
            chatWindow.classList.add('active');
            this.isOpen = true;
            this.hideNotification();

            setTimeout(() => {
                const messageInput = document.getElementById('messageInput');
                if (messageInput) messageInput.focus();
            }, 300);
        }
    }

    closeChat() {
        const chatWindow = document.getElementById('chatWindow');

        if (chatWindow) {
            chatWindow.classList.remove('active');
            this.isOpen = false;
        }
    }

    showNotification() {
        const chatBadge = document.getElementById('chatBadge');
        if (chatBadge && !this.isOpen) {
            chatBadge.style.display = 'flex';
        }
    }

    hideNotification() {
        const chatBadge = document.getElementById('chatBadge');
        if (chatBadge) {
            chatBadge.style.display = 'none';
        }
    }

    sendUserMessage() {
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();

        if (message) {
            this.addMessage(message, 'user');
            messageInput.value = '';

            this.showTyping();

            setTimeout(() => {
                this.hideTyping();
                this.processMessage(message);
            }, 1000 + Math.random() * 1000);
        }
    }

    addMessage(text, type = 'bot') {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;

        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        messageDiv.innerHTML = `
            <div class="message-avatar">
                ${type === 'bot' ? '<img src="assets/images/pizzeria_boy.png" alt="Pizzeria Bot" style="width: 24px; height: 24px;">' : '<i class="fas fa-user"></i>'}
            </div>
            <div class="message-content">
                <p>${text}</p>
                <span class="message-time">${time}</span>
            </div>
        `;

        const quickActions = messagesContainer.querySelector('.quick-actions');
        if (quickActions && type === 'user') {
            quickActions.remove();
        }

        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    showTyping() {
        const messagesContainer = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.id = 'typingIndicator';

        typingDiv.innerHTML = `
            <div class="message-avatar">
                <img src="assets/images/pizzeria_boy.png" alt="Pizzeria Bot" style="width: 24px; height: 24px;">
            </div>
            <div class="typing-text">
                Assistant is typing
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;

        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    hideTyping() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    processMessage(message) {
        this.callChatbotAPI(message);
    }

    async callChatbotAPI(message) {
        try {
            const response = await fetch('api/chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: this.getSessionId()
                })
            });

            const data = await response.json();

            if (data.success && data.response) {
                this.addMessage(data.response, 'bot');
            } else {
                this.addMessage('I apologize, but I\'m having trouble processing that request right now. Please try asking in a different way or call us at +63 920 558 3433.', 'bot');
            }
        } catch (error) {
            console.error('Chatbot API Error:', error);
            this.addMessage('I\'m experiencing some technical difficulties. Please call us at +63 920 558 3433 for immediate assistance.', 'bot');
        }
    }

    getSessionId() {
        if (!this.sessionId) {
            this.sessionId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        return this.sessionId;
    }
    isPizzaCategoryQuery(message) {
        const categoryKeywords = [
            'classic pizzas', 'premium pizzas', 'vegetarian pizzas', 'spicy pizzas', 'gourmet pizzas',
            'what classic', 'what premium', 'what vegetarian', 'what spicy', 'what gourmet'
        ];
        return categoryKeywords.some(keyword => message.includes(keyword));
    }

    isPriceQuery(message) {
        const priceKeywords = [
            'cheapest', 'most expensive', 'price range', 'how much', 'cost', 'prices', 'budget'
        ];
        return priceKeywords.some(keyword => message.includes(keyword));
    }

    handleCategoryQuery(message) {
        let response = "";

        if (message.includes('classic')) {
            response = "Classic Pizzas:<br>• Margherita - ₱299<br>• Pepperoni - ₱349<br>• Hawaiian - ₱329<br><br>These are our traditional favorites!";
        } else if (message.includes('premium')) {
            response = "Premium Pizzas:<br>• Meat Lovers - ₱399<br>• BBQ Chicken - ₱379<br>• Four Cheese - ₱369<br>• Supreme - ₱389<br><br>Premium quality with extra toppings!";
        } else if (message.includes('vegetarian')) {
            response = "Vegetarian Pizzas:<br>• Vegetarian Supreme - ₱319<br>• Garden Fresh - ₱299<br><br>Fresh and healthy options!";
        } else if (message.includes('spicy')) {
            response = "Spicy Pizzas:<br>• Buffalo Chicken - ₱359<br>• Mexican Fiesta - ₱369<br><br>For those who like it hot!";
        } else if (message.includes('gourmet')) {
            response = "Gourmet Pizzas:<br>• Mediterranean - ₱339<br>• Seafood Deluxe - ₱419<br>• Truffle Mushroom - ₱449<br>• Pesto Chicken - ₱359<br><br>Sophisticated flavors for refined tastes!";
        }

        this.addMessage(response, 'bot');
    }

    handlePriceQuery(message) {
        let response = "";

        if (message.includes('cheapest') || message.includes('budget')) {
            response = "Most Affordable Pizzas:<br>• Margherita - ₱299<br>• Garden Fresh - ₱299<br><br>Great taste without breaking the bank!";
        } else if (message.includes('most expensive') || message.includes('premium')) {
            response = "Premium Priced Pizzas:<br>• Truffle Mushroom - ₱449 (Our signature gourmet)<br>• Seafood Deluxe - ₱419<br>• Meat Lovers - ₱399<br><br>Worth every peso for the quality!";
        } else {
            response = "Price Range:<br>• Budget: ₱299-329 (Margherita, Garden Fresh, Hawaiian)<br>• Mid-range: ₱339-379 (Most pizzas)<br>• Premium: ₱389-449 (Gourmet & Specialty)<br><br>All prices include fresh ingredients and generous portions!";
        }

        this.addMessage(response, 'bot');
    }
    findBestResponse(message) {
        console.log('Finding best response for:', message);

        for (const [category, data] of Object.entries(this.responses)) {
            if (category === 'default') continue;

            if (!data.keywords || !Array.isArray(data.keywords)) {
                console.warn(`Invalid keywords for category: ${category}`, data);
                continue;
            }

            for (const keyword of data.keywords) {
                if (message.includes(keyword.toLowerCase())) {
                    console.log(`Found match: "${keyword}" in category "${category}"`);
                    if (data.responses && Array.isArray(data.responses) && data.responses.length > 0) {
                        let response = data.responses[Math.floor(Math.random() * data.responses.length)];

                        if (category === 'hours') {
                            const status = this.isOpenNow() ? "OPEN!" : "CLOSED";
                            response += "<br><br>We're currently " + status;
                        }

                        return response;
                    } else {
                        console.warn(`No valid responses for category: ${category}`, data);
                    }
                }
            }
        }

        console.log('No match found, using default');
        return null;
    }

    handleQuickAction(action) {
        const responses = {
            menu: "You can view our delicious menu by clicking on 'Menu' in the navigation above, or <a href='menu.php' style='color: #4caf50; text-decoration: underline;'>click here</a>.<br><br>We have a variety of traditional and specialty pizzas!",
            hours: "We're open:<br>• Monday-Thursday: 11:00 AM - 10:00 PM<br>• Friday-Saturday: 11:00 AM - 11:00 PM<br>• Sunday: 12:00 PM - 9:00 PM<br><br>We're currently " + (this.isOpenNow() ? "OPEN!" : "CLOSED"),
            delivery: "We deliver within Batangas area only!<br><br>• Delivery fee: ₱200<br>• Average delivery time: 30-45 minutes<br>• Free delivery on orders ₱1,500 and above!",
            contact: "Get in touch with us:<br><br>• Phone: +63 920 558 3433<br>• Email: pizzeriagroup5@gmail.com<br>• Address: Sto Tomas, Batangas<br>• Or use our <a href='contact.php' style='color: #4caf50; text-decoration: underline;'>contact form</a>"
        };

        if (responses[action]) {
            this.addMessage(responses[action], 'bot');
        }
    }

    isOpenNow() {
        const now = new Date();
        const day = now.getDay();
        const hour = now.getHours();

        if (day >= 1 && day <= 4) {
            return hour >= 11 && hour < 22;
        } else if (day === 5 || day === 6) {
            return hour >= 11 && hour < 23;
        } else {
            return hour >= 12 && hour < 21;
        }
    }

    initializeResponses() {
        return {
            greeting: {
                keywords: ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'start'],
                responses: [
                    "Hello! Welcome to our pizzeria! How can I help you today?"
                ]
            },
            margherita: {
                keywords: ['margherita', 'margherita pizza', 'classic margherita'],
                responses: [
                    "Margherita - ₱299.00<br>Classic Italian pizza with fresh tomatoes, mozzarella cheese, and basil leaves. A timeless favorite from our Classic collection!"
                ]
            },
            pepperoni: {
                keywords: ['pepperoni', 'pepperoni pizza'],
                responses: [
                    "Pepperoni - ₱349.00<br>Loaded with generous amounts of pepperoni slices and extra mozzarella cheese. A classic crowd-pleaser!"
                ]
            },
            hawaiian: {
                keywords: ['hawaiian', 'hawaiian pizza', 'ham pineapple', 'pineapple pizza'],
                responses: [
                    "Hawaiian - ₱329.00<br>Sweet and savory combination of ham and pineapple with mozzarella cheese. Love it or hate it, but definitely try it!"
                ]
            },
            meat_lovers: {
                keywords: ['meat lovers', 'meat lover', 'all meat', 'carnivore'],
                responses: [
                    "Meat Lovers - ₱399.00<br>For carnivores! Loaded with pepperoni, sausage, bacon, and ham. This premium pizza is a meat feast!"
                ]
            },
            vegetarian: {
                keywords: ['vegetarian', 'vegetarian supreme', 'veggie', 'vegetables'],
                responses: [
                    "Vegetarian Supreme - ₱319.00<br>Garden fresh vegetables including bell peppers, mushrooms, onions, and olives. Perfect for vegetable lovers!"
                ]
            },
            bbq_chicken: {
                keywords: ['bbq chicken', 'bbq', 'barbecue chicken', 'chicken bbq'],
                responses: [
                    "BBQ Chicken - ₱379.00<br>Grilled chicken with tangy BBQ sauce, red onions, and cilantro. A premium pizza with bold flavors!"
                ]
            },
            four_cheese: {
                keywords: ['four cheese', 'cheese lovers', 'extra cheese', 'quattro formaggi'],
                responses: [
                    "Four Cheese - ₱369.00<br>Cheese lovers dream with mozzarella, parmesan, gorgonzola, and ricotta. Pure cheesy goodness!"
                ]
            },
            supreme: {
                keywords: ['supreme', 'supreme pizza', 'everything pizza'],
                responses: [
                    "Supreme - ₱389.00<br>Everything pizza! Loaded with meats, vegetables, and extra cheese. Our premium 'everything but the kitchen sink' option!"
                ]
            },
            buffalo_chicken: {
                keywords: ['buffalo chicken', 'buffalo', 'spicy chicken'],
                responses: [
                    "Buffalo Chicken - ₱359.00<br>Spicy buffalo sauce with grilled chicken, ranch dressing, and celery. Perfect for those who like it hot!"
                ]
            },
            mediterranean: {
                keywords: ['mediterranean', 'feta', 'olives', 'greek'],
                responses: [
                    "Mediterranean - ₱339.00<br>Feta cheese, olives, sun-dried tomatoes, and spinach. A gourmet taste of the Mediterranean!"
                ]
            },
            mexican: {
                keywords: ['mexican', 'mexican fiesta', 'jalapeño', 'spicy beef'],
                responses: [
                    "Mexican Fiesta - ₱369.00<br>Seasoned beef, jalapeños, onions, tomatoes, and Mexican spices. A spicy fiesta on your plate!"
                ]
            },
            seafood: {
                keywords: ['seafood', 'seafood deluxe', 'shrimp', 'tuna', 'anchovies'],
                responses: [
                    "Seafood Deluxe - ₱419.00<br>Shrimp, tuna, anchovies, and squid with garlic butter. A premium gourmet seafood experience!"
                ]
            },
            truffle: {
                keywords: ['truffle', 'truffle mushroom', 'mushroom truffle', 'premium mushroom'],
                responses: [
                    "Truffle Mushroom - ₱449.00<br>Premium truffle oil with assorted mushrooms and parmesan. Our most luxurious gourmet pizza!"
                ]
            },
            garden_fresh: {
                keywords: ['garden fresh', 'garden', 'light pizza', 'healthy pizza'],
                responses: [
                    "Garden Fresh - ₱299.00<br>Light and healthy with tomatoes, spinach, arugula, and fresh mozzarella. Perfect for health-conscious pizza lovers!"
                ]
            },
            pesto: {
                keywords: ['pesto', 'pesto chicken', 'basil pesto'],
                responses: [
                    "Pesto Chicken - ₱359.00<br>Basil pesto sauce with grilled chicken and cherry tomatoes. A gourmet Italian-inspired creation!"
                ]
            },
            menu: {
                keywords: ['menu', 'pizza', 'pizzas', 'food', 'order', 'what do you have', 'options'],
                responses: [
                    "We have an amazing variety of pizzas! Check out our <a href='menu.php' style='color: #4caf50; text-decoration: underline;'>full menu</a> to see all our delicious options including Margherita, Pepperoni, Supreme, and more!"
                ]
            },
            hours: {
                keywords: ['hours', 'open', 'opened', 'close', 'closed', 'time', 'when', 'schedule', 'operating hours', 'business hours', 'what time'],
                responses: [
                    "Our hours are:<br>Mon-Thu: 11 AM - 10 PM<br>Fri-Sat: 11 AM - 11 PM<br>Sun: 12 PM - 9 PM"
                ]
            },
            delivery: {
                keywords: ['delivery', 'deliver', 'shipping', 'bring', 'send', 'takeout'],
                responses: [
                    "Yes, we deliver within Batangas area only! ₱200 delivery fee. FREE delivery on orders min ₱1,500. Average delivery time is 30-45 minutes."
                ]
            },
            prices: {
                keywords: ['price', 'cost', 'how much', 'expensive', 'cheap', 'deal', 'special', 'promotion'],
                responses: [
                    "Our pizzas range from ₱300-500 depending on size and toppings. Check our <a href='menu.php' style='color: #4caf50; text-decoration: underline;'>menu</a> for detailed pricing!"
                ]
            },
            contact: {
                keywords: ['contact', 'phone', 'call', 'email', 'address', 'location', 'located', 'where', 'where are you', 'find you'],
                responses: [
                    "Contact us:<br>Phone: +63 920 558 3433<br>Email: pizzeriagroup5@gmail.com<br>Address: Sto Tomas, Batangas<br>Or use our <a href='contact.php' style='color: #4caf50; text-decoration: underline;'>contact form</a>!"
                ]
            },
            reservation: {
                keywords: ['reservation', 'reserve', 'book', 'table', 'book table', 'make reservation', 'reservations'],
                responses: [
                    "We don't take reservations, but we welcome walk-ins! Our typical wait time is 15-20 minutes during peak hours.<br><br>You can also call ahead to place your order: +63 920 558 3433"
                ]
            },
            payment: {
                keywords: ['payment', 'pay', 'credit card', 'cash', 'card', 'visa', 'mastercard', 'how to pay', 'payment method'],
                responses: [
                    "We accept the following payment methods:<br>• Cash on Delivery (COD)<br>• PayPal (for online orders)<br><br>Payment is secure and easy! You can pay when your delicious pizza arrives at your door."
                ]
            },
            ordering: {
                keywords: ['order', 'place order', 'want to order', 'buy pizza', 'purchase', 'get pizza', 'i want'],
                responses: [
                    "Great! I'd love to help you order. You can:<br>• Browse our <a href='menu.php' style='color: #4caf50; text-decoration: underline;'>menu</a> and add items to cart<br>• Call us at +63 920 558 3433<br>• Visit us at Sto Tomas, Batangas<br><br>What pizza are you craving today?"
                ]
            },
            pickup: {
                keywords: ['pickup', 'pick up', 'takeaway', 'collect', 'dine in', 'eat in'],
                responses: [
                    "You can pick up your order at our location:<br>Sto Tomas, Batangas<br><br>Pickup is usually ready in 15-20 minutes after ordering. We'll call you when it's ready!<br><br>Want to place an order for pickup? Call +63 920 558 3433"
                ]
            },
            ingredients: {
                keywords: ['ingredients', 'allergen', 'allergies', 'vegan', 'vegetarian', 'gluten', 'dairy', 'lactose', 'nuts', 'dietary'],
                responses: [
                    "We care about your dietary needs:<br>• Vegetarian options: Garden Fresh, Margherita, Vegetarian Supreme<br>• We can accommodate most dietary restrictions<br>• Please inform us of allergies when ordering<br><br>For detailed ingredient lists and allergen info, <a href='contact.php' style='color: #4caf50; text-decoration: underline;'>contact us</a> at +63 920 558 3433"
                ]
            },
            account: {
                keywords: ['account', 'login', 'register', 'sign up', 'profile', 'password'],
                responses: [
                    "You can <a href='login.php' style='color: #4caf50; text-decoration: underline;'>login or create an account</a> to save your favorite orders and track deliveries!"
                ]
            },
            thanks: {
                keywords: ['thank', 'thanks', 'appreciate', 'great', 'awesome', 'perfect'],
                responses: [
                    "You're very welcome! Is there anything else I can help you with?"
                ]
            },
            complaint: {
                keywords: ['complaint', 'problem', 'issue', 'wrong order', 'cold pizza', 'late delivery', 'refund', 'dissatisfied'],
                responses: [
                    "I'm sorry to hear about your experience! Your satisfaction is important to us.<br><br>Please contact us immediately:<br>Phone: +63 920 558 3433<br>Email: pizzeriagroup5@gmail.com<br><br>We'll make it right and ensure you have a better experience next time!"
                ]
            },
            emergency: {
                keywords: ['urgent', 'emergency', 'asap', 'rush', 'quickly', 'fast'],
                responses: [
                    "For urgent orders, please call us directly:<br>Phone: +63 920 558 3433<br><br>We'll do our best to prioritize your order! Our average preparation time is 15-20 minutes for pickup and 30-45 minutes for delivery."
                ]
            },
            goodbye: {
                keywords: ['bye', 'goodbye', 'see you', 'talk later', 'gtg', 'gotta go'],
                responses: [
                    "Goodbye! Thanks for visiting our pizzeria. Come back anytime!"
                ]
            },
            default: [
                "I'm here to help with anything about our pizzeria! I can assist with:<br>• Menu and pizza information<br>• Hours and delivery info<br>• Contact and location details<br>• Pricing and payment options<br><br>What would you like to know?",
                "I'd love to help you! You can ask me about our delicious pizzas, delivery options, hours, or anything else. What's on your mind?",
                "Welcome to our pizzeria! I'm here to answer questions about our menu, help with orders, or provide any information you need. How can I assist you today?"
            ]
        };
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new PizzeriaChatbot();
});

function scrollChatToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

setTimeout(() => {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages && !chatMessages.querySelector('.user-message')) {
        const welcomeMsg = document.createElement('div');
        welcomeMsg.className = 'message bot-message';
        welcomeMsg.innerHTML = `
            <div class="message-avatar">
                <img src="assets/images/pizzeria_boy.png" alt="Pizzeria Bot" style="width: 24px; height: 24px;">
            </div>
            <div class="message-content">
                <p>Quick tip: You can ask me about our menu, hours, delivery info, or anything else about our pizzeria!</p>
                <span class="message-time">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
            </div>
        `;

        const quickActions = chatMessages.querySelector('.quick-actions');
        if (quickActions) {
            chatMessages.insertBefore(welcomeMsg, quickActions);
        }

        scrollChatToBottom();
    }
}, 5000);