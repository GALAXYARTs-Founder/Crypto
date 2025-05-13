<?php
/**
 * Административная панель - страница входа
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/lang.php';
require_once '../includes/security.php';

// Если пользователь уже авторизован, перенаправляем на главную страницу админки
if ($security->isAdmin()) {
    header('Location: index.php');
    exit;
}

// Инициализируем переменные
$username = '';
$error = '';
$success = false;

// Обработка формы авторизации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $error = __('error_security', 'Security verification failed. Please try again.');
    } else {
        // Получаем данные из формы
        $username = $security->sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Проверяем заполнение полей
        if (empty($username) || empty($password)) {
            $error = __('error_empty_fields', 'Please enter both username and password.');
        } else {
            // Пытаемся аутентифицировать пользователя
            $user = $security->authenticate($username, $password);
            
            if ($user === 'locked') {
                $error = __('error_account_locked', 'Your account has been temporarily locked due to too many failed login attempts. Please try again later.');
            } elseif ($user === 'inactive') {
                $error = __('error_account_inactive', 'Your account is inactive. Please contact the administrator.');
            } elseif ($user === false) {
                $error = __('error_invalid_credentials', 'Invalid username or password.');
            } else {
                // Авторизация успешна
                
                // Устанавливаем данные сессии
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                
                // Логируем успешный вход
                $security->logActivity($user['id'], 'login');
                
                // Перенаправляем на главную страницу админки
                header('Location: index.php');
                exit;
            }
        }
    }
    
    // Обновляем CSRF-токен
    $security->refreshCSRFToken();

}

// Заголовок страницы
$pageTitle = __('admin_login', 'Admin Login') . ' - ' . __('site_name');

?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Дополнительные стили -->
    <style>
        /* Основные стили */
        body {
            background-color: #0f172a;
            color: #e2e8f0;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Логотип */
        .admin-logo {
            font-weight: 800;
            font-size: 1.8rem;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        /* Карточка входа */
        .login-card {
            background-color: #1e293b;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        /* Поля ввода */
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #334155;
            color: white;
            border: 1px solid #475569;
            border-radius: 8px;
            margin-top: 0.5rem;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        
        /* Кнопка входа */
        .login-button {
            width: 100%;
            padding: 0.75rem 1rem;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .login-button:hover {
            background: linear-gradient(45deg, #2563eb, #7c3aed);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        /* Сообщение об ошибке */
        .error-message {
            background-color: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #fca5a5;
            border-radius: 0 8px 8px 0;
        }
        
        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="login-card animate-fade-in">
        <div class="text-center mb-8">
            <h1 class="admin-logo"><?php echo __('site_name'); ?></h1>
            <p class="mt-2 text-gray-400"><?php echo __('admin_login_subtitle', 'Admin Login'); ?></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="login.php">
            <!-- CSRF-токен -->
            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
            
            <!-- Имя пользователя -->
            <div class="mb-4">
                <label for="username" class="block text-gray-300 mb-1"><?php echo __('username', 'Username'); ?></label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" class="form-input" autocomplete="username" required>
            </div>
            
            <!-- Пароль -->
            <div class="mb-6">
                <label for="password" class="block text-gray-300 mb-1"><?php echo __('password', 'Password'); ?></label>
                <input type="password" id="password" name="password" class="form-input" autocomplete="current-password" required>
            </div>
            
            <!-- Кнопка входа -->
            <div class="mb-4">
                <button type="submit" class="login-button">
                    <?php echo __('login', 'Login'); ?>
                </button>
            </div>
            
            <!-- Ссылка на главную страницу -->
            <div class="text-center text-sm">
                <a href="../index.php" class="text-gray-400 hover:text-blue-400">
                    <?php echo __('back_to_site', 'Back to Site'); ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- JavaScript для фокуса на первом поле -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>