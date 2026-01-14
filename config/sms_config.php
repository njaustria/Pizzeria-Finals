<?php
define('SMS_GATEWAY_URL', getenv('SMS_GATEWAY_URL') ?: 'http://192.168.5.45:8080');
define('SMS_GATEWAY_USER', getenv('SMS_GATEWAY_USER') ?: 'sms');
define('SMS_GATEWAY_PASS', getenv('SMS_GATEWAY_PASS') ?: '095zctJi');

/**
 * Send SMS message
 * @param string|array $phoneNumbers Phone number(s) to send SMS to
 * @param string $message Message to send
 * @param int|null $userId User ID for logging (optional)
 * @param int|null $orderId Order ID for logging (optional)
 * @param string $smsType Type of SMS for logging (optional)
 * @return array Result array with success status and message
 */
function sendSMS($phoneNumbers, $message, $userId = null, $orderId = null, $smsType = 'custom')
{
    $result = ['success' => false, 'message' => '', 'debug' => ''];

    if (!is_array($phoneNumbers)) {
        $phoneNumbers = [$phoneNumbers];
    }

    $cleanPhoneNumbers = [];
    foreach ($phoneNumbers as $phone) {
        $cleanPhone = preg_replace('/\D+/', '', $phone);
        if (strlen($cleanPhone) >= 10) {
            $cleanPhoneNumbers[] = $cleanPhone;
        }
    }

    if (empty($cleanPhoneNumbers)) {
        $result['message'] = 'No valid phone numbers provided';
        return $result;
    }

    $url = rtrim(SMS_GATEWAY_URL, '/') . '/messages';
    $payload = [
        'phoneNumbers' => $cleanPhoneNumbers,
        'message' => $message,
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode(SMS_GATEWAY_USER . ':' . SMS_GATEWAY_PASS),
            ],
            'content' => json_encode($payload),
            'timeout' => 10,
        ],
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    $status_line = isset($http_response_header) ? $http_response_header[0] : '';

    if ($response === false) {
        $last_error = error_get_last();
        $result['message'] = 'Failed to send SMS';
        $result['debug'] = ($status_line ? "Status: $status_line. " : '') . ($last_error['message'] ?? 'SMS gateway unreachable.');
        $smsStatus = 'failed';
    } else {
        if ($status_line !== '' && !preg_match('/\s2\d{2}\s/', $status_line)) {
            $result['message'] = 'SMS gateway error: ' . $status_line;
            $result['debug'] = 'Response: ' . $response;
            $smsStatus = 'failed';
        } else {
            $result['success'] = true;
            $result['message'] = 'SMS sent successfully to ' . count($cleanPhoneNumbers) . ' recipient(s)';
            $result['debug'] = 'Sent to: ' . implode(', ', $cleanPhoneNumbers);
            $smsStatus = 'sent';
        }
    }

    logSMSActivity($cleanPhoneNumbers, $message, $result['success'], $result['message']);

    foreach ($cleanPhoneNumbers as $phone) {
        logSMSToDatabase($phone, $message, $smsStatus, $result['debug'], $userId, $orderId, $smsType);
    }

    return $result;
}

function logSMSToDatabase($phoneNumber, $message, $status, $gatewayResponse, $userId = null, $orderId = null, $smsType = 'custom')
{
    try {
        if (!function_exists('getDBConnection')) {
            require_once dirname(__DIR__) . '/config/database.php';
        }

        $pdo = getDBConnection();

        $checkTable = $pdo->query("SHOW TABLES LIKE 'sms_logs'");
        if ($checkTable->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO sms_logs (user_id, phone_number, message, status, gateway_response, order_id, sms_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $phoneNumber, $message, $status, $gatewayResponse, $orderId, $smsType]);
        }
    } catch (Exception $e) {
        error_log('SMS database logging failed: ' . $e->getMessage());
    }
}

function logSMSActivity($phoneNumbers, $message, $success, $resultMessage)
{
    $logFile = dirname(__DIR__) . '/logs/sms_log.txt';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $phones = is_array($phoneNumbers) ? implode(', ', $phoneNumbers) : $phoneNumbers;

    $logEntry = "[$timestamp] $status - To: $phones - Message: " . substr($message, 0, 100) .
        (strlen($message) > 100 ? '...' : '') . " - Result: $resultMessage\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function getSMSTemplates()
{
    return [
        'order_confirmed' => 'Your order #ORDER_ID has been confirmed. Total: $ORDER_TOTAL. Thank you for choosing our pizzeria!',
        'order_preparing' => 'Your order #ORDER_ID is being prepared. Estimated time: 20-30 minutes.',
        'order_ready' => 'Your order #ORDER_ID is ready for pickup/delivery!',
        'order_completed' => 'Your order #ORDER_ID has been completed. Thank you for your business!',
        'order_cancelled' => 'Your order #ORDER_ID has been cancelled. If you have any questions, please contact us.',
        'welcome' => 'Welcome to our Pizzeria! Your account has been created successfully. Thank you for joining us!',
        'custom' => ''
    ];
}

function replaceSMSPlaceholders($template, $orderData = null, $userData = null)
{
    $message = $template;

    if ($orderData) {
        $message = str_replace('#ORDER_ID', $orderData['id'], $message);
        $message = str_replace('ORDER_TOTAL', number_format($orderData['total_price'], 2), $message);
    }

    if ($userData) {
        $message = str_replace('USER_NAME', $userData['name'], $message);
    }

    return $message;
}

/**
 * Build order confirmation SMS with pizza details
 * @param int $orderId Order ID
 * @param array $orderItems Array of order items with pizza details
 * @param float $grandTotal Total amount
 * @param string $customerName Customer name
 * @return string Formatted SMS message
 */
function buildOrderConfirmationSMS($orderId, $orderItems, $grandTotal, $customerName)
{
    $message = "Hi $customerName! Your order #$orderId has been confirmed.\n\nItems:\n";

    foreach ($orderItems as $item) {
        $message .= "• " . $item['pizza_name'] . " x" . $item['quantity'] . "\n";
    }

    $message .= "\nTotal: ₱" . number_format($grandTotal, 2);
    $message .= "\n\nWe'll prepare your delicious pizzas right away! Estimated time: 20-30 minutes. Thank you for choosing our pizzeria!";

    if (strlen($message) > 300) {
        $message = "Hi $customerName! Order #$orderId confirmed.\n";
        $itemCount = count($orderItems);
        if ($itemCount == 1) {
            $message .= $orderItems[0]['pizza_name'] . " x" . $orderItems[0]['quantity'];
        } else {
            $message .= "$itemCount pizza" . ($itemCount > 1 ? "s" : "");
        }
        $message .= "\nTotal: ₱" . number_format($grandTotal, 2);
        $message .= "\nETA: 20-30 mins. Thanks!";
    }

    return $message;
}
