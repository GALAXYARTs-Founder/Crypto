<?php
/**
 * Административная панель - управление логотипами
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

// Определяем действие
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Обработка действий с логотипами
$message = '';
$messageType = '';

// Действие активации/деактивации логотипа
if ($action === 'toggle' && $projectId > 0) {
    // Получаем текущий статус
    $project = $db->fetchOne(
        "SELECT active FROM projects WHERE id = ?",
        [$projectId]
    );
    
    if ($project) {
        // Меняем статус на противоположный
        $newStatus = $project['active'] == 1 ? 0 : 1;
        $db->update('projects', ['active' => $newStatus], 'id = ?', [$projectId]);
        
        // Логируем действие
        $security->logActivity($_SESSION['user_id'], $newStatus == 1 ? 'activate_logo' : 'deactivate_logo', 'project', $projectId);
        
        $message = $newStatus == 1 ? __('logo_activated', 'Logo has been activated.') : __('logo_deactivated', 'Logo has been deactivated.');
        $messageType = 'success';
    }
}

// Действие удаления логотипа
if ($action === 'delete' && $projectId > 0 && $isAdmin) {
    // Получаем информацию о логотипе
    $project = $db->fetchOne(
        "SELECT logo_path FROM projects WHERE id = ?",
        [$projectId]
    );
    
    if ($project) {
        try {
            // Начинаем транзакцию
            $db->beginTransaction();
            
            // Удаляем отзывы для логотипа
            $db->delete('reviews', 'project_id = ?', [$projectId]);
            
            // Удаляем платежи, связанные с логотипом
            $db->delete('payments', 'entity_id = ? AND type = ?', [$projectId, 'logo']);
            
            // Удаляем логотип из БД
            $db->delete('projects', 'id = ?', [$projectId]);
            
            // Удаляем файл логотипа
            if (file_exists('../' . $project['logo_path'])) {
                unlink('../' . $project['logo_path']);
            }
            
            // Фиксируем транзакцию
            $db->commit();
            
            // Логируем действие
            $security->logActivity($_SESSION['user_id'], 'delete_logo', 'project', $projectId);
            
            $message = __('logo_deleted', 'Logo has been deleted successfully.');
            $messageType = 'success';
        } catch (Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $db->rollBack();
            
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Получаем список логотипов
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Получаем общее количество логотипов
$totalLogos = $db->count('projects');

// Получаем логотипы с пагинацией
$logos = $db->fetchAll(
    "SELECT p.*, 
     (SELECT COUNT(*) FROM reviews r WHERE r.project_id = p.id AND r.approved = 1) as reviews_count,
     (SELECT AVG(rating) FROM reviews r WHERE r.project_id = p.id AND r.approved = 1) as avg_rating
     FROM projects p
     ORDER BY p.created_at DESC
     LIMIT ?, ?",
    [$offset, $perPage]
);

// Вычисляем общее количество страниц
$totalPages = ceil($totalLogos / $perPage);

// Метка для включаемых файлов
define('INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('manage_logos', 'Manage Logos'); ?> - <?php echo __('admin_panel', 'Admin Panel'); ?></title>
    
    <!-- Tailwind CSS из CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style>
        /* Дополнительные стили */
        .logo-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
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
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination a, .pagination span {
            margin: 0 0.25rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.25rem;
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
                                <a href="logos.php" class="block px-4 py-2 rounded bg-blue-600 text-white">
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
                            <?php if ($isAdmin): ?>
                            <li>
                                <a href="users.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('users', 'Users'); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            
            <!-- Основной контент -->
            <div class="w-full md:w-3/4">
                <h2 class="text-2xl font-bold mb-6"><?php echo __('manage_logos', 'Manage Logos'); ?></h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Таблица логотипов -->
                <div class="bg-gray-800 rounded-lg overflow-hidden">
                    <?php if (count($logos) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="px-4 py-3 text-left"><?php echo __('logo', 'Logo'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('name', 'Name'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('status', 'Status'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('reviews', 'Reviews'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('date_added', 'Date Added'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('actions', 'Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logos as $logo): ?>
                                <tr class="border-t border-gray-700 hover:bg-gray-750">
                                    <td class="px-4 py-3">
                                        <img src="../<?php echo htmlspecialchars($logo['logo_path']); ?>" alt="<?php echo htmlspecialchars($logo['name']); ?>" class="logo-thumbnail">
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php echo htmlspecialchars($logo['name']); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="status-badge status-<?php echo $logo['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $logo['active'] ? __('active', 'Active') : __('inactive', 'Inactive'); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php echo (int)$logo['reviews_count']; ?>
                                        <?php if ($logo['avg_rating'] > 0): ?>
                                        <span class="text-yellow-400 ml-2">★ <?php echo number_format($logo['avg_rating'], 1); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php echo date('Y-m-d', strtotime($logo['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex space-x-2">
                                            <a href="../view.php?id=<?php echo $logo['id']; ?>" target="_blank" class="text-blue-400 hover:text-blue-300">
                                                <?php echo __('view', 'View'); ?>
                                            </a>
                                            <a href="logos.php?action=toggle&id=<?php echo $logo['id']; ?>" class="text-yellow-400 hover:text-yellow-300">
                                                <?php echo $logo['active'] ? __('deactivate', 'Deactivate') : __('activate', 'Activate'); ?>
                                            </a>
                                            <?php if ($isAdmin): ?>
                                            <a href="logos.php?action=delete&id=<?php echo $logo['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('<?php echo __('confirm_delete', 'Are you sure you want to delete this logo? This cannot be undone.'); ?>')">
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
                    
                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination p-4">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="bg-gray-700 hover:bg-gray-600">&laquo; <?php echo __('prev', 'Previous'); ?></a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="bg-blue-600 text-white"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="?page=<?php echo $i; ?>" class="bg-gray-700 hover:bg-gray-600"><?php echo $i; ?></a>
                        <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="bg-gray-700 hover:bg-gray-600"><?php echo __('next', 'Next'); ?> &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="p-6 text-center">
                        <p><?php echo __('no_logos', 'No logos found.'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
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