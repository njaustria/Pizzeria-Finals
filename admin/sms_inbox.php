<?php
require_once '../config/config.php';
require_once '../config/sms_config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'SMS Inbox';
$pdo = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                $messageIds = $_POST['message_ids'] ?? [];
                if (!empty($messageIds)) {
                    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
                    $stmt = $pdo->prepare("UPDATE received_sms SET is_read = TRUE WHERE id IN ($placeholders)");
                    $stmt->execute($messageIds);
                    $success = count($messageIds) . ' message(s) marked as read.';
                }
                break;

            case 'reply_sms':
                $messageId = (int)$_POST['message_id'];
                $replyText = trim($_POST['reply_text']);

                if (empty($replyText)) {
                    $error = 'Reply message is required.';
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM received_sms WHERE id = ?");
                    $stmt->execute([$messageId]);
                    $originalMessage = $stmt->fetch();

                    if ($originalMessage) {
                        $result = sendSMS($originalMessage['sender_phone'], $replyText, $_SESSION['admin_id'], null, 'custom');

                        if ($result['success']) {
                            $stmt = $pdo->prepare("
                                UPDATE received_sms 
                                SET admin_reply = ?, replied_at = NOW(), replied_by = ?, status = 'resolved', is_read = TRUE 
                                WHERE id = ?
                            ");
                            $stmt->execute([$replyText, $_SESSION['admin_id'], $messageId]);

                            $success = 'Reply sent successfully!';
                        } else {
                            $error = 'Failed to send reply: ' . $result['message'];
                        }
                    } else {
                        $error = 'Message not found.';
                    }
                }
                break;

            case 'update_status':
                $messageId = (int)$_POST['message_id'];
                $newStatus = $_POST['status'];
                $priority = $_POST['priority'];

                $stmt = $pdo->prepare("UPDATE received_sms SET status = ?, priority = ? WHERE id = ?");
                if ($stmt->execute([$newStatus, $priority, $messageId])) {
                    $success = 'Message status updated.';
                } else {
                    $error = 'Failed to update message status.';
                }
                break;

            case 'delete_messages':
                $messageIds = $_POST['message_ids'] ?? [];
                if (!empty($messageIds)) {
                    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
                    $stmt = $pdo->prepare("DELETE FROM received_sms WHERE id IN ($placeholders)");
                    $stmt->execute($messageIds);
                    $success = count($messageIds) . ' message(s) deleted.';
                }
                break;
        }
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$readFilter = $_GET['read'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $whereConditions[] = "message_type = ?";
    $params[] = $typeFilter;
}

if ($readFilter === 'unread') {
    $whereConditions[] = "is_read = FALSE";
} elseif ($readFilter === 'read') {
    $whereConditions[] = "is_read = TRUE";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$countSql = "SELECT COUNT(*) FROM received_sms $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalMessages = $stmt->fetchColumn();

$sql = "
    SELECT r.*, u.name as customer_name, a.name as admin_name
    FROM received_sms r
    LEFT JOIN users u ON r.customer_id = u.id
    LEFT JOIN users a ON r.replied_by = a.id
    $whereClause
    ORDER BY r.received_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM received_sms")->fetchColumn(),
    'unread' => $pdo->query("SELECT COUNT(*) FROM received_sms WHERE is_read = FALSE")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM received_sms WHERE DATE(received_at) = CURDATE()")->fetchColumn(),
    'urgent' => $pdo->query("SELECT COUNT(*) FROM received_sms WHERE priority = 'urgent' AND status NOT IN ('resolved', 'closed')")->fetchColumn()
];

