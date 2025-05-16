<?php
/**
 * Удаление резервной копии
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';

// Проверяем авторизацию и права администратора
if (!$security->isAdmin() || !$security->hasAdminRights()) {
    // Если не админ, перенаправляем на главную страницу админки
    header('Location: index.php');
    exit;
}

// Проверяем CSRF-токен
if (!isset($_GET['csrf_token']) || !$security->validateCSRFToken($_GET['csrf_token'])) {
    header('Location: settings.php?section=backup&error=security');
    exit;
}

// Получаем имя файла
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

// Проверяем, что имя файла задано и не содержит путей
if (empty($fileName) || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
    header('Location: settings.php?section=backup&error=invalid_file');
    exit;
}

// Путь к директории с бэкапами
$backupDir = dirname(__FILE__, 2) . '/backups';

// Полный путь к файлу
$filePath = $backupDir . '/' . $fileName;

// Проверяем существование файла
if (!file_exists($filePath)) {
    header('Location: settings.php?section=backup&error=file_not_found');
    exit;
}

// Проверяем, что файл находится в директории с бэкапами
$realBackupDir = realpath($backupDir);
$realFilePath = realpath($filePath);

if (strpos($realFilePath, $realBackupDir) !== 0) {
    header('Location: settings.php?section=backup&error=access_denied');
    exit;
}

// Пытаемся удалить файл
if (unlink($filePath)) {
    // Логируем удаление
    $security->logActivity($_SESSION['user_id'], 'delete_backup', 'backup', null);
    
    // Перенаправляем с сообщением об успехе
    header('Location: settings.php?section=backup&success=deleted');
} else {
    // Перенаправляем с сообщением об ошибке
    header('Location: settings.php?section=backup&error=delete_failed');
}
exit;