<?php
/**
 * Система аутентификации и безопасности
 * CryptoLogoWall
 */

class Security {
    private $db;
    private $maxLoginAttempts = 5;
    private $loginLockoutTime = 900; // 15 минут
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Генерация безопасного хеша пароля
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    // Проверка пароля
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Санитация входных данных
    public function sanitizeInput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeInput($value);
            }
            return $data;
        }
        
        return clean_input($data);
    }
    
    // Проверка CSRF токена
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
            return false;
        }
        return true;
    }
    
    // Генерация CSRF токена
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Обновление CSRF токена
    public function refreshCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    // Генерация JWT токена
    public function generateJWT($userId, $role, $expiry = null) {
        $expiry = $expiry ?? time() + API_TOKEN_EXPIRE;
        
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        
        $payload = [
            'sub' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => $expiry,
            'jti' => bin2hex(random_bytes(16))
        ];
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", SECURE_AUTH_KEY, true);
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    // Проверка JWT токена
    public function validateJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", SECURE_AUTH_KEY, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        
        if ($payload === null) {
            return false;
        }
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    // Базовое кодирование URL Base64
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    // Базовое декодирование URL Base64
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    // Аутентификация пользователя
    public function authenticate($username, $password) {
        // Получаем информацию о пользователе
        $sql = "SELECT * FROM users WHERE username = ?";
        $user = $this->db->fetchOne($sql, [$username]);
        
        // Проверяем существование пользователя
        if (!$user) {
            return false;
        }
        
        // Проверяем блокировку аккаунта
        if ($user['login_attempts'] >= $this->maxLoginAttempts && 
            $user['last_login'] && 
            (strtotime($user['last_login']) + $this->loginLockoutTime) > time()) {
            return 'locked';
        }
        
        // Проверяем активность аккаунта
        if ($user['is_active'] != 1) {
            return 'inactive';
        }
        
        // Проверяем пароль
        if (!$this->verifyPassword($password, $user['password'])) {
            // Увеличиваем счетчик попыток входа
            $this->db->update('users', 
                ['login_attempts' => $user['login_attempts'] + 1], 
                'id = ?', 
                [$user['id']]
            );
            return false;
        }
        
        // Сбрасываем счетчик попыток входа
        $this->db->update('users', 
            [
                'login_attempts' => 0,
                'last_login' => date('Y-m-d H:i:s')
            ], 
            'id = ?', 
            [$user['id']]
        );
        
        // Логируем успешный вход
        $this->logActivity($user['id'], 'login');
        
        return $user;
    }
    
    // Проверка авторизации для админки
    public function isAdmin() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && 
               ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'moderator');
    }
    
    // Проверка прав администратора
    public function hasAdminRights() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && 
               $_SESSION['user_role'] === 'admin';
    }
    
    // Запись действия в лог
    public function logActivity($userId, $action, $entityType = null, $entityId = null) {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        $this->db->insert('activity_logs', $data);
    }
    
    // Получение IP-адреса пользователя
    public function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    // Проверка безопасности загружаемых файлов
    public function validateUploadedFile($file, $allowedTypes, $maxSize) {
        // Проверка на ошибки загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Проверка размера файла
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        // Проверка MIME-типа файла
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            return false;
        }
        
        // Проверка на реальное изображение
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return false;
            }
        }
        
        return true;
    }
    
    // Генерация безопасного имени файла
    public function generateSafeFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(8)) . time() . '.' . $extension;
        return $safeName;
    }
}

// Создаем экземпляр класса безопасности
$security = new Security($db);

// Начинаем сессию безопасно
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Обновляем CSRF токен при первом посещении
if (!isset($_SESSION['csrf_token'])) {
    $security->generateCSRFToken();
}