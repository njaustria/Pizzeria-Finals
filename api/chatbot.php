<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/chatbot_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$message = trim($input['message']);
$sessionId = $input['session_id'] ?? uniqid();

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => $sessionId,
    'message' => $message,
    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

file_put_contents('../logs/chatbot_log.txt', json_encode($logData) . "\n", FILE_APPEND);

try {
    $response = processMessageWithOllama($message);
} catch (Exception $e) {
    error_log("Chatbot Error: " . $e->getMessage());
    $response = "I'm sorry, I'm having technical difficulties right now. Please call us at +63 920 558 3433 for immediate assistance.";
}

echo json_encode([
    'success' => true,
    'response' => $response,
    'session_id' => $sessionId,
    'timestamp' => time()
]);

function processMessageWithOllama($message)
{
    $context = getPizzeriaContext();

    $prompt = createPromptForOllama($message, $context);

    $ollamaResponse = sendToOllama($prompt);

    if ($ollamaResponse === false) {
        return getFallbackResponse($message);
    }

    return formatResponse($ollamaResponse);
}

function getPizzeriaContext()
{
    try {
        $pdo = getDBConnection();

        $pizzaQuery = "SELECT name, description, price, category, availability FROM pizzas WHERE availability = 1 ORDER BY category, price";
        $pizzas = $pdo->query($pizzaQuery)->fetchAll();

        $restaurantInfo = [
            'name' => PIZZERIA_NAME,
            'location' => PIZZERIA_LOCATION,
            'phone' => PIZZERIA_PHONE,
            'email' => PIZZERIA_EMAIL,
            'delivery_fee' => DELIVERY_FEE,
            'free_delivery_minimum' => FREE_DELIVERY_MINIMUM,
            'average_delivery_time' => '30-45 minutes',
            'average_pickup_time' => '15-20 minutes'
        ];

        return [
            'pizzas' => $pizzas,
            'restaurant_info' => $restaurantInfo
        ];
    } catch (Exception $e) {
        error_log("Database error in getPizzeriaContext: " . $e->getMessage());
        return [];
    }
}

function createPromptForOllama($message, $context)
{
    $pizzaList = "";
    if (isset($context['pizzas']) && !empty($context['pizzas'])) {
        $relevantPizzas = array_slice($context['pizzas'], 0, 8); // Only first 8 pizzas
        foreach ($relevantPizzas as $pizza) {
            $pizzaList .= "- {$pizza['name']}: ₱{$pizza['price']} ({$pizza['category']})\n";
        }
    }

    $restaurantInfo = $context['restaurant_info'] ?? [];

    $prompt = "You are a pizzeria assistant. Answer briefly based on this info:\n\n";

    $prompt .= "RESTAURANT: " . ($restaurantInfo['name'] ?? 'Pizzeria') . "\n";
    $prompt .= "LOCATION: " . ($restaurantInfo['location'] ?? 'Sto Tomas, Batangas') . "\n";
    $prompt .= "PHONE: " . ($restaurantInfo['phone'] ?? '+63 920 558 3433') . "\n";
    $prompt .= "HOURS: Mon-Thu 11AM-10PM, Fri-Sat 11AM-11PM, Sun 12PM-9PM\n";
    $prompt .= "DELIVERY: ₱200 fee, FREE on ₱1500+ orders\n\n";

    $prompt .= "TOP PIZZAS:\n";
    $prompt .= $pizzaList;

    $prompt .= "\nRULES: Keep responses short (max 3 sentences). Use ₱ for prices. Be helpful and friendly.\n\n";

    $prompt .= "Question: " . $message . "\n";
    $prompt .= "Answer:";

    return $prompt;
}

function sendToOllama($prompt, $model = null)
{
    if ($model === null) {
        $model = OLLAMA_MODEL;
    }

    $ollamaUrl = OLLAMA_URL . '/api/generate';

    $data = [
        'model' => $model,
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => OLLAMA_TEMPERATURE,
            'max_tokens' => OLLAMA_MAX_TOKENS
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ollamaUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, OLLAMA_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Ollama cURL error: " . $error);
        error_log("Full cURL info: " . print_r(curl_getinfo($ch), true));
        return false;
    }

    if ($httpCode !== 200) {
        error_log("Ollama HTTP error: " . $httpCode);
        error_log("Response body: " . $response);
        return false;
    }

    $decoded = json_decode($response, true);

    if (!$decoded || !isset($decoded['response'])) {
        error_log("Invalid Ollama response: " . $response);
        return false;
    }

    return $decoded['response'];
}

function formatResponse($response)
{
    $response = trim($response);

    if (strpos($response, '<br>') === false && strpos($response, "\n") !== false) {
        $response = str_replace("\n", '<br>', $response);
    }

    return $response;
}

function getFallbackResponse($message)
{
    global $FALLBACK_RESPONSES;
    $message = strtolower($message);

    if (strpos($message, 'hours') !== false || strpos($message, 'time') !== false || strpos($message, 'open') !== false) {
        return getCurrentStoreStatus();
    }

    if (strpos($message, 'contact') !== false || strpos($message, 'phone') !== false || strpos($message, 'call') !== false) {
        return $FALLBACK_RESPONSES['contact'];
    }

    if (strpos($message, 'delivery') !== false) {
        return $FALLBACK_RESPONSES['delivery'];
    }

    return $FALLBACK_RESPONSES['default'];
}

function getCurrentStoreStatus()
{
    $currentHour = (int)date('G');
    $currentDay = (int)date('w');

    $isOpen = false;

    if ($currentDay >= 1 && $currentDay <= 4) {
        $isOpen = $currentHour >= 11 && $currentHour < 22;
    } elseif ($currentDay == 5 || $currentDay == 6) {
        $isOpen = $currentHour >= 11 && $currentHour < 23;
    } else {
        $isOpen = $currentHour >= 12 && $currentHour < 21;
    }

    $status = $isOpen ? 'OPEN 🟢' : 'CLOSED 🔴';

    return "We're currently $status<br><br>Our hours:<br>• Mon-Thu: 11 AM - 10 PM<br>• Fri-Sat: 11 AM - 11 PM<br>• Sun: 12 PM - 9 PM";
}
