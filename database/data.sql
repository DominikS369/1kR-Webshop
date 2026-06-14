-- Webshop_1kR – Schema und Testdaten

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

-- PW: tausendrosen

INSERT INTO users (id, salutation, firstname, lastname, address, zip, city, email, username, password, payment_info, is_admin, is_active) VALUES
(1, 'Herr', 'Dominik', 'Sommer', 'Spechtgasse 72', '2340', 'Mödling', 'wi24b118@technikum-wien.at', 'Dom', '$2y$10$23MfFcgEKBosPD2sh6rqJ.Pqa.YMr/2nQE8cnuqfFNsYWnvr9TODG', '', 0, 1),
(2, 'Herr', 'Mika', 'Stermann', 'Hochstädtplatz 4', '1220', 'Wien', 'mika@tausendrosen.at', 'Mika', '$2y$10$OL44P5vs4oA5NZKSMwKjx.FJb0ZlxEtDaxeaaSVCLNC3ZbgOIPcoC', '', 0, 1),
(3, 'Frau', 'Aylin', 'Karacsonyi', 'Dresdner Straße 9', '1220', 'Wien', 'aylin@tausendrosen.at', 'Aylin', '$2y$10$7WFRpme/j0FyTEZ8GSaRcOrc714l1Ueeon1CQddyphuVLWrryYXoC', '', 0, 1),
(4, 'Herr', 'Helmuth', 'Lammer', 'Jägerstraße 5', '1220', 'Wien', 'lammer@tausendrosen.at', 'Admin', '$2y$10$iBnQNS5oo03uiCB3slRGhe6IBsZ3OeffDFURoJd3lam4L3wiHZh1S', '', 1, 1);


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

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    zip VARCHAR(10) NOT NULL,
    city VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'offen',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS user_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    method VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY uniq_user_method (user_id, method)
);

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    UNIQUE KEY uniq_order_invoice (order_id)
);

INSERT INTO categories (name) VALUES
('Tonträger'),('Shirts'), ('Hoodies'), ('Caps'), ('Accessoires');

INSERT INTO products (category_id, name, description, price, rating, image) VALUES
(2, 'T-Shirt "Tausend Rosen" schwarz', 'Schwarzes Shirt mit Logo-Print vorne.', 24.90, 4.5, 'Tausend Rosen Basic Tee.jpeg'),
(2, 'T-Shirt "Tausend Rosen" weiß', 'Schwarzes Shirt mit Logo-Print vorne.', 24.90, 4.5, 'Tausend Rosen Basic Tee Weiss.jpeg'),
(2, 'T-Shirt "Rosen Tour 2025" schwarz limited', 'Limitiertes Tour-Shirt, schwarz mit Tour-Print vorne.', 29.90, 4.8, 'Rosen Tour Shirt 2025.jpeg'),
(2, 'T-Shirt "Tausend Rosen" vintage', 'Washed-Look, Unisex-Schnitt.', 27.50, 4.2, 'Vintage Logo Tee.jpeg'),
(3, 'Classic Hoodie "Tausend Rosen" schwarz', 'Baumwoll-Hoodie mit gesticktem Logo.', 59.90, 4.7, 'Classic Hoodie schwarz.jpeg'),
(3, 'Zip-Hoodie "Tausend Rosen" grau', 'Baumwoll-Hoodie Mit Reißverschluss, weicher Innenstoff.', 64.90, 4.4, 'Zip-Hoodie grau.jpeg'),
(3, 'Oversize Hoodie "Tausend Rosen" schwarz', 'Boxy Fit, in Sand.', 69.00, 4.6, 'Oversize Hoodie.jpeg'),
(4, 'Snapback Cap "Tausend Rosen" schwarz', 'Verstellbare Snapback mit Stick-Logo.', 19.90, 4.3, 'snapback Cap schwarz.jpeg'),
(4, 'Dad Cap "Tausend Rosen" Beige', 'Curved Brim, dezentes Logo.', 18.50, 4.0, 'Dad Cap Beige.jpeg'),
(4, 'Beanie "Tausend Rosen" Winter', 'Warm gefüttert, einheitsgröße.', 16.90, 4.1, 'Beanie Winter.jpeg'),
(5, 'Tote Bag "Tausend Rosen"', 'Stoffbeutel mit Print.', 12.90, 3.9, 'Tote Bag Canvas.jpeg'),
(5, 'Sticker-Set "Tausend Rosen"', '5 Aufkleber, glänzend.', 4.90, 4.5, 'Sticker-Set.jpeg'),
(5, 'Tasse "Tausend Rosen"', 'Keramik, 330ml.', 11.90, 4.2, 'Tasse Logo.jpeg'),
(1, 'CD Album "Das kleine Schwarze"', 'Das kleine Schwarze ist das Debütalbum der österreichischen Band Tausend Rosen aus Wien, das im November 2022 erschienen ist. Die fünfköpfige Band spielt eine sehr eigenständige, deutschsprachige Rock- und Popmusik, die live im Studio eingespielt wurde und sich nicht in gängige Schubladen stecken lässt.', 15.99, 5.0, 'product_25_1781274834.png');


CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    expires_at DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_used TINYINT(1) DEFAULT 0
);


INSERT INTO coupons (code, discount_type, discount_value, expires_at) VALUES
('SOMMER10', 'fixed', 10.00, '2026-12-31'),
('WELCOME5', 'fixed', 5.00, '2026-12-31'),
('SAVE20', 'percentage', 20.00, '2026-12-31');




