<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = 'Shopping Cart';
$pdo = getDBConnection();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request', 'error');
        header('Location: cart.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    $pizzaId = (int)$_POST['pizza_id'] ?? 0;

    switch ($action) {
        case 'add':
            if ($pizzaId > 0) {
                if (!isInStock($pizzaId, 1)) {
                    setFlashMessage('Sorry, this pizza is currently out of stock.', 'error');
                } else {
                    $currentCartQuantity = $_SESSION['cart'][$pizzaId] ?? 0;
                    $requestedQuantity = $currentCartQuantity + 1;

                    if (!isInStock($pizzaId, $requestedQuantity)) {
                        $availableStock = getCurrentStock($pizzaId);
                        setFlashMessage("Only {$availableStock} items available for this pizza.", 'error');
                    } else {
                        if (!isset($_SESSION['cart'][$pizzaId])) {
                            $_SESSION['cart'][$pizzaId] = 0;
                        }
                        $_SESSION['cart'][$pizzaId]++;
                        setFlashMessage('Pizza added to cart!', 'success');
                    }
                }
            }
            header('Location: ' . ($_POST['redirect'] ?? 'cart.php'));
            exit();

        case 'update':
            $quantity = (int)$_POST['quantity'] ?? 0;
            if ($pizzaId > 0 && $quantity > 0) {
                if (!isInStock($pizzaId, $quantity)) {
                    $availableStock = getCurrentStock($pizzaId);
                    setFlashMessage("Only {$availableStock} items available for this pizza.", 'error');
                } else {
                    $_SESSION['cart'][$pizzaId] = $quantity;
                    setFlashMessage('Cart updated!', 'success');
                }
            } elseif ($quantity === 0) {
                unset($_SESSION['cart'][$pizzaId]);
                setFlashMessage('Item removed from cart', 'success');
            }
            header('Location: cart.php');
            exit();

        case 'remove':
            if ($pizzaId > 0 && isset($_SESSION['cart'][$pizzaId])) {
                unset($_SESSION['cart'][$pizzaId]);
                setFlashMessage('Item removed from cart', 'success');
            }
            header('Location: cart.php');
            exit();

        case 'clear':
            $_SESSION['cart'] = [];
            setFlashMessage('Cart cleared', 'success');
            header('Location: cart.php');
            exit();
    }
}

$cartItems = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $pizzaIds = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($pizzaIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM pizzas WHERE id IN ($placeholders)");
    $stmt->execute($pizzaIds);
    $pizzas = $stmt->fetchAll();

    foreach ($pizzas as $pizza) {
        $quantity = $_SESSION['cart'][$pizza['id']];
        $subtotal = $pizza['price'] * $quantity;
        $total += $subtotal;

        $cartItems[] = [
            'pizza' => $pizza,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}

$deliveryFee = ($total > 1500) ? 0 : 200;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .cart-section {
        min-height: 100vh;
        padding: calc(80px + var(--spacing-xl)) 0 var(--spacing-xl);
    }

    .cart-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .cart-header {
        text-align: center;
        margin-bottom: var(--spacing-xl);
    }

    .cart-table {
        width: 100%;
        margin-bottom: var(--spacing-lg);
    }

    .cart-item {
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-md);
        display: grid;
        grid-template-columns: 100px 1fr auto;
        gap: var(--spacing-lg);
        align-items: flex-start;
    }

    .item-image {
        width: 100px;
        height: 100px;
        border-radius: var(--radius-md);
        overflow: hidden;
        background: var(--gray-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .item-image i {
        font-size: 2.5rem;
        opacity: 0.3;
    }

    .item-details {
        padding-top: var(--spacing-xs);
    }

    .item-details h3 {
        margin-bottom: var(--spacing-sm);
        font-size: 1.25rem;
        line-height: 1.3;
    }

    .item-details p {
        margin-bottom: var(--spacing-sm);
        line-height: 1.5;
    }

    .item-details .text-muted {
        font-size: 0.9rem;
        margin-bottom: var(--spacing-md);
    }

    .item-actions {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        align-items: flex-end;
        padding-top: var(--spacing-xs);
    }

    .quantity-control {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-sm);
        padding: 4px;
    }

    .qty-btn {
        width: 30px;
        height: 30px;
        background: transparent;
        border: none;
        color: var(--white);
        cursor: pointer;
        border-radius: 4px;
        transition: all var(--transition-fast);
    }

    .qty-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .qty-input {
        width: 50px;
        text-align: center;
        background: transparent;
        border: none;
        color: var(--white);
        font-weight: bold;
    }

    .cart-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: var(--spacing-lg);
    }

    .cart-summary {
        padding: var(--spacing-xl);
        position: sticky;
        top: 100px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: var(--spacing-md);
        padding-bottom: var(--spacing-md);
        border-bottom: 1px solid var(--glass-border);
    }

    .summary-row.total {
        font-size: 1.5rem;
        font-weight: bold;
        border-bottom: none;
    }

    .empty-cart {
        text-align: center;
        padding: var(--spacing-xl);
    }

    .empty-cart i {
        font-size: 5rem;
        opacity: 0.3;
        margin-bottom: var(--spacing-md);
    }

    .items-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-md);
        flex-wrap: wrap;
        gap: var(--spacing-sm);
    }

    @media (max-width: 768px) {
        .cart-grid {
            grid-template-columns: 1fr;
        }

        .cart-summary {
            position: relative;
            top: auto;
            order: -1;
        }

        .cart-item {
            grid-template-columns: 90px 1fr;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            align-items: flex-start;
        }

        .item-image {
            width: 90px;
            height: 90px;
        }

        .item-details h3 {
            font-size: 1.1rem;
            margin-bottom: var(--spacing-sm);
        }

        .item-details .text-muted {
            font-size: 0.85rem;
            margin-bottom: var(--spacing-sm);
        }

        .item-actions {
            grid-column: 1 / -1;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-sm);
            border-top: 1px solid var(--glass-border);
        }

        .items-header {
            flex-direction: column;
            align-items: stretch;
            gap: var(--spacing-md);
        }

        .items-header h3 {
            text-align: center;
            margin: 0;
        }
    }

    @media (max-width: 480px) {
        .cart-item {
            grid-template-columns: 1fr;
            text-align: center;
            padding: var(--spacing-md);
        }

        .item-image {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--spacing-md);
        }

        .item-details {
            text-align: center;
            padding-top: 0;
        }

        .item-details h3 {
            font-size: 1rem;
        }

        .item-details .text-muted {
            font-size: 0.8rem;
        }

        .item-actions {
            grid-column: 1;
            justify-content: center;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
            flex-direction: row;
        }

        .quantity-control {
            order: 1;
        }

        .summary-row {
            flex-wrap: wrap;
            gap: var(--spacing-xs);
        }
    }
