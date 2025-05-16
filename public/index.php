<?php
/**
 * Главная страница
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/security.php';

// Получаем статистику
$totalLogos = $db->count('projects', 'active = 1');
$totalReviews = $db->count('reviews', 'approved = 1');

// Получаем последние добавленные логотипы для мобильной версии
$latestLogos = $db->fetchAll(
    "SELECT id, name, logo_path, website FROM projects 
     WHERE active = 1 
     ORDER BY created_at DESC 
     LIMIT 8"
);

// Заголовок страницы
$pageTitle = __('site_name') . ' - ' . __('tagline', 'Crypto Logo Wall');

// Получаем текущий URL без параметров lang (для корректной смены языка)
$currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
// Сохраняем все GET параметры кроме lang для добавления к ссылкам смены языка
$queryParams = $_GET;
if (isset($queryParams['lang'])) {
    unset($queryParams['lang']);
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo __('meta_description', 'Add your cryptocurrency logo to our creative wall for just $1'); ?>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Дополнительные стили -->
    <style>
        /* Основные стили */
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
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
        
        /* Кнопка добавления логотипа */
        .add-logo-button {
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
        }
        
        .add-logo-button::before {
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
        
        .add-logo-button:hover::before {
            left: 0;
        }
        
        /* Стили для панели деталей логотипа */
        .logo-details-panel {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(25, 25, 25, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        
        .details-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .close-button {
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .close-button:hover {
            opacity: 1;
        }
        
        .rating-container {
            margin-bottom: 15px;
        }
        
        .stars {
            display: flex;
            margin-bottom: 5px;
        }
        
        .star {
            font-size: 1.5rem;
            margin-right: 5px;
        }
        
        .star.full {
            color: #ffdd00;
        }
        
        .star.half {
            position: relative;
            color: #555;
        }
        
        .star.half::before {
            content: '★';
            position: absolute;
            color: #ffdd00;
            width: 50%;
            overflow: hidden;
        }
        
        .star.empty {
            color: #555;
        }
        
        .rating-text {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .website-link {
            display: inline-block;
            color: #56c2ff;
            margin-bottom: 15px;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .website-link:hover {
            color: #7d71ff;
            text-decoration: underline;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-button, .review-button {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .view-button {
            background-color: #2a2a2a;
            color: white;
        }
        
        .view-button:hover {
            background-color: #3a3a3a;
        }
        
        .review-button {
            background: linear-gradient(45deg, #ff6b6b, #7d71ff);
            color: white;
        }
        
        .review-button:hover {
            background: linear-gradient(45deg, #ff7e7e, #8f84ff);
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .logo-wall-container {
                height: 400px;
            }
            
            .mobile-logos {
                display: grid;
            }
        }
        
        /* Анимации */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Языковое меню */
        .lang-selector {
            position: relative;
        }
        
        .lang-current {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }
        
        .lang-current:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .lang-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: #222;
            border-radius: 5px;
            padding: 5px 0;
            min-width: 120px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 100;
        }
        
        .lang-selector:hover .lang-dropdown {
            display: block;
        }
        
        .lang-option {
            padding: 8px 15px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #fff;
        }
        
        .lang-option:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .lang-flag {
            width: 20px;
            height: 15px;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Шапка сайта -->
    <header class="py-4 px-6 md:px-10 flex justify-between items-center border-b border-gray-800">
        <a href="index.php" class="site-logo"><?php echo __('site_name'); ?></a>
        
        <div class="flex items-center space-x-4">
            <!-- Переключатель языков -->
            <div class="lang-selector">
                <div class="lang-current">
                    <?php 
                    $currentLang = $lang->getCurrentLanguage();
                    $langNames = $lang->getLanguageNames();
                    ?>
                    <span class="flag-icon flag-icon-<?php echo $currentLang; ?>">
                        <img src="assets/img/flags/flag-<?php echo $currentLang; ?>.svg" alt="<?php echo $langNames[$currentLang]; ?>" class="lang-flag">
                    </span>
                    <span class="ml-2 hidden md:inline"><?php echo $langNames[$currentLang]; ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="ml-1" viewBox="0 0 16 16">
                        <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                    </svg>
                </div>
                
                <div class="lang-dropdown">
                    <?php foreach ($lang->getAvailableLanguages() as $code): ?>
                    <a href="<?php echo $currentUrl . '?' . http_build_query(array_merge($queryParams, ['lang' => $code])); ?>" class="lang-option" aria-label="<?php echo $langNames[$code]; ?>">
                        <img src="assets/img/flags/flag-<?php echo $code; ?>.svg" alt="<?php echo $langNames[$code]; ?>" class="lang-flag">
                        <span><?php echo $langNames[$code]; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Кнопка добавления логотипа -->
            <button id="add-logo-button" class="add-logo-button">
                <?php echo __('add_logo', 'Add Your Logo for $1'); ?>
            </button>
        </div>
    </header>
    
    <!-- Главный контейнер -->
    <main>
        <!-- Заголовок и описание -->
        <section class="text-center py-10 px-6 md:py-16">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">
                <?php echo __('main_title', 'Crypto Logo Wall'); ?>
            </h1>
            <p class="text-xl md:text-2xl text-gray-400 max-w-3xl mx-auto">
                <?php echo __('main_description', 'Showcase your cryptocurrency project on our creative 3D wall for just $1. Add your logo and get noticed!'); ?>
            </p>
            
            <!-- Статистика -->
            <div class="flex justify-center mt-10 space-x-8 md:space-x-16">
                <div class="text-center float-animation" style="animation-delay: 0s;">
                    <div class="text-3xl md:text-4xl font-bold text-blue-400"><?php echo $totalLogos; ?></div>
                    <div class="text-sm md:text-base text-gray-400"><?php echo __('logos_count', 'Logos'); ?></div>
                </div>
                <div class="text-center float-animation" style="animation-delay: 0.5s;">
                    <div class="text-3xl md:text-4xl font-bold text-purple-400"><?php echo $totalReviews; ?></div>
                    <div class="text-sm md:text-base text-gray-400"><?php echo __('reviews_count', 'Reviews'); ?></div>
                </div>
            </div>
        </section>
        
        <!-- 3D стена логотипов (для десктопа) -->
        <section id="logo-wall-section" class="hidden md:block">
            <div id="logo-wall" class="logo-wall-container h-screen"></div>
        </section>
        
        <!-- Сетка логотипов (для мобильных) -->
        <section class="md:hidden px-4 py-8">
            <h2 class="text-2xl font-bold mb-6 text-center">
                <?php echo __('latest_logos', 'Latest Logos'); ?>
            </h2>
            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php foreach ($latestLogos as $logo): ?>
                <a href="view.php?id=<?php echo $logo['id']; ?>" class="block bg-gray-900 rounded-lg p-4 text-center transition-transform hover:scale-105">
                    <img src="<?php echo $logo['logo_path']; ?>" alt="<?php echo $logo['name']; ?>" class="w-24 h-24 object-contain mx-auto mb-3">
                    <h3 class="truncate font-semibold"><?php echo $logo['name']; ?></h3>
                </a>
                <?php endforeach; ?>
                
                <?php if (count($latestLogos) === 0): ?>
                <div class="col-span-2 sm:col-span-4 text-center py-10">
                    <p class="text-gray-400 mb-4"><?php echo __('no_logos_yet', 'No logos added yet. Be the first!'); ?></p>
                    <a href="add-logo.php" class="add-logo-button inline-block">
                        <?php echo __('add_logo', 'Add Your Logo for $1'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Секция с призывом к действию -->
        <section class="bg-gradient-to-r from-blue-900/20 to-purple-900/20 py-16 px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                <?php echo __('cta_title', 'Boost Your Crypto Project Visibility'); ?>
            </h2>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto mb-10">
                <?php echo __('cta_description', 'Add your logo to our wall, get reviews, increase exposure. Simple, effective, affordable.'); ?>
            </p>
            <a href="add-logo.php" class="add-logo-button text-lg py-3 px-8">
                <?php echo __('add_logo_cta', 'Add Your Logo Now'); ?>
            </a>
        </section>
    </main>
    
    <!-- Подвал сайта -->
    <footer class="bg-gray-900 py-8 px-6 border-t border-gray-800">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-xl font-bold mb-4"><?php echo __('site_name'); ?></h3>
                    <p class="text-gray-400">
                        <?php echo __('footer_description', 'The place for cryptocurrency projects to showcase their logos and get community feedback.'); ?>
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4"><?php echo __('links', 'Links'); ?></h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white"><?php echo __('home', 'Home'); ?></a></li>
                        <li><a href="add-logo.php" class="text-gray-400 hover:text-white"><?php echo __('add_logo_link', 'Add Logo'); ?></a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white"><?php echo __('about', 'About'); ?></a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white"><?php echo __('contact', 'Contact'); ?></a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4"><?php echo __('contact_us', 'Contact Us'); ?></h3>
                    <p class="text-gray-400 mb-2">
                        <?php echo __('contact_email', 'Email'); ?>: <a href="mailto:contact@example.com" class="hover:text-white">contact@example.com</a>
                    </p>
                    <p class="text-gray-400">
                        <?php echo __('contact_telegram', 'Telegram'); ?>: <a href="https://t.me/examplechannel" target="_blank" class="hover:text-white">@examplechannel</a>
                    </p>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-6 text-center">
                <p class="text-gray-500">
                    &copy; <?php echo date('Y'); ?> <?php echo __('site_name'); ?>. <?php echo __('all_rights', 'All Rights Reserved'); ?>.
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Скрипты -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.152.0/build/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="assets/js/wall.js"></script>
    
    <script>
        // JavaScript для кнопки добавления логотипа
        document.getElementById('add-logo-button').addEventListener('click', function() {
            window.location.href = 'add-logo.php';
        });
        
        // JavaScript для мобильной версии
        document.addEventListener('DOMContentLoaded', function() {
            // Проверяем, мобильная ли версия
            const isMobile = window.innerWidth < 768;
            
            // Если мобильная версия, скрываем 3D стену
            if (isMobile) {
                const logoWallSection = document.getElementById('logo-wall-section');
                if (logoWallSection) {
                    logoWallSection.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>