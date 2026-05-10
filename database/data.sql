-- Webshop_1kR – Schema und Testdaten
-- Sprint 2 (Produkte, Kategorien, Cart wird in #29 ergänzt)

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salutation VARCHAR(20),
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    zip VARCHAR(10) NOT NULL,
    city VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    payment_info TEXT,
    is_admin TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(8,2) NOT NULL,
    rating DECIMAL(2,1) DEFAULT 0,
    image VARCHAR(255) DEFAULT 'placeholder.jpg',
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO categories (name) VALUES
('Shirts'), ('Hoodies'), ('Caps'), ('Accessoires');

INSERT INTO products (category_id, name, description, price, rating, image) VALUES
(1, 'Tausend Rosen Basic Tee', 'Schwarzes Shirt mit Logo-Print vorne.', 24.90, 4.5, 'produkt1.jpg'),
(1, 'Rosen Tour Shirt 2025', 'Limitiertes Tour-Shirt, weiß mit Backprint.', 29.90, 4.8, 'produkt2.jpg'),
(1, 'Vintage Logo Tee', 'Washed-Look, Unisex-Schnitt.', 27.50, 4.2, 'produkt3.jpg'),
(2, 'Classic Hoodie schwarz', 'Schwerer Baumwoll-Hoodie mit gesticktem Logo.', 59.90, 4.7, 'placeholder.jpg'),
(2, 'Zip-Hoodie grau', 'Mit Reißverschluss, weicher Innenstoff.', 64.90, 4.4, 'placeholder.jpg'),
(2, 'Oversize Hoodie', 'Boxy Fit, in Sand.', 69.00, 4.6, 'placeholder.jpg'),
(3, 'Snapback Cap schwarz', 'Verstellbare Snapback mit Stick-Logo.', 19.90, 4.3, 'placeholder.jpg'),
(3, 'Dad Cap Beige', 'Curved Brim, dezentes Logo.', 18.50, 4.0, 'placeholder.jpg'),
(3, 'Beanie Winter', 'Warm gefüttert, einheitsgröße.', 16.90, 4.1, 'placeholder.jpg'),
(4, 'Tote Bag Canvas', 'Stoffbeutel mit Print.', 12.90, 3.9, 'placeholder.jpg'),
(4, 'Sticker-Set', '5 Aufkleber, glänzend.', 4.90, 4.5, 'placeholder.jpg'),
(4, 'Tasse Logo', 'Keramik, 330ml.', 11.90, 4.2, 'placeholder.jpg');
