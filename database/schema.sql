CREATE DATABASE IF NOT EXISTS alyrabeauty_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE alyrabeauty_inventory;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  sku VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  minimum_stock INT NOT NULL DEFAULT 5,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_products_price CHECK (price >= 0),
  CONSTRAINT chk_products_stock CHECK (stock >= 0),
  CONSTRAINT chk_products_minimum_stock CHECK (minimum_stock >= 0)
) ENGINE=InnoDB;

CREATE TABLE product_suppliers (
  product_id INT NOT NULL,
  supplier_id INT NOT NULL,
  supplier_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, supplier_id),
  CONSTRAINT fk_ps_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ps_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_supplier_price CHECK (supplier_price >= 0)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('IN','OUT') NOT NULL,
  quantity INT NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_sm_quantity CHECK (quantity > 0),
  INDEX idx_sm_created_at (created_at),
  INDEX idx_sm_product_type (product_id, type)
) ENGINE=InnoDB;

-- Demo login: admin / password: password
-- Ganti password setelah login pertama. Hash ini dibuat untuk password "password".
INSERT INTO users (username, password_hash, full_name, role)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

INSERT INTO user_profiles (user_id, phone, address)
SELECT id, NULL, 'Lhokseumawe' FROM users WHERE username = 'admin';

INSERT INTO categories (name, description) VALUES
('Perawatan Wajah', 'Produk skincare untuk perawatan wajah'),
('Makeup', 'Produk rias wajah'),
('Perawatan Tubuh', 'Produk perawatan kulit dan tubuh');

INSERT INTO suppliers (name, phone, email, address) VALUES
('Cantik Bersama Supplier', '081234567890', 'supplier@alyrabeauty.test', 'Lhokseumawe'),
('Nusa Kosmetik Distribusi', '081298765432', 'distribusi@alyrabeauty.test', 'Aceh Utara');

INSERT INTO products (category_id, sku, name, price, stock, minimum_stock) VALUES
(1, 'SKN-001', 'Facial Wash Aloe Vera', 35000, 24, 5),
(1, 'SKN-002', 'Moisturizer Daily Glow', 58000, 15, 5),
(2, 'MUP-001', 'Lip Cream Velvet', 42000, 30, 8),
(3, 'BDY-001', 'Body Lotion Soft Care', 39000, 18, 5);

INSERT INTO product_suppliers (product_id, supplier_id, supplier_price) VALUES
(1,1,24000),(2,1,42000),(3,2,28000),(4,2,26000),(1,2,25000);
