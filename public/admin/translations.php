<?php
/**
 * Административная панель - управление переводами
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

// Обработка действий с переводами
$message = '';
$messageType = '';

// Выбираем язык для отображения/редактирования
$selectedLang = isset($_GET['lang']) ? $_GET['lang'] : DEFAULT_LANG;
if (!in_array($selectedLang, $lang->getAvailableLanguages())) {
    $selectedLang = DEFAULT_LANG;
}

// Добавление нового перевода
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $message = __('error_security', 'Security verification failed. Please try again.');
        $messageType = 'error';
    } else {
        // Получаем данные из формы
        $key = $security->sanitizeInput($_POST['key'] ?? '');
        $translations = [];
        
        foreach ($lang->getAvailableLanguages() as $langCode) {
            $translations[$langCode] = $security->sanitizeInput($_POST[$langCode] ?? '');
        }
        
        // Валидация
        $errors = [];
        
        if (empty($key)) {
            $errors[] = __('error_key_required', 'Translation key is required.');
        } elseif (!preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = __('error_key_format', 'Translation key can only contain lowercase letters, numbers and underscores.');
        }
        
        // Проверяем обязательные переводы для DEFAULT_LANG
        if (empty($translations[DEFAULT_LANG])) {
            $errors[] = __('error_default_translation_required', 'Translation for default language is required.');
        }
        
        // Проверяем существование ключа
        if (!empty($key) && $db->exists('translations', 'translation_key = ?', [$key])) {
            $errors[] = __('error_key_exists', 'Translation key already exists.');
        }
        
        // Если нет ошибок, добавляем перевод
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                foreach ($translations as $langCode => $value) {
                    if (!empty($value)) {
                        $translationData = [
                            'lang_code' => $langCode,
                            'translation_key' => $key,
                            'translation_value' => $value
                        ];
                        
                        $db->insert('translations', $translationData);
                    }
                }
                
                $db->commit();
                
                // Логируем действие
                $security->logActivity($_SESSION['user_id'], 'add_translation', 'translation', null);
                
                $message = __('translation_added', 'Translation has been added successfully.');
                $messageType = 'success';
                
                // Возвращаемся к списку переводов
                $action = 'list';
            } catch (Exception $e) {
                $db->rollBack();
                
                $message = $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    }
}

// Обновление перевода
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $message = __('error_security', 'Security verification failed. Please try again.');
        $messageType = 'error';
    } else {
        // Получаем данные из формы
        $key = $security->sanitizeInput($_POST['key'] ?? '');
        $translations = [];
        
        foreach ($lang->getAvailableLanguages() as $langCode) {
            $translations[$langCode] = $security->sanitizeInput($_POST[$langCode] ?? '');
        }
        
        // Валидация
        $errors = [];
        
        if (empty($key)) {
            $errors[] = __('error_key_required', 'Translation key is required.');
        }
        
        // Проверяем обязательные переводы для DEFAULT_LANG
        if (empty($translations[DEFAULT_LANG])) {
            $errors[] = __('error_default_translation_required', 'Translation for default language is required.');
        }
        
        // Если нет ошибок, обновляем перевод
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                foreach ($translations as $langCode => $value) {
                    // Проверяем существует ли перевод для этого языка
                    $existingTranslation = $db->fetchOne(
                        "SELECT id FROM translations WHERE lang_code = ? AND translation_key = ?", 
                        [$langCode, $key]
                    );
                    
                    if ($existingTranslation) {
                        // Обновляем существующий перевод
                        if (!empty($value)) {
                            $db->update(
                                'translations', 
                                ['translation_value' => $value], 
                                'lang_code = ? AND translation_key = ?', 
                                [$langCode, $key]
                            );
                        } else {
                            // Если значение пустое, удаляем перевод, но только если это не DEFAULT_LANG
                            if ($langCode !== DEFAULT_LANG) {
                                $db->delete(
                                    'translations', 
                                    'lang_code = ? AND translation_key = ?', 
                                    [$langCode, $key]
                                );
                            }
                        }
                    } elseif (!empty($value)) {
                        // Добавляем новый перевод
                        $translationData = [
                            'lang_code' => $langCode,
                            'translation_key' => $key,
                            'translation_value' => $value
                        ];
                        
                        $db->insert('translations', $translationData);
                    }
                }
                
                $db->commit();
                
                // Логируем действие
                $security->logActivity($_SESSION['user_id'], 'update_translation', 'translation', null);
                
                $message = __('translation_updated', 'Translation has been updated successfully.');
                $messageType = 'success';
            } catch (Exception $e) {
                $db->rollBack();
                
                $message = $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    }
}

// Удаление перевода
if ($action === 'delete' && isset($_GET['key']) && $isAdmin) {
    $key = $security->sanitizeInput($_GET['key']);
    
    try {
        // Удаляем все переводы с указанным ключом
        $db->delete('translations', 'translation_key = ?', [$key]);
        
        // Логируем действие
        $security->logActivity($_SESSION['user_id'], 'delete_translation', 'translation', null);
        
        $message = __('translation_deleted', 'Translation has been deleted successfully.');
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
    
    // Сбрасываем действие на список
    $action = 'list';
}

// Если действие - редактирование, получаем данные перевода
if ($action === 'edit' && isset($_GET['key'])) {
    $key = $security->sanitizeInput($_GET['key']);
    
    // Получаем переводы для ключа
    $translations = [];
    
    foreach ($lang->getAvailableLanguages() as $langCode) {
        $translation = $db->fetchOne(
            "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?", 
            [$langCode, $key]
        );
        
        $translations[$langCode] = $translation ? $translation['translation_value'] : '';
    }
}

// Получаем список уникальных ключей
$keys = $db->fetchAll(
    "SELECT DISTINCT translation_key FROM translations ORDER BY translation_key ASC"
);

// Фильтр поиска
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Метка для включаемых файлов
define('INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('manage_translations', 'Manage Translations'); ?> - <?php echo __('admin_panel', 'Admin Panel'); ?></title>
    
    <!-- Tailwind CSS из CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <style>
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
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .language-tab {
            padding: 0.5rem 1rem;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .language-tab.active {
            background-color: #3b82f6;
            color: white;
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
                                <a href="translations.php" class="block px-4 py-2 rounded bg-blue-600 text-white">
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
                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Форма добавления/редактирования перевода -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold">
                            <?php echo $action === 'add' ? __('add_translation', 'Add Translation') : __('edit_translation', 'Edit Translation'); ?>
                        </h2>
                        <a href="translations.php" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded">
                            <?php echo __('back_to_list', 'Back to List'); ?>
                        </a>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-gray-800 rounded-lg p-6">
                        <form action="translations.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&key=' . htmlspecialchars($key) : ''; ?>" method="post">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <!-- Ключ перевода -->
                            <div class="mb-6">
                                <label for="key" class="block mb-2"><?php echo __('translation_key', 'Translation Key'); ?> <span class="text-red-500">*</span></label>
                                <input type="text" id="key" name="key" value="<?php echo htmlspecialchars($key ?? ''); ?>" class="form-input" <?php echo $action === 'edit' ? 'readonly' : ''; ?> required>
                                <?php if ($action === 'add'): ?>
                                <p class="text-sm text-gray-400 mt-1"><?php echo __('key_hint', 'Use only lowercase letters, numbers and underscores.'); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Вкладки языков -->
                            <div class="mb-4 border-b border-gray-700">
                                <div class="flex flex-wrap">
                                    <?php foreach ($lang->getAvailableLanguages() as $code): ?>
                                    <div class="language-tab <?php echo $code === DEFAULT_LANG ? 'active' : ''; ?>" data-lang="<?php echo $code; ?>">
                                        <?php echo $lang->getLanguageNames()[$code]; ?>
                                        <?php if ($code === DEFAULT_LANG): ?>
                                        <span class="ml-1 text-xs bg-blue-800 px-1 rounded"><?php echo __('default', 'Default'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Поля переводов -->
                            <?php foreach ($lang->getAvailableLanguages() as $code): ?>
                            <div class="translation-content" id="lang-<?php echo $code; ?>" style="<?php echo $code === DEFAULT_LANG ? '' : 'display: none;'; ?>">
                                <label for="<?php echo $code; ?>" class="block mb-2">
                                    <?php echo $lang->getLanguageNames()[$code]; ?> <?php echo __('translation', 'Translation'); ?>
                                    <?php if ($code === DEFAULT_LANG): ?>
                                    <span class="text-red-500">*</span>
                                    <?php endif; ?>
                                </label>
                                <textarea id="<?php echo $code; ?>" name="<?php echo $code; ?>" class="form-input form-textarea" <?php echo $code === DEFAULT_LANG ? 'required' : ''; ?>><?php echo htmlspecialchars($translations[$code] ?? ''); ?></textarea>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-6">
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded">
                                    <?php echo $action === 'add' ? __('add_translation', 'Add Translation') : __('save_changes', 'Save Changes'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Список переводов -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold"><?php echo __('manage_translations', 'Manage Translations'); ?></h2>
                        <a href="translations.php?action=add" class="px-4 py-2 bg-green-600 hover:bg-green-500 rounded">
                            <?php echo __('add_translation', 'Add Translation'); ?>
                        </a>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Фильтр по языку и поиск -->
                    <div class="bg-gray-800 rounded-lg p-4 mb-6">
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="w-full md:w-1/3">
                                <label for="lang-filter" class="block mb-2"><?php echo __('language', 'Language'); ?></label>
                                <div class="relative">
                                    <select id="lang-filter" class="form-input appearance-none pr-8">
                                        <?php foreach ($lang->getAvailableLanguages() as $code): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $code === $selectedLang ? 'selected' : ''; ?>>
                                            <?php echo $lang->getLanguageNames()[$code]; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="w-full md:w-2/3">
                                <label for="search" class="block mb-2"><?php echo __('search', 'Search'); ?></label>
                                <div class="relative">
                                    <input type="text" id="search" class="form-input pl-10" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="<?php echo __('search_placeholder', 'Search by key or translation...'); ?>">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                                        <svg class="w-5 h-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Таблица переводов -->
                    <div class="bg-gray-800 rounded-lg overflow-hidden">
                        <?php if (count($keys) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-700">
                                        <th class="px-4 py-3 text-left"><?php echo __('key', 'Key'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('translation', 'Translation'); ?></th>
                                        <th class="px-4 py-3 text-left"><?php echo __('actions', 'Actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="translations-table-body">
                                    <?php foreach ($keys as $keyItem): ?>
                                    <?php 
                                        $translationValue = $db->fetchOne(
                                            "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?", 
                                            [$selectedLang, $keyItem['translation_key']]
                                        );
                                        
                                        // Если перевод для выбранного языка не найден, показываем для языка по умолчанию
                                        if (!$translationValue) {
                                            $translationValue = $db->fetchOne(
                                                "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?", 
                                                [DEFAULT_LANG, $keyItem['translation_key']]
                                            );
                                        }
                                        
                                        $value = $translationValue ? $translationValue['translation_value'] : '';
                                    ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-750 translation-row" data-key="<?php echo htmlspecialchars($keyItem['translation_key']); ?>" data-translation="<?php echo htmlspecialchars($value); ?>">
                                        <td class="px-4 py-3">
                                            <code class="bg-gray-900 px-2 py-1 rounded"><?php echo htmlspecialchars($keyItem['translation_key']); ?></code>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($translationValue): ?>
                                                <?php echo htmlspecialchars($value); ?>
                                            <?php elseif ($selectedLang !== DEFAULT_LANG): ?>
                                                <span class="text-gray-500 italic"><?php echo __('not_translated', 'Not translated'); ?></span>
                                            <?php else: ?>
                                                <span class="text-red-500 italic"><?php echo __('missing_translation', 'Missing translation'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex space-x-2">
                                                <a href="translations.php?action=edit&key=<?php echo urlencode($keyItem['translation_key']); ?>" class="text-blue-400 hover:text-blue-300">
                                                    <?php echo __('edit', 'Edit'); ?>
                                                </a>
                                                <?php if ($isAdmin): ?>
                                                <a href="translations.php?action=delete&key=<?php echo urlencode($keyItem['translation_key']); ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('<?php echo __('confirm_delete', 'Are you sure you want to delete this translation? This cannot be undone.'); ?>')">
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
                            <p><?php echo __('no_translations', 'No translations found.'); ?></p>
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
    <script>
        // Переключение вкладок языков
        document.addEventListener('DOMContentLoaded', function() {
            const languageTabs = document.querySelectorAll('.language-tab');
            
            languageTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const lang = this.getAttribute('data-lang');
                    
                    // Скрываем все содержимое
                    document.querySelectorAll('.translation-content').forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    // Убираем активный класс у всех вкладок
                    languageTabs.forEach(t => {
                        t.classList.remove('active');
                    });
                    
                    // Показываем выбранное содержимое и активируем вкладку
                    document.getElementById('lang-' + lang).style.display = 'block';
                    this.classList.add('active');
                });
            });
            
            // Фильтр по языку
            const langFilter = document.getElementById('lang-filter');
            if (langFilter) {
                langFilter.addEventListener('change', function() {
                    window.location.href = 'translations.php?lang=' + this.value;
                });
            }
            
            // Поиск переводов
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.translation-row');
                    
                    rows.forEach(row => {
                        const key = row.getAttribute('data-key').toLowerCase();
                        const translation = row.getAttribute('data-translation').toLowerCase();
                        
                        if (key.includes(query) || translation.includes(query)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
                
                // Запускаем поиск при загрузке страницы, если есть параметр поиска
                if (searchInput.value) {
                    const event = new Event('input');
                    searchInput.dispatchEvent(event);
                }
            }
        });
    </script>
</body>
</html>