<?php
/**
 * SEO-функции и хелперы
 * CryptoLogoWall
 */

class SEO {
    private $db;
    private $lang;
    
    public function __construct($db, $lang) {
        $this->db = $db;
        $this->lang = $lang;
    }
    
    /**
     * Генерация мета-тегов для страницы
     *
     * @param array $options Опции мета-тегов
     * @return string HTML-код мета-тегов
     */
    public function generateMetaTags($options = []) {
        // Настройки по умолчанию
        $defaults = [
            'title' => $this->getSettingValue('site_name'),
            'description' => $this->getSettingValue('meta_description'),
            'keywords' => $this->getSettingValue('meta_keywords'),
            'canonical' => $this->getCurrentUrl(),
            'og_title' => $this->getSettingValue('og_title'),
            'og_description' => $this->getSettingValue('og_description'),
            'og_image' => $this->getSettingValue('og_image'),
            'og_type' => 'website',
            'twitter_card' => $this->getSettingValue('twitter_card', 'summary'),
            'twitter_site' => $this->getSettingValue('twitter_site'),
            'noindex' => false,
            'custom_tags' => []
        ];
        
        // Объединяем настройки
        $options = array_merge($defaults, $options);
        
        // Если Open Graph title не указан, используем обычный title
        if (empty($options['og_title'])) {
            $options['og_title'] = $options['title'];
        }
        
        // Если Open Graph description не указан, используем обычный description
        if (empty($options['og_description'])) {
            $options['og_description'] = $options['description'];
        }
        
        // Начинаем формировать HTML-код мета-тегов
        $metaTags = '';
        
        // Основные мета-теги
        if (!empty($options['description'])) {
            $metaTags .= '<meta name="description" content="' . htmlspecialchars($options['description']) . '">' . "\n";
        }
        
        if (!empty($options['keywords'])) {
            $metaTags .= '<meta name="keywords" content="' . htmlspecialchars($options['keywords']) . '">' . "\n";
        }
        
        // Canonical URL
        if (!empty($options['canonical'])) {
            $metaTags .= '<link rel="canonical" href="' . htmlspecialchars($options['canonical']) . '">' . "\n";
        }
        
        // Индексация
        if ($options['noindex']) {
            $metaTags .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        } else {
            $metaTags .= '<meta name="robots" content="index, follow">' . "\n";
        }
        
        // Open Graph теги
        $metaTags .= '<meta property="og:title" content="' . htmlspecialchars($options['og_title']) . '">' . "\n";
        
        if (!empty($options['og_description'])) {
            $metaTags .= '<meta property="og:description" content="' . htmlspecialchars($options['og_description']) . '">' . "\n";
        }
        
        $metaTags .= '<meta property="og:type" content="' . htmlspecialchars($options['og_type']) . '">' . "\n";
        $metaTags .= '<meta property="og:url" content="' . htmlspecialchars($options['canonical']) . '">' . "\n";
        
        if (!empty($options['og_image'])) {
            $metaTags .= '<meta property="og:image" content="' . htmlspecialchars($options['og_image']) . '">' . "\n";
            $metaTags .= '<meta property="og:image:width" content="1200">' . "\n";
            $metaTags .= '<meta property="og:image:height" content="630">' . "\n";
        }
        
        // Twitter Card теги
        $metaTags .= '<meta name="twitter:card" content="' . htmlspecialchars($options['twitter_card']) . '">' . "\n";
        
        if (!empty($options['twitter_site'])) {
            $metaTags .= '<meta name="twitter:site" content="' . htmlspecialchars($options['twitter_site']) . '">' . "\n";
        }
        
        $metaTags .= '<meta name="twitter:title" content="' . htmlspecialchars($options['og_title']) . '">' . "\n";
        
        if (!empty($options['og_description'])) {
            $metaTags .= '<meta name="twitter:description" content="' . htmlspecialchars($options['og_description']) . '">' . "\n";
        }
        
        if (!empty($options['og_image'])) {
            $metaTags .= '<meta name="twitter:image" content="' . htmlspecialchars($options['og_image']) . '">' . "\n";
        }
        
        // Google Verification
        $googleVerification = $this->getSettingValue('google_verification');
        if (!empty($googleVerification)) {
            $metaTags .= '<meta name="google-site-verification" content="' . htmlspecialchars($googleVerification) . '">' . "\n";
        }
        
        // Пользовательские теги
        if (!empty($options['custom_tags']) && is_array($options['custom_tags'])) {
            foreach ($options['custom_tags'] as $tag) {
                $metaTags .= $tag . "\n";
            }
        }
        
        return $metaTags;
    }
    
