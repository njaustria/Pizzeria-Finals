<?php
require_once 'config/config.php';
require_once 'config/sms_config.php';
require_once 'includes/functions.php';
require_once 'includes/email.php';

requireLogin();

$pageTitle = 'Checkout';
$pdo = getDBConnection();

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    setFlashMessage('Your cart is empty', 'error');
    header('Location: cart.php');
    exit();
}

$cartItems = [];
$total = 0;

$pizzaIds = array_keys($_SESSION['cart']);
if (empty($pizzaIds)) {
    setFlashMessage('Your cart is empty', 'error');
    header('Location: cart.php');
    exit();
}

$placeholders = implode(',', array_fill(0, count($pizzaIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM pizzas WHERE id IN ($placeholders)");
$stmt->execute($pizzaIds);
$pizzas = $stmt->fetchAll();

foreach ($pizzas as $pizza) {
    $quantity = (int)($_SESSION['cart'][$pizza['id']] ?? 0);
    if ($quantity <= 0) {
        continue;
    }

    $subtotal = (float)$pizza['price'] * $quantity;
    $total += $subtotal;

    $cartItems[] = [
        'pizza' => $pizza,
        'quantity' => $quantity,
        'subtotal' => $subtotal
    ];
}

if (empty($cartItems)) {
    $_SESSION['cart'] = [];
    setFlashMessage('Items in your cart are no longer available. Please add items again.', 'error');
    header('Location: cart.php');
    exit();
}

$deliveryFee = ($total > 1500) ? 0 : 200;
$grandTotal = $total + $deliveryFee;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Your session expired. Please try again.', 'error');
        header('Location: checkout.php');
        exit();
    }

    $street = sanitizeInput($_POST['street']);
    $city = sanitizeInput($_POST['city']);
    $province = 'Batangas';
    $postalCode = sanitizeInput($_POST['postal_code']);
    $deliveryAddress = trim($street . ', ' . $city . ', ' . $province . ' ' . $postalCode);
    $phoneDigits = sanitizeInput($_POST['phone']);
    $phone = '+63' . $phoneDigits;
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $notes = sanitizeInput($_POST['notes']);

    $allowedPaymentMethods = ['cash_on_delivery', 'paypal'];
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        $error = 'Invalid payment method selected.';
    } elseif (empty($street) || empty($city) || empty($province) || empty($postalCode) || empty($phoneDigits)) {
        $error = 'Please fill in all required fields';
    } elseif (!preg_match('/^[0-9]{10}$/', $phoneDigits)) {
        $error = 'Phone number must be exactly 10 digits (e.g., 9123456789)';
    } else {
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->beginTransaction();

            foreach ($cartItems as $item) {
                if (!isInStock($item['pizza']['id'], $item['quantity'])) {
                    $availableStock = getCurrentStock($item['pizza']['id']);
                    $pdo->rollBack();
                    $error = "Sorry, only {$availableStock} {$item['pizza']['name']} available. Please update your cart.";
                    goto skip_order_processing;
                }
            }


            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_price, delivery_address, phone, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $grandTotal,
                $deliveryAddress,
                $phone,
                $paymentMethod,
                $notes ?: null
            ]);
            $orderId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, pizza_id, quantity, price) VALUES (?, ?, ?, ?)");

            foreach ($cartItems as $item) {
                $stmt->execute([
                    $orderId,
                    $item['pizza']['id'],
                    $item['quantity'],
                    $item['pizza']['price']
                ]);

                updateStock($item['pizza']['id'], $item['quantity']);
            }

            $pdo->commit();

            $stmt = $pdo->prepare("
                SELECT o.*, u.name as user_name, u.email as user_email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $orderData = $stmt->fetch();

            $stmt = $pdo->prepare("
                SELECT oi.*, p.name as pizza_name 
                FROM order_items oi 
                JOIN pizzas p ON oi.pizza_id = p.id 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $orderItems = $stmt->fetchAll();

            $userData = [
                'name' => $orderData['user_name'],
                'email' => $orderData['user_email']
            ];

            $emailSent = sendOrderReceipt($orderData, $orderItems, $userData);

            $smsSent = false;
            if (!empty($phone)) {
                $smsMessage = buildOrderConfirmationSMS($orderId, $orderItems, $grandTotal, $orderData['user_name']);
                $smsResult = sendSMS($phone, $smsMessage, $_SESSION['user_id'], $orderId, 'order_confirmation');
                $smsSent = $smsResult['success'];
            }

            $_SESSION['cart'] = [];

            $successMessage = 'Order placed successfully! Order #' . $orderId . '.';
            if ($emailSent && $smsSent) {
                $successMessage .= ' Receipt sent to your email and SMS confirmation sent to your phone.';
            } elseif ($emailSent) {
                $successMessage .= ' Receipt sent to your email.';
            } elseif ($smsSent) {
                $successMessage .= ' SMS confirmation sent to your phone.';
            } else {
                $successMessage .= ' (Email and SMS delivery may be delayed)';
            }

            setFlashMessage($successMessage, 'success');
            header('Location: profile.php?tab=orders');
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout error: ' . $e->getMessage());
            $error = 'Failed to place order. Please try again.';
        }

        skip_order_processing:
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .checkout-section {
        min-height: 100vh;
        padding: calc(80px + var(--spacing-xl)) 0 var(--spacing-xl);
    }

    .checkout-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: var(--spacing-lg);
    }

    .checkout-card {
        padding: var(--spacing-xl);
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-sm) 0;
        border-bottom: 1px solid var(--glass-border);
    }

    @media (max-width: 768px) {
        .checkout-grid {
            grid-template-columns: 1fr;
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

    .payment-option:hover {
        border-color: #80bdff !important;
        background-color: rgba(0, 123, 255, 0.1);
    }

    .payment-option input[type="radio"]:checked+i+span {
        color: #0070ba;
    }

    .payment-option:has(input[type="radio"]:checked) {
        border-color: #0070ba !important;
        background-color: rgba(0, 112, 186, 0.1);
    }

    @media (max-width: 768px) {
        .payment-option {
            flex-direction: column;
            text-align: center;
        }

        .payment-option i {
            margin-bottom: var(--spacing-xs) !important;
            margin-right: 0 !important;
        }
    }
</style>

<section class="checkout-section">
    <div class="container">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h1 style="text-align: center; margin-bottom: var(--spacing-xl);">
                <i class="fas fa-credit-card"></i> Checkout
            </h1>

            <?php if ($error): ?>
                <div style="background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5); padding: var(--spacing-sm); border-radius: var(--radius-sm); margin-bottom: var(--spacing-md);">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="checkout-grid">
                <div class="checkout-card glass-card">
                    <h2 style="margin-bottom: var(--spacing-lg);">Delivery Information</h2>

                    <form method="POST" id="checkout-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" id="default_address_data" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        <input type="hidden" id="default_phone_data" value="<?php echo htmlspecialchars(preg_replace('/^\+63/', '', $user['phone'] ?? '')); ?>">
                        <input type="hidden" id="has_default" value="<?php echo ($user['is_default_address'] ?? 0) ? '1' : '0'; ?>">

                        <div class="form-group">
                            <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: var(--spacing-md);">
                                <input type="checkbox" id="use_default_address" style="margin-right: var(--spacing-sm);" <?php echo ($user['is_default_address'] ?? 0) ? '' : 'disabled'; ?>>
                                <span><?php echo ($user['is_default_address'] ?? 0) ? 'Use my default address and phone from profile' : 'No default address set (update in profile)'; ?></span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="street">Street Address *</label>
                            <input type="text" id="street" name="street" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="city">City/Municipality *</label>
                            <select id="city" name="city" class="form-control" required>
                                <option value="">Select City/Municipality</option>
                                <option value="Agoncillo" data-postal="4211">Agoncillo</option>
                                <option value="Alitagtag" data-postal="4205">Alitagtag</option>
                                <option value="Balayan" data-postal="4213">Balayan</option>
                                <option value="Balete" data-postal="4219">Balete</option>
                                <option value="Batangas City" data-postal="4200">Batangas City</option>
                                <option value="Bauan" data-postal="4201">Bauan</option>
                                <option value="Calaca" data-postal="4212">Calaca</option>
                                <option value="Calatagan" data-postal="4215">Calatagan</option>
                                <option value="Cuenca" data-postal="4222">Cuenca</option>
                                <option value="Ibaan" data-postal="4230">Ibaan</option>
                                <option value="Laurel" data-postal="4221">Laurel</option>
                                <option value="Lemery" data-postal="4209">Lemery</option>
                                <option value="Lian" data-postal="4214">Lian</option>
                                <option value="Lipa City" data-postal="4217">Lipa City</option>
                                <option value="Lobo" data-postal="4207">Lobo</option>
                                <option value="Mabini" data-postal="4202">Mabini</option>
                                <option value="Malvar" data-postal="4233">Malvar</option>
                                <option value="Mataasnakahoy" data-postal="4223">Mataasnakahoy</option>
                                <option value="Nasugbu" data-postal="4231">Nasugbu</option>
                                <option value="Padre Garcia" data-postal="4224">Padre Garcia</option>
                                <option value="Rosario" data-postal="4225">Rosario</option>
                                <option value="San Jose" data-postal="4227">San Jose</option>
                                <option value="San Juan" data-postal="4226">San Juan</option>
                                <option value="San Luis" data-postal="4234">San Luis</option>
                                <option value="San Nicolas" data-postal="4210">San Nicolas</option>
                                <option value="San Pascual" data-postal="4218">San Pascual</option>
                                <option value="Santa Teresita" data-postal="4235">Santa Teresita</option>
                                <option value="Santo Tomas" data-postal="4234">Santo Tomas</option>
                                <option value="Taal" data-postal="4208">Taal</option>
                                <option value="Talisay" data-postal="4220">Talisay</option>
                                <option value="Tanauan City" data-postal="4232">Tanauan City</option>
                                <option value="Taysan" data-postal="4228">Taysan</option>
                                <option value="Tingloy" data-postal="4203">Tingloy</option>
                                <option value="Tuy" data-postal="4216">Tuy</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" class="form-control" value="Batangas" readonly style="background-color: #f8f9fa;">
                        </div>

                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" readonly style="background-color: #f8f9fa;" placeholder="Select city first">
                        </div>

                        <div class="form-group">
                            <label for="phone">Contact Phone *</label>
                            <div style="display: flex; align-items: center;">
                                <span style="background-color: #f8f9fa; border: 1px solid #ced4da; border-right: none; padding: 8px 12px; border-radius: 4px 0 0 4px; color: #333;">+63</span>
                                <input type="tel" id="phone" name="phone" class="form-control" style="border-radius: 0 4px 4px 0; flex: 1;" pattern="[0-9]{10}" maxlength="10" placeholder="9123456789" value="<?php echo htmlspecialchars(preg_replace('/^\+63/', '', $user['phone'] ?? '')); ?>" required>
                            </div>
                            <small class="text-muted">Enter 10 digits (e.g., 9123456789)</small>
                        </div>

                        <div class="form-group">
                            <label>Payment Method *</label>
                            <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-sm);">
                                <label class="payment-option" style="flex: 1; display: flex; align-items: center; padding: var(--spacing-md); border: 2px solid #ced4da; border-radius: var(--radius-sm); cursor: pointer; transition: all 0.3s ease;">
                                    <input type="radio" name="payment_method" value="cash_on_delivery" required style="margin-right: var(--spacing-sm);">
                                    <i class="fas fa-money-bill-wave" style="font-size: 1.5rem; color: #28a745; margin-right: var(--spacing-sm);"></i>
                                    <span style="font-weight: 500;">Cash on Delivery</span>
                                </label>

                                <label class="payment-option" style="flex: 1; display: flex; align-items: center; padding: var(--spacing-md); border: 2px solid #ced4da; border-radius: var(--radius-sm); cursor: pointer; transition: all 0.3s ease;">
                                    <input type="radio" name="payment_method" value="paypal" required style="margin-right: var(--spacing-sm);">
                                    <i class="fab fa-paypal" style="font-size: 1.5rem; color: #0070ba; margin-right: var(--spacing-sm);"></i>
                                    <span style="font-weight: 500;">PayPal</span>
                                </label>
                            </div>

                            <div id="paypal-button-container" style="margin-top: var(--spacing-md); display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Order Notes (Optional)</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Special instructions for your order..."></textarea>
                        </div>
                    </form>
                </div>

                <div>
                    <div class="checkout-card glass-card">
                        <h2 style="margin-bottom: var(--spacing-lg);">Order Summary</h2>

                        <div style="margin-bottom: var(--spacing-md);">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="order-item">
                                    <span>
                                        <?php echo htmlspecialchars($item['pizza']['name']); ?>
                                        <small class="text-muted">× <?php echo $item['quantity']; ?></small>
                                    </span>
                                    <span><?php echo formatPrice($item['subtotal']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--glass-border);">
                            <div class="order-item">
                                <span>Subtotal</span>
                                <span><?php echo formatPrice($total); ?></span>
                            </div>

                            <div class="order-item">
                                <span>Delivery Fee</span>
                                <span><?php echo formatPrice($deliveryFee); ?></span>
                            </div>

                            <div class="order-item" style="font-size: 1.3rem; font-weight: bold; border: none; margin-top: var(--spacing-sm);">
                                <span>Total</span>
                                <span><?php echo formatPrice($grandTotal); ?></span>
                            </div>
                        </div>

                        <button type="submit" form="checkout-form" name="place_order" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-lg); font-size: 1.1rem; padding: var(--spacing-md);">
                            <i class="fas fa-check"></i> Place Order
                        </button>

                        <a href="cart.php" class="btn" style="width: 100%; margin-top: var(--spacing-sm);">
                            <i class="fas fa-arrow-left"></i> Back to Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://www.paypal.com/sdk/js?client-id=AaKO9Zh8SR7Fi8blN9GV7o3WRBuQ0_2iPCh9UHPqzU9FG5yNzvmGxVuz0m2lhNj2Fa9izqhxxyHLwAvb&currency=USD"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const citySelect = document.getElementById('city');
        const postalCodeInput = document.getElementById('postal_code');
        const phoneInput = document.getElementById('phone');
        const useDefaultCheckbox = document.getElementById('use_default_address');
        const streetInput = document.getElementById('street');
        const paypalContainer = document.getElementById('paypal-button-container');
        const checkoutForm = document.getElementById('checkout-form');
        const placeOrderBtn = document.querySelector('button[name="place_order"]');

        const defaultAddress = document.getElementById('default_address_data').value;
        const defaultPhone = document.getElementById('default_phone_data').value;
        const hasDefault = document.getElementById('has_default').value === '1';

        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'paypal') {
                    paypalContainer.style.display = 'block';
                    placeOrderBtn.style.display = 'none';
                } else {
                    paypalContainer.style.display = 'none';
                    placeOrderBtn.style.display = 'block';
                }
            });
        });

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

        if (hasDefault && useDefaultCheckbox) {
            useDefaultCheckbox.addEventListener('change', function() {
                if (this.checked && defaultAddress) {
                    const addressParts = defaultAddress.split(', ');
                    if (addressParts.length >= 3) {
                        const street = addressParts[0];
                        const city = addressParts[1];
                        const provincePostal = addressParts[2].split(' ');
                        const postalCode = provincePostal[provincePostal.length - 1];

                        streetInput.value = street;
                        phoneInput.value = defaultPhone;

                        for (let i = 0; i < citySelect.options.length; i++) {
                            if (citySelect.options[i].value === city) {
                                citySelect.selectedIndex = i;
                                postalCodeInput.value = postalCode;
                                break;
                            }
                        }
                    }
                } else {
                    streetInput.value = '';
                    phoneInput.value = '';
                    citySelect.selectedIndex = 0;
                    postalCodeInput.value = '';
                    postalCodeInput.placeholder = 'Select city first';
                }
            });
        }

        function validateForm() {
            const street = streetInput.value.trim();
            const city = citySelect.value;
            const phone = phoneInput.value.trim();

            return street && city && phone && phone.length === 10;
        }

        const grandTotalPHP = <?php echo $grandTotal; ?>;
        const grandTotalUSD = (grandTotalPHP / 50).toFixed(2);

        paypal.Buttons({
            createOrder: function(data, actions) {
                if (!validateForm()) {
                    alert('Please fill in all required delivery information first.');
                    return false;
                }

                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: grandTotalUSD
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    const formData = new FormData(checkoutForm);
                    formData.set('payment_method', 'paypal');
                    formData.set('place_order', '1');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.ok) {
                            window.location.href = "profile.php?tab=orders";
                        } else {
                            alert('Order processing failed. Please try again.');
                        }
                    }).catch(error => {
                        console.error('Error:', error);
                        alert('Order processing failed. Please try again.');
                    });
                });
            },
            onCancel: function() {
                alert('PayPal payment was cancelled.');
            },
            onError: function(err) {
                console.error('PayPal error:', err);
                alert('PayPal payment failed. Please try again or use Cash on Delivery.');
            }
        }).render('#paypal-button-container');
    });
</script>

<?php require_once 'includes/footer.php'; ?>