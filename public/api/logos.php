<?php
/**
 * API для получения логотипов проектов
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';

// Устанавливаем заголовок для возврата JSON
header('Content-Type: application/json');

// Разрешаем доступ только с текущего домена
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET');

// Защита от CSRF для API вызовов
$requestHeaders = apache_request_headers();
$referer = $requestHeaders['Referer'] ?? '';

// Проверяем, что запрос пришел с нашего сайта
$allowedReferer = parse_url(SITE_URL, PHP_URL_HOST);
$requestReferer = parse_url($referer, PHP_URL_HOST);

if (DEV_MODE === false && $requestReferer !== $allowedReferer) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Получаем параметры запроса
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 35;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'position';

// Ограничиваем максимальное количество записей для предотвращения DoS
if ($limit > 100) {
    $limit = 100;
}

// Проверяем правильность параметра сортировки
$allowedSortFields = ['position', 'created_at', 'name'];
if (!in_array($sort, $allowedSortFields)) {
    $sort = 'position';
}

// Формируем и выполняем запрос
try {
    // Запрос для получения логотипов
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.website,
            p.logo_path,
            p.position,
            p.created_at,
            COALESCE(AVG(r.rating), 0) as average_rating,
            COUNT(r.id) as review_count
        FROM 
            projects p
        LEFT JOIN 
            reviews r ON p.id = r.project_id AND r.approved = 1
        WHERE 
            p.active = 1
        GROUP BY 
            p.id
        ORDER BY 
            {$sort} " . ($sort === 'created_at' ? 'DESC' : 'ASC') . "
        LIMIT 
            ?, ?
    ";
    
    $logos = $db->fetchAll($sql, [$offset, $limit]);
    
    // Подготавливаем данные для ответа, удаляя чувствительную информацию
    $result = [];
    foreach ($logos as $logo) {
        $result[] = [
            'id' => (int)$logo['id'],
            'name' => $logo['name'],
            'website' => $logo['website'],
            'logo_path' => $logo['logo_path'],
            'position' => (int)$logo['position'],
            'average_rating' => round((float)$logo['average_rating'], 1),
            'review_count' => (int)$logo['review_count']
        ];
    }
    
    // Формируем успешный ответ
    echo json_encode([
        'success' => true,
        'count' => count($result),
        'logos' => $result
    ]);
    
} catch (Exception $e) {
    // Логируем ошибку
    error_log('API Error: ' . $e->getMessage());
    
    // Возвращаем ошибку
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => DEV_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}