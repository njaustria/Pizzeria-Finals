<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'Reports';
$pdo = getDBConnection();

initializeDailyStock();

$today = date('Y-m-d');

try {
    $totalSales = $pdo->query("
        SELECT COUNT(*) 
        FROM orders 
        WHERE status = 'completed'
    ")->fetchColumn();

    $totalRevenue = $pdo->query("
        SELECT COALESCE(SUM(total_price), 0) 
        FROM orders 
        WHERE status = 'completed'
    ")->fetchColumn();

    $totalOrders = $pdo->query("
        SELECT COUNT(*) 
        FROM orders
    ")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders 
        WHERE status = 'completed' AND DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $todaysSales = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_price), 0) 
        FROM orders 
        WHERE status = 'completed' AND DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $todaysRevenue = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $todaysOrders = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ps.current_stock), 0) 
        FROM pizza_stock ps 
        WHERE ps.stock_date = ?
    ");
    $stmt->execute([$today]);
    $totalRemainingStock = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT 
            p.name,
            p.category,
            COALESCE(ps.current_stock, 10) as remaining_stock,
            COALESCE(ps.initial_stock, 10) as initial_stock,
            COALESCE(ps.initial_stock - ps.current_stock, 0) as sold_today
        FROM pizzas p
        LEFT JOIN pizza_stock ps ON p.id = ps.pizza_id AND ps.stock_date = ?
        WHERE p.availability = 1
        ORDER BY p.category, p.name
    ");
    $stmt->execute([$today]);
    $stockBreakdown = $stmt->fetchAll();

    $monthlyRevenue = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COALESCE(SUM(total_price), 0) as revenue,
            COUNT(*) as orders
        FROM orders 
        WHERE status = 'completed' 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();

    $stmt = $pdo->prepare("
        SELECT 
            p.name,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN pizzas p ON oi.pizza_id = p.id
        WHERE DATE(o.created_at) = ? AND o.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $topSellersToday = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "Error loading reports data.";
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

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
            height: 100%;
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

        .chart-container {
            background: #ffffff;
            border: 1px solid #efefef;
            border-radius: 12px;
            padding: 30px;
        }

        .stock-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #007bff;
        }

        .stock-item.low-stock {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }

        .stock-item.out-of-stock {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
    </style>
    <?php include 'includes/admin_navbar.php'; ?>

    <div class="container py-4">
        <header class="mb-5">
            <h2 class="fw-bold">Reports & Analytics</h2>
            <p class="text-muted">Business insights and performance metrics for <?= date('M j, Y') ?>.</p>
        </header>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value"><?= number_format($totalSales) ?></div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">₱<?= number_format($totalRevenue, 2) ?></div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?= number_format($totalOrders) ?></div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Stock Remaining</div>
                    <div class="stat-value"><?= number_format($totalRemainingStock) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Sales Today</div>
                    <div class="stat-value"><?= number_format($todaysSales) ?></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Revenue Today</div>
                    <div class="stat-value">₱<?= number_format($todaysRevenue, 2) ?></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card text-center text-lg-start">
                    <div class="stat-label">Orders Today</div>
                    <div class="stat-value"><?= number_format($todaysOrders) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-container shadow-sm">
                    <h5 class="fw-bold mb-3"><i class="fas fa-chart-area me-2"></i>Monthly Revenue Trend</h5>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-container shadow-sm">
                    <h5 class="fw-bold mb-3"><i class="fas fa-trophy me-2"></i>Top Sellers Today</h5>
                    <?php if (empty($topSellersToday)): ?>
                        <p class="text-muted text-center py-4">No sales recorded for today yet.</p>
                    <?php else: ?>
                        <?php foreach ($topSellersToday as $index => $pizza): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 <?= $index < count($topSellersToday) - 1 ? 'border-bottom' : '' ?>">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($pizza['name']) ?></div>
                                    <small class="text-muted"><?= $pizza['total_sold'] ?> sold</small>
                                </div>
                                <span class="badge bg-success rounded-pill">₱<?= number_format($pizza['revenue'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($monthlyRevenue, 'month')) . "'"; ?>],
                datasets: [{
                    label: 'Revenue (₱)',
                    data: [<?php echo implode(',', array_column($monthlyRevenue, 'revenue')); ?>],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>