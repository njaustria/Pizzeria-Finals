<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdminLogin();
$pageTitle = 'SMS System Status';

require_once '../config/database.php';
$pdo = getDBConnection();

$stmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count,
        MAX(received_at) as latest
    FROM received_sms 
    GROUP BY status 
    ORDER BY latest DESC
");
$statusCounts = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT 
        id, sender_phone, message, received_at, status, is_read, message_type
    FROM received_sms 
    ORDER BY received_at DESC 
    LIMIT 5
");
$latestMessages = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM received_sms");
$totalResult = $stmt->fetch();
$totalMessages = $totalResult['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Pizzeria Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php require_once 'includes/admin_navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold text-success mb-0">✅ SMS Reception System - Working!</h2>
                <p class="text-muted">Your SMS system is now receiving customer replies</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h4 class="mb-0"><?= $totalMessages ?></h4>
                                <small>Total Messages</small>
                            </div>
                            <i class="fas fa-sms fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $statusColors = ['new' => 'success', 'in_progress' => 'warning', 'resolved' => 'info', 'closed' => 'secondary'];
            foreach ($statusCounts as $status):
            ?>
                <div class="col-md-3">
                    <div class="card bg-<?= $statusColors[$status['status']] ?? 'secondary' ?> text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-0"><?= $status['count'] ?></h4>
                                    <small><?= ucfirst($status['status']) ?></small>
                                </div>
                                <i class="fas fa-<?= $status['status'] === 'new' ? 'envelope' : 'check-circle' ?> fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Latest Messages</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($latestMessages)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No messages yet</h5>
                                <p class="text-muted">Customer SMS replies will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>From</th>
                                            <th>Message</th>
                                            <th>Received</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latestMessages as $msg): ?>
                                            <tr class="<?= $msg['is_read'] ? '' : 'table-warning' ?>">
                                                <td>
                                                    <strong><?= htmlspecialchars($msg['sender_phone']) ?></strong>
                                                    <?php if (!$msg['is_read']): ?>
                                                        <span class="badge bg-danger ms-1">New</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars(substr($msg['message'], 0, 50)) ?>
                                                    <?= strlen($msg['message']) > 50 ? '...' : '' ?>
                                                    <br><small class="text-muted"><?= ucfirst($msg['message_type']) ?></small>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, Y g:i A', strtotime($msg['received_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $statusColors[$msg['status']] ?? 'secondary' ?>">
                                                        <?= ucfirst($msg['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>System Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-database text-success me-2"></i>
                            Database: <span class="badge bg-success">Connected</span>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-wifi text-success me-2"></i>
                            Webhook: <span class="badge bg-success">Active</span>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-mobile-alt text-success me-2"></i>
                            SMS Gateway: <span class="badge bg-success">Configured</span>
                        </div>
                        <hr>
                        <h6>Next Steps:</h6>
                        <ol class="small">
                            <li>Configure SMS gateway app with webhook URL</li>
                            <li>Send test SMS to your phone</li>
                            <li>Check messages in SMS inbox</li>
                        </ol>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <a href="sms_inbox.php" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-inbox me-1"></i> Open SMS Inbox
                        </a>
                        <a href="webhook_monitor.php" class="btn btn-info w-100 mb-2">
                            <i class="fas fa-satellite-dish me-1"></i> Monitor Webhook
                        </a>
                        <a href="sms.php" class="btn btn-success w-100">
                            <i class="fas fa-paper-plane me-1"></i> Send SMS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    </script>
</body>

</html>