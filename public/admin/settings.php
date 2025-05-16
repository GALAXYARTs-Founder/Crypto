<?php
/**
 * Административная панель - настройки сайта
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
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$section = isset($_GET['section']) ? $_GET['section'] : 'general';

// Обработка сообщений
$message = '';
$messageType = '';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $message = __('error_security', 'Security verification failed. Please try again.');
        $messageType = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Определяем, какую секцию настроек обрабатываем
            switch ($section) {
                case 'general':
                    // Общие настройки сайта
                    processSiteSettings();
                    break;
                    
                case 'seo':
                    // SEO настройки
                    processSeoSettings();
                    break;
                    
                case 'integrations':
                    // Настройки интеграций
                    processIntegrationSettings();
                    break;
                    
                case 'backup':
                    // Резервное копирование
                    processBackupSettings();
                    break;
            }
            
            $db->commit();
            
            // Логирование действия
            $security->logActivity($_SESSION['user_id'], 'update_settings', 'settings', $section);
            
            // Сообщение об успехе
            $message = __('settings_updated', 'Settings have been updated successfully.');
            $messageType = 'success';
            
        } catch (Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $db->rollBack();
            
            // Сообщение об ошибке
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Обработка общих настроек сайта
function processSiteSettings() {
    global $db, $lang;
    
    // Получаем данные формы
    $siteName = isset($_POST['site_name']) ? $_POST['site_name'] : '';
    $tagline = isset($_POST['tagline']) ? $_POST['tagline'] : '';
    $adminEmail = isset($_POST['admin_email']) ? $_POST['admin_email'] : '';
    $defaultLang = isset($_POST['default_lang']) ? $_POST['default_lang'] : DEFAULT_LANG;
    $logoPrice = isset($_POST['logo_price']) ? (float)$_POST['logo_price'] : 1.00;
    $reviewPrice = isset($_POST['review_price']) ? (float)$_POST['review_price'] : 1.00;
    
    // Валидация
    if (empty($siteName)) {
        throw new Exception(__('error_site_name_required', 'Site name is required.'));
    }
    
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception(__('error_admin_email_invalid', 'Please enter a valid admin email address.'));
    }
    
    if ($logoPrice <= 0 || $reviewPrice <= 0) {
        throw new Exception(__('error_price_invalid', 'Prices must be greater than zero.'));
    }
    
    // Сохраняем настройки сайта в translations table
    foreach ($lang->getAvailableLanguages() as $langCode) {
        // Обновляем настройки для текущего языка
        $siteNameKey = $db->fetchOne(
            "SELECT id FROM translations WHERE lang_code = ? AND translation_key = 'site_name'",
            [$langCode]
        );
        
        if ($siteNameKey) {
            $db->update('translations', 
                ['translation_value' => $siteName], 
                'lang_code = ? AND translation_key = ?', 
                [$langCode, 'site_name']
            );
        } else {
            $db->insert('translations', [
                'lang_code' => $langCode,
                'translation_key' => 'site_name',
                'translation_value' => $siteName
            ]);
        }
        
        // Обновляем tagline
        $taglineKey = $db->fetchOne(
            "SELECT id FROM translations WHERE lang_code = ? AND translation_key = 'tagline'",
            [$langCode]
        );
        
        if ($taglineKey) {
            $db->update('translations', 
                ['translation_value' => $tagline], 
                'lang_code = ? AND translation_key = ?', 
                [$langCode, 'tagline']
            );
        } else {
            $db->insert('translations', [
                'lang_code' => $langCode,
                'translation_key' => 'tagline',
                'translation_value' => $tagline
            ]);
        }
    }
    
    // Обновляем значения в config.php (используя константы как маркеры в файле)
    $configPath = dirname(__FILE__, 2) . '/config.php';
    $configContent = file_get_contents($configPath);
    
    // Обновляем значения констант в файле
    $configContent = preg_replace('/define\(\'ADMIN_EMAIL\', \'.*?\'\);/', "define('ADMIN_EMAIL', '$adminEmail');", $configContent);
    $configContent = preg_replace('/define\(\'DEFAULT_LANG\', \'.*?\'\);/', "define('DEFAULT_LANG', '$defaultLang');", $configContent);
    
    // Записываем обновленный файл конфигурации
    file_put_contents($configPath, $configContent);
    
    // Создаем файл с константами для цен (чтобы их можно было легко обновлять)
    $pricesPath = dirname(__FILE__, 2) . '/includes/prices.php';
    $pricesContent = "<?php\n/**\n * Файл с ценами\n * CryptoLogoWall\n */\n\n";
    $pricesContent .= "define('LOGO_PRICE', $logoPrice);\n";
    $pricesContent .= "define('REVIEW_PRICE', $reviewPrice);\n";
    
    file_put_contents($pricesPath, $pricesContent);
}

