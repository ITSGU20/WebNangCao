-- ============================================================
-- LA FLEUR PÂTISSERIE - DATABASE SCHEMA
-- Web2: PHP + MySQL
-- ============================================================
CREATE DATABASE IF NOT EXISTS lafleur;
USE lafleur;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. USERS - tài khoản khách hàng + admin
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    phone       VARCHAR(15)   NOT NULL,
    password    VARCHAR(255)  NOT NULL,          -- password_hash()
    address     VARCHAR(255)  DEFAULT '',
    ward        VARCHAR(100)  DEFAULT '',          -- phường/xã/thị trấn
    city        VARCHAR(100)  DEFAULT 'TP.HCM',
    role        ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. CATEGORIES - loại sản phẩm
-- ============================================================
CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    emoji       VARCHAR(10)  DEFAULT '',
    description TEXT,
    is_active   TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. PRODUCTS - sản phẩm
-- ============================================================
CREATE TABLE products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED NOT NULL,
    code            VARCHAR(50)  NOT NULL UNIQUE,
    name            VARCHAR(200) NOT NULL,
    emoji           VARCHAR(10)  DEFAULT '',
    description     TEXT,
    image_path      VARCHAR(300) DEFAULT '',      -- relative path: uploads/products/xxx.jpg
    unit            VARCHAR(30)  DEFAULT 'cái',
    -- Giá bán = cost_price * (1 + profit_rate/100)
    cost_price      DECIMAL(12,2) NOT NULL DEFAULT 0,   -- giá nhập bình quân (tự cập nhật khi nhập hàng)
    profit_rate     DECIMAL(5,2)  NOT NULL DEFAULT 0,   -- % lợi nhuận mong muốn
    -- stock: số lượng tồn kho hiện tại (tính từ nhập - xuất)
    stock           INT          NOT NULL DEFAULT 0,
    is_active       TINYINT(1)  NOT NULL DEFAULT 1,     -- 0 = ẩn, 1 = đang bán
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. IMPORT_RECEIPTS - phiếu nhập hàng
-- ============================================================
CREATE TABLE import_receipts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_date DATE         NOT NULL,
    note        TEXT,
    status      ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    created_by  INT UNSIGNED,                         -- admin user id
    created_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. IMPORT_ITEMS - chi tiết phiếu nhập (1 phiếu nhiều SP)
-- ============================================================
CREATE TABLE import_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id      INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    quantity        INT          NOT NULL,
    import_price    DECIMAL(12,2) NOT NULL,          -- giá nhập lần này
    FOREIGN KEY (receipt_id) REFERENCES import_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. ORDERS - đơn đặt hàng
-- ============================================================
CREATE TABLE orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    recv_name       VARCHAR(100) NOT NULL,
    recv_phone      VARCHAR(15)  NOT NULL,
    recv_address    VARCHAR(255) NOT NULL,
    recv_district   VARCHAR(100) NOT NULL,
    recv_city       VARCHAR(100) NOT NULL DEFAULT 'TP.HCM',
    note            TEXT,
    payment_method  ENUM('cash','transfer','online') NOT NULL DEFAULT 'cash',
    total_amount    DECIMAL(14,2) NOT NULL DEFAULT 0,
    status          ENUM('new','processing','delivered','cancelled') NOT NULL DEFAULT 'new',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. ORDER_ITEMS - chi tiết đơn hàng
-- ============================================================
CREATE TABLE order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    product_name VARCHAR(200) NOT NULL,             -- snapshot tên tại thời điểm mua
    quantity    INT           NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,             -- giá bán tại thời điểm mua
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin account (password: admin123)
INSERT INTO users (name, email, phone, password, role) VALUES
('Admin', 'admin@lafleur.com', '0900000000',
 '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin');

-- Categories
INSERT INTO categories (name, emoji, description) VALUES
('Bánh kem',     '🎂', 'Bánh kem sinh nhật, tiệc, gia đình'),
('Bánh mì ngọt', '🥐', 'Croissant, brioche, bánh mì hoa cúc'),
('Bánh quy',     '🍪', 'Bánh quy bơ, chocolate chip, hạnh nhân'),
('Macaron',      '🎨', 'Macaron Pháp đủ màu sắc hương vị'),
('Bánh tart',    '🥧', 'Tart trái cây, trứng, socola');