</style>

<section class="cart-section">
    <div class="container">
        <div class="cart-container">
            <div class="cart-header">
                <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
                <p class="text-muted">Review your order before checkout</p>
            </div>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart glass-card">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Your cart is empty</h2>
                </div>
            <?php else: ?>
                <div class="cart-grid">
                    <div>
                        <div class="items-header">
                            <h3>Items (<?php echo count($cartItems); ?>)</h3>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Clear all items from cart?')">
                                    <i class="fas fa-trash"></i> Clear Cart
                                </button>
                            </form>
                        </div>

                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item glass-card">
                                <div class="item-image">
                                    <?php if ($item['pizza']['image'] && file_exists('assets/images/pizzas/' . $item['pizza']['image'])): ?>
                                        <img src="assets/images/pizzas/<?php echo htmlspecialchars($item['pizza']['image']); ?>"
                                            alt="<?php echo htmlspecialchars($item['pizza']['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-pizza-slice"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['pizza']['name']); ?></h3>
                                    <p class="text-muted"><?php echo htmlspecialchars($item['pizza']['description']); ?></p>
                                    <p style="margin-top: var(--spacing-xs); font-weight: bold;">
                                        <?php echo formatPrice($item['pizza']['price']); ?> × <?php echo $item['quantity']; ?> =
                                        <?php echo formatPrice($item['subtotal']); ?>
                                    </p>
                                </div>

                                <div class="item-actions">
                                    <div class="quantity-control">
                                        <form method="POST" style="display: contents;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="pizza_id" value="<?php echo $item['pizza']['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" class="qty-btn">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="text" class="qty-input" value="<?php echo $item['quantity']; ?>" readonly>
                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="qty-btn">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <form method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="pizza_id" value="<?php echo $item['pizza']['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this item?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div>
                        <div class="cart-summary glass-card">
                            <h3 style="margin-bottom: var(--spacing-md);">Order Summary</h3>

                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span><?php echo formatPrice($total); ?></span>
                            </div>

                            <div class="summary-row">
                                <span>Delivery Fee</span>
                                <span><?php echo formatPrice($deliveryFee); ?></span>
                            </div>

                            <div class="summary-row total">
                                <span>Total</span>
                                <span><?php echo formatPrice($total + $deliveryFee); ?></span>
                            </div>

                            <a href="checkout.php" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-md);">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </a>

                            <a href="index.php" class="btn" style="width: 100%; margin-top: var(--spacing-sm);">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>