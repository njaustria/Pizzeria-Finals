<?php
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireAdminLogin()
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isValidAdminSession($timeoutMinutes = 30)
{
    if (!isAdminLoggedIn()) {
        return false;
    }

    if (isset($_SESSION['admin_login_time'])) {
        $sessionAge = time() - $_SESSION['admin_login_time'];
        if ($sessionAge > ($timeoutMinutes * 60)) {
            return false;
        }
        $_SESSION['admin_login_time'] = time();
    }

    return true;
}

function adminLogout()
{
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['admin_login_time']);

    setFlashMessage('Admin session ended', 'info');
    header('Location: login.php');
    exit();
}

function formatPrice($price)
{
    return '₱' . number_format($price, 2);
}

function formatDate($date)
{
    return date('F j, Y g:i A', strtotime($date));
}

function getCartCount()
{
    if (!isset($_SESSION['cart'])) {
        return 0;
    }
    return array_sum($_SESSION['cart']);
}

function getCartTotal($pdo)
{
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }

    $total = 0;
    $pizzaIds = array_keys($_SESSION['cart']);

    if (empty($pizzaIds)) {
        return 0;
    }

    $placeholders = str_repeat('?,', count($pizzaIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, price FROM pizzas WHERE id IN ($placeholders)");
    $stmt->execute($pizzaIds);
    $pizzas = $stmt->fetchAll();

    foreach ($pizzas as $pizza) {
        $total += $pizza['price'] * $_SESSION['cart'][$pizza['id']];
    }

    return $total;
}

function setFlashMessage($message, $type = 'success')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function displayFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        $icon = $type === 'success' ? '✓' : '✕';
        echo "<div class='flash-message flash-{$type}'>{$icon} {$message}</div>";
    }
}

function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function uploadImage($file, $uploadDir = null)
{
    if (!isset($file['error']) || is_array($file['error'])) {
        error_log("Upload error: Invalid file error structure");
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (exceeds php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
        error_log("Upload error: " . $errorMsg);
        return false;
    }

    if ($file['size'] > 5000000) {
        error_log("Upload error: File too large (" . $file['size'] . " bytes)");
        return false;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        error_log("Upload error: Invalid file type: " . $mimeType);
        return false;
    }

    if ($uploadDir === null) {
        $projectRoot = dirname(__DIR__);
        $uploadDir = $projectRoot . '/assets/images/pizzas/';
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Upload error: Failed to create directory: " . $uploadDir);
            return false;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("Upload success: " . $filename . " uploaded to " . $uploadDir);
        return $filename;
    } else {
        error_log("Upload error: Failed to move file from " . $file['tmp_name'] . " to " . $filepath);
        return false;
    }
}

function getUserOrdersCount($pdo, $userId)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch()['count'];
}

function getStatusBadgeClass($status)
{
    $classes = [
        'pending' => 'badge-warning',
        'preparing' => 'badge-info',
        'out_for_delivery' => 'badge-primary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $classes[$status] ?? 'badge-secondary';
}

function sendEmail($to, $subject, $message, $fromName = 'Pizzeria')
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to);
        return false;
    }

    try {
        require_once __DIR__ . '/email.php';

        $gmail = new GmailSender();
        $result = $gmail->sendEmail($to, $subject, $message, true);

        $emailLog = "=== EMAIL LOG ===" . PHP_EOL;
        $emailLog .= "Date: " . date('Y-m-d H:i:s') . PHP_EOL;
        $emailLog .= "To: " . $to . PHP_EOL;
        $emailLog .= "From: " . $fromName . ' <' . SMTP_FROM_EMAIL . '>' . PHP_EOL;
        $emailLog .= "Subject: " . $subject . PHP_EOL;
        $emailLog .= "Status: " . ($result ? 'SENT' : 'FAILED') . PHP_EOL;
        $emailLog .= "Message Preview: " . substr(strip_tags($message), 0, 200) . "..." . PHP_EOL;
        $emailLog .= "=================" . PHP_EOL . PHP_EOL;

        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logDir . '/email_log.txt', $emailLog, FILE_APPEND | LOCK_EX);

        if (!$result) {
            error_log("Failed to send email to: " . $to . " - Subject: " . $subject);
        }

        return $result;
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

