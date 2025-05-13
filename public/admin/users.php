<?php
/**
 * Административная панель - управление пользователями
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/lang.php';
require_once '../includes/security.php';

// Проверяем авторизацию и права администратора
if (!$security->isAdmin() || !$security->hasAdminRights()) {
    // Если не админ, перенаправляем на главную страницу админки
    header('Location: index.php');
    exit;
}

// Определяем действие
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Обработка действий с пользователями
$message = '';
$messageType = '';

// Действие активации/деактивации пользователя
if ($action === 'toggle' && $userId > 0) {
    // Получаем текущий статус
    $user = $db->fetchOne(
        "SELECT is_active FROM users WHERE id = ?",
        [$userId]
    );
    
    if ($user) {
        // Меняем статус на противоположный
        $newStatus = $user['is_active'] == 1 ? 0 : 1;
        $db->update('users', ['is_active' => $newStatus], 'id = ?', [$userId]);
        
        // Логируем действие
        $security->logActivity($_SESSION['user_id'], $newStatus == 1 ? 'activate_user' : 'deactivate_user', 'user', $userId);
        
        $message = $newStatus == 1 ? __('user_activated', 'User has been activated.') : __('user_deactivated', 'User has been deactivated.');
        $messageType = 'success';
    }
}

// Действие удаления пользователя
if ($action === 'delete' && $userId > 0) {
    // Проверяем, не удаляет ли пользователь сам себя
    if ($userId != $_SESSION['user_id']) {
        try {
            // Удаляем пользователя
            $db->delete('users', 'id = ?', [$userId]);
            
            // Логируем действие
            $security->logActivity($_SESSION['user_id'], 'delete_user', 'user', $userId);
            
            $message = __('user_deleted', 'User has been deleted successfully.');
            $messageType = 'success';
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = __('cannot_delete_self', 'You cannot delete your own account.');
        $messageType = 'error';
    }
}

// Добавление нового пользователя
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $message = __('error_security', 'Security verification failed. Please try again.');
        $messageType = 'error';
    } else {
        // Получаем данные из формы
        $username = $security->sanitizeInput($_POST['username'] ?? '');
        $email = $security->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $security->sanitizeInput($_POST['role'] ?? 'moderator');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Валидация
        $errors = [];
        
        if (empty($username)) {
            $errors[] = __('error_username_required', 'Username is required.');
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = __('error_username_length', 'Username must be between 3 and 50 characters.');
        }
        
        if (empty($email)) {
            $errors[] = __('error_email_required', 'Email is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('error_email_invalid', 'Please enter a valid email address.');
        }
        
        if (empty($password)) {
            $errors[] = __('error_password_required', 'Password is required.');
        } elseif (strlen($password) < 8) {
            $errors[] = __('error_password_length', 'Password must be at least 8 characters long.');
        } elseif ($password !== $confirmPassword) {
            $errors[] = __('error_password_mismatch', 'Passwords do not match.');
        }
        
        // Проверяем уникальность username и email
        if (!empty($username) && $db->exists('users', 'username = ?', [$username])) {
            $errors[] = __('error_username_exists', 'Username already exists.');
        }
        
        if (!empty($email) && $db->exists('users', 'email = ?', [$email])) {
            $errors[] = __('error_email_exists', 'Email already exists.');
        }
        
        // Если нет ошибок, добавляем пользователя
        if (empty($errors)) {
            try {
                // Хешируем пароль
                $hashedPassword = $security->hashPassword($password);
                
                // Добавляем пользователя в БД
                $userData = [
                    'username' => $username,
                    'password' => $hashedPassword,
                    'email' => $email,
                    'role' => $role,
                    'is_active' => $isActive,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $newUserId = $db->insert('users', $userData);
                
                // Логируем действие
                $security->logActivity($_SESSION['user_id'], 'add_user', 'user', $newUserId);
                
                $message = __('user_added', 'User has been added successfully.');
                $messageType = 'success';
                
                // Сбрасываем форму
                $username = '';
                $email = '';
                $role = 'moderator';
                $isActive = 1;
                
                // Переключаемся на список пользователей
                $action = 'list';
            } catch (Exception $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    }
}

// Редактирование пользователя
if ($action === 'edit' && $userId > 0) {
    // Получаем информацию о пользователе
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$userId]
    );
    
    if (!$user) {
        header('Location: users.php');
        exit;
    }
    
    // Обработка отправки формы
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Проверяем CSRF-токен
        if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
            $message = __('error_security', 'Security verification failed. Please try again.');
            $messageType = 'error';
        } else {
            // Получаем данные из формы
            $email = $security->sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $security->sanitizeInput($_POST['role'] ?? 'moderator');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Валидация
            $errors = [];
            
            if (empty($email)) {
                $errors[] = __('error_email_required', 'Email is required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('error_email_invalid', 'Please enter a valid email address.');
            }
            
            // Проверяем уникальность email, исключая текущего пользователя
            if (!empty($email) && $email !== $user['email'] && $db->exists('users', 'email = ? AND id != ?', [$email, $userId])) {
                $errors[] = __('error_email_exists', 'Email already exists.');
            }
            
            // Если нет ошибок, обновляем пользователя
            if (empty($errors)) {
                try {
                    $userData = [
                        'email' => $email,
                        'role' => $role,
                        'is_active' => $isActive
                    ];
                    
                    // Если пароль был введен, обновляем его
                    if (!empty($password)) {
                        $userData['password'] = $security->hashPassword($password);
                    }
                    
                    $db->update('users', $userData, 'id = ?', [$userId]);
                    
                    // Логируем действие
                    $security->logActivity($_SESSION['user_id'], 'update_user', 'user', $userId);
                    
                    $message = __('user_updated', 'User has been updated successfully.');
                    $messageType = 'success';
                    
                    // Обновляем данные пользователя для отображения
                    $user = $db->fetchOne(
                        "SELECT * FROM users WHERE id = ?",
                        [$userId]
                    );
                } catch (Exception $e) {
                    $message = $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = implode('<br>', $errors);
                $messageType = 'error';
            }
        }
    }
}

// Если действие - список пользователей
if ($action === 'list') {
    // Получаем список пользователей
    $users = $db->fetchAll(
        "SELECT * FROM users ORDER BY username ASC"
    );
}

// Метка для включаемых файлов
define('INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('manage_users', 'Manage Users'); ?> - <?php echo __('admin_panel', 'Admin Panel'); ?></title>
    
    <!-- Tailwind CSS из CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style>
        /* Дополнительные стили */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-inactive {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .role-admin {
            background-color: rgba(79, 70, 229, 0.2);
            color: #6366f1;
        }
        
        .role-moderator {
            background-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.2);
            border-left: 4px solid #10b981;
            color: #10b981;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            color: #ef4444;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background-color: #374151;
            border: 1px solid #4b5563;
            border-radius: 0.375rem;
            color: white;
        }
        
        .form-input:focus {
            border-color: #6366f1;
            outline: none;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Шапка -->
    <header class="bg-gray-800 py-4 px-6 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold"><?php echo __('admin_panel', 'Admin Panel'); ?></h1>
            
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="index.php" class="text-gray-300 hover:text-white"><?php echo __('dashboard', 'Dashboard'); ?></a></li>
                    <li><a href="../index.php" class="text-gray-300 hover:text-white"><?php echo __('view_site', 'View Site'); ?></a></li>
                    <li><a href="logout.php" class="text-gray-300 hover:text-white"><?php echo __('logout', 'Logout'); ?></a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Основное содержимое -->
    <div class="container mx-auto py-8 px-4">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Боковое меню -->
            <div class="w-full md:w-1/4">
                <div class="bg-gray-800 rounded-lg p-4">
                    <h2 class="text-lg font-semibold mb-4"><?php echo __('admin_menu', 'Admin Menu'); ?></h2>
                    
                    <nav>
                        <ul class="space-y-2">
                            <li>
                                <a href="index.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('dashboard', 'Dashboard'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="logos.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('logos', 'Logos'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="reviews.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('reviews', 'Reviews'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="translations.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('translations', 'Translations'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="users.php" class="block px-4 py-2 rounded bg-blue-600 text-white">
                                    <?php echo __('users', 'Users'); ?>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            
            <!-- Основной контент -->
            <div class="w-full md:w-3/4">
                <?php if ($action === 'add'): ?>
                    <!-- Форма добавления пользователя -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold"><?php echo __('add_user', 'Add User'); ?></h2>
                        <a href="users.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded">
                            <?php echo __('back_to_list', 'Back to List'); ?>
                        </a>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-gray-800 rounded-lg p-6">
                        <form action="users.php?action=add" method="post">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Имя пользователя -->
                                <div>
                                    <label for="username" class="block mb-2"><?php echo __('username', 'Username'); ?> <span class="text-red-500">*</span></label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" class="form-input" required>
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block mb-2"><?php echo __('email', 'Email'); ?> <span class="text-red-500">*</span></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" class="form-input" required>
                                </div>
                                
                                <!-- Пароль -->
                                <div>
                                    <label for="password" class="block mb-2"><?php echo __('password', 'Password'); ?> <span class="text-red-500">*</span></label>
                                    <input type="password" id="password" name="password" class="form-input" required>
                                </div>
                                
                                <!-- Подтверждение пароля -->
                                <div>
                                    <label for="confirm_password" class="block mb-2"><?php echo __('confirm_password', 'Confirm Password'); ?> <span class="text-red-500">*</span></label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                                </div>
                                
                                <!-- Роль -->
                                <div>
                                    <label for="role" class="block mb-2"><?php echo __('role', 'Role'); ?></label>
                                    <select id="role" name="role" class="form-input">
                                        <option value="moderator" <?php echo isset($role) && $role === 'moderator' ? 'selected' : ''; ?>><?php echo __('moderator', 'Moderator'); ?></option>
                                        <option value="admin" <?php echo isset($role) && $role === 'admin' ? 'selected' : ''; ?>><?php echo __('admin', 'Administrator'); ?></option>
                                    </select>
                                </div>
                                
                                <!-- Статус -->
                                <div class="flex items-center">
                                    <label class="flex items-center mt-6">
                                        <input type="checkbox" name="is_active" value="1" class="mr-2" <?php echo !isset($isActive) || $isActive ? 'checked' : ''; ?>>
                                        <?php echo __('active', 'Active'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded">
                                    <?php echo __('add_user', 'Add User'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php elseif ($action === 'edit'): ?>
                    <!-- Форма редактирования пользователя -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold"><?php echo __('edit_user', 'Edit User'); ?></h2>
                        <a href="users.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded">
                            <?php echo __('back_to_list', 'Back to List'); ?>
                        </a>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-gray-800 rounded-lg p-6">
                        <form action="users.php?action=edit&id=<?php echo $userId; ?>" method="post">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Имя пользователя (только для отображения) -->
                                <div>
                                    <label class="block mb-2"><?php echo __('username', 'Username'); ?></label>
                                    <div class="form-input bg-gray-700"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block mb-2"><?php echo __('email', 'Email'); ?> <span class="text-red-500">*</span></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-input" required>
                                </div>
                                
                                <!-- Пароль (необязательно при редактировании) -->
                                <div>
                                    <label for="password" class="block mb-2"><?php echo __('password', 'Password'); ?></label>
                                    <input type="password" id="password" name="password" class="form-input">
                                    <p class="text-sm text-gray-400 mt-1"><?php echo __('password_hint', 'Leave empty to keep current password'); ?></p>
                                </div>
                                
                                <!-- Роль -->
                                <div>
                                    <label for="role" class="block mb-2"><?php echo __('role', 'Role'); ?></label>
                                    <select id="role" name="role" class="form-input">
                                        <option value="moderator" <?php echo $user['role'] === 'moderator' ? 'selected' : ''; ?>><?php echo __('moderator', 'Moderator'); ?></option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>><?php echo __('admin', 'Administrator'); ?></option>
                                    </select>
                                </div>
                                
                                <!-- Статус -->
                                <div class="flex items-center">
                                    <label class="flex items-center mt-6">
                                        <input type="checkbox" name="is_active" value="1" class="mr-2" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                        <?php echo __('active', 'Active'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded">
                                    <?php echo __('save_changes', 'Save Changes'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Список пользователей -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold"><?php echo __('manage_users', 'Manage Users'); ?></h2>
                        <a href="users.php?action=add" class="px-4 py-2 bg-green-600 hover:bg-green-500 rounded">
                            <?php echo __('add_user', 'Add User'); ?>
                        </a>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-gray-800 rounded-lg overflow-hidden">
                        <?php if (count($users) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-700">
                                        <th class="px-4 py-3 text-left"><?php echo __('username', 'Username'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('email', 'Email'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('role', 'Role'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('status', 'Status'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('created_at', 'Created At'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('actions', 'Actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-750">
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo __($user['role'], ucfirst($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $user['is_active'] ? __('active', 'Active') : __('inactive', 'Inactive'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                        <td class="px-4 py-3">
                                            <div class="flex space-x-2">
                                                <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="text-blue-400 hover:text-blue-300">
                                                    <?php echo __('edit', 'Edit'); ?>
                                                </a>
                                                <a href="users.php?action=toggle&id=<?php echo $user['id']; ?>" class="text-yellow-400 hover:text-yellow-300">
                                                    <?php echo $user['is_active'] ? __('deactivate', 'Deactivate') : __('activate', 'Activate'); ?>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('<?php echo __('confirm_delete', 'Are you sure you want to delete this user? This cannot be undone.'); ?>')">
                                                    <?php echo __('delete', 'Delete'); ?>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="p-6 text-center">
                            <p><?php echo __('no_users', 'No users found.'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Подвал -->
    <footer class="bg-gray-800 py-4 px-6 mt-8">
        <div class="container mx-auto text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> <?php echo __('site_name', 'CryptoLogoWall'); ?>. <?php echo __('all_rights', 'All Rights Reserved'); ?>.</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="../assets/js/admin.js"></script>
</body>
</html>