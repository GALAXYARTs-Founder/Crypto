<?php
/**
 * Подвал сайта
 * CryptoLogoWall
 */

// Запрещаем прямой доступ к файлу
if (!defined('INCLUDED')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Прямой доступ к файлу запрещен');
}

?>
    <!-- Подвал сайта -->
    <footer class="bg-gray-900 py-8 px-6 border-t border-gray-800 mt-10">
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
    
    <?php if (isset($additionalScripts)) echo $additionalScripts; ?>
</body>
</html>