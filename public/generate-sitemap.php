<?php
/**
 * Скрипт генерации карты сайта (sitemap.xml)
 * CryptoLogoWall
 */

// Подключаем необходимые файлы
require_once 'config.php';
require_once 'includes/db.php';

// Проверяем авторизацию через CLI или админку
$isAuthorized = false;

// Если запуск через командную строку
if (php_sapi_name() === 'cli') {
    $isAuthorized = true;
} else {
    // Проверяем авторизацию через админку
    require_once 'includes/security.php';
    
    if ($security->isAdmin() && $security->hasAdminRights()) {
        $isAuthorized = true;
    }
}

// Если нет авторизации, выдаем ошибку
if (!$isAuthorized) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

/**
 * Класс для генерации карты сайта
 */
class SitemapGenerator {
    private $db;
    private $baseUrl;
    private $outputFile;
    private $urlSet = [];
    
    /**
     * Конструктор
     *
     * @param object $db Объект базы данных
     * @param string $baseUrl Базовый URL сайта
     * @param string $outputFile Путь к файлу sitemap.xml
     */
    public function __construct($db, $baseUrl, $outputFile) {
        $this->db = $db;
        $this->baseUrl = $baseUrl;
        $this->outputFile = $outputFile;
    }
    
    /**
     * Запуск генерации карты сайта
     */
    public function generate() {
        // Добавляем статические страницы
        $this->addStaticPages();
        
        // Добавляем страницы проектов (логотипов)
        $this->addProjects();
        
        // Сохраняем карту сайта в файл
        $this->saveToFile();
        
        return count($this->urlSet);
    }
    
    /**
     * Добавление URL в карту сайта
     *
     * @param string $url URL страницы
     * @param string $lastmod Дата последнего изменения (Y-m-d)
     * @param string $changefreq Частота изменения (daily, weekly, monthly)
     * @param float $priority Приоритет (0.0 - 1.0)
     */
    private function addUrl($url, $lastmod = null, $changefreq = 'monthly', $priority = 0.5) {
        $entry = [
            'loc' => $this->baseUrl . $url,
            'changefreq' => $changefreq,
            'priority' => $priority
        ];
        
        if ($lastmod) {
            $entry['lastmod'] = $lastmod;
        }
        
        $this->urlSet[] = $entry;
    }
    
    /**
     * Добавление статических страниц сайта
     */
    private function addStaticPages() {
        // Главная страница
        $this->addUrl('/', date('Y-m-d'), 'daily', 1.0);
        
        // Страница добавления логотипа
        $this->addUrl('/add-logo.php', date('Y-m-d'), 'monthly', 0.8);
        
        // Страница "О нас"
        if (file_exists(dirname(__FILE__) . '/about.php')) {
            $this->addUrl('/about.php', date('Y-m-d'), 'monthly', 0.7);
        }
        
        // Страница "Контакты"
        if (file_exists(dirname(__FILE__) . '/contact.php')) {
            $this->addUrl('/contact.php', date('Y-m-d'), 'monthly', 0.7);
        }
    }
    
    /**
     * Добавление страниц проектов
     */
    private function addProjects() {
        // Получаем активные проекты
        $projects = $this->db->fetchAll(
            "SELECT id, name, updated_at, created_at FROM projects WHERE active = 1 ORDER BY id ASC"
        );
        
        foreach ($projects as $project) {
            // Определяем дату последнего изменения
            $lastmod = isset($project['updated_at']) && $project['updated_at'] ? 
                       date('Y-m-d', strtotime($project['updated_at'])) : 
                       date('Y-m-d', strtotime($project['created_at']));
            
            // Генерируем URL-адрес проекта
            $url = '/view.php?id=' . $project['id'];
            
            // Добавляем URL
            $this->addUrl($url, $lastmod, 'weekly', 0.6);
            
            // Также добавляем страницу для добавления отзыва
            $this->addUrl('/add-review.php?id=' . $project['id'], $lastmod, 'monthly', 0.4);
        }
    }
    
    /**
     * Сохранение карты сайта в файл
     */
    private function saveToFile() {
        // Формируем XML
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // Создаем корневой элемент
        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->appendChild($urlset);
        
        // Добавляем URL-адреса
        foreach ($this->urlSet as $url) {
            $urlElement = $xml->createElement('url');
            
            // Добавляем элементы URL
            foreach ($url as $tag => $value) {
                $element = $xml->createElement($tag, $value);
                $urlElement->appendChild($element);
            }
            
            $urlset->appendChild($urlElement);
        }
        
        // Сохраняем XML в файл
        $xml->save($this->outputFile);
    }
}

// Создаем экземпляр генератора карты сайта
$sitemapGenerator = new SitemapGenerator(
    $db,
    SITE_URL,
    dirname(__FILE__) . '/sitemap.xml'
);

// Генерируем карту сайта
$urlCount = $sitemapGenerator->generate();

// Выводим сообщение об успешной генерации
if (php_sapi_name() === 'cli') {
    echo "Sitemap generated successfully with {$urlCount} URLs.\n";
} else {
    // Перенаправляем обратно в админку с сообщением
    header('Location: admin/settings.php?section=seo&success=sitemap_generated&count=' . $urlCount);
    exit;
}