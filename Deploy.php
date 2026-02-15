<?php
// --- НАСТРОЙКИ ---
$secret_key = 'Deploy-Mb8941'; // Придумайте сложный ключ!

// Проверка доступа
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access Denied');
}

set_time_limit(300); // Даем скрипту 5 минут, чтобы не отвалился на Composer

// Ваш путь к Composer на Hoster.by
$composer_bin = '/opt/cpanel/composer/bin/composer';

$commands = [
    // 1. Информация
    'echo "User: $(whoami)"',
    'echo "Path: $(pwd)"',

    // 2. Безопасное обновление с GitHub (защита от force push)
    'git fetch origin 2>&1',
    'git reset --hard origin/main 2>&1',
    // 'git clean -fd 2>&1', // DANGEROUS: Deletes untracked files! Disabled to prevent data loss.

    // 3. Обновление библиотек
    'export COMPOSER_HOME=~/.composer && ' . $composer_bin . ' install --no-dev --optimize-autoloader 2>&1',

    // NOTE: npm не установлен на сервере, поэтому собираем локально и коммитим в git
    // Frontend assets (public/css/app.css, public/js/app.js) обновляются через git pull

    // 4. Миграции
    'php artisan migrate --force 2>&1',

    // 5. Очистка старого кэша
    'php artisan optimize:clear 2>&1',

    // 6. Создание нового кэша
    'php artisan config:cache 2>&1',

    // Кэш маршрутов (все closures заменены на контроллеры 14.02.2026)
    'php artisan route:cache 2>&1',

    'php artisan view:cache 2>&1',
];

// --- ВЫВОД РЕЗУЛЬТАТА ---
echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Deployment</title></head><body style='background:#222;color:#ccc;font-family:monospace;padding:20px;'>";
echo "<h2>🚀 Starting Deployment (Check Routes)...</h2>";

foreach ($commands as $command) {
    echo "<div style='margin-bottom:10px; border-bottom: 1px solid #444; padding-bottom: 5px;'>";
    echo "<span style='color:#4f4;'>$ </span>" . htmlspecialchars($command) . "<br>";
    $output = shell_exec($command);
    echo "<pre style='color:#fff; margin:0;'>" . htmlspecialchars(trim($output)) . "</pre>";
    echo "</div>";
    flush();
}
echo "<h2>✅ Deployment Finished!</h2></body></html>";
?>