<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdminLogin();

if (!isValidAdminSession()) {
    adminLogout();
}

$pageTitle = 'Manage Pizzas';
$pdo = getDBConnection();

$error = '';
$success = '';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM pizzas WHERE id = ?");
    if ($stmt->execute([$id])) {
        setFlashMessage('Pizza deleted successfully', 'success');
    }
    header('Location: pizzas.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_stock') {
        $pizzaId = (int)$_POST['pizza_id'];
        $newStock = (int)$_POST['stock'];

        if ($pizzaId > 0 && $newStock >= 0) {
            if (setStock($pizzaId, $newStock)) {
                setFlashMessage('Stock updated successfully', 'success');
            } else {
                setFlashMessage('Failed to update stock', 'error');
            }
        }
        header('Location: pizzas.php');
        exit();
    }

    $id = (int)($_POST['id'] ?? 0);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $price = (float)$_POST['price'];
    $category = sanitizeInput($_POST['category']);
    $availability = isset($_POST['availability']) ? 1 : 0;

    if (empty($name) || empty($price)) {
        $error = 'Name and price are required';
    } else {
        $image = null;
        $imageUpdated = false;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadedImage = uploadImage($_FILES['image']);
            if ($uploadedImage) {
                $image = $uploadedImage;
                $imageUpdated = true;
                setFlashMessage('Image uploaded successfully', 'success');
            } else {
                setFlashMessage('Failed to upload image. Please check file size and format.', 'warning');
            }
        }

        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            $image = null;
            $imageUpdated = true;
        }

        if ($id > 0) {
            $sql = "UPDATE pizzas SET name = ?, description = ?, price = ?, category = ?, availability = ?";
            $params = [$name, $description, $price, $category, $availability];

            if ($imageUpdated) {
                $sql .= ", image = ?";
                $params[] = $image;
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute($params)) {
                if ($imageUpdated) {
                    setFlashMessage('Pizza and image updated successfully', 'success');
                } else {
                    setFlashMessage('Pizza updated successfully', 'success');
                }
            } else {
                setFlashMessage('Failed to update pizza', 'error');
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO pizzas (name, description, price, category, image, availability) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $price, $category, $image, $availability])) {
                setFlashMessage('Pizza added successfully', 'success');
                $newPizzaId = $pdo->lastInsertId();
                initializeDailyStock($newPizzaId);
            } else {
                setFlashMessage('Failed to add pizza', 'error');
            }
        }
        header('Location: pizzas.php');
        exit();
    }
}

$stmt = $pdo->query("SELECT * FROM pizzas ORDER BY category, name");
$pizzas = $stmt->fetchAll();

$pizzasWithStock = getAllPizzasWithStock();

$stmt = $pdo->query("SELECT DISTINCT category FROM pizzas WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$editPizza = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM pizzas WHERE id = ?");
    $stmt->execute([$editId]);
    $editPizza = $stmt->fetch();
}

require_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #fcfcfc;
        padding-top: 80px;
        color: #000;
    }

    .content-card {
        background: #fff;
        border: 1px solid #efefef;
        border-radius: 12px;
        padding: 30px;
    }

    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #999;
        border-bottom: 2px solid #f0f0f0;
    }

    .btn-add {
        background: #000;
        color: #fff;
        border-radius: 50px;
        padding: 8px 25px;
        font-weight: 600;
        border: none;
    }

    .btn-add:hover {
        background: #333;
        color: #fff;
    }

    .pizza-img {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid #eee;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        z-index: 1050;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .pizza-modal {
        background: #fff;
        width: 100%;
        max-width: 500px;
        padding: 30px;
        border-radius: 15px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .stock-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 30px 20px;
        text-align: center;
        transition: all 0.2s ease;
    }

    .stock-card:hover {
        border-color: #bbb;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .stock-label {
        font-size: 0.85rem;
        color: #888;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .stock-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #000;
        line-height: 1;
    }
</style>

