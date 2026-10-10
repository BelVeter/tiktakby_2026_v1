<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Корень сайта = корень проекта, поэтому всё, что лежит в репозитории, по умолчанию
 * отдаётся по прямой ссылке. 10.10.2026 так открывались /.env (все прод-секреты),
 * /.env.bak, /storage/logs/laravel.log, bb/error_log, дампы *.sql, AGENTS.md и docs/.
 *
 * Две линии защиты: такие файлы не держим в git и закрываем их в .htaccess (404).
 * ⚠️ .zip/.rar и картинки nginx на проде отдаёт сам, мимо .htaccess — для них работает
 * только первая линия.
 */
class ServiceFilesNotTrackedTest extends TestCase
{
    public function test_service_files_are_not_tracked_by_git(): void
    {
        $files = $this->trackedFiles();
        if ($files === null) {
            $this->markTestSkipped('git недоступен.');
        }

        $leaked = array_values(array_filter($files, function (string $f): bool {
            $base = basename($f);
            if ($base === '.env.example') {
                return false;
            }
            return preg_match('/^\.env(\.|$)/', $base)
                || $base === 'error_log'
                || $base === '.DS_Store'
                || preg_match('/\.(sql|log|bak)$/i', $base);
        }));

        $this->assertSame([], $leaked, 'Служебные файлы в git — после деплоя их можно скачать с прода по прямой ссылке.');
    }

    public function test_htaccess_blocks_service_files_before_bb_rule(): void
    {
        $htaccess = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        $dotfiles = strpos($htaccess, 'RewriteRule (^|/)\. - [R=404,L]');
        $dirs = strpos($htaccess, 'RewriteRule ^(app|bootstrap|config|database|docker|docs|resources|routes|storage|tests|vendor|node_modules)(/|$) - [R=404,L]');
        $bb = strpos($htaccess, 'RewriteRule ^bb(/|$) - [L]');

        $this->assertNotFalse($dotfiles, 'В .htaccess нет запрета на файлы с точкой (.env, .git).');
        $this->assertNotFalse($dirs, 'В .htaccess нет запрета на служебные каталоги (storage, docs, ...).');
        $this->assertNotFalse($bb);
        $this->assertLessThan($bb, $dotfiles, 'Запрет должен стоять до правила для bb/, иначе bb/error_log останется открытым.');
    }

    /**
     * Живая проверка через Apache в docker-контейнере (корень = корень проекта).
     * Без сервера — пропускается.
     */
    public function test_apache_returns_404_for_service_files(): void
    {
        if (!$this->status('robots.txt')) {
            $this->markTestSkipped('Локальный Apache недоступен.');
        }

        foreach (['.env', '.env.example', '.git/config', 'storage/logs/laravel.log', 'AGENTS.md',
                  'docs/db_notes.md', 'artisan', 'composer.json', 'bb/error_log', 'bb/logs/x.log',
                  'public/x/error_log'] as $path) {
            $this->assertSame(404, $this->status($path), "/$path должен отдавать 404");
        }
        foreach (['robots.txt', 'llms.txt', 'public/css/app.css'] as $path) {
            $this->assertSame(200, $this->status($path), "/$path должен оставаться доступным");
        }
    }

    private function status(string $path): ?int
    {
        $ch = curl_init('http://127.0.0.1/' . $path);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code ?: null;
    }

    /**
     * Список файлов в git; null — git не сработал.
     * HOME подменяется (safe.directory=*) — как в SitemapNotTrackedTest.
     */
    private function trackedFiles(): ?array
    {
        $home = sys_get_temp_dir() . '/git-home-' . getmypid();
        @mkdir($home);
        file_put_contents($home . '/.gitconfig', "[safe]\n\tdirectory = *\n");

        $cmd = 'cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && HOME=' . escapeshellarg($home) . ' git ls-files 2>/dev/null';
        exec($cmd, $out, $code);

        @unlink($home . '/.gitconfig');
        @rmdir($home);

        return $code === 0 ? $out : null;
    }
}
