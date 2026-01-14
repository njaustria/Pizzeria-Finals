<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Home';
$pdo = getDBConnection();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    body,
    html {
        margin: 0;
        padding: 0;
        overflow: hidden;
        height: 100vh;
        width: 100%;
    }

    .hero {
        text-align: center;
        color: var(--white);
        height: 100vh;
        width: 100%;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        position: relative;

        background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
            url('assets/images/pizzeria_bgindex.png') no-repeat center center;
        background-size: cover;
    }

    .hero-content {
        max-width: 900px;
        margin: 0 auto;
        z-index: 1;
        margin-top: 10px;
        padding: 0 var(--spacing-md);
    }

    .hero h1 {
        margin: 0;
        padding: 0;
        font-weight: 800;
        text-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
        margin-bottom: -10px;
    }

    .hero h1 img {
        display: block;
        margin: 0 auto;
        max-width: 95%;
        height: auto;
        max-height: 450px;
        filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
    }

    .hero p {
        font-size: clamp(1rem, 2vw, 1.25rem);
        margin-top: 0;
        margin-bottom: var(--spacing-lg);
        color: var(--gray-lighter);
        line-height: 1.5;
        max-width: 650px;
        margin-left: auto;
        margin-right: auto;
        padding: 0 var(--spacing-md);
        text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.5);
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .hero-btns {
        display: flex;
        gap: var(--spacing-md);
        justify-content: center;
        flex-wrap: wrap;
        padding: 0 var(--spacing-sm);
    }

    .hero .btn {
        min-width: 160px;
        padding: 0.8rem 1.8rem;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        white-space: nowrap;
    }

    @media (max-width: 992px) {
        .hero-content {
            max-width: 750px;
            padding: 0 var(--spacing-lg);
        }

        .hero h1 img {
            max-height: 380px;
        }

        .hero p {
            font-size: clamp(0.95rem, 1.8vw, 1.1rem);
            padding: 0 var(--spacing-sm);
        }

        .hero .btn {
            min-width: 140px;
            padding: 0.7rem 1.5rem;
            font-size: 0.95rem;
        }
    }

    @media (max-width: 768px) {
        .hero-content {
            margin-top: 20px;
            padding: 0 var(--spacing-md);
        }

        .hero h1 img {
            max-height: 300px;
        }

        .hero p {
            font-size: clamp(0.9rem, 2vw, 1rem);
            margin-bottom: var(--spacing-md);
            padding: 0 var(--spacing-xs);
        }

        .hero-btns {
            flex-direction: column;
            align-items: center;
            gap: var(--spacing-sm);
            padding: 0 var(--spacing-md);
        }

        .hero .btn {
            min-width: 200px;
            max-width: 250px;
            width: 100%;
            padding: 0.8rem 1.5rem;
            font-size: 0.95rem;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .hero-content {
            margin-top: 15px;
            padding: 0 var(--spacing-sm);
        }

        .hero h1 img {
            max-height: 250px;
        }

        .hero p {
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
            margin-bottom: var(--spacing-sm);
            padding: 0 var(--spacing-xs);
        }

        .hero-btns {
            gap: var(--spacing-xs);
            padding: 0 var(--spacing-sm);
        }

        .hero .btn {
            min-width: 180px;
            max-width: 220px;
            padding: 0.7rem 1.2rem;
            font-size: 0.9rem;
        }

        .hero .btn i {
            font-size: 0.85rem;
        }
    }

    @media (max-width: 360px) {
        .hero-content {
            padding: 0 var(--spacing-xs);
        }

        .hero h1 img {
            max-height: 200px;
        }

        .hero p {
            font-size: 0.8rem;
            padding: 0;
        }

        .hero .btn {
            min-width: 160px;
            max-width: 200px;
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
            gap: 8px;
        }
    }
</style>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>
                <img src="assets/images/pizzeria_navbar.png" alt="<?php echo SITE_NAME; ?>">
            </h1>

            <p>Experience the authentic taste of handcrafted pizzas made with the finest ingredients and traditional recipes passed down through generations.</p>

            <div class="hero-btns">
                <?php if (!isLoggedIn()): ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-user"></i> Get Started
                    </a>
                    <a href="menu.php" class="btn">
                        <i class="fas fa-pizza-slice"></i> View Menu
                    </a>
                <?php else: ?>
                    <a href="menu.php" class="btn btn-primary">
                        <i class="fas fa-pizza-slice"></i> Order Now
                    </a>
                    <a href="cart.php" class="btn">
                        <i class="fas fa-shopping-cart"></i> View Cart
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>