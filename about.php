<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'About Us';

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .about-section {
        min-height: 100vh;
        padding: calc(80px + var(--spacing-xl)) 0 var(--spacing-md);
    }

    .about-content {
        max-width: 900px;
        margin: 0 auto;
    }

    .about-header {
        text-align: center;
        margin-bottom: var(--spacing-xl);
    }

    .about-header h1 {
        font-size: 3rem;
        margin-bottom: var(--spacing-md);
    }

    .about-card {
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-lg);
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: var(--spacing-lg);
        margin-top: var(--spacing-xl);
    }

    .feature-card {
        text-align: center;
        padding: var(--spacing-lg);
    }

    .feature-icon {
        font-size: 3rem;
        margin-bottom: var(--spacing-md);
    }

    .feature-card h3 {
        margin-bottom: var(--spacing-sm);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-md);
        margin-top: var(--spacing-xl);
    }

    .stat-card {
        text-align: center;
        padding: var(--spacing-lg);
    }

    .stat-number {
        font-size: 3rem;
        font-weight: bold;
        margin-bottom: var(--spacing-xs);
    }

    .stat-label {
        color: var(--gray-lighter);
        font-size: 1.1rem;
    }
</style>

<section class="about-section">
    <div class="container">
        <div class="about-content">
            <div class="about-header">
                <h1>About <?php echo SITE_NAME; ?></h1>
                <p class="text-muted">Your favorite pizza, delivered with passion</p>
            </div>

            <div class="about-card glass-card">
                <h2 style="margin-bottom: var(--spacing-md);">Our Story</h2>
                <p style="line-height: 1.8; color: var(--gray-lighter);">
                    Founded with a passion for authentic Italian cuisine, <?php echo SITE_NAME; ?> has been serving
                    the community with the finest handcrafted pizzas since day one. We believe in using only
                    the freshest ingredients, traditional recipes, and modern cooking techniques to create
                    pizzas that delight every customer.
                </p>
                <p style="line-height: 1.8; color: var(--gray-lighter); margin-top: var(--spacing-md);">
                    Our commitment to quality and customer satisfaction has made us a beloved local favorite.
                    Whether you're craving a classic Margherita or an adventurous specialty pizza, we've got
                    something for everyone.
                </p>
            </div>

            <div class="features-grid">
                <div class="feature-card glass-card">
                    <div class="feature-icon">🍕</div>
                    <h3>Fresh Ingredients</h3>
                    <p class="text-muted">We source the finest local and imported ingredients daily</p>
                </div>

                <div class="feature-card glass-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Fast Delivery</h3>
                    <p class="text-muted">Hot and fresh pizzas delivered to your door in 30 minutes</p>
                </div>

                <div class="feature-card glass-card">
                    <div class="feature-icon">💯</div>
                    <h3>Quality Guarantee</h3>
                    <p class="text-muted">100% satisfaction or your money back</p>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>