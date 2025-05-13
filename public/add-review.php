<?php
/**
 * Страница просмотра логотипа и отзывов
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/security.php';
require_once 'includes/telegramBot.php';

// Получаем ID проекта
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Если ID не передан, перенаправляем на главную
if ($projectId <= 0) {
    header('Location: index.php');
    exit;
}

// Получаем информацию о проекте
$project = $db->fetchOne(
    "SELECT * FROM projects WHERE id = ?",
    [$projectId]
);

// Если проект не найден, перенаправляем на главную
if (!$project) {
    header('Location: index.php');
    exit;
}

// Если проект не активен и не в статусе ожидания оплаты, проверяем статус платежа
if ($project['active'] != 1 && $project['payment_status'] === 'pending' && $project['payment_id']) {
    $paymentStatus = $telegramBot->checkPaymentStatus($project['payment_id']);
    if ($paymentStatus && isset($paymentStatus['status']) && $paymentStatus['status'] === 'completed') {
        // Обновляем проект, так как оплата прошла
        $db->update('projects', ['active' => 1], 'id = ?', [$projectId]);
        $project['active'] = 1;
    }
}

// Получаем отзывы для проекта
$reviews = $db->fetchAll(
    "SELECT * FROM reviews WHERE project_id = ? AND approved = 1 ORDER BY created_at DESC",
    [$projectId]
);

// Вычисляем средний рейтинг
$averageRating = 0;
$reviewCount = count($reviews);

if ($reviewCount > 0) {
    $totalRating = 0;
    foreach ($reviews as $review) {
        $totalRating += $review['rating'];
    }
    $averageRating = round($totalRating / $reviewCount, 1);
}

// Проверка наличия сообщения об успешном добавлении отзыва
$reviewSuccess = isset($_GET['review_success']) && $_GET['review_success'] == 1;

// Заголовок страницы
$pageTitle = $project['name'] . ' - ' . __('site_name');

?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($project['name']) . ' - ' . __('project_meta_description', 'View details and reviews for this crypto project.'); ?>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Дополнительные стили -->
    <style>
        /* Основные стили */
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Логотип сайта */
        .site-logo {
            font-weight: 800;
            font-size: 1.8rem;
            background: linear-gradient(45deg, #ff6b6b, #7d71ff, #56c2ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        /* Кнопка добавления отзыва */
        .add-review-button {
            background: linear-gradient(45deg, #ff6b6b, #7d71ff);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            outline: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(123, 97, 255, 0.3);
            position: relative;
            overflow: hidden;
            z-index: 1;
            display: inline-block;
            text-decoration: none;
        }
        
        .add-review-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #56c2ff, #7d71ff);
            transition: left 0.5s ease;
            z-index: -1;
        }
        
        .add-review-button:hover::before {
            left: 0;
        }
        
        /* Карточка проекта */
        .project-card {
            background-color: #1e1e1e;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        /* Стили для рейтинга */
        .stars-container {
            display: flex;
            align-items: center;
        }
        
        .stars {
            display: flex;
        }
        
        .star {
            font-size: 24px;
            margin-right: 2px;
        }
        
        .star.full {
            color: #ffdd00;
        }
        
        .star.half {
            position: relative;
            color: #3a3a3a;
        }
        
        .star.half::before {
            content: '★';
            position: absolute;
            color: #ffdd00;
            width: 50%;
            overflow: hidden;
        }
        
        .star.empty {
            color: #3a3a3a;
        }
        
        /* Стили для карточек отзывов */
        .review-card {
            background-color: #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        
        .review-author {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .review-date {
            color: #888;
            font-size: 0.9rem;
        }
        
        .review-rating {
            display: flex;
            margin-bottom: 10px;
        }
        
        .review-rating .star {
            font-size: 18px;
        }
        
        .review-content {
            line-height: 1.6;
        }
        
        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Стили для пендинга оплаты */
        .payment-pending {
            background-color: rgba(245, 158, 11, 0.2);
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }
        
        .payment-button {
            display: inline-block;
            background: linear-gradient(45deg, #0088cc, #2a9adc);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(42, 154, 220, 0.3);
        }
        
        .payment-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(42, 154, 220, 0.4);
        }
    </style>
</head>
<body>
    <!-- Шапка сайта -->
    <header class="py-4 px-6 md:px-10 flex justify-between items-center border-b border-gray-800">
        <a href="index.php" class="site-logo"><?php echo __('site_name'); ?></a>
        
        <div>
            <a href="index.php" class="text-gray-400 hover:text-white mr-6">
                <?php echo __('back_to_wall', 'Back to Wall'); ?>
            </a>
            
            <a href="add-logo.php" class="add-review-button">
                <?php echo __('add_logo', 'Add Your Logo for $1'); ?>
            </a>
        </div>
    </header>
    
    <!-- Основное содержимое -->
    <main class="py-10 px-4 md:px-0">
        <div class="max-w-4xl mx-auto">
            <!-- Карточка проекта -->
            <div class="project-card animate-fade-in">
                <?php if ($project['active'] != 1 && isset($project['payment_status']) && $project['payment_status'] === 'pending'): ?>
                    <!-- Блок пендинга оплаты -->
                    <div class="payment-pending">
                        <h3 class="font-bold text-xl mb-2"><?php echo __('payment_pending_title', 'Payment Pending'); ?></h3>
                        <p><?php echo __('payment_pending_message', 'Your logo is awaiting payment confirmation. Once the payment is processed, your logo will be displayed on our wall.'); ?></p>
                        
                        <?php if (isset($project['payment_id']) && $project['payment_id']): ?>
                            <a href="<?php echo $telegramBot->createLogoPaymentLink($project['id'], $project['name'])['payment_url']; ?>" class="payment-button" target="_blank">
                                <?php echo __('complete_payment', 'Complete Payment ($1)'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="p-6 md:p-8">
                    <!-- Верхняя часть с логотипом и информацией -->
                    <div class="flex flex-col md:flex-row">
                        <!-- Логотип -->
                        <div class="md:w-1/3 mb-6 md:mb-0 flex justify-center">
                            <img src="<?php echo htmlspecialchars($project['logo_path']); ?>" alt="<?php echo htmlspecialchars($project['name']); ?>" class="max-w-full max-h-48 object-contain rounded-lg">
                        </div>
                        
                        <!-- Информация о проекте -->
                        <div class="md:w-2/3 md:pl-8">
                            <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($project['name']); ?></h1>
                            
                            <!-- Рейтинг -->
                            <div class="stars-container mb-4">
                                <div class="stars">
                                    <?php
                                    $fullStars = floor($averageRating);
                                    $halfStar = $averageRating - $fullStars >= 0.5;
                                    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                    
                                    // Полные звезды
                                    for ($i = 0; $i < $fullStars; $i++) {
                                        echo '<span class="star full">★</span>';
                                    }
                                    
                                    // Половина звезды
                                    if ($halfStar) {
                                        echo '<span class="star half">★</span>';
                                    }
                                    
                                    // Пустые звезды
                                    for ($i = 0; $i < $emptyStars; $i++) {
                                        echo '<span class="star empty">★</span>';
                                    }
                                    ?>
                                </div>
                                <div class="ml-3 text-gray-400">
                                    <?php echo $averageRating; ?>/5 
                                    (<?php echo sprintf(
                                        _n(
                                            '%d review', 
                                            '%d reviews', 
                                            $reviewCount
                                        ), 
                                        $reviewCount
                                    ); ?>)
                                </div>
                            </div>
                            
                            <!-- Описание проекта -->
                            <?php if (!empty($project['description'])): ?>
                                <div class="mb-4 text-gray-300">
                                    <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Дополнительная информация -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <?php if (!empty($project['website'])): ?>
                                    <div>
                                        <span class="text-gray-400"><?php echo __('website', 'Website'); ?>:</span>
                                        <a href="<?php echo htmlspecialchars($project['website']); ?>" target="_blank" class="ml-2 text-blue-400 hover:text-blue-300 break-all"><?php echo htmlspecialchars($project['website']); ?></a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($project['telegram_username'])): ?>
                                    <div>
                                        <span class="text-gray-400"><?php echo __('telegram', 'Telegram'); ?>:</span>
                                        <a href="https://t.me/<?php echo str_replace('@', '', htmlspecialchars($project['telegram_username'])); ?>" target="_blank" class="ml-2 text-blue-400 hover:text-blue-300">
                                            <?php echo htmlspecialchars($project['telegram_username']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <span class="text-gray-400"><?php echo __('added_on', 'Added on'); ?>:</span>
                                    <span class="ml-2"><?php echo date('F j, Y', strtotime($project['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Секция отзывов -->
            <div class="mt-12">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold"><?php echo __('reviews_title', 'Reviews'); ?></h2>
                    
                    <a href="add-review.php?id=<?php echo $projectId; ?>" class="add-review-button">
                        <?php echo __('add_review_button', 'Add Review $1'); ?>
                    </a>
                </div>
                
                <?php if ($reviewSuccess): ?>
                    <div class="bg-green-900/30 border-l-4 border-green-500 p-4 mb-6 rounded-r-md">
                        <p class="text-green-400">
                            <?php echo __('review_success_message', 'Your review has been submitted and will be visible after payment confirmation.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Список отзывов -->
                <?php if (count($reviews) > 0): ?>
                    <div class="space-y-6">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-author"><?php echo htmlspecialchars($review['author_name']); ?></div>
                                    <div class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></div>
                                </div>
                                
                                <div class="review-rating">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $review['rating']) {
                                            echo '<span class="star full">★</span>';
                                        } else {
                                            echo '<span class="star empty">★</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <?php if (!empty($review['comment'])): ?>
                                    <div class="review-content">
                                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 bg-gray-900 rounded-lg">
                        <p class="text-xl text-gray-400 mb-6"><?php echo __('no_reviews_yet', 'No reviews yet. Be the first to review this project!'); ?></p>
                        <a href="add-review.php?id=<?php echo $projectId; ?>" class="add-review-button">
                            <?php echo __('add_first_review', 'Add First Review'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Подвал сайта -->
    <footer class="bg-gray-900 py-8 px-6 border-t border-gray-800 mt-10">
        <div class="max-w-6xl mx-auto text-center">
            <p class="text-gray-500">
                &copy; <?php echo date('Y'); ?> <?php echo __('site_name'); ?>. <?php echo __('all_rights', 'All Rights Reserved'); ?>.
            </p>
        </div>
    </footer>
</body>
</html>