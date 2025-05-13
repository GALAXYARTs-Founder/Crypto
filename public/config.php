<?php
/**
 * Файл конфигурации
 * CryptoLogoWall
 */

// Режим разработки (true) или продакшн (false)
define('DEV_MODE', true);

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'cryptologowall');
define('DB_USER', 'root');     // Измените на реальные данные
define('DB_PASS', 'root');         // Измените на реальные данные
define('DB_CHARSET', 'utf8mb4');

// Настройки сайта
define('SITE_URL', 'http://localhost/cryptologowall');  // Измените на реальный URL
define('SITE_NAME', 'CryptoLogoWall');
define('ADMIN_EMAIL', 'admin@example.com');            // Измените на реальный email

// Безопасность
define('SECURE_AUTH_KEY', 'pQz8r4b1x3dN5fhT9vKl');    // Измените на случайную строку
define('AUTH_SALT', 'jW7zF6tY2xE8sG4qP9cB');          // Измените на случайную строку
define('LOGGED_IN_KEY', 'mV3bX9kL5pD1rF7hJ4tZ');      // Измените на случайную строку
define('LOGGED_IN_SALT', 'eA2cR8vK6lX3pM9nB5tF');     // Измените на случайную строку
define('SECURE_AUTH_SALT', 'qP2xC7vZ3kM8nL5bJ1dF');   // Измените на случайную строку

// Настройки загрузки файлов
define('MAX_LOGO_SIZE', 512 * 1024);                  // 512KB
define('ALLOWED_LOGO_TYPES', ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml']);
define('UPLOAD_DIR', dirname(__FILE__) . '/uploads/');

// Настройки языка
define('DEFAULT_LANG', 'en');
define('AVAILABLE_LANGUAGES', ['en', 'ru', 'uk']);

// Настройки Telegram Crypto Bot
define('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');  // Измените на реальный токен
define('TELEGRAM_BOT_USERNAME', 'CryptoBot');             // @CryptoBot - официальный бот для платежей

// Настройки API
define('API_RATE_LIMIT', 100);                           // Запросов в час
define('API_TOKEN_EXPIRE', 3600 * 24);                   // 24 часа

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if(!DEV_MODE) {
    ini_set('session.cookie_secure', 1);
}
session_name('cryptolw_session');

// Обработка ошибок
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__FILE__) . '/logs/error.log');
}

// Функция для генерации случайной строки
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Функция для очистки ввода
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}