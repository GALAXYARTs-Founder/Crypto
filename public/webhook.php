<?php
/**
 * Обработчик вебхуков Telegram Crypto Bot
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/telegramBot.php';

// Запрещаем выполнение скрипта в браузере
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

// Функция логирования для отладки
function webhookLog($message) {
    if (DEV_MODE) {
        error_log('[Telegram Webhook] ' . $message);
    }
}

// Получаем данные из запроса
$inputData = file_get_contents('php://input');
webhookLog('Received webhook data: ' . $inputData);

// Проверяем данные
if (empty($inputData)) {
    webhookLog('Empty webhook data');
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Проверяем секретный токен (безопасность)
$secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($secretToken !== SECURE_AUTH_KEY) {
    webhookLog('Invalid secret token');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Декодируем JSON
$data = json_decode($inputData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    webhookLog('Invalid JSON: ' . json_last_error_msg());
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Проверяем наличие нужных полей
if (!isset($data['update_type']) || !isset($data['payload'])) {
    webhookLog('Missing required fields');
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Обрабатываем платежи
if ($data['update_type'] === 'invoice_paid') {
    try {
        // Получаем ID платежа
        $paymentId = $data['payload'];
        webhookLog('Processing payment: ' . $paymentId);
        
        // Получаем информацию о платеже из БД
        $payment = $db->fetchOne(
            "SELECT * FROM payments WHERE payment_id = ?", 
            [$paymentId]
        );
        
        if (!$payment) {
            webhookLog('Payment not found: ' . $paymentId);
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        
        // Получаем тип платежа
        $paymentType = explode('_', $paymentId, 2)[0];
        
        // Начинаем транзакцию
        $db->beginTransaction();
        
        // Обновляем статус платежа
        $db->update(
            'payments',
            [
                'status' => 'completed',
                'telegram_payment_charge_id' => $data['invoice_id'] ?? null,
                'completed_at' => date('Y-m-d H:i:s')
            ],
            'payment_id = ?',
            [$paymentId]
        );
        
        // Обновляем соответствующую запись в зависимости от типа платежа
        if ($paymentType === 'logo') {
            // Обработка платежа за логотип
            $project = $db->fetchOne(
                "SELECT * FROM projects WHERE payment_id = ?",
                [$paymentId]
            );
            
            if ($project) {
                // Активируем проект
                $db->update(
                    'projects',
                    [
                        'payment_status' => 'completed',
                        'active' => 1,
                        'position' => time() // Устанавливаем позицию на основе времени
                    ],
                    'id = ?',
                    [$project['id']]
                );
                
                webhookLog('Logo payment completed for project: ' . $project['id']);
            } else {
                webhookLog('Project not found for payment: ' . $paymentId);
            }
        } elseif ($paymentType === 'review') {
            // Обработка платежа за отзыв
            $review = $db->fetchOne(
                "SELECT * FROM reviews WHERE payment_id = ?",
                [$paymentId]
            );
            
            if ($review) {
                // Одобряем отзыв
                $db->update(
                    'reviews',
                    [
                        'payment_status' => 'completed',
                        'approved' => 1
                    ],
                    'id = ?',
                    [$review['id']]
                );
                
                webhookLog('Review payment completed for review: ' . $review['id']);
            } else {
                webhookLog('Review not found for payment: ' . $paymentId);
            }
        } else {
            webhookLog('Unknown payment type: ' . $paymentType);
        }
        
        // Фиксируем транзакцию
        $db->commit();
        
        // Отправляем успешный ответ
        header('HTTP/1.1 200 OK');
        echo json_encode(['success' => true]);
        exit;
        
    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        $db->rollBack();
        
        // Логируем ошибку
        webhookLog('Error processing payment: ' . $e->getMessage());
        
        // Отправляем ошибку
        header('HTTP/1.1 500 Internal Server Error');
        if (DEV_MODE) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
} else {
    // Неизвестный тип обновления
    webhookLog('Unknown update type: ' . $data['update_type']);
    header('HTTP/1.1 400 Bad Request');
    exit;
}