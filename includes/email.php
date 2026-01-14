<?php
require_once __DIR__ . '/smtp.php';
require_once __DIR__ . '/alternative-email.php';

class GmailSender
{
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;

    public function __construct()
    {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = SMTP_FROM_EMAIL;
        $this->from_name = SMTP_FROM_NAME;
    }

    public function sendEmail($to, $subject, $body, $isHTML = true)
    {
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: " . $to);
            }

            $success = false;
            $lastError = '';

            try {
                $smtp = new SimpleSMTP(
                    $this->smtp_host,
                    $this->smtp_port,
                    $this->smtp_username,
                    $this->smtp_password
                );

                $smtp->setDebug(true);
                $success = $smtp->send(
                    $this->from_email,
                    $this->from_name,
                    $to,
                    $subject,
                    $body,
                    $isHTML
                );

                if ($success) {
                    $this->logEmail($to, $subject, $body, true, "SimpleSMTP method succeeded");
                    return true;
                }
            } catch (Exception $e) {
                $lastError = "SimpleSMTP failed: " . $e->getMessage();
            }

            try {
                $altSender = new AlternativeEmailSender();
                $success = $altSender->send($to, $subject, $body, $isHTML);

                if ($success) {
                    $this->logEmail($to, $subject, $body, true, "Alternative method succeeded");
                    return true;
                }
            } catch (Exception $e) {
                $lastError .= " | Alternative failed: " . $e->getMessage();
            }

            try {
                $headers = [
                    'From: ' . $this->from_name . ' <' . $this->from_email . '>',
                    'Reply-To: ' . $this->from_email,
                    'X-Mailer: PHP/' . phpversion(),
                    'MIME-Version: 1.0'
                ];

                if ($isHTML) {
                    $headers[] = 'Content-Type: text/html; charset=UTF-8';
                } else {
                    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                }

                ini_set('SMTP', $this->smtp_host);
                ini_set('smtp_port', '587');
                ini_set('sendmail_from', $this->from_email);

                $success = mail($to, $subject, $body, implode("\r\n", $headers));

                if ($success) {
                    $this->logEmail($to, $subject, $body, true, "PHP mail() method succeeded");
                    return true;
                }
            } catch (Exception $e) {
                $lastError .= " | PHP mail() failed: " . $e->getMessage();
            }

            $this->logEmail($to, $subject, $body, false, "All methods failed: " . $lastError);
            return false;
        } catch (Exception $e) {
            error_log("Gmail sending error: " . $e->getMessage());
            $this->logEmail($to, $subject, $body, false, $e->getMessage());
            return false;
        }
    }

    private function logEmail($to, $subject, $body, $success, $error = null)
    {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $emailLog = "=== GMAIL EMAIL LOG ===" . PHP_EOL;
        $emailLog .= "Date: " . date('Y-m-d H:i:s') . PHP_EOL;
        $emailLog .= "To: " . $to . PHP_EOL;
        $emailLog .= "From: " . $this->from_name . ' <' . $this->from_email . '>' . PHP_EOL;
        $emailLog .= "Subject: " . $subject . PHP_EOL;
        $emailLog .= "Status: " . ($success ? 'SUCCESS' : 'FAILED') . PHP_EOL;

        if ($error) {
            $emailLog .= "Error: " . $error . PHP_EOL;
        }

        $emailLog .= "Body Preview: " . substr(strip_tags($body), 0, 200) . "..." . PHP_EOL;
        $emailLog .= "========================" . PHP_EOL . PHP_EOL;

        file_put_contents($logDir . '/gmail_email_log.txt', $emailLog, FILE_APPEND | LOCK_EX);
    }
}

function sendOrderReceipt($orderData, $orderItems, $userData)
{
    $gmail = new GmailSender();

    $receiptHTML = generateOrderReceiptEmail($orderData, $orderItems, $userData);

    $subject = "Order Confirmation #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);

    return $gmail->sendEmail($userData['email'], $subject, $receiptHTML, true);
}

function sendOrderStatusUpdate($orderData, $userData, $newStatus)
{
    $gmail = new GmailSender();

    $orderNumber = str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);
    $statusMessages = [
        'pending' => 'We have received your order and it is being processed.',
        'preparing' => 'Your order is now being prepared by our chefs.',
        'out_for_delivery' => 'Your order is on its way to you!',
        'completed' => 'Your order has been delivered. Thank you for choosing us!',
        'cancelled' => 'Your order has been cancelled. If you have any questions, please contact us.'
    ];

    $message = $statusMessages[$newStatus] ?? 'Your order status has been updated.';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { 
                font-family: "Poppins", sans-serif; 
                margin: 0; 
                padding: 20px; 
                background-color: #ffffff; 
                color: #000000;
                line-height: 1.6;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: #ffffff; 
                border: 1px solid #000000; 
                padding: 30px; 
            }
            .header { 
                text-align: center; 
                color: #000000; 
                margin-bottom: 30px; 
                border-bottom: 1px solid #000000;
                padding-bottom: 20px;
            }
            .header h1 {
                margin: 0;
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 2px;
            }
            .header h2 {
                margin: 10px 0 0 0;
                font-size: 18px;
                font-weight: 500;
            }
            .status-update { 
                border: 1px solid #000000; 
                padding: 20px; 
                text-align: center; 
                margin: 20px 0; 
            }
            .status-update h3 {
                margin: 0 0 15px 0;
                font-size: 20px;
                font-weight: 600;
            }
            .status-update p {
                margin: 10px 0;
                font-size: 14px;
            }
            .status-text {
                background-color: #000000;
                color: #ffffff;
                padding: 8px 16px;
                display: inline-block;
                font-weight: 600;
                font-size: 14px;
                margin: 10px 0;
            }
            .content-text {
                font-size: 14px;
                margin: 15px 0;
            }
            .footer { 
                background-color: #000000; 
                color: #ffffff; 
                padding: 20px; 
                text-align: center; 
                margin: 30px -30px -30px -30px; 
            }
            .footer p {
                margin: 5px 0;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>PIZZERIA</h1>
                <h2>Order Status Update</h2>
            </div>
            
            <div class="status-update">
                <h3>Order #' . $orderNumber . '</h3>
                <div class="status-text">' . strtoupper(str_replace('_', ' ', $newStatus)) . '</div>
                <p>' . $message . '</p>
            </div>
            
            <p class="content-text">Hello ' . htmlspecialchars($userData['name']) . ',</p>
            <p class="content-text">This is to inform you that your order status has been updated.</p>
            
            <div class="footer">
                <p>&copy; 2026 Pizzeria. All rights reserved.</p>
                <p>For inquiries, contact us at pizzeriagroup5@gmail.com</p>
            </div>
        </div>
    </body>
    </html>';

    $subject = "Order Update - #" . $orderNumber . " is " . ucfirst(str_replace('_', ' ', $newStatus));

    return $gmail->sendEmail($userData['email'], $subject, $html, true);
}
