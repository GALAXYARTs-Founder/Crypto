<?php
/**
 * Административная панель - управление отзывами
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
$reviewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Обработка действий с отзывами
$message = '';
$messageType = '';

// Действие одобрения/отклонения отзыва
if ($action === 'approve' && $reviewId > 0) {
    $db->update('reviews', ['approved' => 1], 'id = ?', [$reviewId]);
    $security->logActivity($_SESSION['user_id'], 'approve_review', 'review', $reviewId);
    $message = __('review_approved', 'Review has been approved.');
    $messageType = 'success';
} elseif ($action === 'disapprove' && $reviewId > 0) {
    $db->update('reviews', ['approved' => 0], 'id = ?', [$reviewId]);
    $security->logActivity($_SESSION['user_id'], 'disapprove_review', 'review', $reviewId);
    $message = __('review_disapproved', 'Review has been disapproved.');
    $messageType = 'success';
}

// Действие удаления отзыва
if ($action === 'delete' && $reviewId > 0 && $isAdmin) {
    try {
        // Удаляем отзыв
        $db->delete('reviews', 'id = ?', [$reviewId]);
        
        // Логируем действие
        $security->logActivity($_SESSION['user_id'], 'delete_review', 'review', $reviewId);
        
        $message = __('review_deleted', 'Review has been deleted successfully.');
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Получаем список отзывов
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Фильтр по статусу
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$whereClause = '';
$params = [];

if ($filterStatus === 'approved') {
    $whereClause = 'WHERE r.approved = 1';
} elseif ($filterStatus === 'pending') {
    $whereClause = 'WHERE r.approved = 0';
}

// Получаем общее количество отзывов
$totalReviews = $db->count('reviews r', $whereClause);

// Получаем отзывы с пагинацией
$reviews = $db->fetchAll(
    "SELECT r.*, p.name as project_name, p.logo_path
     FROM reviews r
     JOIN projects p ON r.project_id = p.id
     {$whereClause}
     ORDER BY r.created_at DESC
     LIMIT ?, ?",
    array_merge($params, [$offset, $perPage])
);

// Вычисляем общее количество страниц
$totalPages = ceil($totalReviews / $perPage);

// Метка для включаемых файлов
define('INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('manage_reviews', 'Manage Reviews'); ?> - <?php echo __('admin_panel', 'Admin Panel'); ?></title>
    
    <!-- Tailwind CSS из CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style>
        /* Дополнительные стили */
        .logo-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-approved {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        
        .star-rating {
            color: #eab308; /* желтый цвет для звезд */
            font-size: 1.2rem;
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
                                <a href="logos.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('logos', 'Logos'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="reviews.php" class="block px-4 py-2 rounded bg-blue-600 text-white">
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
                <h2 class="text-2xl font-bold mb-6"><?php echo __('manage_reviews', 'Manage Reviews'); ?></h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Фильтры -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <a href="reviews.php" class="px-4 py-2 rounded <?php echo $filterStatus === '' ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600'; ?>">
                        <?php echo __('all_reviews', 'All Reviews'); ?>
                    </a>
                    <a href="reviews.php?status=approved" class="px-4 py-2 rounded <?php echo $filterStatus === 'approved' ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600'; ?>">
                        <?php echo __('approved', 'Approved'); ?>
                    </a>
                    <a href="reviews.php?status=pending" class="px-4 py-2 rounded <?php echo $filterStatus === 'pending' ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600'; ?>">
                        <?php echo __('pending', 'Pending'); ?>
                    </a>
                </div>
                
                <!-- Таблица отзывов -->
                <div class="bg-gray-800 rounded-lg overflow-hidden">
                    <?php if (count($reviews) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="px-4 py-3 text-left"><?php echo __('project', 'Project'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('author', 'Author'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('rating', 'Rating'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('comment', 'Comment'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('status', 'Status'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('date', 'Date'); ?></th>
                                    <th class="px-4 py-3 text-left"><?php echo __('actions', 'Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr class="border-t border-gray-700 hover:bg-gray-750">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <img src="../<?php echo htmlspecialchars($review['logo_path']); ?>" alt="<?php echo htmlspecialchars($review['project_name']); ?>" class="logo-thumbnail mr-2">
                                            <span><?php echo htmlspecialchars($review['project_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php echo htmlspecialchars($review['author_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 star-rating">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $review['rating'] ? '★' : '☆';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="max-w-xs truncate">
                                            <?php echo !empty($review['comment']) ? htmlspecialchars($review['comment']) : '<span class="text-gray-500">—</span>'; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="status-badge status-<?php echo $review['approved'] ? 'approved' : 'pending'; ?>">
                                            <?php echo $review['approved'] ? __('approved', 'Approved') : __('pending', 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php echo date('Y-m-d', strtotime($review['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex space-x-2">
                                            <?php if (!$review['approved']): ?>
                                            <a href="reviews.php?action=approve&id=<?php echo $review['id']; ?>" class="text-green-400 hover:text-green-300">
                                                <?php echo __('approve', 'Approve'); ?>
                                            </a>
                                            <?php else: ?>
                                            <a href="reviews.php?action=disapprove&id=<?php echo $review['id']; ?>" class="text-yellow-400 hover:text-yellow-300">
                                                <?php echo __('disapprove', 'Disapprove'); ?>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($isAdmin): ?>
                                            <a href="reviews.php?action=delete&id=<?php echo $review['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('<?php echo __('confirm_delete', 'Are you sure you want to delete this review? This cannot be undone.'); ?>')">
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
                        <a href="?status=<?php echo $filterStatus; ?>&page=<?php echo $page - 1; ?>" class="bg-gray-700 hover:bg-gray-600">&laquo; <?php echo __('prev', 'Previous'); ?></a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="bg-blue-600 text-white"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="?status=<?php echo $filterStatus; ?>&page=<?php echo $i; ?>" class="bg-gray-700 hover:bg-gray-600"><?php echo $i; ?></a>
                        <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?status=<?php echo $filterStatus; ?>&page=<?php echo $page + 1; ?>" class="bg-gray-700 hover:bg-gray-600"><?php echo __('next', 'Next'); ?> &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="p-6 text-center">
                        <p><?php echo __('no_reviews', 'No reviews found.'); ?></p>
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