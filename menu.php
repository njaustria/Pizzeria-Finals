<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Menu';
$pdo = getDBConnection();

$pizzasWithStock = getAllPizzasWithStock();

$pizzas = array_filter($pizzasWithStock, function ($pizza) {
    return $pizza['availability'] == 1;
});

$categories = [];
foreach ($pizzas as $pizza) {
    $category = $pizza['category'] ?? 'Other';
    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }
    $categories[$category][] = $pizza;
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .menu-hero {
        padding: 160px 0 var(--spacing-xl);
        text-align: center;
        color: var(--white);
    }

    .menu-hero h1 {
        font-size: 3.5rem;
        margin-bottom: var(--spacing-sm);
        font-weight: 800;
        text-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    }

    .menu-hero p {
        font-size: 1.25rem;
        color: var(--gray-lighter);
        max-width: 600px;
        margin: 0 auto;
    }

    .category-section {
        margin-bottom: var(--spacing-xl);
        scroll-margin-top: 100px;
    }

    .category-title {
        font-size: 2rem;
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-xs);
        border-bottom: 2px solid var(--glass-border);
        color: var(--white);
    }

    .search-container {
        max-width: 500px;
        margin: 0 auto var(--spacing-lg) auto;
        position: relative;
    }

    .search-input {
        width: 100%;
        padding: var(--spacing-sm) var(--spacing-md) var(--spacing-sm) 50px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        color: var(--white);
        font-size: 1rem;
        transition: all var(--transition-normal);
    }

    .search-input::placeholder {
        color: var(--gray-lighter);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--white);
        background: rgba(255, 255, 255, 0.1);
    }

    .search-icon {
        position: absolute;
        left: var(--spacing-sm);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-lighter);
        pointer-events: none;
    }

    .clear-search {
        position: absolute;
        right: var(--spacing-sm);
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--gray-lighter);
        cursor: pointer;
        padding: 4px;
        border-radius: var(--radius-sm);
        transition: color var(--transition-normal);
        display: none;
    }

    .clear-search:hover {
        color: var(--white);
    }

    .clear-search.show {
        display: block;
    }

    .filter-tabs {
        display: flex;
        justify-content: center;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-xl);
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: var(--spacing-sm) var(--spacing-md);
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: all var(--transition-normal);
        text-decoration: none;
        color: var(--white);
        font-weight: 500;
    }

    .filter-tab:hover,
    .filter-tab.active {
        background: var(--white);
        color: var(--black);
        transform: translateY(-2px);
    }

    .badge {
        display: inline-block;
        padding: 4px 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        margin-top: var(--spacing-xs);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .pizza-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--transition-slow);
    }

    .pizza-card:hover .pizza-image img {
        transform: scale(1.1);
    }

    .pizza-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-xs);
        margin-top: var(--spacing-xs);
        flex-wrap: wrap;
    }

    .stock-info {
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .stock-text {
        color: var(--gray-lighter);
    }

    .out-of-stock {
        opacity: 0.7;
        position: relative;
    }

    .stock-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
    }

    .out-of-stock-label {
        color: var(--white);
        font-weight: 600;
        font-size: 0.9rem;
        background: rgba(220, 53, 69, 0.9);
        padding: 4px 12px;
        border-radius: var(--radius-sm);
    }

    .btn:disabled,
    .btn.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--gray-dark);
        color: var(--gray-lighter);
    }
</style>

</section>

