-- Создание базы данных
CREATE DATABASE IF NOT EXISTS cryptologowall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cryptologowall;

-- Таблица пользователей (администраторов)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'moderator') NOT NULL DEFAULT 'moderator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    login_attempts INT NOT NULL DEFAULT 0,
    reset_token VARCHAR(255) NULL,
    reset_expires TIMESTAMP NULL
) ENGINE=InnoDB;

-- Таблица компаний/проектов
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    website VARCHAR(255) NULL,
    description TEXT NULL,
    logo_path VARCHAR(255) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    telegram_username VARCHAR(100) NULL,
    payment_id VARCHAR(255) NULL UNIQUE,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date TIMESTAMP NULL
) ENGINE=InnoDB;

-- Таблица отзывов
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NULL,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_id VARCHAR(255) NULL UNIQUE,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    ip_address VARCHAR(45) NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Таблица платежей
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    type ENUM('logo', 'review') NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    entity_id INT NOT NULL,
    telegram_payment_charge_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- Таблица для переводов
CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lang_code VARCHAR(5) NOT NULL,
    translation_key VARCHAR(255) NOT NULL,
    translation_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY lang_key (lang_code, translation_key)
) ENGINE=InnoDB;

-- Таблица для логов действий
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Создаем индексы для оптимизации
CREATE INDEX idx_projects_active ON projects(active);
CREATE INDEX idx_reviews_project_id ON reviews(project_id);
CREATE INDEX idx_reviews_approved ON reviews(approved);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_translations_lang ON translations(lang_code);

-- Создаем админ-пользователя (пароль нужно будет изменить)
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$XmfbUa6qWF.qWVCUZeKJZO4nHCd5oC69X.9meDPQrdjcrQ8QcPsVW', 'admin@example.com', 'admin');
-- Пароль по умолчанию: Admin123! (хешированный)

-- Добавляем начальные языковые переводы
INSERT INTO translations (lang_code, translation_key, translation_value) VALUES
('en', 'site_name', 'CryptoLogoWall'),
('ru', 'site_name', 'КриптоЛогоСтена'),
('uk', 'site_name', 'КриптоЛогоСтіна'),
('en', 'add_logo', 'Add Your Logo for $1'),
('ru', 'add_logo', 'Добавить логотип за $1'),
('uk', 'add_logo', 'Додати логотип за $1');