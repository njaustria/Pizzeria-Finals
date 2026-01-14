<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'Admin Dashboard';
$pdo = getDBConnection();

$stats = [
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'total_pizzas' => $pdo->query("SELECT COUNT(*) FROM pizzas")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'completed'")->fetchColumn() ?? 0,
    'today_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];

$stockStats = [
    'total_stock' => $pdo->query("
        SELECT COALESCE(SUM(ps.current_stock), 0) 
        FROM pizza_stock ps 
        WHERE ps.stock_date = CURDATE()
    ")->fetchColumn() ?? 0,
    'out_of_stock' => $pdo->query("
        SELECT COUNT(*) FROM pizzas p
        LEFT JOIN pizza_stock ps ON p.id = ps.pizza_id AND ps.stock_date = CURDATE()
        WHERE COALESCE(ps.current_stock, 10) = 0
    ")->fetchColumn() ?? 0,
    'low_stock' => $pdo->query("
        SELECT COUNT(*) FROM pizzas p
        LEFT JOIN pizza_stock ps ON p.id = ps.pizza_id AND ps.stock_date = CURDATE()
        WHERE COALESCE(ps.current_stock, 10) > 0 AND COALESCE(ps.current_stock, 10) <= 3
    ")->fetchColumn() ?? 0,
];

initializeDailyStock();

$stmt = $pdo->query("SELECT o.*, u.name as customer_name, u.email as customer_email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10");
$recentOrders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Pizzeria Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <?php
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert' style='margin-bottom: 0;'>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
    }
    ?>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fcfcfc;
            color: #000000;
            padding-top: 80px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #efefef;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: #000;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #888;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            margin-top: 5px;
        }

        .content-container {
            background: #ffffff;
            border: 1px solid #efefef;
            border-radius: 12px;
            padding: 30px;
        }

        .table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #f0f0f0;
            color: #999;
        }

        .btn-details {
            background: #000;
            color: #fff;
            font-size: 0.8rem;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .btn-details:hover {
            color: #fff;
            opacity: 0.8;
        }
    </style>

    <?php include 'includes/admin_navbar.php'; ?>

    <div class="container py-4">
        <header class="mb-5">
            <h2 class="fw-bold">Dashboard Overview</h2>
            <p class="text-muted">Welcome back, Administrator.</p>
        </header>

        <div class="row g-4 mb-5">
            <div class="col-md-4 col-lg-2">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Revenue</div>
                    <div class="stat-value"><?php echo formatPrice($stats['total_revenue']); ?></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Orders</div>
                    <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Customers</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Pizzas</div>
                    <div class="stat-value"><?php echo $stats['total_pizzas']; ?></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Today</div>
                    <div class="stat-value"><?php echo $stats['today_orders']; ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-label">Total Stock</div>
                    <div class="stat-value"><?php echo $stockStats['total_stock']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value"><?php echo $stockStats['out_of_stock']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-label">Low Stock</div>
                    <div class="stat-value"><?php echo $stockStats['low_stock']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-label">In Stock</div>
                    <div class="stat-value"><?php echo ($stats['total_pizzas'] - $stockStats['out_of_stock']); ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="content-container shadow-sm">
                    <h5 class="fw-bold mb-3">📧 Email System</h5>
                    <p class="text-muted small mb-3">Gmail integration for order receipts and status updates</p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success">✅ Active & Configured</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-container shadow-sm">
                    <h5 class="fw-bold mb-3">⚡ Quick Actions</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="orders.php?filter=pending" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-clock me-1"></i> Pending Orders
                        </a>
                        <a href="pizzas.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-pizza-slice me-1"></i> Manage Menu
                        </a>
                        <a href="contacts.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-comments me-1"></i> Messages
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-container shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold">Recent Orders</h5>
                <a href="orders.php" class="text-dark small fw-bold">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $order['id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                </td>
                                <td class="fw-bold"><?php echo formatPrice($order['total_price']); ?></td>
                                <td>
                                    <span class="badge rounded-pill <?php echo $order['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-success text-white'; ?> px-3 py-2">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td class="text-end">
                                    <a href="orders.php?id=<?php echo $order['id']; ?>" class="btn-details">Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>