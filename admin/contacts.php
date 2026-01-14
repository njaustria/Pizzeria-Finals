<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'Contact Messages';
$pdo = getDBConnection();

if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $pdo->prepare("UPDATE contacts SET status = 'read' WHERE id = ?")->execute([$id]);
    header('Location: contacts.php');
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
    setFlashMessage('Message deleted', 'success');
    header('Location: contacts.php');
    exit();
}

if (isset($_POST['reply_submit'])) {
    $messageId = (int)$_POST['message_id'];
    $replySubject = sanitizeInput($_POST['reply_subject']);
    $replyMessage = sanitizeInput($_POST['reply_message']);

    $stmt = $pdo->prepare("SELECT name, email, subject_type FROM contacts WHERE id = ?");
    $stmt->execute([$messageId]);
    $originalMessage = $stmt->fetch();

    if ($originalMessage && !empty($replySubject) && !empty($replyMessage)) {
        $emailSubject = 'Re: ' . $originalMessage['subject_type'];
        $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #000; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .footer { background: #f4f4f4; padding: 15px; font-size: 0.9em; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . SITE_NAME . " - Customer Support</h2>
                </div>
                <div class='content'>
                    <p>Dear " . htmlspecialchars($originalMessage['name']) . ",</p>
                    <p>Thank you for contacting us. Here's our response to your inquiry:</p>
                    <hr>
                    <p>" . nl2br(htmlspecialchars($replyMessage)) . "</p>
                    <hr>
                    <p>If you have any additional questions, please don't hesitate to contact us.</p>
                    <p>Best regards,<br>" . SITE_NAME . " Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated response from " . SITE_NAME . ". Please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        if (sendEmail($originalMessage['email'], $emailSubject, $emailBody)) {
            $stmt = $pdo->prepare("UPDATE contacts SET status = 'replied', reply_message = ? WHERE id = ?");
            if ($stmt->execute([$replyMessage, $messageId])) {
                setFlashMessage('Reply sent successfully!', 'success');
            } else {
                setFlashMessage('Reply sent but failed to save in database. Please check your database configuration.', 'warning');
            }
        } else {
            setFlashMessage('Failed to send reply. Please check your email configuration in includes/email.php or includes/smtp.php', 'error');
        }
    } else {
        setFlashMessage('Please fill in all reply fields.', 'error');
    }

    header('Location: contacts.php');
    exit();
}

