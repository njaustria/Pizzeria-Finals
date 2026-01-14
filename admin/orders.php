<?php
require_once '../config/config.php';
require_once '../config/sms_config.php';
require_once '../includes/functions.php';
require_once '../includes/email.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'Manage Orders';
$pdo = getDBConnection();

$stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE payment_method = 'paypal' AND payment_status != 'paid'");
$stmt->execute();

if (isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];

    $stmt = $pdo->prepare("
        SELECT o.*, u.name as user_name, u.email as user_email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $orderData = $stmt->fetch();

    if ($orderData && $orderData['status'] !== $newStatus) {
        $updatePaymentStatus = false;

        if ($orderData['payment_method'] === 'paypal') {
            $updatePaymentStatus = true;
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, payment_status = 'paid' WHERE id = ?");
        } else if ($newStatus === 'completed' && $orderData['payment_method'] === 'cash_on_delivery') {
            $updatePaymentStatus = true;
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, payment_status = 'paid' WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        }

        if ($stmt->execute([$newStatus, $orderId])) {
            $userData = [
                'name' => $orderData['user_name'],
                'email' => $orderData['user_email']
            ];

            $orderData['status'] = $newStatus;

            $emailSent = sendOrderStatusUpdate($orderData, $userData, $newStatus);

            $smsMessage = '';
            if (!empty($orderData['customer_phone'])) {
                $templates = getSMSTemplates();
                $templateKey = 'order_' . $newStatus;

                if (isset($templates[$templateKey])) {
                    $smsMessage = replaceSMSPlaceholders($templates[$templateKey], $orderData, $userData);
                    $smsResult = sendSMS($orderData['customer_phone'], $smsMessage, $orderData['user_id'], $orderId, 'order_status');
                }
            }

            if ($emailSent && !empty($smsMessage) && isset($smsResult) && $smsResult['success']) {
                setFlashMessage('Order status updated and customer notified via email and SMS', 'success');
            } elseif ($emailSent) {
                setFlashMessage('Order status updated and customer notified via email', 'success');
            } else {
                setFlashMessage('Order status updated (notifications may be delayed)', 'success');
            }
        } else {
            setFlashMessage('Failed to update order status', 'error');
        }
    } else {
        setFlashMessage('No changes made to order status', 'info');
    }

    header('Location: orders.php');
    exit();
}

if (isset($_POST['send_order_sms'])) {
    $orderId = (int)$_POST['order_id'];
    $message = trim($_POST['sms_message']);

    if (empty($message)) {
        setFlashMessage('SMS message is required', 'error');
    } else {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.phone as customer_phone 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $orderData = $stmt->fetch();

        if ($orderData && !empty($orderData['customer_phone'])) {
            $result = sendSMS($orderData['customer_phone'], $message, $orderData['user_id'], $orderId, 'custom');
            if ($result['success']) {
                setFlashMessage('SMS sent successfully to ' . htmlspecialchars($orderData['customer_name']), 'success');
            } else {
                setFlashMessage('Failed to send SMS: ' . $result['message'], 'error');
            }
        } else {
            setFlashMessage('Order not found or customer has no phone number', 'error');
        }
    }

    header('Location: orders.php');
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
        FROM orders o 
        JOIN users u ON o.user_id = u.id";

if ($filter !== 'all') {
    $sql .= " WHERE o.status = ?";
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($filter !== 'all') {
    $stmt->execute([$filter]);
} else {
    $stmt->execute();
}
$orders = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #fcfcfc;
        padding-top: 80px;
    }

    .admin-navbar {
        background-color: #fff;
        border-bottom: 1px solid #eee;
        padding: 0.8rem 2rem;
    }

    .navbar-brand {
        font-weight: 800;
        color: #000 !important;
    }

    .nav-link {
        color: #666 !important;
        font-size: 0.9rem;
        font-weight: 500;
        padding: 0.5rem 1rem !important;
    }

    .nav-link.active {
        color: #000 !important;
        font-weight: 700;
        border-bottom: 2px solid #000;
    }

    .filter-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        overflow-x: auto;
        padding-bottom: 10px;
    }

    .filter-btn {
        padding: 8px 20px;
        border-radius: 50px;
        background: #fff;
        border: 1px solid #eee;
        color: #666;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .filter-btn.active {
        background: #000;
        border-color: #000;
        color: #fff;
    }

    .order-card {
        background: #fff;
        border: 1px solid #efefef;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
    }

    .info-section h6 {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #999;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .status-select {
        font-size: 0.85rem;
        border-radius: 5px;
        border: 1px solid #ddd;
    }

    .flash-message {
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
        border: 1px solid;
    }

    .flash-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }

    .flash-error {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }

    .flash-info {
        background-color: #cce7ff;
        border-color: #b3d9ff;
        color: #004085;
    }
</style>

<?php include 'includes/admin_navbar.php'; ?>