function generateOrderReceiptEmail($orderData, $orderItems, $userData)
{
    $orderNumber = str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);
    $orderDate = formatDate($orderData['created_at']);

    $subtotal = 0;
    foreach ($orderItems as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }

    $deliveryFee = ($subtotal > 1500) ? 0 : 200;

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Receipt</title>
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
            }
            .header { 
                background-color: #ffffff; 
                color: #000000; 
                padding: 30px; 
                text-align: center; 
                border-bottom: 1px solid #000000;
            }
            .header h1 { 
                margin: 0; 
                font-size: 32px; 
                font-weight: 700;
                letter-spacing: 2px;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                font-weight: 400;
            }
            .content { 
                padding: 30px; 
            }
            .order-info { 
                border: 1px solid #000000; 
                padding: 20px; 
                margin-bottom: 20px; 
            }
            .order-info h2 { 
                margin-top: 0; 
                color: #000000; 
                font-weight: 600;
                font-size: 18px;
            }
            .order-info p {
                margin: 8px 0;
                font-size: 14px;
            }
            .items-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 20px 0; 
                border: 1px solid #000000;
            }
            .items-table th, .items-table td { 
                padding: 12px; 
                text-align: left; 
                border: 1px solid #000000; 
                font-size: 14px;
            }
            .items-table th { 
                background-color: #000000; 
                color: #ffffff; 
                font-weight: 600;
            }
            .items-table td {
                background-color: #ffffff;
            }
            .total-section { 
                border: 1px solid #000000; 
                padding: 20px; 
                margin-top: 20px; 
            }
            .total-row { 
                display: flex; 
                justify-content: space-between; 
                margin: 10px 0; 
                font-size: 14px;
            }
            .grand-total { 
                font-size: 18px; 
                font-weight: 700; 
                color: #000000; 
                border-top: 2px solid #000000; 
                padding-top: 10px; 
                margin-top: 15px;
            }
            .paid-status { 
                background-color: #000000; 
                color: #ffffff; 
                padding: 8px 15px; 
                display: inline-block; 
                font-weight: 600; 
                margin: 10px 0; 
                font-size: 12px;
            }
            .footer { 
                background-color: #000000; 
                color: #ffffff; 
                padding: 20px; 
                text-align: center; 
            }
            .footer p {
                margin: 5px 0;
                font-size: 12px;
            }
            .thank-you { 
                color: #000000; 
                font-size: 18px; 
                margin: 20px 0; 
                text-align: center; 
                font-weight: 600;
            }
            .order-status {
                text-align: center;
                margin-top: 30px;
                color: #000000;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>PIZZERIA</h1>
                <p>Order Receipt</p>
            </div>
            
            <div class="content">
                <div class="order-info">
                    <h2>Order Details</h2>
                    <p><strong>Order Number:</strong> #' . $orderNumber . '</p>
                    <p><strong>Order Date:</strong> ' . $orderDate . '</p>
                    <p><strong>Customer:</strong> ' . htmlspecialchars($userData['name']) . '</p>
                    <p><strong>Email:</strong> ' . htmlspecialchars($userData['email']) . '</p>
                    <p><strong>Phone:</strong> ' . htmlspecialchars($orderData['phone']) . '</p>
                    <p><strong>Delivery Address:</strong> ' . htmlspecialchars($orderData['delivery_address']) . '</p>
                    <p><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $orderData['payment_method'])) . '</p>';

    if (strtolower($orderData['payment_method']) === 'paypal') {
        $html .= '<p><span class="paid-status">PAID</span> <em>Payment completed via PayPal</em></p>';
    }

    if (!empty($orderData['notes'])) {
        $html .= '<p><strong>Order Notes:</strong> ' . htmlspecialchars($orderData['notes']) . '</p>';
    }

    $html .= '
                </div>
                
                <h3 style="font-weight: 600; color: #000000; margin: 20px 0 10px 0;">Order Items</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Pizza</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($orderItems as $item) {
        $itemTotal = $item['quantity'] * $item['price'];
        $html .= '
                        <tr>
                            <td>' . htmlspecialchars($item['pizza_name']) . '</td>
                            <td>' . $item['quantity'] . '</td>
                            <td>' . formatPrice($item['price']) . '</td>
                            <td>' . formatPrice($itemTotal) . '</td>
                        </tr>';
    }

    $html .= '
                    </tbody>
                </table>
                
                <div class="total-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>' . formatPrice($subtotal) . '</span>
                    </div>
                    <div class="total-row">
                        <span>Delivery Fee:</span>
                        <span>' . formatPrice($deliveryFee) . '</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Grand Total:</span>
                        <span>' . formatPrice($orderData['total_price']) . '</span>
                    </div>
                </div>
                
                <div class="thank-you">
                    Thank you for your order!
                </div>
                
                <div class="order-status">
                    Your order is being prepared and will be delivered to your address.
                    <br>
                    <strong>Estimated delivery time: 30-45 minutes</strong>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; 2026 Pizzeria. All rights reserved.</p>
                <p>For inquiries, contact us at pizzeriagroup5@gmail.com</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

