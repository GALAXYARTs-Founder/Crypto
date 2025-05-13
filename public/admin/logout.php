<?php
/**
 * Административная панель - выход из системы
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';

// Если пользователь авторизован, логируем выход
if (isset($_SESSION['user_id'])) {
    $security->logActivity($_SESSION['user_id'], 'logout');
}

// Удаляем данные сессии
$_SESSION = array();

// Удаляем куки сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Уничтожаем сессию
session_destroy();

// Перенаправляем на страницу входа
header("Location: login.php");
exit;