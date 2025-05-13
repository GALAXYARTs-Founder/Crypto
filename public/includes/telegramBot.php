<?php
/**
 * Интеграция с Telegram Crypto Bot
 * CryptoLogoWall
 */

class TelegramBot {
    private $db;
    private $botToken;
    private $botUsername;
    
    public function __construct($db) {
        $this->db = $db;
        $this->botToken = TELEGRAM_BOT_TOKEN;
        $this->botUsername = TELEGRAM_BOT_USERNAME;
    }
    
    /**
     * Создание платежной ссылки для добавления логотипа
     * 
     * @param int $projectId ID проекта
     * @param string $projectName Название проекта
     * @return array Массив с данными платежной ссылки
     */
    public function createLogoPaymentLink($projectId, $projectName) {
        // Создаем уникальный ID платежа
        $paymentId = 'logo_' . bin2hex(random_bytes(8)) . '_' . time();
        
        // Создаем запись о платеже в БД
        $paymentData = [
            'payment_id' => $paymentId,
            'amount' => 1.00,
            'currency' => 'USD',
            'type' => 'logo',
            'status' => 'pending',
            'entity_id' => $projectId
        ];
        
        $this->db->insert('payments', $paymentData);
        
        // Обновляем запись проекта
        $this->db->update('projects', 
            ['payment_id' => $paymentId, 'payment_status' => 'pending'], 
            'id = ?', 
            [$projectId]
        );
        
        // Формируем описание платежа
        $description = "Add logo for {$projectName} on " . SITE_NAME;
        
        // Формируем параметры для создания платежной ссылки
        $params = [
            'amount' => 1,
            'currency' => 'USD',
            'description' => $description,
            'allow_anonymous' => true,
            'allow_comments' => false,
            'payload' => $paymentId,
            'paid_btn_name' => 'viewLogo',
            'paid_btn_url' => SITE_URL . '/view.php?id=' . $projectId
        ];
        
        // Создаем платежную ссылку
        $paymentUrl = 'https://t.me/' . $this->botUsername . '/pay?'. http_build_query($params);
        
        return [
            'payment_id' => $paymentId,
            'payment_url' => $paymentUrl
        ];
    }
    
    /**
     * Создание платежной ссылки для отзыва
     * 
     * @param int $reviewId ID отзыва
     * @param int $projectId ID проекта
     * @param string $projectName Название проекта
     * @return array Массив с данными платежной ссылки
     */
    public function createReviewPaymentLink($reviewId, $projectId, $projectName) {
        // Создаем уникальный ID платежа
        $paymentId = 'review_' . bin2hex(random_bytes(8)) . '_' . time();
        
        // Создаем запись о платеже в БД
        $paymentData = [
            'payment_id' => $paymentId,
            'amount' => 1.00,
            'currency' => 'USD',
            'type' => 'review',
            'status' => 'pending',
            'entity_id' => $reviewId
        ];
        
        $this->db->insert('payments', $paymentData);
        
        // Обновляем запись отзыва
        $this->db->update('reviews', 
            ['payment_id' => $paymentId, 'payment_status' => 'pending'], 
            'id = ?', 
            [$reviewId]
        );
        
        // Формируем описание платежа
        $description = "Review for {$projectName} on " . SITE_NAME;
        
        // Формируем параметры для создания платежной ссылки
        $params = [
            'amount' => 1,
            'currency' => 'USD',
            'description' => $description,
            'allow_anonymous' => true,
            'allow_comments' => false,
            'payload' => $paymentId,
            'paid_btn_name' => 'viewProject',
            'paid_btn_url' => SITE_URL . '/view.php?id=' . $projectId
        ];
        
        // Создаем платежную ссылку
        $paymentUrl = 'https://t.me/' . $this->botUsername . '/pay?'. http_build_query($params);
        
        return [
            'payment_id' => $paymentId,
            'payment_url' => $paymentUrl
        ];
    }
    
