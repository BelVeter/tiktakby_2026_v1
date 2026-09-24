<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Иконки в <head> должны указывать на реально существующие файлы, которые
 * попадут на прод через git.
 *
 * Ловушка: .gitignore игнорирует *.png и *.ico, поэтому иконка, добавленная
 * обычным `git add`, локально есть, а на проде — нет (так /tiktak.ico и
 * /public/favicon-32x32.png месяцами отдавали 404 на каждой странице).
 * git check-ignore возвращает 0 только для файлов, которые игнорируются И не
 * отслеживаются, то есть именно для такой потери.
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

            $ignored = $this->isIgnoredAndUntracked($root, $rel);
            if ($ignored === null) {
                $gitUsable = false;
                continue;
            }
            $this->assertFalse($ignored, "Иконка $href игнорируется .gitignore и не отслеживается — на прод не попадёт (нужен git add -f).");
        }

        if (!$gitUsable) {
            $this->markTestSkipped('git недоступен: проверено только существование файлов иконок.');
        }
    }

    /**
     * true — файл игнорируется и не отслеживается; false — нормально; null — git не сработал.
     * HOME подменяется на временный каталог с safe.directory=*, иначе git в docker-контейнере
     * отказывается работать с репозиторием, смонтированным под другим владельцем.
     */
    private function isIgnoredAndUntracked(string $root, string $rel): ?bool
    {
        $home = sys_get_temp_dir() . '/git-home-' . getmypid();
        @mkdir($home);
        file_put_contents($home . '/.gitconfig', "[safe]\n\tdirectory = *\n");

        $cmd = 'cd ' . escapeshellarg($root) . ' && HOME=' . escapeshellarg($home)
            . ' git check-ignore -q -- ' . escapeshellarg($rel) . ' 2>/dev/null';
        exec($cmd, $out, $code);

        @unlink($home . '/.gitconfig');
        @rmdir($home);

        return $code > 1 ? null : $code === 0;
    }
}
