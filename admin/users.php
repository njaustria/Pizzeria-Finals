<?php
require_once '../config/config.php';
require_once '../config/sms_config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'Manage Users';
$pdo = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_admin':
                $name = sanitizeInput($_POST['name']);
                $email = sanitizeInput($_POST['email']);
                $password = $_POST['password'];

                if (empty($name) || empty($email) || empty($password)) {
                    $error = 'All fields are required.';
                } elseif (!validateEmail($email)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Email already exists.';
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
                        if ($stmt->execute([$name, $email, $passwordHash])) {
                            $success = 'Administrator added successfully.';
                        } else {
                            $error = 'Failed to add administrator.';
                        }
                    }
                }
                break;

            case 'edit_user':
                $userId = (int)$_POST['user_id'];
                $name = sanitizeInput($_POST['name']);
                $email = sanitizeInput($_POST['email']);
                $role = $_POST['role'];
                $password = $_POST['password'];

                if (empty($name) || empty($email)) {
                    $error = 'Name and email are required.';
                } elseif (!validateEmail($email)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $userId]);
                    if ($stmt->fetch()) {
                        $error = 'Email already exists for another user.';
                    } else {
                        if (!empty($password)) {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                            $result = $stmt->execute([$name, $email, $passwordHash, $role, $userId]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                            $result = $stmt->execute([$name, $email, $role, $userId]);
                        }

                        if ($result) {
                            $success = 'User updated successfully.';
                        } else {
                            $error = 'Failed to update user.';
                        }
                    }
                }
                break;

            case 'delete_user':
                $userId = (int)$_POST['user_id'];
                if ($userId == $_SESSION['admin_id']) {
                    $error = 'You cannot delete your own account.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$userId])) {
                        $success = 'User deleted successfully.';
                    } else {
                        $error = 'Failed to delete user.';
                    }
                }
                break;

            case 'send_user_sms':
                $userId = (int)$_POST['user_id'];
                $message = trim($_POST['sms_message']);

                if (empty($message)) {
                    $error = 'SMS message is required.';
                } else {
                    $stmt = $pdo->prepare("SELECT name, phone FROM users WHERE id = ? AND phone IS NOT NULL AND phone != ''");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $result = sendSMS($user['phone'], $message);
                        if ($result['success']) {
                            $success = 'SMS sent successfully to ' . htmlspecialchars($user['name']);
                        } else {
                            $error = 'Failed to send SMS: ' . $result['message'];
                        }
                    } else {
                        $error = 'User not found or has no phone number.';
                    }
                }
                break;
        }
    }
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

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
        color: #000;
    }

    .admin-navbar {
        background-color: #fff;
        border-bottom: 1px solid #eeeeee;
        padding: 0.8rem 2rem;
    }

    .navbar-brand {
        font-weight: 800;
        letter-spacing: 1px;
        color: #000 !important;
    }

    .nav-link {
        color: #666 !important;
        font-weight: 500;
        font-size: 0.9rem;
        padding: 0.5rem 1rem !important;
    }

    .nav-link.active {
        color: #000 !important;
        font-weight: 700;
        border-bottom: 2px solid #000;
    }

    .content-card {
        background: #fff;
        border: 1px solid #efefef;
        border-radius: 12px;
        padding: 30px;
    }

    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #999;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: #f0f0f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        color: #333;
        border: 1px solid #eee;
    }

    .role-badge {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 5px 12px;
        border-radius: 50px;
    }

    .badge-admin {
        background: #000;
        color: #fff;
    }

    .badge-customer {
        background: #f8f9fa;
        color: #666;
        border: 1px solid #ddd;
    }

    .order-count {
        background: #f1f1f1;
        padding: 2px 10px;
        border-radius: 5px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .btn-action {
        padding: 5px 10px;
        font-size: 0.75rem;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
    }

    .btn-edit {
        background: #007bff;
        color: white;
        border: 1px solid #007bff;
    }

    .btn-sms {
        background: #28a745;
        color: white;
        border: 1px solid #28a745;
    }

    .btn-delete {
        background: #dc3545;
        color: white;
        border: 1px solid #dc3545;
    }

    .btn-add {
        background: #28a745;
        color: white;
        border: 1px solid #28a745;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .btn-add:hover,
    .btn-edit:hover,
    .btn-delete:hover {
        opacity: 0.8;
        color: white;
        text-decoration: none;
    }

    .alert-custom {
        border: none;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<?php include 'includes/admin_navbar.php'; ?>

<div class="container py-4">
    <header class="mb-5">
        <h2 class="fw-bold">User Management</h2>
        <p class="text-muted">Overview of all registered customers and administrators.</p>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert-custom alert-error">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-custom alert-success">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="content-card shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">All Users</h5>
            <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="fas fa-plus me-2"></i>Add Administrator
            </button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>User Info</th>
                        <th>Contact Details</th>
                        <th>Role</th>
                        <th class="text-center">Total Orders</th>
                        <th>Joined Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $orderCount = getUserOrdersCount($pdo, $user['id']);
                        $initials = strtoupper(substr($user['name'], 0, 1));
                    ?>
                        <tr>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar"><?php echo $initials; ?></div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="text-muted small">ID: #<?php echo $user['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-medium text-dark"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($user['phone'] ?? 'No phone provided'); ?></div>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="role-badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span class="role-badge badge-customer">Customer</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="order-count"><?php echo $orderCount; ?></span>
                            </td>
                            <td class="text-muted small">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="text-center">
                                <div class="action-buttons justify-content-center">
                                    <?php if ($user['role'] === 'customer' && !empty($user['phone'])): ?>
                                        <button type="button" class="btn-action btn-sms"
                                            data-bs-toggle="modal"
                                            data-bs-target="#smsModal"
                                            data-user-id="<?php echo $user['id']; ?>"
                                            data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                            data-user-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                                            title="Send SMS">
                                            <i class="fas fa-sms me-1"></i>SMS
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn-action btn-edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-user-id="<?php echo $user['id']; ?>"
                                        data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                        data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-user-role="<?php echo $user['role']; ?>">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                        <button type="button" class="btn-action btn-delete"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteUserModal"
                                            data-user-id="<?php echo $user['id']; ?>"
                                            data-user-name="<?php echo htmlspecialchars($user['name']); ?>">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-users-slash d-block mb-2 fs-2"></i>
                                No users found in the database.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="smsModal" tabindex="-1" aria-labelledby="smsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="smsModalLabel">
                    <i class="fas fa-sms me-2"></i>Send SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_user_sms">
                <input type="hidden" name="user_id" id="sms_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sms_recipient" class="form-label">Recipient</label>
                        <input type="text" class="form-control" id="sms_recipient" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="sms_phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="sms_phone" readonly>
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
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send SMS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAdminModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Add New Administrator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_admin">

                    <div class="mb-3">
                        <label for="add_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="add_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="add_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="add_email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="add_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="add_password" name="password" required minlength="6">
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Administrator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">
                    <i class="fas fa-user-edit me-2"></i>Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="customer">Customer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_password" class="form-label">New Password (Optional)</label>
                        <input type="password" class="form-control" id="edit_password" name="password" minlength="6">
                        <div class="form-text">Leave blank to keep current password.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">

                    <p>Are you sure you want to delete the user <strong id="delete_user_name"></strong>?</p>
                    <p class="text-danger small">
                        <i class="fas fa-warning me-1"></i>
                        This action cannot be undone. All user data will be permanently removed.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('editUserModal').addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        const userEmail = button.getAttribute('data-user-email');
        const userRole = button.getAttribute('data-user-role');

        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_name').value = userName;
        document.getElementById('edit_email').value = userEmail;
        document.getElementById('edit_role').value = userRole;
        document.getElementById('edit_password').value = '';
    });

    document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');

        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_user_name').textContent = userName;
    });

    document.getElementById('smsModal').addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        const userPhone = button.getAttribute('data-user-phone');

        document.getElementById('sms_user_id').value = userId;
        document.getElementById('sms_recipient').value = userName;
        document.getElementById('sms_phone').value = userPhone;
        document.getElementById('sms_message').value = '';

        document.getElementById('sms-char-count').textContent = '0';
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

    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-custom');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);
</script>