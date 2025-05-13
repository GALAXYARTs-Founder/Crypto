<?php
/**
 * Страница добавления логотипа
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/security.php';
require_once 'includes/telegramBot.php';

// Инициализируем переменные для формы
$name = '';
$website = '';
$description = '';
$telegram = '';
$errors = [];
$success = false;
$paymentUrl = '';
$projectId = 0;

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = __('error_security', 'Security verification failed. Please try again.');
    } else {
        // Очищаем входные данные
        $name = $security->sanitizeInput($_POST['name'] ?? '');
        $website = $security->sanitizeInput($_POST['website'] ?? '');
        $description = $security->sanitizeInput($_POST['description'] ?? '');
        $telegram = $security->sanitizeInput($_POST['telegram'] ?? '');
        
        // Валидация
        if (empty($name)) {
            $errors[] = __('error_name_required', 'Project name is required.');
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = __('error_name_length', 'Project name must be between 2 and 100 characters.');
        }
        
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = __('error_website_invalid', 'Please enter a valid website URL.');
        }
        
        if (!empty($telegram) && !preg_match('/^@?[a-zA-Z0-9_]{5,32}$/', $telegram)) {
            $errors[] = __('error_telegram_invalid', 'Please enter a valid Telegram username.');
        }
        
        // Проверка загрузки логотипа
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('error_logo_upload', 'Please upload a logo image.');
        } else {
            // Проверяем тип и размер файла
            if (!$security->validateUploadedFile(
                $_FILES['logo'], 
                ALLOWED_LOGO_TYPES, 
                MAX_LOGO_SIZE
            )) {
                $errors[] = sprintf(
                    __('error_logo_invalid', 'Invalid logo file. Please upload a PNG, JPG, GIF or SVG image under %s KB.'),
                    MAX_LOGO_SIZE / 1024
                );
            }
        }
        
        // Если нет ошибок, сохраняем проект и логотип
        if (empty($errors)) {
            try {
                // Начинаем транзакцию
                $db->beginTransaction();
                
                // Генерируем безопасное имя файла
                $logoFileName = $security->generateSafeFilename($_FILES['logo']['name']);
                $logoPath = 'uploads/' . $logoFileName;
                
                // Создаем запись проекта в БД
                $projectData = [
                    'name' => $name,
                    'website' => $website,
                    'description' => $description,
                    'logo_path' => $logoPath,
                    'telegram_username' => $telegram,
                    'active' => 0, // Неактивно до оплаты
                    'position' => 0 // Будет обновлено после активации
                ];
                
                $projectId = $db->insert('projects', $projectData);
                
                // Перемещаем загруженный файл
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                    throw new Exception(__('error_file_move', 'Failed to save logo file. Please try again.'));
                }
                
                // Создаем платежную ссылку
                $paymentData = $telegramBot->createLogoPaymentLink($projectId, $name);
                $paymentUrl = $paymentData['payment_url'];
                
                // Фиксируем транзакцию
                $db->commit();
                
                // Устанавливаем флаг успешного добавления
                $success = true;
                
                // Логируем действие
                $security->logActivity(null, 'add_logo', 'project', $projectId);
                
            } catch (Exception $e) {
                // Откатываем транзакцию в случае ошибки
                $db->rollBack();
                
                // Добавляем ошибку
                $errors[] = $e->getMessage();
                
                // Логируем ошибку
                error_log('Add Logo Error: ' . $e->getMessage());
            }
        }
    }
    
    // Обновляем CSRF-токен после отправки формы
    $security->refreshCSRFToken();
}

// Заголовок страницы
$pageTitle = __('add_logo_page_title', 'Add Your Logo') . ' - ' . __('site_name');

?>
<!DOCTYPE html>
<html lang="<?php echo $lang->getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
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
        
        /* Стили формы */
        .form-card {
            background-color: #1e1e1e;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(45deg, #ff6b6b, #7d71ff);
            padding: 20px;
            color: white;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #e0e0e0;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            background-color: #2a2a2a;
            border: 1px solid #3a3a3a;
            border-radius: 8px;
            color: white;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            border-color: #7d71ff;
            outline: none;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-hint {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }
        
        .form-submit-btn {
            background: linear-gradient(45deg, #ff6b6b, #7d71ff);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            outline: none;
            cursor: pointer;
            width: 100%;
        }
        
        .form-submit-btn:hover {
            background: linear-gradient(45deg, #ff7e7e, #8f84ff);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(123, 97, 255, 0.3);
        }
        
        /* Стили для загрузки логотипа */
        .logo-upload-area {
            border: 2px dashed #3a3a3a;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            transition: border-color 0.3s;
            cursor: pointer;
        }
        
        .logo-upload-area:hover {
            border-color: #7d71ff;
        }
        
        .logo-preview {
            max-height: 150px;
            max-width: 100%;
            margin-top: 20px;
            border-radius: 5px;
            display: none;
        }
        
        /* Стили для сообщений об ошибках */
        .error-message {
            background-color: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
            color: #fca5a5;
        }
        
        /* Стили для успешных сообщений */
        .success-message {
            background-color: rgba(34, 197, 94, 0.2);
            border-left: 4px solid #22c55e;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
            color: #86efac;
        }
        
        /* Стили для блока оплаты */
        .payment-container {
            text-align: center;
        }
        
        .payment-link {
            display: inline-block;
            background: linear-gradient(45deg, #0088cc, #2a9adc);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            margin: 20px 0;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(42, 154, 220, 0.3);
        }
        
        .payment-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(42, 154, 220, 0.4);
        }
        
        .payment-icon {
            display: inline-block;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Шапка сайта -->
    <header class="py-4 px-6 md:px-10 flex justify-between items-center border-b border-gray-800">
        <a href="index.php" class="site-logo"><?php echo __('site_name'); ?></a>
    </header>
    
    <!-- Основное содержимое -->
    <main class="py-10 px-4 md:px-0">
        <div class="max-w-2xl mx-auto">
            <?php if ($success && !empty($paymentUrl)): ?>
                <!-- Сообщение об успешном добавлении и блок оплаты -->
                <div class="form-card animate-fade-in">
                    <div class="form-header">
                        <h1 class="text-2xl font-bold"><?php echo __('logo_added_title', 'Logo Added Successfully!'); ?></h1>
                    </div>
                    
                    <div class="p-6">
                        <div class="success-message">
                            <?php echo __('logo_added_message', 'Your logo has been uploaded. To complete the process and make it visible on our wall, please make a payment of $1.'); ?>
                        </div>
                        
                        <div class="payment-container">
                            <p class="mb-4"><?php echo __('payment_instructions', 'Click the button below to pay $1 via Telegram Crypto Bot:'); ?></p>
                            
                            <a href="<?php echo $paymentUrl; ?>" class="payment-link" target="_blank">
                                <span class="payment-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20.665 3.717L2.93497 10.554C1.72497 11.04 1.73197 11.715 2.71297 12.016L7.26497 13.436L17.797 6.791C18.295 6.488 18.75 6.651 18.376 6.983L9.84297 14.684H9.84097L9.84297 14.685L9.52897 19.377C9.98897 19.377 10.192 19.166 10.45 18.917L12.661 16.767L17.26 20.164C18.108 20.631 18.717 20.391 18.928 19.379L21.947 5.151C22.256 3.912 21.474 3.351 20.665 3.717Z" fill="white"/>
                                    </svg>
                                </span>
                                <?php echo __('pay_with_telegram', 'Pay $1 with Telegram'); ?>
                            </a>
                            
                            <p class="text-gray-400 text-sm">
                                <?php echo __('payment_note', 'After payment, your logo will be automatically activated and displayed on our wall.'); ?>
                            </p>
                            
                            <div class="mt-8 pt-6 border-t border-gray-700">
                                <a href="view.php?id=<?php echo $projectId; ?>" class="text-blue-400 hover:text-blue-300">
                                    <?php echo __('view_project_page', 'View your project page'); ?>
                                </a>
                                <span class="mx-3 text-gray-600">|</span>
                                <a href="index.php" class="text-blue-400 hover:text-blue-300">
                                    <?php echo __('back_to_home', 'Back to home'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Форма добавления логотипа -->
                <div class="form-card">
                    <div class="form-header">
                        <h1 class="text-2xl font-bold"><?php echo __('add_logo_title', 'Add Your Crypto Logo'); ?></h1>
                        <p class="mt-2 text-white/80"><?php echo __('add_logo_subtitle', 'Showcase your cryptocurrency project for just $1'); ?></p>
                    </div>
                    
                    <div class="p-6">
                        <?php if (!empty($errors)): ?>
                            <div class="error-message">
                                <ul class="list-disc list-inside">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form action="add-logo.php" method="post" enctype="multipart/form-data">
                            <!-- CSRF-токен -->
                            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                            
                            <!-- Загрузка логотипа -->
                            <div class="input-group">
                                <label for="logo-input" class="form-label"><?php echo __('logo_upload', 'Upload Logo'); ?> <span class="text-red-500">*</span></label>
                                
                                <div class="logo-upload-area" id="logo-upload-area">
                                    <div id="upload-icon">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3">
                                            <path d="M12 16L12 8" stroke="#7d71ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M9 11L12 8 15 11" stroke="#7d71ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M20 16.7428C21.2215 16.1262 22 14.9201 22 13.5C22 11.567 20.433 10 18.5 10C18.2815 10 18.0771 10.0194 17.8559 10.0564C17.0683 7.68016 14.7788 6 12 6C8.68629 6 6 8.68629 6 12C6 12.6392 6.1143 13.2486 6.32698 13.8054C4.97277 14.5183 4 15.9375 4 17.5C4 19.9853 6.01472 22 8.5 22H17C18.6569 22 20 20.6569 20 19C20 18.1716 19.6716 17.4214 19.142 16.8677" stroke="#7d71ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <p><?php echo __('logo_drop_instructions', 'Click or drag to upload your logo'); ?></p>
                                        <p class="form-hint"><?php echo __('logo_format_hint', 'PNG, JPG, GIF or SVG, max 512KB'); ?></p>
                                    </div>
                                    <img id="logo-preview" class="logo-preview">
                                    <input type="file" id="logo-input" name="logo" accept="image/png, image/jpeg, image/gif, image/svg+xml" class="hidden">
                                </div>
                            </div>
                            
                            <!-- Название проекта -->
                            <div class="input-group">
                                <label for="name" class="form-label"><?php echo __('project_name', 'Project Name'); ?> <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" class="form-input" required>
                                <p class="form-hint"><?php echo __('name_hint', 'Enter the name of your cryptocurrency or project'); ?></p>
                            </div>
                            
                            <!-- Веб-сайт -->
                            <div class="input-group">
                                <label for="website" class="form-label"><?php echo __('website', 'Website'); ?></label>
                                <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($website); ?>" class="form-input" placeholder="https://example.com">
                                <p class="form-hint"><?php echo __('website_hint', 'Optional: Enter your project website URL'); ?></p>
                            </div>
                            
                            <!-- Описание -->
                            <div class="input-group">
                                <label for="description" class="form-label"><?php echo __('description', 'Description'); ?></label>
                                <textarea id="description" name="description" class="form-input form-textarea"><?php echo htmlspecialchars($description); ?></textarea>
                                <p class="form-hint"><?php echo __('description_hint', 'Optional: Briefly describe your cryptocurrency project'); ?></p>
                            </div>
                            
                            <!-- Telegram -->
                            <div class="input-group">
                                <label for="telegram" class="form-label"><?php echo __('telegram', 'Telegram Username'); ?></label>
                                <input type="text" id="telegram" name="telegram" value="<?php echo htmlspecialchars($telegram); ?>" class="form-input" placeholder="@username">
                                <p class="form-hint"><?php echo __('telegram_hint', 'Optional: Your project\'s Telegram username'); ?></p>
                            </div>
                            
                            <!-- Отправка формы -->
                            <div class="input-group pt-4">
                                <button type="submit" class="form-submit-btn">
                                    <?php echo __('submit_logo', 'Submit Logo for $1'); ?>
                                </button>
                                <p class="form-hint text-center mt-4">
                                    <?php echo __('submit_hint', 'You\'ll be directed to payment after submission. Your logo will be visible after payment.'); ?>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
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
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logoInput = document.getElementById('logo-input');
            const logoUploadArea = document.getElementById('logo-upload-area');
            const logoPreview = document.getElementById('logo-preview');
            const uploadIcon = document.getElementById('upload-icon');
            
            // Обработчик клика на область загрузки
            if (logoUploadArea) {
                logoUploadArea.addEventListener('click', function() {
                    logoInput.click();
                });
            }
            
            // Обработчик выбора файла
            if (logoInput) {
                logoInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        // Проверяем размер файла
                        if (file.size > <?php echo MAX_LOGO_SIZE; ?>) {
                            alert('<?php echo __('error_logo_too_large', 'Logo file is too large. Maximum size is {size} KB.', ['size' => MAX_LOGO_SIZE / 1024]); ?>');
                            return;
                        }
                        
                        // Создаем превью
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            logoPreview.src = e.target.result;
                            logoPreview.style.display = 'block';
                            uploadIcon.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Обработчик Drag & Drop
            if (logoUploadArea) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    logoUploadArea.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    logoUploadArea.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    logoUploadArea.addEventListener(eventName, unhighlight, false);
                });
                
                function highlight() {
                    logoUploadArea.style.borderColor = '#7d71ff';
                    logoUploadArea.style.backgroundColor = 'rgba(125, 113, 255, 0.1)';
                }
                
                function unhighlight() {
                    logoUploadArea.style.borderColor = '#3a3a3a';
                    logoUploadArea.style.backgroundColor = 'transparent';
                }
                
                logoUploadArea.addEventListener('drop', handleDrop, false);
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    
                    if (files && files.length) {
                        logoInput.files = files;
                        
                        // Вызываем событие change вручную
                        const event = new Event('change', { bubbles: true });
                        logoInput.dispatchEvent(event);
                    }
                }
            }
        });
    </script>
</body>
</html>