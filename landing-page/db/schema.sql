-- นำเข้าไฟล์นี้ผ่าน phpMyAdmin (แท็บ Import) หลังจากสร้างฐานข้อมูลใน cPanel แล้ว

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(120) DEFAULT NULL,
    model VARCHAR(120) DEFAULT NULL,
    price DECIMAL(12,2) DEFAULT NULL,
    condition_text VARCHAR(120) DEFAULT NULL,
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('available','sold','hidden') NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
