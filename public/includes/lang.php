<?php
/**
 * Система мультиязычности
 * CryptoLogoWall
 */

class Lang {
    private $db;
    private $currentLang;
    private $translations = [];
    private $availableLanguages = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->availableLanguages = AVAILABLE_LANGUAGES;
        $this->init();
    }
    
    // Инициализация языковой системы
    private function init() {
        // Проверяем наличие параметра 'lang' в URL
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->availableLanguages)) {
            $this->currentLang = $_GET['lang'];
            $_SESSION['user_lang'] = $this->currentLang;
            setcookie('user_lang', $this->currentLang, time() + (86400 * 30), '/'); // Куки на 30 дней
        }
        // Если нет в URL, проверяем в сессии
        elseif (isset($_SESSION['user_lang']) && in_array($_SESSION['user_lang'], $this->availableLanguages)) {
            $this->currentLang = $_SESSION['user_lang'];
        }
        // Если нет в сессии, проверяем в куках
        elseif (isset($_COOKIE['user_lang']) && in_array($_COOKIE['user_lang'], $this->availableLanguages)) {
            $this->currentLang = $_COOKIE['user_lang'];
            $_SESSION['user_lang'] = $this->currentLang;
        }
        else {
            // Определяем язык по заголовку Accept-Language
            $this->currentLang = $this->detectLanguage();
            $_SESSION['user_lang'] = $this->currentLang;
            setcookie('user_lang', $this->currentLang, time() + (86400 * 30), '/'); // Куки на 30 дней
        }
        
        // Загружаем переводы
        $this->loadTranslations();
    }
    
    // Обнаружение языка пользователя
    private function detectLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browserLangs as $lang) {
                $lang = substr($lang, 0, 2);
                if (in_array($lang, $this->availableLanguages)) {
                    return $lang;
                }
            }
        }
        
        return DEFAULT_LANG;
    }
    
    // Загрузка переводов из базы данных
    private function loadTranslations() {
        $sql = "SELECT translation_key, translation_value FROM translations WHERE lang_code = ?";
        $translations = $this->db->fetchAll($sql, [$this->currentLang]);
        
        foreach ($translations as $translation) {
            $this->translations[$translation['translation_key']] = $translation['translation_value'];
        }
        
        // Если перевод отсутствует, загружаем английский как резервный
        if (empty($this->translations) && $this->currentLang !== 'en') {
            $sql = "SELECT translation_key, translation_value FROM translations WHERE lang_code = 'en'";
            $translations = $this->db->fetchAll($sql, []);
            
            foreach ($translations as $translation) {
                $this->translations[$translation['translation_key']] = $translation['translation_value'];
            }
        }
    }
    
    // Получение перевода по ключу
    public function get($key, $default = null) {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        
        // Если перевод не найден, возвращаем ключ или значение по умолчанию
        return $default !== null ? $default : $key;
    }
    
    // Альтернативный синтаксис для получения перевода
    public function __($key, $default = null) {
        return $this->get($key, $default);
    }
    
    // Установка текущего языка
    public function setLanguage($lang) {
        if (in_array($lang, $this->availableLanguages)) {
            $this->currentLang = $lang;
            $_SESSION['user_lang'] = $lang;
            setcookie('user_lang', $lang, time() + (86400 * 30), '/');
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    // Получение текущего языка
    public function getCurrentLanguage() {
        return $this->currentLang;
    }
    
    // Получение списка доступных языков
    public function getAvailableLanguages() {
        return $this->availableLanguages;
    }
    
    // Получение имен языков для отображения
    public function getLanguageNames() {
        return [
            'en' => 'English',
            'ru' => 'Русский',
            'uk' => 'Українська'
        ];
    }
    
    // Добавление нового перевода
    public function addTranslation($langCode, $key, $value) {
        if (!in_array($langCode, $this->availableLanguages)) {
            return false;
        }
        
        // Проверяем существование перевода
        $sql = "SELECT id FROM translations WHERE lang_code = ? AND translation_key = ?";
        $exists = $this->db->fetchOne($sql, [$langCode, $key]);
        
        if ($exists) {
            // Обновляем существующий перевод
            $sql = "UPDATE translations SET translation_value = ? WHERE lang_code = ? AND translation_key = ?";
            $this->db->query($sql, [$value, $langCode, $key]);
        } else {
            // Добавляем новый перевод
            $data = [
                'lang_code' => $langCode,
                'translation_key' => $key,
                'translation_value' => $value
            ];
            $this->db->insert('translations', $data);
        }
        
        // Обновляем кеш переводов, если это текущий язык
        if ($langCode === $this->currentLang) {
            $this->translations[$key] = $value;
        }
        
        return true;
    }
    
    // Удаление перевода
    public function removeTranslation($langCode, $key) {
        if (!in_array($langCode, $this->availableLanguages)) {
            return false;
        }
        
        $sql = "DELETE FROM translations WHERE lang_code = ? AND translation_key = ?";
        $this->db->query($sql, [$langCode, $key]);
        
        // Удаляем из кеша, если это текущий язык
        if ($langCode === $this->currentLang && isset($this->translations[$key])) {
            unset($this->translations[$key]);
        }
        
        return true;
    }
}

// Создаем экземпляр класса для использования
$lang = new Lang($db);

// Функция-хелпер для получения перевода
function __($key, $default = null) {
    global $lang;
    return $lang->get($key, $default);
}