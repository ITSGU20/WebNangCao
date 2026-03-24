-- ============================================================
-- LA FLEUR PATISSERIE - DATABASE SCHEMA
-- Chay file nay trong phpMyAdmin hoac MySQL CLI
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS lafleur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lafleur;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  emoji VARCHAR(10) DEFAULT '',
  description TEXT,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- cost_price = gia nhap binh quan, profit_rate = ty le loi nhuan %
-- price = cost_price * (1 + profit_rate/100) [tinh tu dong khi nhap hang]
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cat_id INT,
  code VARCHAR(50),
  name VARCHAR(200) NOT NULL,
  emoji VARCHAR(10) DEFAULT '',
  unit VARCHAR(50) DEFAULT 'cai',
  cost_price DECIMAL(15,2) DEFAULT 0,
  profit_rate DECIMAL(5,2) DEFAULT 0,
  price DECIMAL(15,2) DEFAULT 0,
  stock INT DEFAULT 0,
  description TEXT,
  image VARCHAR(255) DEFAULT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cat_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  phone VARCHAR(20),
  address TEXT,
  password VARCHAR(255) NOT NULL,
  role ENUM('customer','admin') DEFAULT 'customer',
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  user_name VARCHAR(100),
  phone VARCHAR(20),
  address TEXT,
  payment_method VARCHAR(50),
  status ENUM('new','processing','delivered','cancelled') DEFAULT 'new',
  total DECIMAL(15,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT,
  name VARCHAR(200),
  qty INT DEFAULT 1,
  price DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS imports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  status ENUM('pending','completed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS import_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  import_id INT NOT NULL,
  product_id INT,
  qty INT DEFAULT 0,
  cost_price DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lich su ton kho: qty_change duong=nhap vao, am=xuat ra
-- Dung de tra cuu ton kho tai thoi diem bat ky va bao cao nhap-xuat
CREATE TABLE IF NOT EXISTS stock_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT,
  type ENUM('import','sale','adjust','cancel') DEFAULT 'adjust',
  qty_change INT DEFAULT 0,
  stock_before INT DEFAULT 0,
  stock_after INT DEFAULT 0,
  note TEXT,
  ref_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Gio hang luu tren server theo user
CREATE TABLE IF NOT EXISTS cart (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT DEFAULT 1,
  UNIQUE KEY uq_cart (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cai dat he thong: nguong canh bao, thong tin ngan hang...
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(50) PRIMARY KEY,
  `value` VARCHAR(500) NOT NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DU LIEU MAU
-- ============================================================
INSERT INTO categories (id,name,emoji,description,active) VALUES
(1,'Banh kem','','Banh kem sinh nhat, tiec, gia dinh',1),
(2,'Banh mi ngot','','Croissant, brioche, banh mi hoa cuc',1),
(3,'Banh quy','','Banh quy bo, chocolate chip, hanh nhan',1),
(4,'Macaron','','Macaron Phap du mau sac huong vi',1),
(5,'Banh tart','','Tart trai cay, trung, socola',1);

INSERT INTO products (id,cat_id,code,name,emoji,unit,cost_price,profit_rate,price,stock,description,active) VALUES
(1,1,'BK001','Banh kem dau tay','','cai',90000,66.67,150000,15,'Banh kem tuoi phu dau tay ngot chua, kem vanilla mem min.',1),
(2,1,'BK002','Banh kem chocolate','','cai',110000,63.64,180000,12,'Banh kem chocolate Bi dam da, lop mousse muot ma.',1),
(3,1,'BK003','Banh kem matcha','','cai',100000,60.00,160000,8,'Banh kem matcha Nhat Ban thuong hang, vi tra xanh thanh mat.',1),
(4,1,'BK004','Banh kem tiramisu','','cai',120000,62.50,195000,10,'Banh kem tiramisu Y voi mascarpone beo ngay.',1),
(5,2,'BMN001','Croissant bo Phap','','cai',18000,94.44,35000,50,'Croissant bo Phap chinh goc, 72 lop bot xep tang.',1),
(6,2,'BMN002','Banh mi hoa cuc','','cai',25000,80.00,45000,30,'Banh mi hoa cuc kieu Phap - Brioche, bo thom.',1),
(7,3,'BQ001','Banh quy bo hop','','hop',65000,84.62,120000,40,'Hop banh quy bo Denmark cao cap 300g.',1),
(8,3,'BQ002','Banh quy chocolate chip','','hop',70000,85.71,130000,35,'Banh quy chocolate chip My dac biet.',1),
(9,4,'MC001','Macaron huong dau','','cai',13000,92.31,25000,60,'Macaron vo hong huong dau tay, nhan buttercream dau min.',1),
(10,4,'MC002','Macaron vanilla','','cai',13000,92.31,25000,55,'Macaron vanilla Madagascar thuan khiet.',1),
(11,4,'MC003','Hop macaron 12 cai','','hop',145000,79.31,260000,20,'Hop macaron mix 12 huong vi dac biet.',1),
(12,5,'BT001','Tart trai cay tuoi','','cai',30000,83.33,55000,25,'Banh tart vo gion nhan kem patisserie min.',1),
(13,5,'BT002','Tart trung Bo Dao Nha','','cai',15000,100.00,30000,45,'Pastel de nata - Tart trung Bo Dao Nha chuan goc.',1);

INSERT INTO users (id,name,email,phone,address,password,role,active) VALUES
(1,'Nguyen Thi Lan','lan.nguyen@email.com','0901234567','123 Le Loi, P.Ben Nghe, Q1, TP.HCM','123456','customer',1),
(2,'Tran Van Minh','minh.tran@email.com','0912345678','456 Nguyen Hue, P.Ben Nghe, Q1, TP.HCM','123456','customer',1),
(3,'Pham Thi Thu','thu.pham@email.com','0923456789','789 Dien Bien Phu, P.7, Q3, TP.HCM','123456','customer',0),
(4,'Admin','admin@lafleur.com','0900000000','','admin123','admin',1);

INSERT INTO orders (id,user_id,user_name,phone,address,payment_method,status,total,created_at) VALUES
(1,1,'Nguyen Thi Lan','0901234567','123 Le Loi, Q1, TP.HCM','Tien mat','delivered',250000,'2024-11-10 09:00:00'),
(2,2,'Tran Van Minh','0912345678','456 Nguyen Hue, Q1, TP.HCM','Chuyen khoan','processing',180000,'2024-11-15 10:30:00'),
(3,1,'Nguyen Thi Lan','0901234567','123 Le Loi, Q1, TP.HCM','Tien mat','new',240000,'2024-11-20 14:00:00');

INSERT INTO order_items (order_id,product_id,name,qty,price) VALUES
(1,1,'Banh kem dau tay',1,150000),(1,9,'Macaron huong dau',4,25000),
(2,2,'Banh kem chocolate',1,180000),
(3,7,'Banh quy bo hop',2,120000);

INSERT INTO imports (id,date,status,created_at) VALUES
(1,'2024-11-01','completed','2024-11-01 08:00:00'),
(2,'2024-11-10','pending','2024-11-10 09:00:00');

INSERT INTO import_items (import_id,product_id,qty,cost_price) VALUES
(1,1,20,90000),(1,2,15,110000),(2,5,100,18000),(2,6,60,25000);

INSERT INTO stock_history (product_id,type,qty_change,stock_before,stock_after,note,ref_id,created_at) VALUES
(1,'import',20,0,20,'Phieu nhap #1',1,'2024-11-01 08:00:00'),
(2,'import',15,0,15,'Phieu nhap #1',1,'2024-11-01 08:00:00'),
(1,'sale',-1,20,19,'Don hang #1',1,'2024-11-10 09:00:00'),
(9,'sale',-4,60,56,'Don hang #1',1,'2024-11-10 09:00:00'),
(2,'sale',-1,15,14,'Don hang #2',2,'2024-11-15 10:30:00'),
(7,'sale',-2,40,38,'Don hang #3',3,'2024-11-20 14:00:00');

INSERT INTO settings (`key`,`value`) VALUES
('low_stock_threshold','10'),
('bank_name','Vietcombank'),
('bank_account','1234567890'),
('bank_owner','La Fleur Patisserie');
