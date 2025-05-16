<?php
/**
 * Административная панель - просмотр логов активности
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

// Очистка логов
if ($action === 'clear' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    try {
        // Очищаем логи старше 30 дней
        $db->query("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Логируем действие
        $security->logActivity($_SESSION['user_id'], 'clear_logs');
        
        $message = __('logs_cleared_success', 'Logs older than 30 days have been cleared successfully.');
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
    
    // Сбрасываем действие на список
    $action = 'list';
}

// Фильтры
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterUser = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Построение условия WHERE с учетом фильтров
$whereClause = '1=1';
$params = [];

if (!empty($filterType)) {
    $whereClause .= " AND a.action LIKE ?";
    $params[] = "%{$filterType}%";
}

if ($filterUser > 0) {
    $whereClause .= " AND a.user_id = ?";
    $params[] = $filterUser;
}

if (!empty($searchQuery)) {
    $whereClause .= " AND (a.action LIKE ? OR a.entity_type LIKE ? OR a.ip_address LIKE ? OR u.username LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

if (!empty($dateFrom)) {
    $whereClause .= " AND a.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereClause .= " AND a.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

// Получаем список логов с пагинацией
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Получаем общее количество логов
$totalLogs = $db->count('activity_logs a LEFT JOIN users u ON a.user_id = u.id', $whereClause, $params);

// Получаем логи
$logs = $db->fetchAll(
    "SELECT a.*, u.username 
     FROM activity_logs a 
     LEFT JOIN users u ON a.user_id = u.id 
     WHERE {$whereClause}
     ORDER BY a.created_at DESC 
     LIMIT ?, ?",
    array_merge($params, [$offset, $perPage])
);

// Вычисляем общее количество страниц
$totalPages = ceil($totalLogs / $perPage);

// Получаем список пользователей для фильтра
$users = $db->fetchAll("SELECT id, username FROM users ORDER BY username ASC");

// Получаем список уникальных типов действий для фильтра
$actionTypes = $db->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");

// Метка для включаемых файлов
define('INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('activity_logs', 'Activity Logs'); ?> - <?php echo __('admin_panel', 'Admin Panel'); ?></title>
    
    <!-- Tailwind CSS из CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style>
        /* Основные стили */
        body {
            background-color: #0f172a;
            color: #e2e8f0;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Боковое меню */
        .sidebar {
            background-color: #1e293b;
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 40;
            transition: transform 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0 !important;
            }
        }
        
        /* Контент */
        .content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Активный пункт меню */
        .nav-link.active {
            background-color: #3b82f6;
            color: white;
        }
        
        /* Логотип */
        .admin-logo {
            font-weight: 800;
            font-size: 1.4rem;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        /* Меню-гамбургер */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 50;
            background-color: #3b82f6;
            color: white;
            border-radius: 6px;
            padding: 8px 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
        }
        
        /* Фильтры */
        .filter-card {
            background-color: #1e293b;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background-color: #334155;
            border: 1px solid #475569;
            border-radius: 0.375rem;
            color: white;
            margin-top: 0.25rem;
        }
        
        .form-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        
        /* Таблица логов */
        .logs-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .logs-table th {
            background-color: #334155;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .logs-table td {
            padding: 12px;
            border-bottom: 1px solid #334155;
        }
        
        .logs-table tr:hover td {
            background-color: #1e293b;
        }
        
        /* Пагинация */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination a, .pagination span {
            margin: 0 0.25rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.25rem;
            text-decoration: none;
        }
        
        .pagination a {
            background-color: #334155;
            color: white;
        }
        
        .pagination a:hover {
            background-color: #475569;
        }
        
        .pagination span {
            background-color: #3b82f6;
            color: white;
        }
        
        /* Сообщения */
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
        
        /* Хлебные крошки */
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item {
            display: flex;
            align-items: center;
        }
        
        .breadcrumb-item:not(:last-child)::after {
            content: '/';
            margin: 0 0.5rem;
            color: #64748b;
        }
        
        .breadcrumb-link {
            color: #94a3b8;
            text-decoration: none;
        }
        
        .breadcrumb-link:hover {
            color: #e2e8f0;
        }
        
        .breadcrumb-current {
            color: #e2e8f0;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Кнопка-гамбургер для мобильных устройств -->
    <button id="menu-toggle" class="menu-toggle">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
        </svg>
    </button>
    
    <!-- Боковое меню -->
    <div id="sidebar" class="sidebar">
        <div class="p-4 border-b border-gray-700">
            <a href="index.php" class="admin-logo"><?php echo __('admin_panel', 'Admin Panel'); ?></a>
        </div>
        
        <nav class="mt-4">
            <ul>
                <li class="mb-1">
                    <a href="index.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">📊</span> <?php echo __('dashboard', 'Dashboard'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="logos.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">🖼️</span> <?php echo __('logos', 'Logos'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="reviews.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">⭐</span> <?php echo __('reviews', 'Reviews'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="translations.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">🌍</span> <?php echo __('translations', 'Translations'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="users.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">👥</span> <?php echo __('users', 'Users'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="logs.php" class="nav-link active block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">📝</span> <?php echo __('activity_logs', 'Activity Logs'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="settings.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">⚙️</span> <?php echo __('settings', 'Settings'); ?>
                    </a>
                </li>
            </ul>
            
            <div class="border-t border-gray-700 mt-6 pt-4 px-4">
                <a href="../index.php" class="block text-gray-300 hover:text-white mb-2">
                    <span class="mr-2">🏠</span> <?php echo __('view_site', 'View Site'); ?>
                </a>
                <a href="logout.php" class="block text-gray-300 hover:text-red-400">
                    <span class="mr-2">🚪</span> <?php echo __('logout', 'Logout'); ?>
                </a>
            </div>
        </nav>
    </div>
    
    <!-- Основной контент -->
    <div class="content">
        <!-- Хлебные крошки -->
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="index.php" class="breadcrumb-link"><?php echo __('dashboard', 'Dashboard'); ?></a>
            </div>
            <div class="breadcrumb-item">
                <span class="breadcrumb-current"><?php echo __('activity_logs', 'Activity Logs'); ?></span>
            </div>
        </div>
        
        <h1 class="text-3xl font-bold mb-6"><?php echo __('activity_logs', 'Activity Logs'); ?></h1>
        
        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> mb-6">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Фильтры -->
        <div class="filter-card">
            <h2 class="text-xl font-semibold mb-4"><?php echo __('filters', 'Filters'); ?></h2>
            
            <form action="logs.php" method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Поиск -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-400"><?php echo __('search', 'Search'); ?></label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="<?php echo __('search_placeholder', 'Search by action, user...'); ?>" class="form-input">
                </div>
                
                <!-- Фильтр по типу действия -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-400"><?php echo __('action_type', 'Action Type'); ?></label>
                    <select id="type" name="type" class="form-input">
                        <option value=""><?php echo __('all_actions', 'All Actions'); ?></option>
                        <?php foreach ($actionTypes as $actionType): ?>
                        <option value="<?php echo htmlspecialchars($actionType['action']); ?>" <?php echo $filterType === $actionType['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($actionType['action']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Фильтр по пользователю -->
                <div>
                    <label for="user" class="block text-sm font-medium text-gray-400"><?php echo __('user', 'User'); ?></label>
                    <select id="user" name="user" class="form-input">
                        <option value="0"><?php echo __('all_users', 'All Users'); ?></option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filterUser === $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Фильтр по дате от -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-400"><?php echo __('date_from', 'Date From'); ?></label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="form-input">
                </div>
                
                <!-- Фильтр по дате до -->
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-400"><?php echo __('date_to', 'Date To'); ?></label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="form-input">
                </div>
                
                <!-- Кнопки действий -->
                <div class="flex items-end space-x-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded text-white">
                        <?php echo __('apply_filters', 'Apply Filters'); ?>
                    </button>
                    
                    <a href="logs.php" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded text-white">
                        <?php echo __('reset_filters', 'Reset'); ?>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Кнопка очистки логов -->
        <div class="mb-6 flex justify-end">
            <a href="logs.php?action=clear&confirm=yes" class="px-4 py-2 bg-red-600 hover:bg-red-500 rounded text-white" onclick="return confirm('<?php echo __('confirm_clear_logs', 'Are you sure you want to clear logs older than 30 days? This cannot be undone.'); ?>')">
                <?php echo __('clear_old_logs', 'Clear Logs Older Than 30 Days'); ?>
            </a>
        </div>
        
        <!-- Таблица логов -->
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <?php if (count($logs) > 0): ?>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th class="w-36"><?php echo __('date', 'Date'); ?></th>
                        <th class="w-32"><?php echo __('user', 'User'); ?></th>
                        <th><?php echo __('action', 'Action'); ?></th>
                        <th class="w-32"><?php echo __('entity', 'Entity'); ?></th>
                        <th class="w-36"><?php echo __('ip_address', 'IP Address'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                        <td>
                            <?php if ($log['username']): ?>
                            <span class="text-blue-400"><?php echo htmlspecialchars($log['username']); ?></span>
                            <?php else: ?>
                            <span class="text-gray-500"><?php echo __('guest', 'Guest'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td>
                            <?php if ($log['entity_type'] && $log['entity_id']): ?>
                            <?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo $log['entity_id']; ?>
                            <?php else: ?>
                            <span class="text-gray-500">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination p-4">
                <?php
                // Формируем параметры для пагинации
                $queryParams = $_GET;
                unset($queryParams['page']);
                $queryString = http_build_query($queryParams);
                $queryString = !empty($queryString) ? '&' . $queryString : '';
                ?>
                
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1 . $queryString; ?>">&laquo; <?php echo __('prev', 'Previous'); ?></a>
                <?php endif; ?>
                
                <?php
                // Определяем диапазон страниц для отображения
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                // Показываем первую страницу, если текущая далеко от начала
                if ($start > 1) {
                    echo '<a href="?page=1' . $queryString . '">1</a>';
                    if ($start > 2) {
                        echo '<span class="mx-1">...</span>';
                    }
                }
                
                // Показываем страницы вокруг текущей
                for ($i = $start; $i <= $end; $i++) {
                    if ($i == $page) {
                        echo '<span>' . $i . '</span>';
                    } else {
                        echo '<a href="?page=' . $i . $queryString . '">' . $i . '</a>';
                    }
                }
                
                // Показываем последнюю страницу, если текущая далеко от конца
                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) {
                        echo '<span class="mx-1">...</span>';
                    }
                    echo '<a href="?page=' . $totalPages . $queryString . '">' . $totalPages . '</a>';
                }
                ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1 . $queryString; ?>"><?php echo __('next', 'Next'); ?> &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="p-8 text-center">
                <p class="text-xl text-gray-400"><?php echo __('no_logs_found', 'No logs found.'); ?></p>
                <?php if (!empty($searchQuery) || !empty($filterType) || $filterUser > 0 || !empty($dateFrom) || !empty($dateTo)): ?>
                <p class="mt-2 text-gray-500"><?php echo __('adjust_filters', 'Try adjusting your filters to see more results.'); ?></p>
                <div class="mt-4">
                    <a href="logs.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded text-white">
                        <?php echo __('reset_filters', 'Reset Filters'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Переключение мобильного меню
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
                
                // Закрытие меню при клике вне его
                document.addEventListener('click', function(event) {
                    const isClickInsideMenu = sidebar.contains(event.target);
                    const isClickOnToggle = menuToggle.contains(event.target);
                    
                    if (!isClickInsideMenu && !isClickOnToggle && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });
            }
            
            // Валидация дат
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (dateTo.value && new Date(dateFrom.value) > new Date(dateTo.value)) {
                        dateTo.value = dateFrom.value;
                    }
                });
                
                dateTo.addEventListener('change', function() {
                    if (dateFrom.value && new Date(dateTo.value) < new Date(dateFrom.value)) {
                        dateFrom.value = dateTo.value;
                    }
                });
            }
        });
    </script>
</body>
</html>