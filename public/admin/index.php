<?php
/**
 * Административная панель - главная страница
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/lang.php';
require_once '../includes/security.php';

// Проверяем авторизацию
if (!$security->isAdmin()) {
    // Если не авторизован, перенаправляем на страницу входа
    header('Location: login.php');
    exit;
}

// Права текущего пользователя
$isAdmin = $security->hasAdminRights();

// Получаем статистику сайта
$totalLogos = $db->count('projects');
$activeLogos = $db->count('projects', 'active = 1');
$pendingLogos = $db->count('projects', 'active = 0 AND payment_status = "pending"');
$totalReviews = $db->count('reviews');
$approvedReviews = $db->count('reviews', 'approved = 1');
$pendingReviews = $db->count('reviews', 'approved = 0 AND payment_status = "pending"');

// Получаем последние 5 логотипов
$latestLogos = $db->fetchAll(
    "SELECT id, name, logo_path, active, created_at, payment_status 
     FROM projects 
     ORDER BY created_at DESC 
     LIMIT 5"
);

// Получаем последние 5 отзывов
$latestReviews = $db->fetchAll(
    "SELECT r.id, r.author_name, r.rating, r.approved, r.created_at, p.id as project_id, p.name as project_name 
     FROM reviews r
     JOIN projects p ON r.project_id = p.id
     ORDER BY r.created_at DESC 
     LIMIT 5"
);

// Получаем последние 10 действий в системе
$latestActivity = $db->fetchAll(
    "SELECT a.*, u.username 
     FROM activity_logs a 
     LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC 
     LIMIT 10"
);

// Получаем основные настройки
$settings = $db->fetchAll(
    "SELECT * FROM translations WHERE lang_code = ? AND translation_key IN ('site_name', 'tagline', 'meta_description')", 
    [DEFAULT_LANG]
);

$siteSettings = [];
foreach ($settings as $setting) {
    $siteSettings[$setting['translation_key']] = $setting['translation_value'];
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('admin_dashboard', 'Admin Dashboard'); ?> - <?php echo __('site_name'); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Дополнительные стили -->
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
        
        /* Карточки статистики */
        .stat-card {
            background-color: #1e293b;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Таблицы */
        .admin-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .admin-table th {
            background-color: #334155;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .admin-table td {
            padding: 12px;
            border-bottom: 1px solid #334155;
        }
        
        .admin-table tr:hover td {
            background-color: #1e293b;
        }
        
        /* Статусы */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        
        .status-inactive {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        /* Кнопки */
        .admin-button {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .admin-button-primary {
            background-color: #3b82f6;
            color: white;
        }
        
        .admin-button-primary:hover {
            background-color: #2563eb;
        }
        
        .admin-button-secondary {
            background-color: #475569;
            color: white;
        }
        
        .admin-button-secondary:hover {
            background-color: #334155;
        }
        
        .admin-button-danger {
            background-color: #ef4444;
            color: white;
        }
        
        .admin-button-danger:hover {
            background-color: #dc2626;
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
                    <a href="index.php" class="nav-link active block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
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
                <?php if ($isAdmin): ?>
                <li class="mb-1">
                    <a href="users.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">👥</span> <?php echo __('users', 'Users'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="logs.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">📝</span> <?php echo __('activity_logs', 'Activity Logs'); ?>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="settings.php" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white rounded-md">
                        <span class="mr-2">⚙️</span> <?php echo __('settings', 'Settings'); ?>
                    </a>
                </li>
                <?php endif; ?>
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
        <h1 class="text-3xl font-bold mb-6"><?php echo __('dashboard', 'Dashboard'); ?></h1>
        
        <!-- Статистика сайта -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="stat-card">
                <h3 class="text-xl font-semibold mb-2"><?php echo __('logos_stats', 'Logos'); ?></h3>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <div class="text-3xl font-bold text-blue-400"><?php echo $totalLogos; ?></div>
                        <div class="text-sm text-gray-400"><?php echo __('total', 'Total'); ?></div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-green-400"><?php echo $activeLogos; ?></div>
                        <div class="text-sm text-gray-400"><?php echo __('active', 'Active'); ?></div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-orange-400">
                        <span class="text-lg font-semibold"><?php echo $pendingLogos; ?></span>
                        <span class="text-sm ml-1"><?php echo __('pending_payment', 'Pending Payment'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <h3 class="text-xl font-semibold mb-2"><?php echo __('reviews_stats', 'Reviews'); ?></h3>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <div class="text-3xl font-bold text-purple-400"><?php echo $totalReviews; ?></div>
                        <div class="text-sm text-gray-400"><?php echo __('total', 'Total'); ?></div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-green-400"><?php echo $approvedReviews; ?></div>
                        <div class="text-sm text-gray-400"><?php echo __('approved', 'Approved'); ?></div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-orange-400">
                        <span class="text-lg font-semibold"><?php echo $pendingReviews; ?></span>
                        <span class="text-sm ml-1"><?php echo __('pending_payment', 'Pending Payment'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card md:col-span-2 lg:col-span-1">
                <h3 class="text-xl font-semibold mb-4"><?php echo __('site_settings', 'Site Settings'); ?></h3>
                <ul class="space-y-2">
                    <li>
                        <span class="text-gray-400"><?php echo __('site_name', 'Site Name'); ?>:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($siteSettings['site_name'] ?? SITE_NAME); ?></span>
                    </li>
                    <li>
                        <span class="text-gray-400"><?php echo __('tagline', 'Tagline'); ?>:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($siteSettings['tagline'] ?? ''); ?></span>
                    </li>
                    <li>
                        <span class="text-gray-400"><?php echo __('default_lang', 'Default Language'); ?>:</span>
                        <span class="ml-2"><?php echo DEFAULT_LANG; ?></span>
                    </li>
                </ul>
                <div class="mt-4">
                    <a href="settings.php" class="admin-button admin-button-secondary">
                        <?php echo __('edit_settings', 'Edit Settings'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Последние логотипы -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold"><?php echo __('latest_logos', 'Latest Logos'); ?></h2>
                <a href="logos.php" class="text-blue-400 hover:text-blue-300 text-sm">
                    <?php echo __('view_all', 'View All'); ?> →
                </a>
            </div>
            
            <?php if (count($latestLogos) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo __('logo', 'Logo'); ?></th>
                                <th><?php echo __('name', 'Name'); ?></th>
                                <th><?php echo __('status', 'Status'); ?></th>
                                <th><?php echo __('date', 'Date Added'); ?></th>
                                <th><?php echo __('actions', 'Actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestLogos as $logo): ?>
                                <tr>
                                    <td class="w-12">
                                        <img src="../<?php echo htmlspecialchars($logo['logo_path']); ?>" alt="<?php echo htmlspecialchars($logo['name']); ?>" class="w-10 h-10 object-contain">
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($logo['name']); ?>
                                    </td>
                                    <td>
                                        <?php if ($logo['active'] == 1): ?>
                                            <span class="status-badge status-active"><?php echo __('active', 'Active'); ?></span>
                                        <?php elseif ($logo['payment_status'] === 'pending'): ?>
                                            <span class="status-badge status-pending"><?php echo __('pending_payment', 'Pending Payment'); ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive"><?php echo __('inactive', 'Inactive'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($logo['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="edit_logo.php?id=<?php echo $logo['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-2"><?php echo __('edit', 'Edit'); ?></a>
                                        <a href="../view.php?id=<?php echo $logo['id']; ?>" target="_blank" class="text-gray-400 hover:text-white"><?php echo __('view', 'View'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4"><?php echo __('no_logos_yet', 'No logos added yet.'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Последние отзывы -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold"><?php echo __('latest_reviews', 'Latest Reviews'); ?></h2>
                <a href="reviews.php" class="text-blue-400 hover:text-blue-300 text-sm">
                    <?php echo __('view_all', 'View All'); ?> →
                </a>
            </div>
            
            <?php if (count($latestReviews) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo __('author', 'Author'); ?></th>
                                <th><?php echo __('project', 'Project'); ?></th>
                                <th><?php echo __('rating', 'Rating'); ?></th>
                                <th><?php echo __('status', 'Status'); ?></th>
                                <th><?php echo __('date', 'Date Added'); ?></th>
                                <th><?php echo __('actions', 'Actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestReviews as $review): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($review['author_name']); ?>
                                    </td>
                                    <td>
                                        <a href="../view.php?id=<?php echo $review['project_id']; ?>" target="_blank" class="text-blue-400 hover:text-blue-300">
                                            <?php echo htmlspecialchars($review['project_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $review['rating']) {
                                                echo '<span class="text-yellow-400">★</span>';
                                            } else {
                                                echo '<span class="text-gray-600">★</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($review['approved'] == 1): ?>
                                            <span class="status-badge status-active"><?php echo __('approved', 'Approved'); ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending"><?php echo __('pending', 'Pending'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="edit_review.php?id=<?php echo $review['id']; ?>" class="text-blue-400 hover:text-blue-300"><?php echo __('edit', 'Edit'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4"><?php echo __('no_reviews_yet', 'No reviews added yet.'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Последние действия в системе -->
        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold"><?php echo __('latest_activities', 'Latest Activities'); ?></h2>
                <?php if ($isAdmin): ?>
                <a href="logs.php" class="text-blue-400 hover:text-blue-300 text-sm">
                    <?php echo __('view_all', 'View All'); ?> →
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (count($latestActivity) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo __('action', 'Action'); ?></th>
                                <th><?php echo __('user', 'User'); ?></th>
                                <th><?php echo __('entity', 'Entity'); ?></th>
                                <th><?php echo __('ip_address', 'IP Address'); ?></th>
                                <th><?php echo __('date', 'Date'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($activity['action']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($activity['username'] ?? __('guest', 'Guest')); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($activity['entity_type'] && $activity['entity_id']) {
                                            echo htmlspecialchars($activity['entity_type'] . ' #' . $activity['entity_id']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-center py-4"><?php echo __('no_activities_yet', 'No activities recorded yet.'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Переключение мобильного меню
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
        });
    </script>
</body>
</html>