-- Products
-- stock mặc định = 0, cập nhật qua phiếu nhập hàng (import.php)
INSERT INTO products (category_id, code, name, emoji, description, cost_price, profit_rate) VALUES
(1,'BK001','Bánh kem dâu tây',   '🍓','Bánh kem tươi phủ dâu tây ngọt chua, kem vanilla mềm mịn.', 90000, 66.67),
(1,'BK002','Bánh kem chocolate', '🍫','Bánh kem chocolate Bỉ đậm đà, lớp mousse mượt mà.',        110000, 63.64),
(1,'BK003','Bánh kem matcha',    '🍵','Bánh kem matcha Nhật Bản thượng hạng, vị trà xanh.',        100000, 60.00),
(1,'BK004','Bánh kem tiramisu',  '☕','Bánh kem tiramisu Ý với mascarpone béo ngậy.',               120000, 62.50),
(2,'BMN001','Croissant bơ Pháp','🥐','Croissant bơ Pháp chính gốc, 72 lớp bột xếp tầng.',         18000, 94.44),
(2,'BMN002','Bánh mì hoa cúc',  '🍞','Bánh mì hoa cúc kiểu Pháp - Brioche, bơ thơm.',             25000, 80.00),
(3,'BQ001','Bánh quy bơ hộp',   '🍪','Hộp bánh quy bơ Denmark cao cấp 300g.',                     65000, 84.62),
(3,'BQ002','Bánh quy choco chip','🫐','Bánh quy chocolate chip Mỹ đặc biệt, chocolate 70%.',       70000, 85.71),
(4,'MC001','Macaron hương dâu', '🩷','Macaron vỏ hồng đậu hương dâu tây, nhân buttercream.',       13000, 92.31),
(4,'MC002','Macaron vanilla',   '🤍','Macaron vanilla Madagascar thuần khiết.',                     13000, 92.31),
(4,'MC003','Hộp macaron 12 cái','🎁','Hộp macaron mix 12 hương vị đặc biệt.',                     145000, 79.31),
(5,'BT001','Tart trái cây tươi','🍇','Bánh tart vỏ giòn nhân kem patisserie mịn.',                 30000, 83.33),
(5,'BT002','Tart trứng Bồ Đào Nha','🥚','Pastel de nata - Tart trứng chuẩn gốc.',                 15000,100.00);

-- Sample customer accounts (password: 123456)
INSERT INTO users (name, email, phone, password, address, ward, city, role) VALUES
('Nguyễn Thị Lan',  'lan.nguyen@email.com',  '0901234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123 Lê Lợi', 'Phường Bến Thành', 'TP.HCM', 'customer'),
('Trần Văn Minh',   'minh.tran@email.com',   '0912345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '456 Nguyễn Huệ', 'Phường Bến Nghé', 'TP.HCM', 'customer');
-- NOTE: hash trên = password_hash('password') — đây chỉ là tài khoản demo
-- Khách hàng thật đăng ký qua trang register.php

-- ============================================================
-- MIGRATION: đổi tên cột district → ward trong bảng users
-- Chạy lệnh này nếu database đã tồn tại từ phiên bản cũ:
-- ============================================================
-- ALTER TABLE users CHANGE district ward VARCHAR(100) DEFAULT '';

-- ============================================================
-- MIGRATION: bỏ chức năng điều chỉnh tồn kho thủ công
-- Cột stock vẫn giữ nguyên, KHÔNG xóa.
-- stock chỉ được cập nhật tự động qua 2 luồng:
--   + Tăng: khi hoàn thành phiếu nhập (import.php → status=completed)
--   + Giảm: khi đặt hàng (checkout.php) hoặc hủy đơn (admin_orders.php → cancel)
-- Tồn kho tại ngày bất kỳ tính bằng: SUM(nhập đến ngày đó) - SUM(bán đến ngày đó)
-- Không cần ALTER nào thêm cho migration này.
-- ============================================================