$totalPages = ceil($totalMessages / $perPage);
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
                <h2 class="fw-bold mb-0">SMS Inbox</h2>
                <p class="text-muted">Manage incoming SMS messages from customers</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope fa-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?= $stats['total'] ?></div>
                                <div class="text-muted">Total Messages</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope-open fa-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?= $stats['unread'] ?></div>
                                <div class="text-muted">Unread Messages</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock fa-2x text-info"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?= $stats['today'] ?></div>
                                <div class="text-muted">Today</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?= $stats['urgent'] ?></div>
                                <div class="text-muted">Urgent</div>
                            </div>
                        </div>
                    </div>
                </div>
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

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="order_query" <?= $typeFilter === 'order_query' ? 'selected' : '' ?>>Order Query</option>
                            <option value="complaint" <?= $typeFilter === 'complaint' ? 'selected' : '' ?>>Complaint</option>
                            <option value="inquiry" <?= $typeFilter === 'inquiry' ? 'selected' : '' ?>>Inquiry</option>
                            <option value="general" <?= $typeFilter === 'general' ? 'selected' : '' ?>>General</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Read Status</label>
                        <select name="read" class="form-select">
                            <option value="all" <?= $readFilter === 'all' ? 'selected' : '' ?>>All Messages</option>
                            <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                            <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bulk Actions</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="markSelectedAsRead()">
                                <i class="fas fa-eye me-1"></i>Mark Read
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="deleteSelected()">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Messages (<?= $totalMessages ?> total)</h5>
                    <div>
                        <input type="checkbox" id="select_all" class="form-check-input me-2">
                        <label for="select_all" class="form-check-label">Select All</label>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No messages found</h5>
                        <p class="text-muted">No SMS messages match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <tbody>
                                <?php foreach ($messages as $message): ?>
                                    <tr class="<?= !$message['is_read'] ? 'table-warning' : '' ?>">
                                        <td width="30">
                                            <input type="checkbox" class="form-check-input message-checkbox"
                                                value="<?= $message['id'] ?>">
                                        </td>
                                        <td width="150">
                                            <div class="fw-bold"><?= htmlspecialchars($message['sender_phone']) ?></div>
                                            <small class="text-muted">
                                                <?= $message['customer_name'] ? htmlspecialchars($message['customer_name']) : 'Unknown' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="flex-grow-1">
                                                    <div class="message-preview">
                                                        <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>
                                                        <?= strlen($message['message']) > 100 ? '...' : '' ?>
                                                    </div>
                                                    <?php if ($message['admin_reply']): ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <small class="text-muted">
                                                                <i class="fas fa-reply me-1"></i>Reply:
                                                            </small>
                                                            <?= htmlspecialchars(substr($message['admin_reply'], 0, 80)) ?>
                                                            <?= strlen($message['admin_reply']) > 80 ? '...' : '' ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <div class="badge bg-<?= getStatusColor($message['status']) ?> mb-1">
                                                        <?= ucfirst(str_replace('_', ' ', $message['status'])) ?>
                                                    </div>
                                                    <div class="badge bg-<?= getPriorityColor($message['priority']) ?> mb-1">
                                                        <?= ucfirst($message['priority']) ?>
                                                    </div>
                                                    <div class="badge bg-<?= getTypeColor($message['message_type']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $message['message_type'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td width="100" class="text-center">
                                            <div class="small text-muted">
                                                <?= date('M d, Y', strtotime($message['received_at'])) ?><br>
                                                <?= date('h:i A', strtotime($message['received_at'])) ?>
                                            </div>
                                        </td>
                                        <td width="120">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#messageModal"
                                                    onclick="openMessageModal(<?= htmlspecialchars(json_encode($message)) ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-success"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#replyModal"
                                                    onclick="openReplyModal(<?= $message['id'] ?>, '<?= htmlspecialchars($message['sender_phone']) ?>')">
                                                    <i class="fas fa-reply"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#statusModal"
                                                    onclick="openStatusModal(<?= $message['id'] ?>, '<?= $message['status'] ?>', '<?= $message['priority'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Messages pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="messageDetails"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Reply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="reply_sms">
                    <input type="hidden" name="message_id" id="reply_message_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reply to:</label>
                            <input type="text" class="form-control" id="reply_phone" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reply Message</label>
                            <textarea class="form-control" name="reply_text" rows="4"
                                placeholder="Type your reply..." required></textarea>
                            <div class="form-text">Character count: <span id="reply-char-count">0</span>/160</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="message_id" id="status_message_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="status_select" class="form-select">
                                <option value="new">New</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" id="priority_select" class="form-select">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('select_all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        const replyTextarea = document.querySelector('textarea[name="reply_text"]');
        if (replyTextarea) {
            replyTextarea.addEventListener('input', function() {
                const count = this.value.length;
                const counter = document.getElementById('reply-char-count');
                counter.textContent = count;

                if (count > 160) {
                    counter.style.color = 'red';
                } else {
                    counter.style.color = '';
                }
            });
        }

        function openMessageModal(message) {
            const detailsDiv = document.getElementById('messageDetails');
            detailsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>From:</strong> ${message.sender_phone}<br>
                        <strong>Customer:</strong> ${message.customer_name || 'Unknown'}<br>
                        <strong>Type:</strong> ${message.message_type}<br>
                        <strong>Priority:</strong> ${message.priority}<br>
                        <strong>Status:</strong> ${message.status}
                    </div>
                    <div class="col-md-6">
                        <strong>Received:</strong> ${new Date(message.received_at).toLocaleString()}<br>
                        <strong>Read:</strong> ${message.is_read ? 'Yes' : 'No'}<br>
                        ${message.replied_at ? '<strong>Replied:</strong> ' + new Date(message.replied_at).toLocaleString() : ''}
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <strong>Message:</strong>
                    <div class="mt-2 p-3 bg-light rounded">${message.message}</div>
                </div>
                ${message.admin_reply ? `
                    <div>
                        <strong>Reply:</strong>
                        <div class="mt-2 p-3 bg-primary text-white rounded">${message.admin_reply}</div>
                    </div>
                ` : ''}
            `;
        }

        function openReplyModal(messageId, phone) {
            document.getElementById('reply_message_id').value = messageId;
            document.getElementById('reply_phone').value = phone;
            document.querySelector('textarea[name="reply_text"]').value = '';
            document.getElementById('reply-char-count').textContent = '0';
        }

        function openStatusModal(messageId, status, priority) {
            document.getElementById('status_message_id').value = messageId;
            document.getElementById('status_select').value = status;
            document.getElementById('priority_select').value = priority;
        }

        function markSelectedAsRead() {
            const selected = getSelectedMessages();
            if (selected.length === 0) {
                alert('Please select messages to mark as read.');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="mark_read">
                ${selected.map(id => `<input type="hidden" name="message_ids[]" value="${id}">`).join('')}
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function deleteSelected() {
            const selected = getSelectedMessages();
            if (selected.length === 0) {
                alert('Please select messages to delete.');
                return;
            }

            if (!confirm(`Are you sure you want to delete ${selected.length} message(s)?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_messages">
                ${selected.map(id => `<input type="hidden" name="message_ids[]" value="${id}">`).join('')}
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function getSelectedMessages() {
            const checkboxes = document.querySelectorAll('.message-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
    </script>
</body>

</html>

<?php
function getStatusColor($status)
{
    switch ($status) {
        case 'new':
            return 'primary';
        case 'in_progress':
            return 'warning';
        case 'resolved':
            return 'success';
        case 'closed':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function getPriorityColor($priority)
{
    switch ($priority) {
        case 'urgent':
            return 'danger';
        case 'high':
            return 'warning';
        case 'normal':
            return 'info';
        case 'low':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function getTypeColor($type)
{
    switch ($type) {
        case 'order_query':
            return 'primary';
        case 'complaint':
            return 'danger';
        case 'inquiry':
            return 'info';
        case 'general':
            return 'secondary';
        default:
            return 'secondary';
    }
}
?>