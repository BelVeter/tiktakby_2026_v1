<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * sitemap.xml генерируется командой sitemap:generate и не должен лежать в git.
 *
 * Ловушка: Deploy.php делает `git reset --hard`, поэтому закоммиченный sitemap.xml
 * после каждого деплоя возвращал старую версию (23.09.2026 вернулась версия от 11.08:
 * 70 из 1021 адреса не отдавали 200) — свежий файл появлялся только в 02:00.
 */
class SitemapNotTrackedTest extends TestCase
{
    public function test_sitemap_files_are_not_tracked_by_git(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['sitemap.xml', 'public/sitemap.xml'] as $rel) {
            $tracked = $this->isTracked($root, $rel);
            if ($tracked === null) {
                $this->markTestSkipped('git недоступен.');
            }
            $this->assertFalse($tracked, "$rel отслеживается git — деплой будет откатывать свежий sitemap к закоммиченной версии.");
        }
    }

    /**
     * true — файл отслеживается git; false — нет; null — git не сработал.
     * HOME подменяется на временный каталог с safe.directory=*, иначе git в docker-контейнере
     * отказывается работать с репозиторием, смонтированным под другим владельцем.
     */
    private function isTracked(string $root, string $rel): ?bool
    {
        $home = sys_get_temp_dir() . '/git-home-' . getmypid();
        @mkdir($home);
        file_put_contents($home . '/.gitconfig', "[safe]\n\tdirectory = *\n");

        $cmd = 'cd ' . escapeshellarg($root) . ' && HOME=' . escapeshellarg($home)
            . ' git ls-files --error-unmatch -- ' . escapeshellarg($rel) . ' 2>/dev/null';
        exec($cmd, $out, $code);

        @unlink($home . '/.gitconfig');
        @rmdir($home);

        return $code === 0 ? true : ($code === 1 ? false : null);
    }
}
