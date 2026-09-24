<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Иконки в <head> должны указывать на реально существующие файлы, которые
 * попадут на прод через git.
 *
 * Ловушка: .gitignore игнорирует *.png и *.ico (для иконок сделаны исключения),
 * поэтому иконка, положенная в неподходящее место, локально есть, а на проде —
 * нет (так /tiktak.ico и /public/favicon-32x32.png месяцами отдавали 404 на каждой
 * странице). Проверяем, что файл существует И отслеживается git.
 */
class LayoutIconLinksTest extends TestCase
{
    public function test_icon_links_point_to_existing_tracked_files(): void
    {
        $root = dirname(__DIR__, 2);
        $blade = file_get_contents($root . '/resources/views/layouts/app.blade.php');
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

        preg_match_all('/<link\b[^>]*>/i', $blade, $tags);

        $hrefs = [];
        foreach ($tags[0] as $tag) {
            if (!preg_match('/\brel="[^"]*icon[^"]*"/i', $tag)) {
                continue;
            }
            if (preg_match('/\bhref="(\/[^"?#]*)/i', $tag, $m)) {
                $hrefs[] = $m[1];
            }
        }

        $this->assertNotEmpty($hrefs, 'В layouts/app.blade.php не найдено ни одной иконки.');

        $gitUsable = true;
        foreach (array_unique($hrefs) as $href) {
            $rel = ltrim($href, '/');
            $this->assertFileExists($root . '/' . $rel, "Иконка $href не существует.");

            $tracked = $this->isTracked($root, $rel);
            if ($tracked === null) {
                $gitUsable = false;
                continue;
            }
            $this->assertTrue($tracked, "Иконка $href не отслеживается git — на прод не попадёт (git add; для нестандартного пути нужно исключение в .gitignore).");
        }

        if (!$gitUsable) {
            $this->markTestSkipped('git недоступен: проверено только существование файлов иконок.');
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