    /**
     * Генерация Google Analytics кода
     *
     * @return string HTML-код Google Analytics
     */
    public function generateGoogleAnalytics() {
        $gaId = $this->getSettingValue('google_analytics_id');
        
        if (empty($gaId)) {
            return '';
        }
        
        return <<<HTML
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$gaId}');
</script>
HTML;
    }
    
    /**
     * Генерация хлебных крошек на базе schema.org
     *
     * @param array $items Массив элементов хлебных крошек
     * @return string HTML-код хлебных крошек с микроданными
     */
    public function generateBreadcrumbs($items) {
        if (empty($items) || !is_array($items)) {
            return '';
        }
        
        $html = '<nav class="breadcrumbs" aria-label="Breadcrumb">' . "\n";
        $html .= '  <ol itemscope itemtype="https://schema.org/BreadcrumbList">' . "\n";
        
        $count = count($items);
        $index = 1;
        
        foreach ($items as $item) {
            $isLast = ($index === $count);
            $html .= '    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">' . "\n";
            
            if (!$isLast && isset($item['url'])) {
                $html .= '      <a itemprop="item" href="' . htmlspecialchars($item['url']) . '">' . "\n";
                $html .= '        <span itemprop="name">' . htmlspecialchars($item['name']) . '</span>' . "\n";
                $html .= '      </a>' . "\n";
            } else {
                $html .= '      <span itemprop="name">' . htmlspecialchars($item['name']) . '</span>' . "\n";
            }
            
            $html .= '      <meta itemprop="position" content="' . $index . '" />' . "\n";
            $html .= '    </li>' . "\n";
            
            if (!$isLast) {
                $html .= '    <li class="separator">/</li>' . "\n";
            }
            
            $index++;
        }
        
        $html .= '  </ol>' . "\n";
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Генерация JSON-LD разметки для логотипа
     *
     * @param array $project Данные о проекте
     * @return string HTML-код JSON-LD разметки
     */
    public function generateProjectSchema($project) {
        if (empty($project)) {
            return '';
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $project['name'],
            'description' => $project['description'] ?? '',
            'url' => $this->getCurrentUrl(),
            'image' => isset($project['logo_path']) ? SITE_URL . '/' . $project['logo_path'] : '',
        ];
        
        // Добавляем рейтинг, если есть
        if (isset($project['avg_rating']) && isset($project['review_count']) && $project['review_count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $project['avg_rating'],
                'reviewCount' => $project['review_count'],
                'bestRating' => '5',
                'worstRating' => '1'
            ];
        }
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }
    
    /**
     * Получение текущего URL
     *
     * @return string Текущий URL
     */
    public function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * Получение значения настройки
     *
     * @param string $key Ключ настройки
     * @param string $default Значение по умолчанию
     * @return string Значение настройки
     */
    public function getSettingValue($key, $default = '') {
        // Проверяем настройки в таблице settings
        $setting = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        
        if ($setting) {
            return $setting['setting_value'];
        }
        
        // Проверяем настройки в таблице translations
        $translation = $this->db->fetchOne(
            "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?",
            [$this->lang->getCurrentLanguage(), $key]
        );
        
        if ($translation) {
            return $translation['translation_value'];
        }
        
        // Проверяем значение для языка по умолчанию
        $defaultTranslation = $this->db->fetchOne(
            "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?",
            [DEFAULT_LANG, $key]
        );
        
        if ($defaultTranslation) {
            return $defaultTranslation['translation_value'];
        }
        
        return $default;
    }
    
    /**
     * Генерация настраиваемого заголовка страницы
     *
     * @param string $title Заголовок страницы
     * @param bool $includeSiteName Включать ли имя сайта
     * @return string Полный заголовок
     */
    public function generateTitle($title, $includeSiteName = true) {
        $siteName = $this->getSettingValue('site_name');
        
        if ($includeSiteName && !empty($siteName) && $title !== $siteName) {
            return $title . ' - ' . $siteName;
        }
        
        return $title;
    }
    
    /**
     * Формирование перманентной ссылки для проекта
     *
     * @param int $id ID проекта
     * @param string $name Название проекта
     * @return string Перманентная ссылка
     */
    public function generateProjectSlug($id, $name) {
        // Транслитерация названия для URL
        $slug = $this->transliterateString($name);
        
        // Добавляем ID проекта для уникальности
        return $slug . '-' . $id;
    }
    
    /**
     * Транслитерация строки для URL
     *
     * @param string $string Исходная строка
     * @return string Транслитерированная строка
     */
    private function transliterateString($string) {
        // Преобразуем в нижний регистр
        $string = mb_strtolower($string, 'UTF-8');
        
        // Заменяем пробелы на дефисы
        $string = preg_replace('/\s+/', '-', $string);
        
        // Удаляем специальные символы
        $string = preg_replace('/[^a-z0-9\-]/', '', $string);
        
        // Удаляем множественные дефисы
        $string = preg_replace('/-+/', '-', $string);
        
        // Обрезаем дефисы в начале и конце
        $string = trim($string, '-');
        
        // Если строка пустая, используем placeholder
        if (empty($string)) {
            $string = 'project';
        }
        
        return $string;
    }
}

// Создаем экземпляр класса SEO
$seo = new SEO($db, $lang);