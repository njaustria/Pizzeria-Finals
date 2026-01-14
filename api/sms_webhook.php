<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/sms_config.php';

header('Content-Type: application/json');

try {
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    $logFile = dirname(__DIR__) . '/logs/webhook_log.txt';
    $logDir = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[{$timestamp}] " . $rawData . PHP_EOL, FILE_APPEND | LOCK_EX);

    $senderPhone = '';
    $messageText = '';
    $receivedTime = date('Y-m-d H:i:s');

    if ($data) {
        if (isset($data['phone']) && isset($data['message'])) {
            $senderPhone = $data['phone'];
            $messageText = $data['message'];
        } elseif (isset($data['from']) && isset($data['text'])) {
            $senderPhone = $data['from'];
            $messageText = $data['text'];
        } elseif (isset($data['number']) && isset($data['body'])) {
            $senderPhone = $data['number'];
            $messageText = $data['body'];
        } elseif (isset($data['phoneNumber']) && isset($data['messageText'])) {
            $senderPhone = $data['phoneNumber'];
            $messageText = $data['messageText'];
        } elseif (isset($data['sender']) && isset($data['content'])) {
            $senderPhone = $data['sender'];
            $messageText = $data['content'];
        }

        if (isset($data['timestamp'])) {
            $receivedTime = $data['timestamp'];
        } elseif (isset($data['time'])) {
            $receivedTime = $data['time'];
        } elseif (isset($data['date'])) {
            $receivedTime = $data['date'];
        }
    }

    file_put_contents($logFile, "[{$timestamp}] PARSED: Phone='{$senderPhone}', Message='{$messageText}'" . PHP_EOL, FILE_APPEND | LOCK_EX);

    if (empty($senderPhone) || empty($messageText)) {
        $errorMsg = "Missing required data - Phone: '{$senderPhone}', Message: '{$messageText}'";
        file_put_contents($logFile, "[{$timestamp}] ERROR: {$errorMsg}" . PHP_EOL, FILE_APPEND | LOCK_EX);

        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $errorMsg,
            "received_data" => $data
        ]);
        exit;
    }

    $cleanPhone = preg_replace('/\D+/', '', $senderPhone);

    $pdo = getDBConnection();

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE phone LIKE ? OR phone LIKE ? LIMIT 1");
        $stmt->execute(["%$cleanPhone%", "%$senderPhone%"]);
        $customer = $stmt->fetch();

        $customerId = $customer ? $customer['id'] : null;

        $messageType = determineMessageType($messageText);
        $priority = determinePriority($messageText, $messageType);

        $stmt = $pdo->prepare("
            INSERT INTO received_sms 
            (sender_phone, message, received_at, customer_id, message_type, priority, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'new')
        ");

        $result = $stmt->execute([
            $senderPhone,
            $messageText,
            $receivedTime,
            $customerId,
            $messageType,
            $priority
        ]);

        if (!$result) {
            throw new Exception('Failed to insert message into database: ' . implode(', ', $stmt->errorInfo()));
        }

        $messageId = $pdo->lastInsertId();

        if (!$messageId) {
            throw new Exception('Failed to get last insert ID');
        }

        file_put_contents($logFile, "[{$timestamp}] SUCCESS: Message ID {$messageId} stored from {$senderPhone}" . PHP_EOL, FILE_APPEND | LOCK_EX);

        $autoReplyResult = null;
        $autoReply = getAutoReplyMessage($messageType);
        if ($autoReply) {
            $replyResult = sendSMS($senderPhone, $autoReply, null, null, 'auto_reply');
            $autoReplyResult = $replyResult;

            if ($replyResult['success']) {
                $stmt = $pdo->prepare("
                    UPDATE received_sms 
                    SET admin_reply = ?, replied_at = NOW(), status = 'in_progress' 
                    WHERE id = ?
                ");
                $updateResult = $stmt->execute(['[AUTO-REPLY] ' . $autoReply, $messageId]);

                if (!$updateResult) {
                    file_put_contents($logFile, "[{$timestamp}] WARNING: Auto-reply sent but failed to update database" . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }
        }

        $pdo->commit();
        file_put_contents($logFile, "[{$timestamp}] TRANSACTION COMMITTED for message ID {$messageId}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Exception $dbError) {
        $pdo->rollback();
        $errorMsg = "Database transaction failed: " . $dbError->getMessage();
        file_put_contents($logFile, "[{$timestamp}] DB_ERROR: {$errorMsg}" . PHP_EOL, FILE_APPEND | LOCK_EX);

        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $dbError->getMessage()
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message_id" => $messageId,
        "customer_identified" => !is_null($customerId),
        "auto_reply_sent" => isset($autoReplyResult) ? $autoReplyResult['success'] : false
    ]);
} catch (Exception $e) {
    $timestamp = date("Y-m-d H:i:s");
    $logFile = dirname(__DIR__) . '/logs/webhook_log.txt';

    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];

    file_put_contents($logFile, "[{$timestamp}] FATAL_ERROR: " . json_encode($errorDetails) . PHP_EOL, FILE_APPEND | LOCK_EX);

    error_log('Webhook error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal server error: " . $e->getMessage(),
        "debug_info" => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

function determineMessageType($message)
{
    $message = strtolower($message);

    if (preg_match('/\b(order|pizza|delivery|menu|price)\b/i', $message)) {
        return 'order_query';
    }

    if (preg_match('/\b(complain|problem|issue|wrong|bad|terrible|awful)\b/i', $message)) {
        return 'complaint';
    }

    if (preg_match('/\b(question|ask|inquiry|info|information|help)\b/i', $message)) {
        return 'inquiry';
    }

    return 'general';
}
function determinePriority($message, $messageType)
{
    $message = strtolower($message);

    if (preg_match('/\b(urgent|emergency|asap|immediate|now)\b/i', $message)) {
        return 'urgent';
    }

    if ($messageType === 'complaint') {
        return 'high';
    }

    if ($messageType === 'order_query') {
        return 'normal';
    }

    return 'normal';
}

function getAutoReplyMessage($messageType)
{
    $autoReplies = [
        'order_query' => "Thank you for your message! For menu and orders, please visit our website or call us directly. We'll also respond to your message shortly.",
        'complaint' => "We're sorry to hear about your concern. A manager will review your message and respond as soon as possible. Thank you for your feedback.",
        'inquiry' => "Thank you for contacting us! We've received your message and will get back to you shortly.",
        'general' => "Hi! Thanks for messaging us. We'll respond to your message as soon as possible during business hours."
    ];

    return $autoReplies[$messageType] ?? null;
}
