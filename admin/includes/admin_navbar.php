<?php
function isActivePage($page)
{
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page ? 'active' : '';
}
?>

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
</style>

<nav class="navbar navbar-expand-lg admin-navbar fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="../assets/images/pizzeria_navbar.png" alt="Pizzeria Logo" style="height: 48px; width: auto;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= isActivePage('dashboard.php') ?>" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActivePage('pizzas.php') ?>" href="pizzas.php">Pizzas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActivePage('orders.php') ?>" href="orders.php">Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActivePage('users.php') ?>" href="users.php">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActivePage('contacts.php') ?>" href="contacts.php">Messages</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['sms.php', 'sms_inbox.php', 'sms_tools.php']) ? 'active' : '' ?>"
                        href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        SMS
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= isActivePage('sms_inbox.php') ?>" href="sms_inbox.php">
                                <i class="fas fa-inbox me-2"></i>SMS Inbox
                            </a></li>
                        <li><a class="dropdown-item <?= isActivePage('sms.php') ?>" href="sms.php">
                                <i class="fas fa-paper-plane me-2"></i>Send SMS
                            </a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isActivePage('reports.php') ?>" href="reports.php">Reports</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <a href="../index.php" class="btn btn-outline-dark btn-sm rounded-pill px-3 me-3">
                    <i class="fas fa-external-link-alt me-1"></i> View Site
                </a>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-dark fw-bold" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($_SESSION['admin_name']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>