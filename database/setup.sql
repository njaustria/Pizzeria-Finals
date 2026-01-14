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
    is_default_address TINYINT(1) DEFAULT 0,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_email (email),
    INDEX idx_user_role (role)
) ENGINE=InnoDB;

CREATE TABLE pizzas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    category VARCHAR(50),
    availability TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pizza_availability (availability),
    INDEX idx_pizza_category (category)
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
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_order_status (status),
    INDEX idx_order_payment (payment_status)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    pizza_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_pizza FOREIGN KEY (pizza_id) REFERENCES pizzas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject_type ENUM('Concern and Feedback', 'Refund Payment') DEFAULT 'Concern and Feedback',
    message TEXT NOT NULL,
    reply_message TEXT DEFAULT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_status (status)
) ENGINE=InnoDB;

CREATE TABLE pizza_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pizza_id INT NOT NULL,
    stock_date DATE NOT NULL,
    current_stock INT NOT NULL DEFAULT 10,
    initial_stock INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pizza_id) REFERENCES pizzas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pizza_date (pizza_id, stock_date),
    INDEX idx_stock_date (stock_date),
    INDEX idx_pizza_id (pizza_id)
) ENGINE=InnoDB;

CREATE TABLE received_sms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    admin_reply TEXT NULL,
    replied_at TIMESTAMP NULL,
    replied_by INT NULL,
    customer_id INT NULL,
    message_type ENUM('inquiry', 'complaint', 'order_query', 'general', 'auto_reply') DEFAULT 'general',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    CONSTRAINT fk_received_sms_customer_id FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_received_sms_replied_by FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_sender_phone (sender_phone),
    INDEX idx_received_at (received_at),
    INDEX idx_is_read (is_read),
    INDEX idx_status (status),
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    gateway_response TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    order_id INT NULL,
    sms_type ENUM('order_confirmation', 'order_status', 'welcome', 'custom') DEFAULT 'custom',
    CONSTRAINT fk_sms_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sms_order_id FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_phone (phone_number),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE TRIGGER tr_orders_before_insert
BEFORE INSERT ON orders
FOR EACH ROW 
BEGIN
    IF NEW.payment_method = 'paypal' THEN
        SET NEW.payment_status = 'paid';
    END IF;
END//
DELIMITER ;

DELIMITER //
CREATE PROCEDURE ResetDailyStock()
BEGIN
    INSERT INTO pizza_stock (pizza_id, stock_date, current_stock, initial_stock)
    SELECT id, CURDATE(), 10, 10 
    FROM pizzas 
    WHERE NOT EXISTS (
        SELECT 1 FROM pizza_stock 
        WHERE pizza_stock.pizza_id = pizzas.id 
        AND pizza_stock.stock_date = CURDATE()
    );
    
    DELETE FROM pizza_stock 
    WHERE stock_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY);
END //
DELIMITER ;

INSERT INTO users (name, email, password_hash, role) VALUES
('Administrator', 'pizzeriagroup5@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO pizzas (name, description, price, image, category, availability) VALUES
('Margherita', 'Classic Italian pizza with fresh tomatoes, mozzarella cheese, and basil leaves', 299.00, 'margherita.png', 'Classic', 1),
('Pepperoni', 'Loaded with generous amounts of pepperoni slices and extra mozzarella cheese', 349.00, 'pepperoni.png', 'Classic', 1),
('Hawaiian', 'Sweet and savory combination of ham and pineapple with mozzarella cheese', 329.00, 'hawaiian.png', 'Classic', 1),
('Meat Lovers', 'For carnivores! Loaded with pepperoni, sausage, bacon, and ham', 399.00, 'meat_lovers.png', 'Premium', 1),
('Vegetarian Supreme', 'Garden fresh vegetables including bell peppers, mushrooms, onions, and olives', 319.00, 'vegetarian_supreme.png', 'Vegetarian', 1),
('BBQ Chicken', 'Grilled chicken with tangy BBQ sauce, red onions, and cilantro', 379.00, 'bbq_chicken.png', 'Premium', 1),
('Four Cheese', 'Cheese lovers dream with mozzarella, parmesan, gorgonzola, and ricotta', 369.00, 'four_cheese.png', 'Premium', 1),
('Buffalo Chicken', 'Spicy buffalo sauce with grilled chicken, ranch dressing, and celery', 359.00, 'buffalo_chicken.png', 'Spicy', 1),
('Mediterranean', 'Feta cheese, olives, sun-dried tomatoes, and spinach', 339.00, 'mediterranean.png', 'Gourmet', 1),
('Mexican Fiesta', 'Seasoned beef, jalapeños, onions, tomatoes, and Mexican spices', 369.00, 'mexican_fiesta.png', 'Spicy', 1),
('Truffle Mushroom', 'Premium truffle oil with assorted mushrooms and parmesan', 449.00, 'truffle_mushroom.png', 'Gourmet', 1),
('Garden Fresh', 'Light and healthy with tomatoes, spinach, arugula, and fresh mozzarella', 299.00, 'garden_fresh.png', 'Vegetarian', 1),
('Pesto Chicken', 'Basil pesto sauce with grilled chicken and cherry tomatoes', 359.00, 'pesto_chicken.png', 'Gourmet', 1),
('Spicy Sausage', 'A bold and savory pizza topped with spicy sausage', 399.00, 'spicy_sausage.png', 'Spicy', 1);

INSERT INTO pizza_stock (pizza_id, stock_date, current_stock, initial_stock)
SELECT id, CURDATE(), 10, 10 
FROM pizzas 
WHERE NOT EXISTS (
    SELECT 1 FROM pizza_stock 
    WHERE pizza_stock.pizza_id = pizzas.id 
    AND pizza_stock.stock_date = CURDATE()
);

SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS daily_stock_reset
ON SCHEDULE EVERY 1 DAY
STARTS DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 0 HOUR)
DO CALL ResetDailyStock();

SELECT 'Database created successfully!' as Status;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_pizzas FROM pizzas;