<section class="menu-section">
    <div class="container">
        <?php if (!empty($categories)): ?>
            <div class="filter-tabs">
                <a href="#" class="filter-tab active" onclick="filterCategory(event, 'all')">All</a>
                <?php foreach (array_keys($categories) as $categoryName): ?>
                    <a href="#" class="filter-tab" onclick="filterCategory(event, '<?php echo htmlspecialchars($categoryName); ?>')">
                        <?php echo htmlspecialchars($categoryName); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" id="pizzaSearch" placeholder="Search for pizzas..." autocomplete="off">
            <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="menu-container">
            <?php foreach ($categories as $categoryName => $categoryPizzas): ?>
                <div class="category-section" data-category="<?php echo htmlspecialchars($categoryName); ?>">
                    <h3 class="category-title"><?php echo htmlspecialchars($categoryName); ?></h3>

                    <div class="pizza-grid">
                        <?php foreach ($categoryPizzas as $pizza): ?>
                            <div class="pizza-card <?php echo $pizza['current_stock'] <= 0 ? 'out-of-stock' : ''; ?>">
                                <div class="pizza-image">
                                    <?php
                                    $imagePath = getPizzaImagePath($pizza['image']);
                                    if ($imagePath):
                                    ?>
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($pizza['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-pizza-slice"></i>
                                    <?php endif; ?>

                                    <?php if ($pizza['current_stock'] <= 0): ?>
                                        <div class="stock-overlay">
                                            <span class="out-of-stock-label">Out of Stock</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="pizza-content">
                                    <h3 class="pizza-title"><?php echo htmlspecialchars($pizza['name']); ?></h3>
                                    <p class="pizza-description"><?php echo htmlspecialchars($pizza['description']); ?></p>
                                    <div class="pizza-meta">
                                        <span class="badge"><?php echo htmlspecialchars($pizza['category']); ?></span>
                                        <span class="stock-info">
                                            <?php if ($pizza['current_stock'] > 0): ?>
                                                <i class="fas fa-circle text-success"></i>
                                                <span class="stock-text"><?php echo $pizza['current_stock']; ?> available</span>
                                            <?php else: ?>
                                                <i class="fas fa-circle text-danger"></i>
                                                <span class="stock-text text-danger">Out of stock</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="pizza-footer">
                                        <span class="pizza-price"><?php echo formatPrice($pizza['price']); ?></span>

                                        <?php if (isLoggedIn()): ?>
                                            <?php if ($pizza['current_stock'] > 0): ?>
                                                <form method="POST" action="cart.php" style="display: inline;">
                                                    <input type="hidden" name="pizza_id" value="<?php echo $pizza['id']; ?>">
                                                    <input type="hidden" name="action" value="add">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <button type="submit" class="btn btn-sm">
                                                        <i class="fas fa-cart-plus"></i> Add
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm" disabled>
                                                    <i class="fas fa-times"></i> Sold Out
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-sm <?php echo $pizza['current_stock'] <= 0 ? 'disabled' : ''; ?>">
                                                <?php echo $pizza['current_stock'] <= 0 ? 'Sold Out' : 'Order'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($pizzas)): ?>
            <div class="text-center" style="padding: var(--spacing-xl);">
                <i class="fas fa-pizza-slice" style="font-size: 4rem; opacity: 0.3;"></i>
                <p class="text-muted" style="margin-top: var(--spacing-md);">No pizzas available at the moment.</p>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    let currentCategory = 'all';
    let currentSearch = '';

    function filterCategory(event, category) {
        event.preventDefault();
        const tabs = document.querySelectorAll('.filter-tab');

        tabs.forEach(tab => tab.classList.remove('active'));
        event.currentTarget.classList.add('active');

        currentCategory = category;
        applyFilters();
    }

    function searchPizzas(searchTerm) {
        currentSearch = searchTerm.toLowerCase();
        applyFilters();
    }

    function applyFilters() {
        const sections = document.querySelectorAll('.category-section');
        let hasVisiblePizzas = false;

        sections.forEach(section => {
            const sectionCategory = section.getAttribute('data-category');
            const pizzaCards = section.querySelectorAll('.pizza-card');
            let sectionHasVisiblePizzas = false;

            const showSection = currentCategory === 'all' || sectionCategory === currentCategory;

            if (!showSection) {
                section.style.display = 'none';
                return;
            }

            pizzaCards.forEach(card => {
                const pizzaTitle = card.querySelector('.pizza-title').textContent.toLowerCase();
                const pizzaDescription = card.querySelector('.pizza-description').textContent.toLowerCase();

                const matchesSearch = currentSearch === '' ||
                    pizzaTitle.includes(currentSearch) ||
                    pizzaDescription.includes(currentSearch);

                if (matchesSearch) {
                    card.style.display = 'block';
                    sectionHasVisiblePizzas = true;
                    hasVisiblePizzas = true;
                } else {
                    card.style.display = 'none';
                }
            });

            section.style.display = sectionHasVisiblePizzas ? 'block' : 'none';
        });

        updateNoResultsMessage(!hasVisiblePizzas && (currentSearch !== '' || currentCategory !== 'all'));
    }

    function updateNoResultsMessage(show) {
        let noResultsMsg = document.getElementById('no-results-message');

        if (show && !noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'no-results-message';
            noResultsMsg.className = 'text-center';
            noResultsMsg.style.padding = 'var(--spacing-xl)';
            noResultsMsg.innerHTML = `
            <i class="fas fa-search" style="font-size: 3rem; opacity: 0.3; color: var(--gray-lighter);"></i>
            <p style="margin-top: var(--spacing-md); color: var(--gray-lighter);">No pizzas found matching your search.</p>
        `;
            document.getElementById('menu-container').appendChild(noResultsMsg);
        } else if (!show && noResultsMsg) {
            noResultsMsg.remove();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('pizzaSearch');
        const clearButton = document.getElementById('clearSearch');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value;
            searchPizzas(searchTerm);

            if (searchTerm.length > 0) {
                clearButton.classList.add('show');
            } else {
                clearButton.classList.remove('show');
            }
        });

        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.focus();
            this.classList.remove('show');
            searchPizzas('');
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                clearButton.classList.remove('show');
                searchPizzas('');
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>