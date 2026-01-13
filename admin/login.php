<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$pageTitle = 'Admin Login';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_unset();

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_login_time'] = time();

            setFlashMessage('Welcome to Admin Panel, ' . $admin['name'] . '!', 'success');
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid admin credentials';
        }
    }
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
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fcfcfc;
            color: #000000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #efefef;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .navbar-brand {
            font-weight: 800;
            letter-spacing: 1px;
            color: #000;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            color: #999;
        }

        .form-control {
            border: 1px solid #efefef;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-control:focus {
            border-color: #000;
            box-shadow: none;
        }

        .btn-admin {
            background: #000;
            color: #fff;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: opacity 0.2s;
        }

        .btn-admin:hover {
            opacity: 0.8;
            color: #fff;
        }

        .alert {
            font-size: 0.85rem;
            border-radius: 8px;
            border: none;
        }

        .btn-password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: color 0.2s;
        }

        .btn-password-toggle:hover {
            color: #000;
        }

        .btn-password-toggle:focus {
            outline: none;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <a class="navbar-brand" href="#">ADMIN PANEL</a>

        <div class="mb-4">
            <h5 class="fw-bold mb-1">Login</h5>
            <p class="text-muted small">Enter your credentials to manage the pizzeria.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="admin@pizzeria.com" required>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" name="password" id="password" class="form-control pe-5" placeholder="••••••••" required>
                    <button type="button" class="btn-password-toggle" id="togglePassword">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-admin mb-3">Access Dashboard</button>

            <div class="text-center">
                <a href="../index.php" class="text-dark small text-decoration-none fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Back to Main Site
                </a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    </script>

</body>

</html>