$stmt = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC");
$messages = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #fcfcfc;
        color: #000000;
        padding-top: 80px;
    }

    .admin-navbar {
        background-color: #ffffff;
        border-bottom: 1px solid #eeeeee;
        padding: 0.8rem 2rem;
    }

    .navbar-brand {
        font-weight: 800;
        letter-spacing: 1px;
        color: #000 !important;
    }

    .nav-link {
        color: #666666 !important;
        font-weight: 500;
        font-size: 0.9rem;
        padding: 0.5rem 1rem !important;
        transition: color 0.2s;
    }

    .nav-link:hover,
    .nav-link.active {
        color: #000000 !important;
    }

    .nav-link.active {
        font-weight: 700;
        border-bottom: 2px solid #000;
    }

    .message-container {
        background: #ffffff;
        border: 1px solid #efefef;
        border-radius: 12px;
        margin-bottom: 20px;
        transition: all 0.2s ease;
        overflow: hidden;
    }

    .message-container:hover {
        border-color: #000;
    }

    .message-container.unread {
        border-left: 5px solid #000;
    }

    .message-header-clickable {
        padding: 25px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .message-header-clickable:hover {
        background-color: #f8f9fa;
    }

    .collapse-content {
        padding: 0 25px 25px 25px;
        border-top: 1px solid #f0f0f0;
    }

    .msg-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0;
    }

    .expand-indicator {
        transition: transform 0.2s ease;
        color: #666;
        font-size: 1.2rem;
    }

    .expand-indicator.collapsed {
        transform: rotate(-90deg);
    }

    .sender-name {
        font-weight: 700;
        font-size: 1.1rem;
        margin: 0;
    }

    .sender-email {
        color: #888;
        font-size: 0.85rem;
    }

    .msg-date {
        font-size: 0.8rem;
        color: #aaa;
        font-weight: 500;
    }

    .msg-body {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        font-size: 0.95rem;
        color: #333;
        line-height: 1.6;
    }

    .btn-action {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-radius: 5px;
        padding: 6px 12px;
    }

    .status-badge {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-unread {
        background: #ffc107;
        color: #000;
    }

    .status-read {
        background: #6c757d;
        color: white;
    }

    .status-replied {
        background: #28a745;
        color: white;
    }

    .modal-header {
        border-bottom: 1px solid #dee2e6;
    }

    .modal-footer {
        border-top: 1px solid #dee2e6;
    }

    .flash-message {
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .flash-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .flash-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .flash-info {
        background-color: #cce7ff;
        color: #004085;
        border: 1px solid #b8d4f0;
    }
</style>

<?php include 'includes/admin_navbar.php'; ?>

<div class="container py-4">
    <?php displayFlashMessage(); ?>

    <header class="mb-5">
        <h2 class="fw-bold">Contact Messages</h2>
        <p class="text-muted">Manage inquiries and feedback from your customers.</p>
    </header>

    <?php if (empty($messages)): ?>
        <div class="text-center py-5">
            <i class="fas fa-envelope-open text-light mb-3" style="font-size: 4rem;"></i>
            <h5 class="text-muted">No messages found.</h5>
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php foreach ($messages as $msg): ?>
                    <div class="message-container shadow-sm <?php echo $msg['status'] === 'unread' ? 'unread' : ''; ?>">
                        <div class="message-header-clickable"
                            data-bs-toggle="collapse"
                            data-bs-target="#message-<?php echo $msg['id']; ?>"
                            aria-expanded="false"
                            aria-controls="message-<?php echo $msg['id']; ?>">
                            <div class="msg-header">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <h3 class="sender-name"><?php echo htmlspecialchars($msg['name']); ?></h3>
                                        <div class="sender-email"><?php echo htmlspecialchars($msg['email']); ?></div>
                                        <div class="msg-date mt-1">
                                            <i class="far fa-clock me-1"></i> <?php echo date('M j, Y - g:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                        <?php if ($msg['subject_type']): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-light text-dark border">Subject: <?php echo htmlspecialchars($msg['subject_type']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="expand-indicator ms-3 collapsed">
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <span class="status-badge status-<?php echo $msg['status']; ?>">
                                        <?php echo ucfirst($msg['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="collapse" id="message-<?php echo $msg['id']; ?>">
                            <div class="collapse-content">
                                <div class="msg-body mb-3">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>

                                <?php if ($msg['status'] === 'replied' && !empty($msg['reply_message'])): ?>
                                    <div class="mb-3 p-3 bg-light border-start border-success border-3">
                                        <h6 class="fw-bold text-success mb-2">
                                            <i class="fas fa-reply me-1"></i> Your Reply:
                                        </h6>
                                        <div class="text-muted">
                                            <?php echo nl2br(htmlspecialchars($msg['reply_message'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2">
                                    <?php if ($msg['status'] === 'unread'): ?>
                                        <a href="?read=<?php echo $msg['id']; ?>" class="btn btn-dark btn-action">
                                            Mark Read
                                        </a>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-primary btn-action"
                                        onclick="openReplyModal(<?php echo $msg['id']; ?>, '<?php echo htmlspecialchars($msg['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($msg['subject_type'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-reply"></i> Reply
                                    </button>

                                    <a href="?delete=<?php echo $msg['id']; ?>" class="btn btn-outline-danger btn-action"
                                        onclick="return confirm('Permanently delete this message?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replyModalLabel">Reply to Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="modal-message-id" name="message_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Replying to:</label>
                        <div id="modal-customer-info" class="text-muted"></div>
                    </div>

                    <div class="mb-3">
                        <label for="reply-subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="reply-subject" name="reply_subject" required>
                    </div>

                    <div class="mb-3">
                        <label for="reply-message" class="form-label">Your Reply *</label>
                        <textarea class="form-control" id="reply-message" name="reply_message" rows="6" required placeholder="Type your reply here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reply_submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function openReplyModal(messageId, customerName, customerEmail, originalSubject) {
        document.getElementById('modal-message-id').value = messageId;
        document.getElementById('modal-customer-info').innerHTML = `<strong>${customerName}</strong> &lt;${customerEmail}&gt;`;
        document.getElementById('reply-subject').value = `Re: ${originalSubject}`;
        document.getElementById('reply-message').value = '';

        const modal = new bootstrap.Modal(document.getElementById('replyModal'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(element) {
            element.addEventListener('click', function() {
                const target = this.getAttribute('data-bs-target');
                const chevron = this.querySelector('.expand-indicator');

                setTimeout(function() {
                    const targetElement = document.querySelector(target);
                    if (targetElement.classList.contains('show')) {
                        chevron.classList.remove('collapsed');
                    } else {
                        chevron.classList.add('collapsed');
                    }
                }, 10);
            });
        });
    });
</script>