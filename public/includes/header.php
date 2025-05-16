<?php
/**
 * Шапка сайта с SEO оптимизацией
 * CryptoLogoWall
 */

// Запрещаем прямой доступ к файлу
if (!defined('INCLUDED')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Прямой доступ к файлу запрещен');
}

// Формируем SEO данные
$seoTitle = isset($pageTitle) ? $seo->generateTitle($pageTitle, true) : $seo->generateTitle(__('site_name'));
$seoDescription = isset($pageDescription) ? $pageDescription : $seo->getSettingValue('meta_description');
$seoKeywords = isset($pageKeywords) ? $pageKeywords : $seo->getSettingValue('meta_keywords');
$seoCanonical = isset($pageCanonical) ? $pageCanonical : $seo->getCurrentUrl();
$seoImage = isset($pageImage) ? $pageImage : $seo->getSettingValue('og_image');
$seoType = isset($pageType) ? $pageType : 'website';
$seoNoIndex = isset($pageNoIndex) ? $pageNoIndex : false;

// Формируем мета-теги
$seoMetaTags = $seo->generateMetaTags([
    'title' => $seoTitle,
    'description' => $seoDescription,
    'keywords' => $seoKeywords,
    'canonical' => $seoCanonical,
    'og_image' => $seoImage,
    'og_type' => $seoType,
    'noindex' => $seoNoIndex,
    'custom_tags' => isset($customMetaTags) ? $customMetaTags : []
]);

// Формируем хлебные крошки
$breadcrumbsHtml = '';
if (isset($breadcrumbs) && is_array($breadcrumbs)) {
    $breadcrumbsHtml = $seo->generateBreadcrumbs($breadcrumbs);
}

// Формируем schema.org разметку
$schemaHtml = '';
if (isset($project) && is_array($project)) {
    $schemaHtml = $seo->generateProjectSchema($project);
}

// Определяем текущую страницу
$currentPage = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seoTitle; ?></title>
    
    <!-- SEO Meta Tags -->
    <?php echo $seoMetaTags; ?>
    
    <!-- Schema.org Markup -->
    <?php echo $schemaHtml; ?>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/img/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/img/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/img/apple-touch-icon.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <!-- Google Analytics -->
    <?php echo $seo->generateGoogleAnalytics(); ?>
    
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
            display: inline-block;
            text-decoration: none;
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
        
        /* Хлебные крошки */
        .breadcrumbs {
            margin-bottom: 20px;
            padding: 10px 0;
        }

        .breadcrumbs ol {
            display: flex;
            flex-wrap: wrap;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .breadcrumbs li {
            display: inline-flex;
            align-items: center;
            color: #a0aec0;
            font-size: 14px;
        }

        .breadcrumbs a {
            color: #a0aec0;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumbs a:hover {
            color: #ffffff;
        }

        .breadcrumbs .separator {
            margin: 0 8px;
            color: #4a5568;
        }
        
        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Дополнительные стили могут быть определены в страницах */
        <?php if (isset($additionalStyles)) echo $additionalStyles; ?>
    </style>
    
    <?php if (isset($additionalHead)) echo $additionalHead; ?>
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
                        <img src="assets/img/flags/<?php echo $currentLang; ?>.svg" alt="<?php echo $langNames[$currentLang]; ?>" class="lang-flag">
                    </span>
                    <span class="ml-2 hidden md:inline"><?php echo $langNames[$currentLang]; ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="ml-1" viewBox="0 0 16 16">
                        <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                    </svg>
                </div>
                
                <div class="lang-dropdown">
                    <?php foreach ($lang->getAvailableLanguages() as $code): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => $code])); ?>" class="lang-option" aria-label="<?php echo $langNames[$code]; ?>">
                        <img src="assets/img/flags/<?php echo $code; ?>.svg" alt="<?php echo $langNames[$code]; ?>" class="lang-flag">
                        <span><?php echo $langNames[$code]; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Кнопка добавления логотипа -->
            <a href="add-logo.php" class="add-logo-button">
                <?php echo __('add_logo', 'Add Your Logo for $1'); ?>
            </a>
        </div>
    </header>
    
    <?php if (!empty($breadcrumbsHtml)): ?>
    <!-- Хлебные крошки -->
    <div class="container mx-auto px-6 md:px-10">
        <?php echo $breadcrumbsHtml; ?>
    </div>
    <?php endif; ?>