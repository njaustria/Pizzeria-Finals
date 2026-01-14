DROP DATABASE IF EXISTS pizzeria_db;
CREATE DATABASE pizzeria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pizzeria_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_default_address BOOLEAN DEFAULT 0,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

CREATE TABLE pizzas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    category VARCHAR(50),
    availability BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_availability (availability),
    INDEX idx_category (category)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'preparing', 'out_for_delivery', 'completed', 'cancelled') DEFAULT 'pending',
    delivery_address TEXT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    payment_method ENUM('cash_on_delivery', 'paypal') DEFAULT 'cash_on_delivery',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    pizza_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (pizza_id) REFERENCES pizzas(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_pizza_id (pizza_id)
) ENGINE=InnoDB;

CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    reply_message TEXT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

INSERT INTO users (name, email, password_hash, role) VALUES
('Admin', 'admin@pizzeria.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');


INSERT INTO pizzas (name, description, price, image, category, availability) VALUES
('Margherita', 'Classic pizza with fresh tomatoes, mozzarella, and basil', 299.00, 'margherita.jpg', 'Classic', 1),
('Pepperoni', 'Loaded with pepperoni slices and extra cheese', 349.00, 'pepperoni.jpg', 'Classic', 1),
('Hawaiian', 'Ham and pineapple with mozzarella cheese', 329.00, 'hawaiian.jpg', 'Classic', 1),
('Meat Lovers', 'Loaded with pepperoni, sausage, bacon, and ham', 399.00, 'meat-lovers.jpg', 'Premium', 1),
('Vegetarian', 'Fresh vegetables with bell peppers, mushrooms, and olives', 319.00, 'vegetarian.jpg', 'Vegetarian', 1),
('BBQ Chicken', 'Grilled chicken with BBQ sauce and red onions', 379.00, 'bbq-chicken.jpg', 'Premium', 1),
('Four Cheese', 'Mozzarella, parmesan, gorgonzola, and ricotta', 369.00, 'four-cheese.jpg', 'Premium', 1),
('Supreme', 'Loaded with everything - meat and veggies', 389.00, 'supreme.jpg', 'Premium', 1);
