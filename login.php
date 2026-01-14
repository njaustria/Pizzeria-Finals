<?php
require_once 'config/config.php';
require_once 'config/sms_config.php';
require_once 'includes/functions.php';

$pageTitle = 'Login';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields';
        } else {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                setFlashMessage('Welcome back, ' . $user['name'] . '!', 'success');

                if ($user['role'] === 'admin') {
                    $_SESSION['admin_login_time'] = time();
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        }
    } elseif (isset($_POST['register'])) {
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $name = trim($firstName . ' ' . $lastName);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $phone = sanitizeInput($_POST['phone']);
        $streetAddress = sanitizeInput($_POST['street_address']);
        $city = sanitizeInput($_POST['city']);
        $province = 'Batangas';
        $postalCode = sanitizeInput($_POST['postal_code']);

        $address = trim($streetAddress . ', ' . $city . ', ' . $province . ' ' . $postalCode);

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword) || empty($phone) || empty($city)) {
            $error = 'Please fill in all required fields';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email format';
        } elseif (!preg_match('/^\+639[0-9]{9}$/', $phone)) {
            $error = 'Phone number must be in format +639XXXXXXXXX (10 digits after +63)';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, phone, address) VALUES (?, ?, ?, ?, ?)");

                if ($stmt->execute([$name, $email, $passwordHash, $phone, $address])) {
                    $newUserId = $pdo->lastInsertId();

                    if (!empty($phone)) {
                        $templates = getSMSTemplates();
                        $welcomeMessage = str_replace('USER_NAME', $name, $templates['welcome']);
                        $smsResult = sendSMS($phone, $welcomeMessage, $newUserId, null, 'welcome');
                    }

                    setFlashMessage('Registration successful! Please login.', 'success');
                    header('Location: login.php');
                    exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .auth-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-xl) var(--spacing-md);
    }

    .auth-box {
        width: 100%;
        max-width: 500px;
        padding: var(--spacing-xl);
        margin-top: 80px;
    }

    .auth-tabs {
        display: flex;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-lg);
    }

    .tab-btn {
        flex: 1;
        padding: var(--spacing-sm);
        background: var(--card-glass-bg);
        border: 1px solid var(--card-glass-border);
        border-radius: var(--radius-md);
        color: var(--white);
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition-fast);
    }

    .tab-btn.active {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .auth-header {
        text-align: center;
        margin-bottom: var(--spacing-lg);
    }

    .auth-header h2 {
        font-size: 2rem;
        margin-bottom: var(--spacing-xs);
    }

    .error-message {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid rgba(220, 53, 69, 0.5);
        padding: var(--spacing-sm);
        border-radius: var(--radius-sm);
        margin-bottom: var(--spacing-md);
        color: var(--white);
    }

    .form-footer {
        text-align: center;
        margin-top: var(--spacing-md);
        color: var(--gray-lighter);
    }

    .form-footer a {
        color: var(--white);
        text-decoration: none;
    }

    .form-footer a:hover {
        text-decoration: underline;
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
    }

    .form-check-input {
        margin: 0;
        cursor: pointer;
    }

    .form-check-label {
        font-size: 0.9rem;
        color: var(--gray-lighter);
        cursor: pointer;
        margin: 0;
    }

    .form-text {
        font-size: 0.8rem;
        color: var(--gray-lighter);
        margin-top: 0.25rem;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -0.75rem;
        margin-left: -0.75rem;
    }

    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding-right: 0.75rem;
        padding-left: 0.75rem;
    }

    @media (max-width: 768px) {
        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    select.form-control {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--white);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    select.form-control option {
        background-color: #2c2c2c;
        color: var(--white);
        padding: 8px;
    }

    select.form-control:focus {
        background-color: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.4);
        color: var(--white);
    }

    .back-to-home {
        margin-bottom: var(--spacing-md);
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
        color: var(--gray-lighter);
        text-decoration: none;
        font-size: 0.9rem;
        transition: all var(--transition-fast);
    }

    .back-link:hover {
        color: var(--white);
        text-decoration: none;
    }

    .back-link i {
        font-size: 0.8rem;
    }
</style>

