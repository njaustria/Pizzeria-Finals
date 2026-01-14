<nav class="navbar glass-nav">
    <div class="container nav-container">
        <a href="index.php" class="logo">
            <img src="assets/images/pizzeria_navbar.png" alt="Pizzeria Logo" style="height: 48px; width: auto;">
        </a>

        <ul class="nav-links">
            <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a></li>
            <li><a href="menu.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">Menu</a></li>
            <li><a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">About</a></li>

            <?php if (isLoggedIn()): ?>
                <li><a href="cart.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <?php if (getCartCount() > 0): ?>
                            <span class="cart-badge"><?php echo getCartCount(); ?></span>
                        <?php endif; ?>
                    </a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/dashboard.php" class="admin-link">
                            <i class="fas fa-tachometer-alt"></i> Admin
                        </a></li>
                <?php endif; ?>
                <li><a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                    </a></li>
                <li><a href="logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a></li>
            <?php else: ?>
                <li><a href="login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a></li>
            <?php endif; ?>
        </ul>

        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</nav>