<?php
/**
 * Скачивание резервной копии
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

// Получаем имя файла
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

// Проверяем, что имя файла задано и не содержит путей
if (empty($fileName) || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid file name';
    exit;
}

// Путь к директории с бэкапами
$backupDir = dirname(__FILE__, 2) . '/backups';

// Полный путь к файлу
$filePath = $backupDir . '/' . $fileName;

// Проверяем существование файла
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found';
    exit;
}

// Проверяем, что файл находится в директории с бэкапами
$realBackupDir = realpath($backupDir);
$realFilePath = realpath($filePath);

if (strpos($realFilePath, $realBackupDir) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

// Логируем скачивание
$security->logActivity($_SESSION['user_id'], 'download_backup', 'backup', null);

// Отправляем файл для скачивания
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;