    /**
     * Проверка статуса платежа через API Crypto Bot
     * 
     * @param string $paymentId ID платежа
     * @return array Информация о платеже или false в случае ошибки
     */
    public function checkPaymentStatus($paymentId) {
        // API URL для проверки платежа
        $apiUrl = "https://pay.crypt.bot/api/getInvoices?status=paid&order_ids={$paymentId}";
        
        // Заголовок с токеном
        $headers = [
            'Crypto-Pay-API-Token: ' . $this->botToken
        ];
        
        // Инициализация cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Выполнение запроса
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Проверка ответа
        if ($httpCode !== 200) {
            error_log("Telegram Bot API Error: HTTP Code {$httpCode}, Response: {$response}");
            return false;
        }
        
        // Декодируем ответ
        $data = json_decode($response, true);
        
        if (!isset($data['ok']) || $data['ok'] !== true) {
            error_log("Telegram Bot API Error: " . json_encode($data));
            return false;
        }
        
        // Проверяем наличие платежей
        if (!isset($data['result']) || count($data['result']) === 0) {
            return ['status' => 'pending'];
        }
        
        // Берем первый результат
        $payment = $data['result'][0];
        
        // Обновляем статус платежа в БД
        $this->db->update('payments', 
            [
                'status' => 'completed',
                'telegram_payment_charge_id' => $payment['id'],
                'completed_at' => date('Y-m-d H:i:s')
            ], 
            'payment_id = ?', 
            [$paymentId]
        );
        
        // Определяем тип платежа и обновляем соответствующую запись
        list($type, $rest) = explode('_', $paymentId, 2);
        
        if ($type === 'logo') {
            $this->db->update('projects', 
                ['payment_status' => 'completed'], 
                'payment_id = ?', 
                [$paymentId]
            );
            
            // Получаем ID проекта
            $projectId = $this->db->fetchColumn(
                "SELECT entity_id FROM payments WHERE payment_id = ?", 
                [$paymentId]
            );
            
            // Активируем проект
            if ($projectId) {
                $this->db->update('projects', 
                    ['active' => 1], 
                    'id = ?', 
                    [$projectId]
                );
            }
        } elseif ($type === 'review') {
            $this->db->update('reviews', 
                ['payment_status' => 'completed', 'approved' => 1], 
                'payment_id = ?', 
                [$paymentId]
            );
        }
        
        return ['status' => 'completed', 'payment' => $payment];
    }
    
    /**
     * Обработка вебхука от Telegram Crypto Bot
     * 
     * @param array $data Данные вебхука
     * @return bool Успешность обработки
     */
    public function handleWebhook($data) {
        // Проверяем наличие необходимых данных
        if (!isset($data['update_type']) || $data['update_type'] !== 'invoice_paid') {
            return false;
        }
        
        if (!isset($data['payload']) || empty($data['payload'])) {
            return false;
        }
        
        $paymentId = $data['payload'];
        
        // Получаем информацию о платеже из БД
        $payment = $this->db->fetchOne(
            "SELECT * FROM payments WHERE payment_id = ?", 
            [$paymentId]
        );
        
        if (!$payment) {
            return false;
        }
        
        // Обновляем статус платежа
        $this->db->update('payments', 
            [
                'status' => 'completed',
                'telegram_payment_charge_id' => $data['invoice_id'] ?? null,
                'completed_at' => date('Y-m-d H:i:s')
            ], 
            'payment_id = ?', 
            [$paymentId]
        );
        
        // Обновляем соответствующую запись в зависимости от типа платежа
        if ($payment['type'] === 'logo') {
            $this->db->update('projects', 
                ['payment_status' => 'completed', 'active' => 1], 
                'payment_id = ?', 
                [$paymentId]
            );
        } elseif ($payment['type'] === 'review') {
            $this->db->update('reviews', 
                ['payment_status' => 'completed', 'approved' => 1], 
                'payment_id = ?', 
                [$paymentId]
            );
        }
        
        return true;
    }
}

// Создаем экземпляр класса
$telegramBot = new TelegramBot($db);