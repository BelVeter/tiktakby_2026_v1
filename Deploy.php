<?php
// --- НАСТРОЙКИ ---
$secret_key = 'Deploy-Mb8941'; // Придумайте сложный ключ!

// Защита от посторонних
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die('Access Denied');
}

// Увеличиваем время выполнения скрипта (Composer может думать долго)
set_time_limit(300);

// --- СПИСОК КОМАНД ---
$commands = [
    // 1. Показываем, кто мы и где мы (для отладки)
    'echo "Current User: " . whoami',
    'echo "Current Path: " . getcwd()',

    // 2. Скачиваем обновления с GitHub
    'git pull origin main 2>&1',

    // 3. Настройка окружения для Composer (ВАЖНО для cPanel)
    // Указываем домашнюю папку, иначе Composer может ругаться на кэш
    'export COMPOSER_HOME=~/.composer',

    // 4. Обновляем зависимости Laravel
    // --no-dev: не качаем инструменты для тестирования (экономит место)
    // --optimize-autoloader: ускоряет загрузку классов
    '/usr/local/bin/composer install --no-dev --optimize-autoloader 2>&1',

    // 5. Запускаем миграции (обновляем структуру базы данных)
    // --force: обязательно, иначе Laravel будет спрашивать "Вы уверены?", и скрипт зависнет
    'php artisan migrate --force 2>&1',

    // 6. Чистим и обновляем кэш (чтобы изменения применились сразу)
    'php artisan optimize:clear 2>&1',
    'php artisan config:cache 2>&1',
    'php artisan route:cache 2>&1',
    'php artisan view:cache 2>&1',

    // 7. Ставим правильные права на папки (на всякий случай)
    'chmod -R 775 storage bootstrap/cache 2>&1'
];

// --- ЗАПУСК ---
echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Deployment</title></head><body style='background:#222;color:#4f4;font-family:monospace;padding:20px;'>";
echo "<h2>🚀 Starting Deployment...</h2>";

foreach ($commands as $command) {
    echo "<div style='margin-bottom:10px;'>";
    echo "<span style='color:#fff;'>$ </span>" . htmlspecialchars($command) . "<br>";

    // Выполняем команду
    $output = shell_exec($command);

    // Выводим результат
    echo "<pre style='color:#ccc; margin:0;'>" . htmlspecialchars(trim($output)) . "</pre>";
    echo "</div>";

    // Сбрасываем буфер вывода, чтобы видеть прогресс в реальном времени
    flush();
}

echo "<h2>✅ Deployment Finished!</h2></body></html>";
?>