function getPizzaImagePath($imageName)
{
    if (empty($imageName)) {
        return null;
    }

    $imagePath = 'assets/images/pizzas/' . $imageName;

    $basePath = '';
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
        $basePath = '../';
    }

    $fullPath = $basePath . $imagePath;

    if (file_exists($fullPath)) {
        return $imagePath;
    }

    return null;
}

function initializeDailyStock($pizzaId = null)
{
    $pdo = getDBConnection();

    if ($pizzaId) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO pizza_stock (pizza_id, stock_date, current_stock, initial_stock)
            VALUES (?, CURDATE(), 10, 10)
        ");
        return $stmt->execute([$pizzaId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO pizza_stock (pizza_id, stock_date, current_stock, initial_stock)
            SELECT id, CURDATE(), 10, 10 
            FROM pizzas
        ");
        return $stmt->execute();
    }
}

function getCurrentStock($pizzaId)
{
    $pdo = getDBConnection();

    initializeDailyStock($pizzaId);

    $stmt = $pdo->prepare("
        SELECT current_stock 
        FROM pizza_stock 
        WHERE pizza_id = ? AND stock_date = CURDATE()
    ");
    $stmt->execute([$pizzaId]);
    $result = $stmt->fetchColumn();

    return $result !== false ? (int)$result : 10;
}

function updateStock($pizzaId, $quantity)
{
    $pdo = getDBConnection();

    initializeDailyStock($pizzaId);

    $stmt = $pdo->prepare("
        UPDATE pizza_stock 
        SET current_stock = current_stock - ? 
        WHERE pizza_id = ? AND stock_date = CURDATE()
    ");
    return $stmt->execute([$quantity, $pizzaId]);
}

function setStock($pizzaId, $stock)
{
    $pdo = getDBConnection();

    initializeDailyStock($pizzaId);

    $stmt = $pdo->prepare("
        UPDATE pizza_stock 
        SET current_stock = ? 
        WHERE pizza_id = ? AND stock_date = CURDATE()
    ");
    return $stmt->execute([$stock, $pizzaId]);
}

function isInStock($pizzaId, $quantity = 1)
{
    return getCurrentStock($pizzaId) >= $quantity;
}

function getAllPizzasWithStock()
{
    $pdo = getDBConnection();

    initializeDailyStock();

    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(ps.current_stock, 10) as current_stock,
               COALESCE(ps.initial_stock, 10) as initial_stock
        FROM pizzas p
        LEFT JOIN pizza_stock ps ON p.id = ps.pizza_id AND ps.stock_date = CURDATE()
        ORDER BY p.category, p.name
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function resetDailyStock()
{
    $pdo = getDBConnection();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO pizza_stock (pizza_id, stock_date, current_stock, initial_stock)
            SELECT id, CURDATE(), 10, 10 
            FROM pizzas 
            WHERE NOT EXISTS (
                SELECT 1 FROM pizza_stock 
                WHERE pizza_stock.pizza_id = pizzas.id 
                AND pizza_stock.stock_date = CURDATE()
            )
        ");
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM pizza_stock 
            WHERE stock_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();

        return true;
    } catch (Exception $e) {
        error_log("Error resetting daily stock: " . $e->getMessage());
        return false;
    }
}