// Обработка SEO настроек
function processSeoSettings() {
    global $db, $lang;
    
    // Получаем данные формы
    $metaDescription = isset($_POST['meta_description']) ? $_POST['meta_description'] : '';
    $metaKeywords = isset($_POST['meta_keywords']) ? $_POST['meta_keywords'] : '';
    $ogTitle = isset($_POST['og_title']) ? $_POST['og_title'] : '';
    $ogDescription = isset($_POST['og_description']) ? $_POST['og_description'] : '';
    $ogImage = isset($_POST['og_image']) ? $_POST['og_image'] : '';
    $twitterCard = isset($_POST['twitter_card']) ? $_POST['twitter_card'] : '';
    $twitterSite = isset($_POST['twitter_site']) ? $_POST['twitter_site'] : '';
    $googleAnalyticsId = isset($_POST['google_analytics_id']) ? $_POST['google_analytics_id'] : '';
    $googleVerification = isset($_POST['google_verification']) ? $_POST['google_verification'] : '';
    
    // Сохраняем SEO настройки в translations для всех языков
    foreach ($lang->getAvailableLanguages() as $langCode) {
        // Meta Description
        updateTranslation($langCode, 'meta_description', $metaDescription);
        
        // Meta Keywords
        updateTranslation($langCode, 'meta_keywords', $metaKeywords);
        
        // OG Title
        updateTranslation($langCode, 'og_title', $ogTitle);
        
        // OG Description
        updateTranslation($langCode, 'og_description', $ogDescription);
    }
    
    // Сохраняем остальные SEO настройки в отдельной таблице settings
    saveSettingValue('og_image', $ogImage);
    saveSettingValue('twitter_card', $twitterCard);
    saveSettingValue('twitter_site', $twitterSite);
    saveSettingValue('google_analytics_id', $googleAnalyticsId);
    saveSettingValue('google_verification', $googleVerification);
    
    // Обновляем robots.txt
    $robotsContent = isset($_POST['robots_txt']) ? trim($_POST['robots_txt']) : '';
    if (!empty($robotsContent)) {
        $robotsPath = dirname(__FILE__, 2) . '/robots.txt';
        file_put_contents($robotsPath, $robotsContent);
    }
    
    // Обновляем sitemap.xml если загружен новый файл
    if (isset($_FILES['sitemap_file']) && $_FILES['sitemap_file']['error'] === UPLOAD_ERR_OK) {
        $sitemapPath = dirname(__FILE__, 2) . '/sitemap.xml';
        move_uploaded_file($_FILES['sitemap_file']['tmp_name'], $sitemapPath);
    }
}

// Обработка настроек интеграций
function processIntegrationSettings() {
    // Получаем данные формы
    $telegramBotToken = isset($_POST['telegram_bot_token']) ? $_POST['telegram_bot_token'] : '';
    $telegramBotUsername = isset($_POST['telegram_bot_username']) ? $_POST['telegram_bot_username'] : '';
    
    // Обновляем значения в config.php
    $configPath = dirname(__FILE__, 2) . '/config.php';
    $configContent = file_get_contents($configPath);
    
    // Обновляем значения констант в файле
    $configContent = preg_replace('/define\(\'TELEGRAM_BOT_TOKEN\', \'.*?\'\);/', "define('TELEGRAM_BOT_TOKEN', '$telegramBotToken');", $configContent);
    $configContent = preg_replace('/define\(\'TELEGRAM_BOT_USERNAME\', \'.*?\'\);/', "define('TELEGRAM_BOT_USERNAME', '$telegramBotUsername');", $configContent);
    
    // Записываем обновленный файл конфигурации
    file_put_contents($configPath, $configContent);
}