<div class="auth-container">
    <div class="auth-box glass-card">
        <div class="back-to-home">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>

        <div class="auth-header">
            <img src="assets/images/pizzeria_index.png" alt="Pizzeria Logo" style="width: 120px; height: 120px; margin-bottom: var(--spacing-sm);">
            <p class="text-muted">Delicious pizzas delivered to your door</p>
        </div>

        <div class="auth-tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Login</button>
            <button class="tab-btn" onclick="switchTab('register')">Register</button>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div id="login-tab" class="tab-content active">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="login-email">Email *</label>
                    <input type="email" id="login-email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="login-password">Password *</label>
                    <input type="password" id="login-password" name="password" class="form-control" required>
                    <div class="form-check mt-2">
                        <input type="checkbox" id="show-login-password" class="form-check-input" onchange="togglePassword('login-password', this)">
                        <label for="show-login-password" class="form-check-label">Show password</label>
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

            </form>
        </div>

        <div id="register-tab" class="tab-content">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="register-first-name">First Name *</label>
                            <input type="text" id="register-first-name" name="first_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="register-last-name">Last Name *</label>
                            <input type="text" id="register-last-name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="register-email">Email *</label>
                    <input type="email" id="register-email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="register-phone">Phone Number *</label>
                    <input type="tel" id="register-phone" name="phone" class="form-control"
                        placeholder="+639XXXXXXXXX"
                        pattern="^\+639[0-9]{9}$"
                        title="Phone number must be in format +639XXXXXXXXX"
                        required>
                    <small class="form-text">Format: +639XXXXXXXXX (10 digits after +63)</small>
                </div>

                <div class="form-group">
                    <label for="register-street">Street Address</label>
                    <input type="text" id="register-street" name="street_address" class="form-control"
                        placeholder="House/Building No., Street Name">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="register-city">City/Municipality *</label>
                            <select id="register-city" name="city" class="form-control" required onchange="updatePostalCode()">
                                <option value="">Select City/Municipality</option>
                                <option value="Agoncillo">Agoncillo</option>
                                <option value="Alitagtag">Alitagtag</option>
                                <option value="Balayan">Balayan</option>
                                <option value="Balete">Balete</option>
                                <option value="Batangas City">Batangas City</option>
                                <option value="Bauan">Bauan</option>
                                <option value="Calaca">Calaca</option>
                                <option value="Calatagan">Calatagan</option>
                                <option value="Cuenca">Cuenca</option>
                                <option value="Ibaan">Ibaan</option>
                                <option value="Laurel">Laurel</option>
                                <option value="Lemery">Lemery</option>
                                <option value="Lian">Lian</option>
                                <option value="Lipa City">Lipa City</option>
                                <option value="Lobo">Lobo</option>
                                <option value="Mabini">Mabini</option>
                                <option value="Malvar">Malvar</option>
                                <option value="Mataasnakahoy">Mataasnakahoy</option>
                                <option value="Nasugbu">Nasugbu</option>
                                <option value="Padre Garcia">Padre Garcia</option>
                                <option value="Rosario">Rosario</option>
                                <option value="San Jose">San Jose</option>
                                <option value="San Juan">San Juan</option>
                                <option value="San Luis">San Luis</option>
                                <option value="San Nicolas">San Nicolas</option>
                                <option value="San Pascual">San Pascual</option>
                                <option value="Santa Teresita">Santa Teresita</option>
                                <option value="Santo Tomas">Santo Tomas</option>
                                <option value="Taal">Taal</option>
                                <option value="Talisay">Talisay</option>
                                <option value="Taysan">Taysan</option>
                                <option value="Tingloy">Tingloy</option>
                                <option value="Tuy">Tuy</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="register-province">Province</label>
                            <input type="text" id="register-province" name="province" class="form-control"
                                value="Batangas" readonly style="background-color: rgba(255,255,255,0.1);">
                            <small class="form-text">Delivery available in Batangas only</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="register-postal">Postal Code</label>
                    <input type="text" id="register-postal" name="postal_code" class="form-control"
                        readonly
                        style="background-color: rgba(255,255,255,0.1); color: var(--gray-lighter);"
                        placeholder="Select city first">
                    <small class="form-text">Postal code is automatically set based on your city</small>
                </div>

                <div class="form-group">
                    <label for="register-password">Password *</label>
                    <input type="password" id="register-password" name="password" class="form-control" required minlength="6">
                    <div class="form-check mt-2">
                        <input type="checkbox" id="show-register-password" class="form-check-input" onchange="togglePassword('register-password', this)">
                        <label for="show-register-password" class="form-check-label">Show password</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="register-confirm">Confirm Password *</label>
                    <input type="password" id="register-confirm" name="confirm_password" class="form-control" required>
                    <div class="form-check mt-2">
                        <input type="checkbox" id="show-confirm-password" class="form-check-input" onchange="togglePassword('register-confirm', this)">
                        <label for="show-confirm-password" class="form-check-label">Show password</label>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');

        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(tab + '-tab').classList.add('active');
    }

    function togglePassword(fieldId, checkbox) {
        const passwordField = document.getElementById(fieldId);
        if (checkbox.checked) {
            passwordField.type = 'text';
        } else {
            passwordField.type = 'password';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('register-phone');

        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');

            if (value.length > 0 && !value.startsWith('63')) {
                if (value.startsWith('9') && value.length <= 10) {
                    value = '63' + value;
                }
            }

            if (value.startsWith('63') && value.length <= 12) {
                e.target.value = '+' + value;
            } else if (value.length > 12) {
                e.target.value = '+' + value.substring(0, 12);
            }
        });

        phoneInput.addEventListener('focus', function(e) {
            if (e.target.value === '') {
                e.target.value = '+639';
            }
        });
    });

    const postalCodes = {
        'Agoncillo': '4211',
        'Alitagtag': '4205',
        'Balayan': '4213',
        'Balete': '4219',
        'Batangas City': '4200',
        'Bauan': '4201',
        'Calaca': '4212',
        'Calatagan': '4215',
        'Cuenca': '4222',
        'Ibaan': '4230',
        'Laurel': '4221',
        'Lemery': '4209',
        'Lian': '4216',
        'Lipa City': '4217',
        'Lobo': '4207',
        'Mabini': '4202',
        'Malvar': '4233',
        'Mataasnakahoy': '4223',
        'Nasugbu': '4231',
        'Padre Garcia': '4224',
        'Rosario': '4225',
        'San Jose': '4227',
        'San Juan': '4226',
        'San Luis': '4220',
        'San Nicolas': '4232',
        'San Pascual': '4228',
        'Santa Teresita': '4203',
        'Santo Tomas': '4234',
        'Taal': '4208',
        'Talisay': '4210',
        'Taysan': '4204',
        'Tingloy': '4206',
        'Tuy': '4214'
    };

    function updatePostalCode() {
        const citySelect = document.getElementById('register-city');
        const postalInput = document.getElementById('register-postal');

        if (citySelect.value && postalCodes[citySelect.value]) {
            postalInput.value = postalCodes[citySelect.value];
        } else {
            postalInput.value = '';
        }
    }
</script>