<?php include 'includes/admin_navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold m-0">Manage Pizzas</h2>
        <div>
            <button onclick="openModal()" class="btn btn-add shadow-sm">
                <i class="fas fa-plus me-2"></i>Add New Pizza
            </button>
        </div>
    </div>

    <div class="content-card shadow-sm">
        <?php if ($error): ?>
            <div class="alert alert-danger mb-3"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php displayFlashMessage(); ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stock-card">
                    <div class="stock-label">Total Pizzas</div>
                    <div class="stock-value"><?php echo count($pizzasWithStock); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stock-card">
                    <div class="stock-label">Out of Stock</div>
                    <div class="stock-value"><?php echo count(array_filter($pizzasWithStock, function ($p) {
                                                    return $p['current_stock'] == 0;
                                                })); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stock-card">
                    <div class="stock-label">Low Stock</div>
                    <div class="stock-value"><?php echo count(array_filter($pizzasWithStock, function ($p) {
                                                    return $p['current_stock'] > 0 && $p['current_stock'] <= 3;
                                                })); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stock-card">
                    <div class="stock-label">In Stock</div>
                    <div class="stock-value"><?php echo count(array_filter($pizzasWithStock, function ($p) {
                                                    return $p['current_stock'] > 0;
                                                })); ?></div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pizzasWithStock as $pizza): ?>
                        <tr>
                            <td>
                                <?php
                                $imagePath = getPizzaImagePath($pizza['image']);
                                if ($imagePath):
                                ?>
                                    <img src="../<?php echo htmlspecialchars($imagePath); ?>" class="pizza-img" alt="<?php echo htmlspecialchars($pizza['name']); ?>">
                                <?php else: ?>
                                    <div class="pizza-img bg-light d-flex align-items-center justify-content-center"><i class="fas fa-pizza-slice text-muted"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($pizza['name']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($pizza['category']); ?></span></td>
                            <td class="fw-bold"><?php echo formatPrice($pizza['price']); ?></td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="action" value="update_stock">
                                    <input type="hidden" name="pizza_id" value="<?php echo $pizza['id']; ?>">
                                    <div class="d-flex align-items-center">
                                        <input type="number" name="stock" value="<?php echo $pizza['current_stock']; ?>"
                                            min="0" max="50" class="form-control form-control-sm me-2 
                                               <?php
                                                if ($pizza['current_stock'] == 0) echo 'border-danger';
                                                elseif ($pizza['current_stock'] <= 3) echo 'border-warning';
                                                else echo 'border-success';
                                                ?>" style="width: 70px;">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <?php if ($pizza['availability'] && $pizza['current_stock'] > 0): ?>
                                    <span class="text-success small fw-bold">Available (<?php echo $pizza['current_stock']; ?>)</span>
                                <?php elseif ($pizza['current_stock'] == 0): ?>
                                    <span class="text-danger small fw-bold">Out of Stock</span>
                                <?php elseif ($pizza['current_stock'] <= 3): ?>
                                    <span class="text-warning small fw-bold">Low Stock (<?php echo $pizza['current_stock']; ?>)</span>
                                <?php else: ?>
                                    <span class="text-warning small fw-bold">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="?edit=<?php echo $pizza['id']; ?>" class="btn btn-sm btn-outline-dark">Edit</a>
                                <a href="?delete=<?php echo $pizza['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Delete this pizza?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay <?php echo $editPizza ? 'active' : ''; ?>" id="pizzaModal">
    <div class="pizza-modal shadow-lg">
        <h4 class="fw-bold mb-4"><?php echo $editPizza ? 'Edit Pizza' : 'Add New Pizza'; ?></h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $editPizza['id'] ?? ''; ?>">

            <div class="mb-3">
                <label class="form-label small fw-bold">Pizza Name</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editPizza['name'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($editPizza['description'] ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">Price</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $editPizza['price'] ?? ''; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo (isset($editPizza['category']) && $editPizza['category'] === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php
                if ($editPizza && $editPizza['image']) {
                    $imagePath = getPizzaImagePath($editPizza['image']);
                    if ($imagePath):
                ?>
                        <div class="mt-2">
                            <img src="../<?php echo htmlspecialchars($imagePath); ?>" class="rounded" style="width: 80px;">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                                <label class="form-check-label small text-danger" for="removeImage">
                                    Remove current image
                                </label>
                            </div>
                        </div>
                <?php
                    endif;
                }
                ?>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="availability" id="avail" <?php echo ($editPizza['availability'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="avail">Available for ordering</label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold">Save Pizza</button>
                <button type="button" onclick="closeModal()" class="btn btn-light w-100 fw-bold border">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('pizzaModal').classList.add('active');
    }

    function closeModal() {
        window.location.href = 'pizzas.php';
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>