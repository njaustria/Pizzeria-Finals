<?php
require_once '../config/config.php';
require_once '../config/sms_config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'SMS Management';
$pdo = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_sms':
                $phoneNumber = trim($_POST['phone_number']);
                $message = trim($_POST['message']);

                if (empty($phoneNumber) || empty($message)) {
                    $error = 'Phone number and message are required.';
                } else {
                    $result = sendSMS($phoneNumber, $message);
                    if ($result['success']) {
                        $success = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                }
                break;

            case 'send_bulk_sms':
                $userIds = $_POST['user_ids'] ?? [];
                $message = trim($_POST['bulk_message']);
                $template = $_POST['template'];

                if ($template !== 'custom' && !empty($template)) {
                    $templates = getSMSTemplates();
                    $message = $templates[$template];
                }

                if (empty($userIds) || empty($message)) {
                    $error = 'Please select users and enter a message.';
                } else {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    $stmt = $pdo->prepare("SELECT name, phone FROM users WHERE id IN ($placeholders) AND phone IS NOT NULL AND phone != ''");
                    $stmt->execute($userIds);
                    $users = $stmt->fetchAll();

                    if (empty($users)) {
                        $error = 'No users found with valid phone numbers.';
                    } else {
                        $phoneNumbers = array_column($users, 'phone');
                        $result = sendSMS($phoneNumbers, $message);

                        if ($result['success']) {
                            $success = $result['message'];
                        } else {
                            $error = $result['message'];
                        }
                    }
                }
                break;
        }
    }
}

$stmt = $pdo->query("SELECT id, name, email, phone FROM users WHERE role = 'customer' AND phone IS NOT NULL AND phone != '' ORDER BY name");
$usersWithPhones = $stmt->fetchAll();

$logFile = '../logs/sms_log.txt';
$recentLogs = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $recentLogs = array_slice(array_reverse($lines), 0, 10);
}

$templates = getSMSTemplates();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Pizzeria Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>
    <?php require_once 'includes/admin_navbar.php'; ?>

    <div class="container-fluid px-4 py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold mb-0">SMS Management</h2>
                <p class="text-muted">Send SMS notifications to customers</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Send Single SMS</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="send_sms">
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number"
                                    placeholder="Enter phone number" required>
                                <div class="form-text">Enter phone number (numbers only or with country code)</div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="4"
                                    placeholder="Enter your message..." required></textarea>
                                <div class="form-text">
                                    <span id="char-count">0</span>/160 characters
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Send SMS
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Send Bulk SMS</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="send_bulk_sms">
                            <div class="mb-3">
                                <label class="form-label">Select Users</label>
                                <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all">
                                        <label class="form-check-label fw-bold" for="select_all">
                                            Select All
                                        </label>
                                    </div>
                                    <hr>
                                    <?php foreach ($usersWithPhones as $user): ?>
                                        <div class="form-check">
                                            <input class="form-check-input user-checkbox" type="checkbox"
                                                name="user_ids[]" value="<?= $user['id'] ?>"
                                                id="user_<?= $user['id'] ?>">
                                            <label class="form-check-label" for="user_<?= $user['id'] ?>">
                                                <?= htmlspecialchars($user['name']) ?>
                                                <small class="text-muted">(<?= htmlspecialchars($user['phone']) ?>)</small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text"><?= count($usersWithPhones) ?> users with phone numbers</div>
                            </div>

                            <div class="mb-3">
                                <label for="template" class="form-label">Message Template</label>
                                <select class="form-select" id="template" name="template">
                                    <option value="custom">Custom Message</option>
                                    <?php foreach ($templates as $key => $template): ?>
                                        <?php if ($key !== 'custom'): ?>
                                            <option value="<?= $key ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="bulk_message" class="form-label">Message</label>
                                <textarea class="form-control" id="bulk_message" name="bulk_message" rows="4"
                                    placeholder="Enter your message..." required></textarea>
                                <div class="form-text">
                                    <span id="bulk-char-count">0</span>/160 characters
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-broadcast-tower me-1"></i> Send Bulk SMS
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent SMS Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentLogs)): ?>
                            <p class="text-muted mb-0">No SMS activity logged yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Status</th>
                                            <th>Recipients</th>
                                            <th>Message Preview</th>
                                            <th>Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentLogs as $log): ?>
                                            <?php
                                            if (preg_match('/\[(.*?)\] (.*?) - To: (.*?) - Message: (.*?) - Result: (.*)/', $log, $matches)) {
                                                $timestamp = $matches[1];
                                                $status = $matches[2];
                                                $recipients = $matches[3];
                                                $message = $matches[4];
                                                $result = $matches[5];

                                                $statusClass = $status === 'SUCCESS' ? 'success' : 'danger';
                                                $statusIcon = $status === 'SUCCESS' ? 'check-circle' : 'times-circle';
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($timestamp) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $statusClass ?>">
                                                            <i class="fas fa-<?= $statusIcon ?> me-1"></i><?= $status ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($recipients) ?></td>
                                                    <td><?= htmlspecialchars($message) ?></td>
                                                    <td><?= htmlspecialchars($result) ?></td>
                                                </tr>
                                            <?php } ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCharCount(textareaId, counterId) {
            const textarea = document.getElementById(textareaId);
            const counter = document.getElementById(counterId);

            textarea.addEventListener('input', function() {
                const count = this.value.length;
                counter.textContent = count;

                if (count > 160) {
                    counter.style.color = 'red';
                } else {
                    counter.style.color = '';
                }
            });
        }

        updateCharCount('message', 'char-count');
        updateCharCount('bulk_message', 'bulk-char-count');

        document.getElementById('select_all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        const templates = <?= json_encode($templates) ?>;
        document.getElementById('template').addEventListener('change', function() {
            const selectedTemplate = this.value;
            const messageTextarea = document.getElementById('bulk_message');

            if (selectedTemplate !== 'custom' && templates[selectedTemplate]) {
                messageTextarea.value = templates[selectedTemplate];
                messageTextarea.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>

</html>