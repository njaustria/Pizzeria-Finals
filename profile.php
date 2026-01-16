<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = 'My Profile';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'profile';
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM contacts WHERE email = ? ORDER BY created_at DESC");
$stmt->execute([$user['email']]);
$contactMessages = $stmt->fetchAll();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $name = trim($firstName . ' ' . $lastName);
    $email = sanitizeInput($_POST['email']);
    $phoneDigits = sanitizeInput($_POST['phone']);
    $phone = !empty($phoneDigits) ? '+63' . $phoneDigits : '';
    $street = sanitizeInput($_POST['street']);
    $city = sanitizeInput($_POST['city']);
    $province = 'Batangas';
    $postalCode = sanitizeInput($_POST['postal_code']);
    $address = '';
    if (!empty($street) || !empty($city)) {
        $address = trim($street . ', ' . $city . ', ' . $province . ' ' . $postalCode);
    }
    $useAsDefault = isset($_POST['use_as_default']) ? 1 : 0;

    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'First name, last name and email are required';
    } elseif (!empty($phoneDigits) && !preg_match('/^[0-9]{10}$/', $phoneDigits)) {
        $error = 'Phone number must be exactly 10 digits (e.g., 9123456789)';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);

        if ($stmt->fetch()) {
            $error = 'Email already in use';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, is_default_address = ? WHERE id = ?");

            if ($stmt->execute([$name, $email, $phone, $address, $useAsDefault, $_SESSION['user_id']])) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                setFlashMessage('Profile updated successfully!', 'success');
                header('Location: profile.php');
                exit();
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All password fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        if (password_verify($currentPassword, $user['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");

            if ($stmt->execute([$newHash, $_SESSION['user_id']])) {
                setFlashMessage('Password changed successfully!', 'success');
                header('Location: profile.php');
                exit();
            } else {
                $error = 'Failed to change password';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .profile-section {
        min-height: 100vh;
        padding: calc(80px + var(--spacing-xl)) 0 var(--spacing-xl);
    }

    .profile-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .profile-header {
        text-align: center;
        margin-bottom: var(--spacing-xl);
    }

    .profile-tabs {
        display: flex;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-lg);
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .profile-tabs {
            flex-direction: column;
        }

        .tab-link {
            text-align: center;
            font-size: 0.9rem;
        }
    }

    .tab-link {
        padding: var(--spacing-sm) var(--spacing-md);
        background: var(--card-glass-bg);
        border: 1px solid var(--card-glass-border);
        border-radius: var(--radius-md);
        color: var(--white);
        text-decoration: none;
        transition: all var(--transition-fast);
        font-weight: 600;
    }

    .tab-link:hover,
    .tab-link.active {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .profile-card {
        padding: var(--spacing-xl);
    }

    .info-item {
        margin-bottom: var(--spacing-sm);
        padding: var(--spacing-xs) 0;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .info-item strong {
        display: inline-block;
        min-width: 80px;
        color: var(--gray-lighter);
        font-size: 0.9rem;
        font-weight: 600;
    }

    .profile-display {
        overflow-x: auto;
    }

    @media (max-width: 768px) {
        .profile-card {
            padding: var(--spacing-lg);
        }

        .info-item {
            margin-bottom: var(--spacing-xs);
            font-size: 0.9rem;
        }

        .info-item strong {
            font-size: 0.85rem;
        }
    }

    @media (max-width: 480px) {
        .profile-card {
            padding: var(--spacing-md);
        }

        .info-item {
            font-size: 0.85rem;
        }
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-md);
        margin-bottom: var(--spacing-xl);
    }

    .stat-box {
        padding: var(--spacing-lg);
        text-align: center;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: var(--spacing-xs);
    }

    .stat-label {
        color: var(--gray-lighter);
        font-size: 0.9rem;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-sm);
        }

        .stat-box {
            padding: var(--spacing-md);
        }

        .stat-number {
            font-size: 1.8rem;
        }

        .stat-label {
            font-size: 0.8rem;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-number {
            font-size: 1.5rem;
        }
    }

    .order-card {
        padding: var(--spacing-md);
        margin-bottom: var(--spacing-md);
    }

    .order-info-grid {
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    @media (max-width: 768px) {
        .order-card {
            padding: var(--spacing-sm);
        }

        .order-info-grid {
            grid-template-columns: 1fr !important;
            gap: var(--spacing-xs) !important;
        }

        .order-header {
            flex-direction: column;
            gap: var(--spacing-xs);
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .order-card {
            padding: var(--spacing-xs);
            font-size: 0.9rem;
        }
    }

    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-sm);
        padding-bottom: var(--spacing-sm);
        border-bottom: 1px solid var(--glass-border);
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        font-weight: 600;
    }

    .badge-pending {
        background: rgba(255, 193, 7, 0.2);
        border: 1px solid rgba(255, 193, 7, 0.5);
    }

    .badge-preparing {
        background: rgba(13, 202, 240, 0.2);
        border: 1px solid rgba(13, 202, 240, 0.5);
    }

    .badge-out_for_delivery {
        background: rgba(13, 110, 253, 0.2);
        border: 1px solid rgba(13, 110, 253, 0.5);
    }

    .badge-completed {
        background: rgba(25, 135, 84, 0.2);
        border: 1px solid rgba(25, 135, 84, 0.5);
    }

    .badge-cancelled {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid rgba(220, 53, 69, 0.5);
    }

    .order-items {
        margin-top: var(--spacing-sm);
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-xs) 0;
        color: var(--gray-lighter);
    }

    @media (max-width: 768px) {
        .profile-tabs {
            flex-direction: column;
        }

        .order-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--spacing-xs);
        }
    }

    .form-control {
        background-color: #ffffff;
        color: #333333;
        border: 1px solid #ced4da;
    }

    .form-control:focus {
        background-color: #ffffff;
        color: #333333;
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    select.form-control {
        background-color: #ffffff;
        color: #333333;
    }

    select.form-control option {
        background-color: #ffffff;
        color: #333333;
    }

    .profile-display .info-item {
        margin-bottom: var(--spacing-sm);
        padding: var(--spacing-xs) 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .profile-display .info-item:last-child {
        border-bottom: none;
    }

    .profile-display h4 {
        color: var(--primary-color) !important;
        margin-bottom: var(--spacing-md) !important;
    }

    @media (max-width: 768px) {

        .profile-info-grid,
        .form-row {
            grid-template-columns: 1fr !important;
            gap: var(--spacing-md) !important;
        }

        .profile-header h1 {
            font-size: 1.8rem;
        }

        .profile-tabs {
            flex-direction: column;
        }

        .tab-link {
            text-align: center;
            font-size: 0.9rem;
        }

        .profile-card {
            padding: var(--spacing-lg);
        }

        .info-item {
            font-size: 0.9rem;
        }

        .info-item strong {
            font-size: 0.85rem;
            display: block;
            margin-bottom: 0.25rem;
        }

        h4 {
            font-size: 1.1rem !important;
        }
    }

    @media (max-width: 480px) {
        .profile-header h1 {
            font-size: 1.5rem;
        }

        .profile-header p {
            font-size: 0.9rem;
        }

        .profile-card {
            padding: var(--spacing-md);
        }

        .info-item {
            font-size: 0.85rem;
        }

        h4 {
            font-size: 1rem !important;
        }

        .btn {
            font-size: 0.85rem;
            padding: var(--spacing-xs) var(--spacing-sm);
        }
    }

    .message-card {
        padding: var(--spacing-md);
        margin-bottom: var(--spacing-md);
        border-left: 4px solid var(--primary-color);
    }

    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: var(--spacing-sm);
        padding-bottom: var(--spacing-sm);
        border-bottom: 1px solid var(--glass-border);
    }

    .message-subject {
        font-weight: 600;
        color: var(--white);
        margin-bottom: var(--spacing-xs);
    }

    .message-date {
        font-size: 0.85rem;
        color: var(--gray-lighter);
    }

    .message-status {
        padding: 4px 8px;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-unread {
        background: rgba(255, 193, 7, 0.2);
        border: 1px solid rgba(255, 193, 7, 0.5);
        color: #ffc107;
    }

    .status-read {
        background: rgba(108, 117, 125, 0.2);
        border: 1px solid rgba(108, 117, 125, 0.5);
        color: #6c757d;
    }

    .status-replied {
        background: rgba(25, 135, 84, 0.2);
        border: 1px solid rgba(25, 135, 84, 0.5);
        color: #28a745;
    }

    .message-content {
        background: rgba(255, 255, 255, 0.05);
        padding: var(--spacing-sm);
        border-radius: var(--radius-sm);
        margin-bottom: var(--spacing-sm);
        line-height: 1.5;
    }

    .admin-reply {
        background: rgba(25, 135, 84, 0.1);
        border-left: 3px solid #28a745;
        padding: var(--spacing-sm);
        border-radius: var(--radius-sm);
        margin-top: var(--spacing-sm);
    }

    .reply-header {
        font-weight: 600;
        color: #28a745;
        margin-bottom: var(--spacing-xs);
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
    }

    .badge {
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .bg-success {
        background-color: #28a745 !important;
        color: white;
    }

    .ms-1 {
        margin-left: 0.25rem;
    }

    .payment-status-badge {
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-paid {
        background: rgba(25, 135, 84, 0.2);
        border: 1px solid rgba(25, 135, 84, 0.5);
        color: #28a745;
    }

    .status-pending-payment {
        background: rgba(255, 193, 7, 0.2);
        border: 1px solid rgba(255, 193, 7, 0.5);
        color: #ffc107;
    }

    @media (max-width: 768px) {
        .message-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--spacing-xs);
        }

        .message-card {
            padding: var(--spacing-sm);
        }
    }
</style>

<section class="profile-section">
    <div class="container">
        <div class="profile-container">
            <div class="profile-header">
                <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</p>
            </div>

            <div class="stats-grid">
                <div class="stat-box glass-card">
                    <div class="stat-number"><?php echo count($orders); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>

                <div class="stat-box glass-card">
                    <div class="stat-number">
                        <?php
                        $pending = array_filter($orders, fn($o) => $o['status'] === 'pending');
                        echo count($pending);
                        ?>
                    </div>
                    <div class="stat-label">Pending Orders</div>
                </div>

                <div class="stat-box glass-card">
                    <div class="stat-number">
                        <?php
                        $completed = array_filter($orders, fn($o) => $o['status'] === 'completed');
                        echo count($completed);
                        ?>
                    </div>
                    <div class="stat-label">Completed Orders</div>
                </div>
            </div>

            <div class="profile-tabs">
                <a href="?tab=profile" class="tab-link <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="?tab=orders" class="tab-link <?php echo $activeTab === 'orders' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag"></i> My Orders
                </a>
                <a href="?tab=messages" class="tab-link <?php echo $activeTab === 'messages' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Messages
                    <?php if (count(array_filter($contactMessages, fn($m) => $m['status'] === 'replied'))): ?>
                        <span class="badge bg-success ms-1"><?php echo count(array_filter($contactMessages, fn($m) => $m['status'] === 'replied')); ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <?php if ($error): ?>
                <div style="background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5); padding: var(--spacing-sm); border-radius: var(--radius-sm); margin-bottom: var(--spacing-md);">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'profile'): ?>
                <div class="profile-card glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                        <h2>Profile Information</h2>
                        <?php if (!$editMode): ?>
                            <a href="?tab=profile&edit=1" class="btn btn-secondary">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$editMode): ?>
                        <div class="profile-display">
                            <div class="profile-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg); margin-bottom: var(--spacing-lg);">
                                <div>
                                    <h4 style="margin-bottom: var(--spacing-sm); color: var(--primary-color);">Personal Information</h4>
                                    <div class="info-item">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($user['name'] ?? 'Not set'); ?>
                                    </div>
                                    <div class="info-item">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?>
                                    </div>
                                    <div class="info-item">
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?>
                                    </div>
                                </div>

                                <div>
                                    <h4 style="margin-bottom: var(--spacing-sm); color: var(--primary-color);">Address Information</h4>
                                    <?php if (!empty($user['address'])): ?>
                                        <div class="info-item">
                                            <strong>Address:</strong><br>
                                            <span style="margin-left: var(--spacing-md);"><?php echo htmlspecialchars($user['address']); ?></span>
                                        </div>
                                        <?php if ($user['is_default_address'] ?? 0): ?>
                                            <div class="info-item">
                                                <span style="color: var(--success-color); font-size: 0.9rem;">
                                                    <i class="fas fa-check-circle"></i> Set as default for orders
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="info-item">
                                            <em style="color: var(--gray-lighter);">No address saved</em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars(explode(' ', $user['name'], 2)[0] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars(explode(' ', $user['name'], 2)[1] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <div style="display: flex; align-items: center;">
                                    <span style="background-color: #f8f9fa; border: 1px solid #ced4da; border-right: none; padding: 8px 12px; border-radius: 4px 0 0 4px; color: #333;">+63</span>
                                    <input type="tel" id="phone" name="phone" class="form-control" style="border-radius: 0 4px 4px 0; flex: 1;" pattern="[0-9]{10}" maxlength="10" placeholder="9123456789" value="<?php echo htmlspecialchars(preg_replace('/^\+63/', '', $user['phone'] ?? '')); ?>">
                                </div>
                                <small class="text-muted">Enter 10 digits (e.g., 9123456789)</small>
                            </div>

                            <?php
                            $existingStreet = '';
                            $existingCity = '';
                            $existingPostal = '';
                            if (!empty($user['address'])) {
                                $addressParts = explode(', ', $user['address']);
                                if (count($addressParts) >= 3) {
                                    $existingStreet = $addressParts[0];
                                    $existingCity = $addressParts[1];
                                    $provincePostal = explode(' ', $addressParts[2]);
                                    $existingPostal = end($provincePostal);
                                }
                            }
                            ?>

                            <div class="form-group">
                                <label for="street">Street Address</label>
                                <input type="text" id="street" name="street" class="form-control" value="<?php echo htmlspecialchars($existingStreet); ?>">
                            </div>

                            <div class="form-group">
                                <label for="city">City/Municipality</label>
                                <select id="city" name="city" class="form-control">
                                    <option value="">Select City/Municipality</option>
                                    <option value="Agoncillo" data-postal="4211" <?php echo $existingCity === 'Agoncillo' ? 'selected' : ''; ?>>Agoncillo</option>
                                    <option value="Alitagtag" data-postal="4205" <?php echo $existingCity === 'Alitagtag' ? 'selected' : ''; ?>>Alitagtag</option>
                                    <option value="Balayan" data-postal="4213" <?php echo $existingCity === 'Balayan' ? 'selected' : ''; ?>>Balayan</option>
                                    <option value="Balete" data-postal="4219" <?php echo $existingCity === 'Balete' ? 'selected' : ''; ?>>Balete</option>
                                    <option value="Batangas City" data-postal="4200" <?php echo $existingCity === 'Batangas City' ? 'selected' : ''; ?>>Batangas City</option>
                                    <option value="Bauan" data-postal="4201" <?php echo $existingCity === 'Bauan' ? 'selected' : ''; ?>>Bauan</option>
                                    <option value="Calaca" data-postal="4212" <?php echo $existingCity === 'Calaca' ? 'selected' : ''; ?>>Calaca</option>
                                    <option value="Calatagan" data-postal="4215" <?php echo $existingCity === 'Calatagan' ? 'selected' : ''; ?>>Calatagan</option>
                                    <option value="Cuenca" data-postal="4222" <?php echo $existingCity === 'Cuenca' ? 'selected' : ''; ?>>Cuenca</option>
                                    <option value="Ibaan" data-postal="4230" <?php echo $existingCity === 'Ibaan' ? 'selected' : ''; ?>>Ibaan</option>
                                    <option value="Laurel" data-postal="4221" <?php echo $existingCity === 'Laurel' ? 'selected' : ''; ?>>Laurel</option>
                                    <option value="Lemery" data-postal="4209" <?php echo $existingCity === 'Lemery' ? 'selected' : ''; ?>>Lemery</option>
                                    <option value="Lian" data-postal="4214" <?php echo $existingCity === 'Lian' ? 'selected' : ''; ?>>Lian</option>
                                    <option value="Lipa City" data-postal="4217" <?php echo $existingCity === 'Lipa City' ? 'selected' : ''; ?>>Lipa City</option>
                                    <option value="Lobo" data-postal="4207" <?php echo $existingCity === 'Lobo' ? 'selected' : ''; ?>>Lobo</option>
                                    <option value="Mabini" data-postal="4202" <?php echo $existingCity === 'Mabini' ? 'selected' : ''; ?>>Mabini</option>
                                    <option value="Malvar" data-postal="4233" <?php echo $existingCity === 'Malvar' ? 'selected' : ''; ?>>Malvar</option>
                                    <option value="Mataasnakahoy" data-postal="4223" <?php echo $existingCity === 'Mataasnakahoy' ? 'selected' : ''; ?>>Mataasnakahoy</option>
                                    <option value="Nasugbu" data-postal="4231" <?php echo $existingCity === 'Nasugbu' ? 'selected' : ''; ?>>Nasugbu</option>
                                    <option value="Padre Garcia" data-postal="4224" <?php echo $existingCity === 'Padre Garcia' ? 'selected' : ''; ?>>Padre Garcia</option>
                                    <option value="Rosario" data-postal="4225" <?php echo $existingCity === 'Rosario' ? 'selected' : ''; ?>>Rosario</option>
                                    <option value="San Jose" data-postal="4227" <?php echo $existingCity === 'San Jose' ? 'selected' : ''; ?>>San Jose</option>
                                    <option value="San Juan" data-postal="4226" <?php echo $existingCity === 'San Juan' ? 'selected' : ''; ?>>San Juan</option>
                                    <option value="San Luis" data-postal="4234" <?php echo $existingCity === 'San Luis' ? 'selected' : ''; ?>>San Luis</option>
                                    <option value="San Nicolas" data-postal="4210" <?php echo $existingCity === 'San Nicolas' ? 'selected' : ''; ?>>San Nicolas</option>
                                    <option value="San Pascual" data-postal="4218" <?php echo $existingCity === 'San Pascual' ? 'selected' : ''; ?>>San Pascual</option>
                                    <option value="Santa Teresita" data-postal="4235" <?php echo $existingCity === 'Santa Teresita' ? 'selected' : ''; ?>>Santa Teresita</option>
                                    <option value="Santo Tomas" data-postal="4234" <?php echo $existingCity === 'Santo Tomas' ? 'selected' : ''; ?>>Santo Tomas</option>
                                    <option value="Taal" data-postal="4208" <?php echo $existingCity === 'Taal' ? 'selected' : ''; ?>>Taal</option>
                                    <option value="Talisay" data-postal="4220" <?php echo $existingCity === 'Talisay' ? 'selected' : ''; ?>>Talisay</option>
                                    <option value="Tanauan City" data-postal="4232" <?php echo $existingCity === 'Tanauan City' ? 'selected' : ''; ?>>Tanauan City</option>
                                    <option value="Taysan" data-postal="4228" <?php echo $existingCity === 'Taysan' ? 'selected' : ''; ?>>Taysan</option>
                                    <option value="Tingloy" data-postal="4203" <?php echo $existingCity === 'Tingloy' ? 'selected' : ''; ?>>Tingloy</option>
                                    <option value="Tuy" data-postal="4216" <?php echo $existingCity === 'Tuy' ? 'selected' : ''; ?>>Tuy</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="province">Province</label>
                                <input type="text" id="province" name="province" class="form-control" value="Batangas" readonly style="background-color: #f8f9fa;">
                            </div>

                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-control" readonly style="background-color: #f8f9fa;" value="<?php echo htmlspecialchars($existingPostal); ?>" placeholder="Select city first">
                            </div>

                            <div class="form-group">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" id="use_as_default" name="use_as_default" style="margin-right: var(--spacing-sm);" <?php echo ($user['is_default_address'] ?? 0) ? 'checked' : ''; ?>>
                                    <span>Use this address and phone as default for future orders</span>
                                </label>
                            </div>

                            <div style="display: flex; gap: var(--spacing-md);">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                                <a href="?tab=profile" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>

                        <div style="margin-top: var(--spacing-xl); padding-top: var(--spacing-xl); border-top: 2px solid var(--glass-border);">
                            <h3 style="margin-bottom: var(--spacing-lg); color: var(--primary-color);">Change Password</h3>

                            <form method="POST">
                                <div class="form-group">
                                    <label for="current_password">Current Password *</label>
                                    <div style="position: relative;">
                                        <input type="password" id="current_password" name="current_password" class="form-control password-input" required>
                                        <button type="button" class="btn btn-sm show-password-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #000; color: #fff; border: none;" onclick="togglePasswordVisibility('current_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="new_password">New Password *</label>
                                    <div style="position: relative;">
                                        <input type="password" id="new_password" name="new_password" class="form-control password-input" required minlength="6">
                                        <button type="button" class="btn btn-sm show-password-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #000; color: #fff; border: none;" onclick="togglePasswordVisibility('new_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password *</label>
                                    <div style="position: relative;">
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control password-input" required>
                                        <button type="button" class="btn btn-sm show-password-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #000; color: #fff; border: none;" onclick="togglePasswordVisibility('confirm_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'orders'): ?>
                <div class="profile-card glass-card">
                    <h2 style="margin-bottom: var(--spacing-lg);">Order History</h2>

                    <?php if (empty($orders)): ?>
                        <div style="text-align: center; padding: var(--spacing-xl);">
                            <i class="fas fa-shopping-bag" style="font-size: 4rem; opacity: 0.3;"></i>
                            <p class="text-muted" style="margin-top: var(--spacing-md);">No orders yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order):
                            $stmt = $pdo->prepare("
                                SELECT oi.*, p.name as pizza_name 
                                FROM order_items oi 
                                JOIN pizzas p ON oi.pizza_id = p.id 
                                WHERE oi.order_id = ?
                            ");
                            $stmt->execute([$order['id']]);
                            $items = $stmt->fetchAll();
                        ?>
                            <div class="order-card glass-card">
                                <div class="order-header">
                                    <div>
                                        <strong>Order #<?php echo $order['id']; ?></strong>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-secondary btn-sm toggle-order-details" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye" id="icon-<?php echo $order['id']; ?>"></i>
                                            <span id="text-<?php echo $order['id']; ?>">Show Order</span>
                                        </button>
                                    </div>
                                </div>

                                <div id="order-details-<?php echo $order['id']; ?>" class="order-details" style="display: none;">
                                    <div style="margin-top: var(--spacing-md); margin-bottom: var(--spacing-xs); padding-bottom: var(--spacing-xs); border-bottom: 1px solid var(--glass-border);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <small class="text-muted"><?php echo formatDate($order['created_at']); ?></small>
                                            <span class="status-badge <?php echo getStatusBadgeClass($order['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="margin-bottom: var(--spacing-md); padding: var(--spacing-sm); background: rgba(255, 255, 255, 0.05); border-radius: var(--radius-sm);">
                                        <h5 style="margin-bottom: var(--spacing-xs); color: var(--primary-color);">Customer Information</h5>
                                        <div class="order-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-sm);">
                                            <div>
                                                <small class="text-muted">Name:</small><br>
                                                <span><?php echo htmlspecialchars($user['name']); ?></span>
                                            </div>
                                            <div>
                                                <small class="text-muted">Email:</small><br>
                                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                                            </div>
                                            <div>
                                                <small class="text-muted">Phone:</small><br>
                                                <span><?php echo htmlspecialchars($order['phone'] ?? $user['phone'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div>
                                                <small class="text-muted">Payment Method:</small><br>
                                                <span style="display: flex; align-items: center; gap: var(--spacing-xs);">
                                                    <?php if (strtolower($order['payment_method']) === 'cod'): ?>
                                                        <i class="fas fa-money-bill-wave" style="color: #28a745;"></i>
                                                        Cash on Delivery
                                                    <?php elseif (strtolower($order['payment_method']) === 'cash_on_delivery'): ?>
                                                        <i class="fas fa-money-bill-wave" style="color: #28a745;"></i>
                                                        Cash On Delivery
                                                    <?php elseif (strtolower($order['payment_method']) === 'paypal'): ?>
                                                        <i class="fab fa-paypal" style="color: #0070ba;"></i>
                                                        PayPal
                                                    <?php else: ?>
                                                        <i class="fas fa-credit-card" style="color: #6c757d;"></i>
                                                        <?php echo htmlspecialchars(ucfirst($order['payment_method'] ?? 'N/A')); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <small class="text-muted">Payment Status:</small><br>
                                                <?php if (strtolower($order['payment_method']) === 'paypal'): ?>
                                                    <span class="payment-status-badge status-paid">
                                                        <i class="fas fa-check-circle"></i>
                                                        Paid
                                                    </span>
                                                <?php elseif (in_array(strtolower($order['payment_method']), ['cod', 'cash_on_delivery'])): ?>
                                                    <?php if ($order['status'] === 'completed'): ?>
                                                        <span class="payment-status-badge status-paid">
                                                            <i class="fas fa-check-circle"></i>
                                                            Paid
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="payment-status-badge status-pending-payment">
                                                            <i class="fas fa-clock"></i>
                                                            Pending Payment
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="payment-status-badge status-pending-payment">
                                                        <i class="fas fa-clock"></i>
                                                        Pending Payment
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="order-items">
                                        <?php
                                        $subtotal = 0;
                                        foreach ($items as $item):
                                            $subtotal += $item['price'] * $item['quantity'];
                                        ?>
                                            <div class="order-item">
                                                <span><?php echo htmlspecialchars($item['pizza_name']); ?> × <?php echo $item['quantity']; ?></span>
                                                <span><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php
                                        $deliveryFee = ($subtotal > 1500) ? 0 : 200;
                                        ?>

                                        <div style="margin-top: var(--spacing-sm); padding-top: var(--spacing-sm); border-top: 1px solid var(--glass-border);">
                                            <div class="order-item">
                                                <span>Subtotal</span>
                                                <span><?php echo formatPrice($subtotal); ?></span>
                                            </div>
                                            <div class="order-item">
                                                <span>Delivery Fee</span>
                                                <span><?php echo formatPrice($deliveryFee); ?></span>
                                            </div>
                                        </div>

                                        <div class="order-item" style="margin-top: var(--spacing-sm); padding-top: var(--spacing-sm); border-top: 1px solid var(--glass-border); font-weight: bold; color: var(--white);">
                                            <span>Total</span>
                                            <span><?php echo formatPrice($order['total_price']); ?></span>
                                        </div>
                                    </div>

                                    <div style="margin-top: var(--spacing-sm);">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['delivery_address']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'messages'): ?>
                <div class="profile-card glass-card">
                    <h2 style="margin-bottom: var(--spacing-lg);">My Messages</h2>
                    <p class="text-muted" style="margin-bottom: var(--spacing-lg);">View your messages to admin and their replies</p>

                    <?php if (empty($contactMessages)): ?>
                        <div style="text-align: center; padding: var(--spacing-xl);">
                            <i class="fas fa-envelope-open" style="font-size: 4rem; opacity: 0.3;"></i>
                            <p class="text-muted" style="margin-top: var(--spacing-md);">No messages found</p>
                            <p class="text-muted" style="margin-top: var(--spacing-sm);">Send a message through our <a href="contact.php" style="color: var(--primary-color);">Contact Page</a></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contactMessages as $msg): ?>
                            <div class="message-card glass-card">
                                <div class="order-header">
                                    <div>
                                        <strong><?php echo !empty($msg['subject']) ? htmlspecialchars($msg['subject']) : htmlspecialchars($msg['subject_type'] ?? 'General Inquiry'); ?></strong>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-secondary btn-sm toggle-message-details" onclick="toggleMessageDetails(<?php echo $msg['id']; ?>)">
                                            <i class="fas fa-eye" id="msg-icon-<?php echo $msg['id']; ?>"></i>
                                            <span id="msg-text-<?php echo $msg['id']; ?>">Show Message</span>
                                        </button>
                                    </div>
                                </div>

                                <div id="message-details-<?php echo $msg['id']; ?>" class="message-details" style="display: none;">
                                    <div style="margin-top: var(--spacing-md); margin-bottom: var(--spacing-xs); padding-bottom: var(--spacing-xs); border-bottom: 1px solid var(--glass-border);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <small class="text-muted"><i class="fas fa-clock"></i> <?php echo formatDate($msg['created_at']); ?></small>
                                            <div class="message-status status-<?php echo $msg['status']; ?>">
                                                <?php echo ucfirst($msg['status']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="message-content">
                                        <strong style="color: var(--gray-lighter); font-size: 0.9rem;">Your Message:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>

                                    <?php if ($msg['status'] === 'replied' && !empty($msg['reply_message'])): ?>
                                        <div class="admin-reply">
                                            <div class="reply-header">
                                                <i class="fas fa-reply"></i> Admin Reply:
                                            </div>
                                            <div>
                                                <?php echo nl2br(htmlspecialchars($msg['reply_message'])); ?>
                                            </div>
                                        </div>
                                    <?php elseif ($msg['status'] === 'read'): ?>
                                        <div style="margin-top: var(--spacing-sm); padding: var(--spacing-sm); background: rgba(108, 117, 125, 0.1); border-radius: var(--radius-sm); font-size: 0.9rem; color: var(--gray-lighter);">
                                            <i class="fas fa-eye"></i> Your message has been read by our admin team. You'll receive a reply soon.
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top: var(--spacing-sm); padding: var(--spacing-sm); background: rgba(255, 193, 7, 0.1); border-radius: var(--radius-sm); font-size: 0.9rem; color: var(--gray-lighter);">
                                            <i class="fas fa-hourglass-half"></i> Your message is pending review.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>


        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone');
        const citySelect = document.getElementById('city');
        const postalCodeInput = document.getElementById('postal_code');

        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');

                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });

            phoneInput.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes(e.key)) {
                    e.preventDefault();
                }
            });
        }

        if (citySelect && postalCodeInput) {
            citySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const postalCode = selectedOption.getAttribute('data-postal');

                if (postalCode) {
                    postalCodeInput.value = postalCode;
                } else {
                    postalCodeInput.value = '';
                    postalCodeInput.placeholder = 'Select city first';
                }
            });
        }
    });

    function toggleOrderDetails(orderId) {
        const detailsDiv = document.getElementById('order-details-' + orderId);
        const iconElement = document.getElementById('icon-' + orderId);
        const textElement = document.getElementById('text-' + orderId);

        if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
            detailsDiv.style.display = 'block';
            iconElement.className = 'fas fa-eye-slash';
            textElement.textContent = 'Hide Order';
        } else {
            detailsDiv.style.display = 'none';
            iconElement.className = 'fas fa-eye';
            textElement.textContent = 'Show Order';
        }
    }

    function toggleMessageDetails(messageId) {
        const detailsDiv = document.getElementById('message-details-' + messageId);
        const iconElement = document.getElementById('msg-icon-' + messageId);
        const textElement = document.getElementById('msg-text-' + messageId);

        if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
            detailsDiv.style.display = 'block';
            iconElement.className = 'fas fa-eye-slash';
            textElement.textContent = 'Hide Message';
        } else {
            detailsDiv.style.display = 'none';
            iconElement.className = 'fas fa-eye';
            textElement.textContent = 'Show Message';
        }
    }

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>