// Обработка настроек резервного копирования
function processBackupSettings() {
    // Получение формы не требуется, так как это активное действие
    
    // Генерируем резервную копию базы данных
    $backupDir = dirname(__FILE__, 2) . '/backups';
    
    // Создаем директорию, если она не существует
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Создаем файл бэкапа с датой
    $backupFileName = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Формируем команду для дампа базы данных
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s',
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        $backupFileName
    );
    
    // Выполняем команду
    exec($command, $output, $returnVar);
    
    // Проверяем успешность выполнения
    if ($returnVar !== 0) {
        throw new Exception(__('error_backup_failed', 'Failed to create database backup. Please check your server configuration.'));
    }
    
    // Если необходимо, можно добавить функционал для сжатия бэкапа
    $zipFileName = $backupFileName . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($backupFileName, basename($backupFileName));
        $zip->close();
        
        // Удаляем исходный SQL файл
        unlink($backupFileName);
    } else {
        throw new Exception(__('error_backup_zip_failed', 'Failed to create compressed backup file.'));
    }
}

// Функция для обновления перевода
function updateTranslation($langCode, $key, $value) {
    global $db;
    
    $existingTranslation = $db->fetchOne(
        "SELECT id FROM translations WHERE lang_code = ? AND translation_key = ?",
        [$langCode, $key]
    );
    
    if ($existingTranslation) {
        $db->update('translations', 
            ['translation_value' => $value], 
            'lang_code = ? AND translation_key = ?', 
            [$langCode, $key]
        );
    } else {
        $db->insert('translations', [
            'lang_code' => $langCode,
            'translation_key' => $key,
            'translation_value' => $value
        ]);
    }
}

// Функция для сохранения значения настройки
function saveSettingValue($key, $value) {
    global $db;
    
    // Проверяем, существует ли таблица settings
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'settings'");
    
    if (!$tableExists) {
        // Создаем таблицу настроек, если она не существует
        $db->query("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");
    }
    
    // Проверяем, существует ли настройка
    $existingSetting = $db->fetchOne(
        "SELECT id FROM settings WHERE setting_key = ?",
        [$key]
    );
    
    if ($existingSetting) {
        $db->update('settings', 
            ['setting_value' => $value], 
            'setting_key = ?', 
            [$key]
        );
    } else {
        $db->insert('settings', [
            'setting_key' => $key,
            'setting_value' => $value
        ]);
    }
}

// Функция для получения значения настройки
function getSettingValue($key, $default = '') {
    global $db;
    
    // Проверяем, существует ли таблица settings
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'settings'");
    
    if (!$tableExists) {
        return $default;
    }
    
    $setting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = ?",
        [$key]
    );
    
    return $setting ? $setting['setting_value'] : $default;
}

// Получаем текущие настройки
function getCurrentSettings() {
    global $db, $lang;
    
    $settings = [
        'site_name' => '',
        'tagline' => '',
        'admin_email' => ADMIN_EMAIL,
        'default_lang' => DEFAULT_LANG,
        'logo_price' => 1.0,
        'review_price' => 1.0,
        'meta_description' => '',
        'meta_keywords' => '',
        'og_title' => '',
        'og_description' => '',
        'og_image' => getSettingValue('og_image'),
        'twitter_card' => getSettingValue('twitter_card'),
        'twitter_site' => getSettingValue('twitter_site'),
        'google_analytics_id' => getSettingValue('google_analytics_id'),
        'google_verification' => getSettingValue('google_verification'),
        'telegram_bot_token' => TELEGRAM_BOT_TOKEN,
        'telegram_bot_username' => TELEGRAM_BOT_USERNAME
    ];
    
    // Получаем переводы
    $translations = $db->fetchAll(
        "SELECT lang_code, translation_key, translation_value 
         FROM translations 
         WHERE translation_key IN ('site_name', 'tagline', 'meta_description', 'meta_keywords', 'og_title', 'og_description')
         AND lang_code = ?",
        [DEFAULT_LANG]
    );
    
    foreach ($translations as $translation) {
        $settings[$translation['translation_key']] = $translation['translation_value'];
    }
    
    // Проверяем, есть ли файл с ценами
    $pricesPath = dirname(__FILE__, 2) . '/includes/prices.php';
    if (file_exists($pricesPath)) {
        include $pricesPath;
        if (defined('LOGO_PRICE')) $settings['logo_price'] = LOGO_PRICE;
        if (defined('REVIEW_PRICE')) $settings['review_price'] = REVIEW_PRICE;
    }
    
    // Получаем содержимое robots.txt
    $robotsPath = dirname(__FILE__, 2) . '/robots.txt';
    if (file_exists($robotsPath)) {
        $settings['robots_txt'] = file_get_contents($robotsPath);
    } else {
        $settings['robots_txt'] = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /includes/\nSitemap: " . SITE_URL . "/sitemap.xml";
    }
    
    return $settings;
}