<div class="container py-4">
    <header class="mb-4">
        <h2 class="fw-bold">Manage Orders</h2>
    </header>

    <?php displayFlashMessage(); ?>

    <div class="filter-tabs">
        <?php $statuses = ['all' => 'All Orders', 'pending' => 'Pending', 'preparing' => 'Preparing', 'out_for_delivery' => 'On Delivery', 'completed' => 'Completed'];
        foreach ($statuses as $val => $label): ?>
            <a href="?filter=<?php echo $val; ?>" class="filter-btn <?php echo $filter === $val ? 'active' : ''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <?php foreach ($orders as $order):
        $stmt = $pdo->prepare("SELECT oi.*, p.name as pizza_name FROM order_items oi JOIN pizzas p ON oi.pizza_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
    ?>
        <div class="order-card shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <span class="fw-bold fs-5">Order #<?php echo $order['id']; ?></span>
                    <span class="text-muted ms-2 small"><?php echo date('M j, Y - g:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <select name="status" class="form-select form-select-sm status-select">
                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="out_for_delivery" <?php echo $order['status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                        <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-dark btn-sm px-3">Update</button>
                    <?php if (!empty($order['customer_phone'])): ?>
                        <button type="button" class="btn btn-success btn-sm px-3"
                            data-bs-toggle="modal"
                            data-bs-target="#smsModal"
                            data-order-id="<?php echo $order['id']; ?>"
                            data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>"
                            data-customer-phone="<?php echo htmlspecialchars($order['customer_phone']); ?>"
                            title="Send SMS">
                            <i class="fas fa-sms me-1"></i>SMS
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="row">
                <div class="col-md-4 info-section border-end">
                    <h6>Customer Details</h6>
                    <p class="mb-1 fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($order['phone']); ?></p>
                    <p class="text-muted small"><?php echo htmlspecialchars($order['delivery_address']); ?></p>
                </div>
                <div class="col-md-4 info-section border-end ps-md-4">
                    <h6>Payment Information</h6>
                    <?php
                    $paymentMethodLabels = [
                        'cash_on_delivery' => 'Cash on Delivery',
                        'paypal' => 'PayPal'
                    ];

                    $paymentStatusLabels = [
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed'
                    ];

                    $paymentStatusColors = [
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger'
                    ];
                    ?>
                    <p class="mb-1"><strong>Method:</strong> <?php echo $paymentMethodLabels[$order['payment_method']] ?? $order['payment_method']; ?></p>
                    <p class="mb-1">
                        <strong>Status:</strong>
                        <span class="badge bg-<?php echo $paymentStatusColors[$order['payment_status']] ?? 'secondary'; ?>">
                            <?php echo $paymentStatusLabels[$order['payment_status']] ?? $order['payment_status']; ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-4 info-section ps-md-4">
                    <h6>Order Items</h6>
                    <?php
                    $subtotal = 0;
                    foreach ($items as $item):
                        $subtotal += $item['price'] * $item['quantity'];
                    ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?php echo htmlspecialchars($item['pizza_name']); ?> × <?php echo $item['quantity']; ?></span>
                            <span class="fw-bold"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php
                    $deliveryFee = ($subtotal > 1500) ? 0 : 200;
                    ?>

                    <div class="mt-2 pt-2 border-top">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Subtotal</span>
                            <span><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Delivery Fee</span>
                            <span><?php echo formatPrice($deliveryFee); ?></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3 pt-2 border-top fw-bold">
                        <span>Total Amount</span>
                        <span><?php echo formatPrice($order['total_price']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="smsModal" tabindex="-1" aria-labelledby="smsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="smsModalLabel">
                    <i class="fas fa-sms me-2"></i>Send Order SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="order_id" id="sms_order_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sms_customer" class="form-label">Customer</label>
                        <input type="text" class="form-control" id="sms_customer" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="sms_phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="sms_phone" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="sms_template" class="form-label">Message Template</label>
                        <select class="form-select" id="sms_template">
                            <option value="custom">Custom Message</option>
                            <option value="order_confirmed">Order Confirmed</option>
                            <option value="order_preparing">Order Being Prepared</option>
                            <option value="order_ready">Order Ready</option>
                            <option value="order_completed">Order Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="sms_message" class="form-label">Message</label>
                        <textarea class="form-control" id="sms_message" name="sms_message" rows="4"
                            placeholder="Enter your SMS message..." required></textarea>
                        <div class="form-text">
                            <span id="sms-char-count">0</span>/160 characters
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_order_sms" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i>Send SMS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('smsModal').addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const orderId = button.getAttribute('data-order-id');
        const customerName = button.getAttribute('data-customer-name');
        const customerPhone = button.getAttribute('data-customer-phone');

        document.getElementById('sms_order_id').value = orderId;
        document.getElementById('sms_customer').value = customerName;
        document.getElementById('sms_phone').value = customerPhone;
        document.getElementById('sms_message').value = '';
        document.getElementById('sms_template').value = 'custom';

        document.getElementById('sms-char-count').textContent = '0';
    });

    const templates = {
        'order_confirmed': 'Your order #ORDER_ID has been confirmed. Total: $ORDER_TOTAL. Thank you for choosing our pizzeria!',
        'order_preparing': 'Your order #ORDER_ID is being prepared. Estimated time: 20-30 minutes.',
        'order_ready': 'Your order #ORDER_ID is ready for pickup/delivery!',
        'order_completed': 'Your order #ORDER_ID has been completed. Thank you for your business!'
    };

    document.getElementById('sms_template').addEventListener('change', function() {
        const selectedTemplate = this.value;
        const messageTextarea = document.getElementById('sms_message');
        const orderId = document.getElementById('sms_order_id').value;

        if (selectedTemplate !== 'custom' && templates[selectedTemplate]) {
            let message = templates[selectedTemplate];
            message = message.replace('#ORDER_ID', orderId);
            messageTextarea.value = message;
            messageTextarea.dispatchEvent(new Event('input'));
        }
    });

    document.getElementById('sms_message').addEventListener('input', function() {
        const count = this.value.length;
        const counter = document.getElementById('sms-char-count');
        counter.textContent = count;

        if (count > 160) {
            counter.style.color = 'red';
        } else {
            counter.style.color = '';
        }
    });
</script>