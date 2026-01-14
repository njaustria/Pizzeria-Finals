<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Contact Us';
$pdo = getDBConnection();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $name = trim($first_name . ' ' . $last_name);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        $stmt = $pdo->prepare("INSERT INTO contacts (name, email, subject_type, message) VALUES (?, ?, ?, ?)");

        if ($stmt->execute([$name, $email, $subject, $message])) {
            $success = true;
            setFlashMessage('Thank you for contacting us! We will get back to you soon.', 'success');
        } else {
            $error = 'Failed to send message. Please try again.';
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .contact-section {
        min-height: 100vh;
        padding: calc(80px + var(--spacing-xl)) 0 var(--spacing-xl);
    }

    .contact-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .contact-header {
        text-align: center;
        margin-bottom: var(--spacing-xl);
    }

    .contact-header h1 {
        font-size: 3rem;
        margin-bottom: var(--spacing-md);
    }

    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--spacing-lg);
        margin-top: var(--spacing-xl);
    }

    .contact-info-card {
        padding: var(--spacing-lg);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }

    .info-item {
        display: flex;
        align-items: start;
        gap: var(--spacing-md);
    }

    .info-icon {
        width: 50px;
        height: 50px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .contact-form-card {
        padding: var(--spacing-xl);
    }

    @media (max-width: 768px) {
        .contact-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-control {
        background-color: #fff !important;
        border: 2px solid #ddd;
        color: #333 !important;
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        background-color: #fff !important;
    }

    select.form-control {
        background-color: #fff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 12px center;
        background-repeat: no-repeat;
        background-size: 16px 12px;
        padding-right: 40px;
        cursor: pointer;
    }

    select.form-control option {
        color: #333 !important;
        background-color: #fff !important;
        padding: 8px 12px;
    }

    textarea.form-control {
        background-color: #fff !important;
        resize: vertical;
        min-height: 120px;
    }
</style>

<section class="contact-section">
    <div class="container">
        <div class="contact-container">
            <div class="contact-header">
                <h1>Get in Touch</h1>
                <p class="text-muted">Have questions? We'd love to hear from you!</p>
            </div>

            <div class="contact-grid">
                <div class="contact-info-card glass-card">
                    <h2 style="margin-bottom: var(--spacing-md);">Contact Information</h2>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h4>Address</h4>
                            <p class="text-muted">Sto Tomas, Batangas <br>Philippines 4234</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h4>Phone</h4>
                            <p class="text-muted">+63 920 558 3433 <br>+63 966 553 8406</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h4>Email</h4>
                            <p class="text-muted"><?php echo ADMIN_EMAIL; ?><br>pizzeriagroup5@gmail.com</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h4>Business Hours</h4>
                            <p class="text-muted">Mon - Sun: 10:00 AM - 11:00 PM</p>
                        </div>
                    </div>
                </div>

                <div class="contact-form-card glass-card">
                    <h2 style="margin-bottom: var(--spacing-md);">Send us a Message</h2>

                    <?php if ($success): ?>
                        <div style="background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.5); padding: var(--spacing-sm); border-radius: var(--radius-sm); margin-bottom: var(--spacing-md);">
                            <i class="fas fa-check-circle"></i> Message sent successfully!
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div style="background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5); padding: var(--spacing-sm); border-radius: var(--radius-sm); margin-bottom: var(--spacing-md);">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Your Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" class="form-control" required>
                                <option value="">Select a subject...</option>
                                <option value="Concern and Feedback">Concern and Feedback</option>
                                <option value="Refund Payment">Refund Payment</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                        </div>

                        <button type="submit" name="submit_contact" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>