// Получаем список бэкапов
function getBackupsList() {
    $backupDir = dirname(__FILE__, 2) . '/backups';
    $backups = [];
    
    if (is_dir($backupDir)) {
        $files = scandir($backupDir);
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'zip') {
                $fullPath = $backupDir . '/' . $file;
                
                $backups[] = [
                    'name' => $file,
                    'size' => filesize($fullPath),
                    'date' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'path' => $fullPath
                ];
            }
        }
        
        // Сортируем по дате (самые новые сверху)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
    return $backups;
}

// Получаем текущие настройки
$currentSettings = getCurrentSettings();

// Метка для включаемых файлов
define('INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('settings', 'Settings'); ?> - <?php echo __('admin_panel', 'Admin Panel'); ?></title>
    
    <!-- Tailwind CSS из CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style>
        /* Дополнительные стили */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background-color: #374151;
            border: 1px solid #4b5563;
            border-radius: 0.375rem;
            color: white;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        
        .form-hint {
            margin-top: 0.375rem;
            font-size: 0.875rem;
            color: #9ca3af;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #374151;
            margin-bottom: 1.5rem;
        }
        
        .nav-tab {
            padding: 0.75rem 1rem;
            border-bottom: 2px solid transparent;
            margin-right: 0.5rem;
            color: #9ca3af;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-tab:hover {
            color: #f9fafb;
        }
        
        .nav-tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
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
                                <a href="users.php" class="block px-4 py-2 rounded hover:bg-gray-700">
                                    <?php echo __('users', 'Users'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="settings.php" class="block px-4 py-2 rounded bg-blue-600 text-white">
                                    <?php echo __('settings', 'Settings'); ?>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            
            <!-- Основной контент -->
            <div class="w-full md:w-3/4">
                <h2 class="text-2xl font-bold mb-6"><?php echo __('site_settings', 'Site Settings'); ?></h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <!-- Вкладки настроек -->
                <div class="bg-gray-800 rounded-lg overflow-hidden p-6">
                    <div class="nav-tabs">
                        <a href="?section=general" class="nav-tab <?php echo $section === 'general' ? 'active' : ''; ?>">
                            <?php echo __('general_settings', 'General Settings'); ?>
                        </a>
                        <a href="?section=seo" class="nav-tab <?php echo $section === 'seo' ? 'active' : ''; ?>">
                            <?php echo __('seo_settings', 'SEO Settings'); ?>
                        </a>
                        <a href="?section=integrations" class="nav-tab <?php echo $section === 'integrations' ? 'active' : ''; ?>">
                            <?php echo __('integration_settings', 'Integrations'); ?>
                        </a>
                        <a href="?section=backup" class="nav-tab <?php echo $section === 'backup' ? 'active' : ''; ?>">
                            <?php echo __('backup_restore', 'Backup & Restore'); ?>
                        </a>
                    </div>
                    
                    <!-- Общие настройки -->
                    <div id="general-settings" class="tab-content <?php echo $section === 'general' ? 'active' : ''; ?>">
                        <form method="post" action="settings.php?section=general">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label for="site_name" class="form-label"><?php echo __('site_name', 'Site Name'); ?></label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($currentSettings['site_name']); ?>" class="form-input" required>
                                <p class="form-hint"><?php echo __('site_name_hint', 'The name of your website (displayed in header and browser title)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="tagline" class="form-label"><?php echo __('tagline', 'Tagline'); ?></label>
                                <input type="text" id="tagline" name="tagline" value="<?php echo htmlspecialchars($currentSettings['tagline']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('tagline_hint', 'A short description of your site (used in various places)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_email" class="form-label"><?php echo __('admin_email', 'Admin Email'); ?></label>
                                <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($currentSettings['admin_email']); ?>" class="form-input" required>
                                <p class="form-hint"><?php echo __('admin_email_hint', 'Used for notifications and contact form submissions'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="default_lang" class="form-label"><?php echo __('default_language', 'Default Language'); ?></label>
                                <select id="default_lang" name="default_lang" class="form-input">
                                    <?php foreach ($lang->getAvailableLanguages() as $code): ?>
                                    <?php $langNames = $lang->getLanguageNames(); ?>
                                    <option value="<?php echo $code; ?>" <?php echo $code === $currentSettings['default_lang'] ? 'selected' : ''; ?>>
                                        <?php echo $langNames[$code]; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-hint"><?php echo __('default_language_hint', 'Default language for visitors if their language is not detected'); ?></p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label for="logo_price" class="form-label"><?php echo __('logo_price', 'Logo Price ($)'); ?></label>
                                    <input type="number" id="logo_price" name="logo_price" value="<?php echo htmlspecialchars($currentSettings['logo_price']); ?>" step="0.01" min="0.01" class="form-input" required>
                                    <p class="form-hint"><?php echo __('logo_price_hint', 'Price for adding a logo to the wall'); ?></p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="review_price" class="form-label"><?php echo __('review_price', 'Review Price ($)'); ?></label>
                                    <input type="number" id="review_price" name="review_price" value="<?php echo htmlspecialchars($currentSettings['review_price']); ?>" step="0.01" min="0.01" class="form-input" required>
                                    <p class="form-hint"><?php echo __('review_price_hint', 'Price for adding a review to a project'); ?></p>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="save_settings" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded">
                                    <?php echo __('save_settings', 'Save Settings'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- SEO настройки -->
                    <div id="seo-settings" class="tab-content <?php echo $section === 'seo' ? 'active' : ''; ?>">
                        <form method="post" action="settings.php?section=seo" enctype="multipart/form-data">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label for="meta_description" class="form-label"><?php echo __('meta_description', 'Meta Description'); ?></label>
                                <textarea id="meta_description" name="meta_description" class="form-input form-textarea"><?php echo htmlspecialchars($currentSettings['meta_description']); ?></textarea>
                                <p class="form-hint"><?php echo __('meta_description_hint', 'A brief description of your site for search engines (up to 160 characters recommended)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="meta_keywords" class="form-label"><?php echo __('meta_keywords', 'Meta Keywords'); ?></label>
                                <input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo htmlspecialchars($currentSettings['meta_keywords']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('meta_keywords_hint', 'Keywords related to your site, separated by commas (less important for SEO nowadays)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="og_title" class="form-label"><?php echo __('og_title', 'Open Graph Title'); ?></label>
                                <input type="text" id="og_title" name="og_title" value="<?php echo htmlspecialchars($currentSettings['og_title']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('og_title_hint', 'Title for social media sharing (leave empty to use Site Name)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="og_description" class="form-label"><?php echo __('og_description', 'Open Graph Description'); ?></label>
                                <textarea id="og_description" name="og_description" class="form-input form-textarea"><?php echo htmlspecialchars($currentSettings['og_description']); ?></textarea>
                                <p class="form-hint"><?php echo __('og_description_hint', 'Description for social media sharing (leave empty to use Meta Description)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="og_image" class="form-label"><?php echo __('og_image', 'Open Graph Image URL'); ?></label>
                                <input type="url" id="og_image" name="og_image" value="<?php echo htmlspecialchars($currentSettings['og_image']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('og_image_hint', 'Image URL for social media sharing (1200x630px recommended)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="twitter_card" class="form-label"><?php echo __('twitter_card', 'Twitter Card Type'); ?></label>
                                <select id="twitter_card" name="twitter_card" class="form-input">
                                    <option value="summary" <?php echo $currentSettings['twitter_card'] === 'summary' ? 'selected' : ''; ?>>Summary</option>
                                    <option value="summary_large_image" <?php echo $currentSettings['twitter_card'] === 'summary_large_image' ? 'selected' : ''; ?>>Summary with Large Image</option>
                                </select>
                                <p class="form-hint"><?php echo __('twitter_card_hint', 'Type of Twitter card for social sharing'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="twitter_site" class="form-label"><?php echo __('twitter_site', 'Twitter Username'); ?></label>
                                <input type="text" id="twitter_site" name="twitter_site" value="<?php echo htmlspecialchars($currentSettings['twitter_site']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('twitter_site_hint', 'Your Twitter username (e.g. @yourusername)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="google_analytics_id" class="form-label"><?php echo __('google_analytics_id', 'Google Analytics ID'); ?></label>
                                <input type="text" id="google_analytics_id" name="google_analytics_id" value="<?php echo htmlspecialchars($currentSettings['google_analytics_id']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('google_analytics_hint', 'Google Analytics tracking ID (e.g. G-XXXXXXXXXX)'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="google_verification" class="form-label"><?php echo __('google_verification', 'Google Site Verification'); ?></label>
                                <input type="text" id="google_verification" name="google_verification" value="<?php echo htmlspecialchars($currentSettings['google_verification']); ?>" class="form-input">
                                <p class="form-hint"><?php echo __('google_verification_hint', 'Google Search Console verification code'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="robots_txt" class="form-label"><?php echo __('robots_txt', 'Robots.txt Content'); ?></label>
                                <textarea id="robots_txt" name="robots_txt" class="form-input form-textarea" style="font-family: monospace;"><?php echo htmlspecialchars($currentSettings['robots_txt']); ?></textarea>
                                <p class="form-hint"><?php echo __('robots_txt_hint', 'Content for your robots.txt file to control search engine crawling'); ?></p>
                            </div>
                            
                            <div class="form-group">
                                <label for="sitemap_file" class="form-label"><?php echo __('sitemap_file', 'Upload Sitemap.xml'); ?></label>
                                <input type="file" id="sitemap_file" name="sitemap_file" class="form-input">
                                <p class="form-hint"><?php echo __('sitemap_hint', 'Upload your sitemap.xml file or generate it using third-party tools'); ?></p>
                                <?php if (file_exists(dirname(__FILE__, 2) . '/sitemap.xml')): ?>
                                <p class="mt-2">
                                    <a href="../sitemap.xml" target="_blank" class="text-blue-400 hover:underline">
                                        <?php echo __('view_current_sitemap', 'View Current Sitemap'); ?>
                                    </a>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="save_settings" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded">
                                    <?php echo __('save_settings', 'Save Settings'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Настройки интеграций -->
                    <div id="integration-settings" class="tab-content <?php echo $section === 'integrations' ? 'active' : ''; ?>">
                        <form method="post" action="settings.php?section=integrations">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <h3 class="text-xl font-semibold mb-4 pb-2 border-b border-gray-700">
                                    <?php echo __('telegram_payment_integration', 'Telegram Crypto Bot Integration'); ?>
                                </h3>
                                
                                <div class="form-group">
                                    <label for="telegram_bot_token" class="form-label"><?php echo __('telegram_bot_token', 'Telegram Bot Token'); ?></label>
                                    <input type="text" id="telegram_bot_token" name="telegram_bot_token" value="<?php echo htmlspecialchars($currentSettings['telegram_bot_token']); ?>" class="form-input">
                                    <p class="form-hint"><?php echo __('telegram_bot_token_hint', 'API token from BotFather for your payment bot'); ?></p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="telegram_bot_username" class="form-label"><?php echo __('telegram_bot_username', 'Telegram Bot Username'); ?></label>
                                    <input type="text" id="telegram_bot_username" name="telegram_bot_username" value="<?php echo htmlspecialchars($currentSettings['telegram_bot_username']); ?>" class="form-input">
                                    <p class="form-hint"><?php echo __('telegram_bot_username_hint', 'Username of your payment bot (without @)'); ?></p>
                                </div>
                                
                                <div class="bg-gray-700/50 p-4 rounded-lg mt-4">
                                    <h4 class="font-semibold mb-2"><?php echo __('webhook_setup', 'Webhook Setup Instructions'); ?></h4>
                                    <p class="text-sm text-gray-300 mb-2"><?php echo __('webhook_instructions', 'Set up your webhook URL in Telegram Crypto Bot using the following URL:'); ?></p>
                                    <div class="bg-gray-900 p-3 rounded font-mono text-sm break-all"><?php echo SITE_URL; ?>/webhook.php</div>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" name="save_settings" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded">
                                    <?php echo __('save_settings', 'Save Settings'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Резервное копирование -->
                    <div id="backup-restore" class="tab-content <?php echo $section === 'backup' ? 'active' : ''; ?>">
                        <!-- Создание резервной копии -->
                        <div class="mb-8">
                            <h3 class="text-xl font-semibold mb-4"><?php echo __('create_backup', 'Create Backup'); ?></h3>
                            
                            <p class="text-gray-300 mb-4"><?php echo __('backup_description', 'Create a backup of your database. This will export all tables and data.'); ?></p>
                            
                            <form method="post" action="settings.php?section=backup">
                                <!-- CSRF-токен -->
                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                
                                <button type="submit" name="save_settings" class="px-4 py-2 bg-green-600 hover:bg-green-500 rounded">
                                    <?php echo __('create_backup_button', 'Create Database Backup'); ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Список существующих резервных копий -->
                        <div>
                            <h3 class="text-xl font-semibold mb-4"><?php echo __('existing_backups', 'Existing Backups'); ?></h3>
                            
                            <?php $backups = getBackupsList(); ?>
                            
                            <?php if (count($backups) > 0): ?>
                                <div class="bg-gray-700 rounded-lg overflow-hidden">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="bg-gray-600">
                                                <th class="px-4 py-2 text-left"><?php echo __('backup_name', 'Backup File'); ?></th>
                                                <th class="px-4 py-2 text-left"><?php echo __('backup_size', 'Size'); ?></th>
                                                <th class="px-4 py-2 text-left"><?php echo __('backup_date', 'Date Created'); ?></th>
                                                <th class="px-4 py-2 text-left"><?php echo __('actions', 'Actions'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups as $backup): ?>
                                                <tr class="border-t border-gray-600">
                                                    <td class="px-4 py-2"><?php echo htmlspecialchars($backup['name']); ?></td>
                                                    <td class="px-4 py-2"><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                                                    <td class="px-4 py-2"><?php echo $backup['date']; ?></td>
                                                    <td class="px-4 py-2">
                                                        <a href="download_backup.php?file=<?php echo urlencode($backup['name']); ?>" class="text-blue-400 hover:text-blue-300 mr-3">
                                                            <?php echo __('download', 'Download'); ?>
                                                        </a>
                                                        <a href="delete_backup.php?file=<?php echo urlencode($backup['name']); ?>&csrf_token=<?php echo $security->generateCSRFToken(); ?>" 
                                                           class="text-red-400 hover:text-red-300"
                                                           onclick="return confirm('<?php echo __('confirm_delete_backup', 'Are you sure you want to delete this backup?'); ?>')">
                                                            <?php echo __('delete', 'Delete'); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-700 rounded-lg p-4 text-center">
                                    <p><?php echo __('no_backups', 'No backups available. Create your first backup!'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Подвал -->
    <footer class="bg-gray-800 py-4 px-6 mt-8">
        <div class="container mx-auto text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> <?php echo __('site_name'); ?>. <?php echo __('all_rights', 'All Rights Reserved'); ?>.</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Активация вкладки при загрузке страницы
            const activeTab = document.querySelector('.nav-tab.active');
            const activeTabContent = document.querySelector('.tab-content.active');
            
            if (activeTab && activeTabContent) {
                // Уже активированы через PHP
            } else {
                // Активируем первую вкладку по умолчанию
                const firstTab = document.querySelector('.nav-tab');
                const firstTabContent = document.querySelector('.tab-content');
                
                if (firstTab && firstTabContent) {
                    firstTab.classList.add('active');
                    firstTabContent.classList.add('active');
                }
            }
            
            // Предпросмотр изображения для загрузки
            const sitemap_file = document.getElementById('sitemap_file');
            if (sitemap_file) {
                sitemap_file.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Проверим расширение файла
                        const fileName = file.name;
                        const fileExt = fileName.split('.').pop().toLowerCase();
                        
                        if (fileExt !== 'xml') {
                            alert('<?php echo __('error_sitemap_format', 'Sitemap file must be in XML format.'); ?>');
                            this.value = '';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>