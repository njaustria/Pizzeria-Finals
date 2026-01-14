<?php
define('OLLAMA_URL', 'http://localhost:11434');
define('OLLAMA_MODEL', 'llama3.2:3b');
define('OLLAMA_TIMEOUT', 45);
define('OLLAMA_TEMPERATURE', 0.3);
define('OLLAMA_MAX_TOKENS', 200);

define('PIZZERIA_NAME', 'Pizzeria');
define('PIZZERIA_LOCATION', 'Sto Tomas, Batangas');
define('PIZZERIA_PHONE', '+63 920 558 3433');
define('PIZZERIA_EMAIL', 'pizzeriagroup5@gmail.com');
define('DELIVERY_FEE', 200);
define('FREE_DELIVERY_MINIMUM', 1500);

$STORE_HOURS = [
    'monday' => ['open' => '11:00', 'close' => '22:00'],
    'tuesday' => ['open' => '11:00', 'close' => '22:00'],
    'wednesday' => ['open' => '11:00', 'close' => '22:00'],
    'thursday' => ['open' => '11:00', 'close' => '22:00'],
    'friday' => ['open' => '11:00', 'close' => '23:00'],
    'saturday' => ['open' => '11:00', 'close' => '23:00'],
    'sunday' => ['open' => '12:00', 'close' => '21:00']
];

$FALLBACK_RESPONSES = [
    'default' => "I'm sorry, our AI assistant is temporarily unavailable. For immediate assistance, please call us at " . PIZZERIA_PHONE . " or visit our menu page!",
    'hours' => "We're open Mon-Thu: 11 AM - 10 PM, Fri-Sat: 11 AM - 11 PM, Sun: 12 PM - 9 PM",
    'contact' => "Contact us:<br>Phone: " . PIZZERIA_PHONE . "<br>Email: " . PIZZERIA_EMAIL . "<br>Location: " . PIZZERIA_LOCATION,
    'delivery' => "We deliver within Batangas area! Delivery fee is ₱" . DELIVERY_FEE . ". FREE delivery on orders ₱" . FREE_DELIVERY_MINIMUM . " and above."
];
