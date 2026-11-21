<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Статические картинки главной (src без Blade-выражений в home, шапке, футере, слайдере и url() в
 * стилях её секций) должны существовать, а новые картинки (public/img/home/, public/img/site/) —
 * ещё и отслеживаться git; иначе на проде они отдают 404.
 *
 * Ловушка: отсутствующая картинка под /public/ не отдаётся nginx'ом как статика, а уходит
 * в Laravel (у public/.htaccess свой RewriteEngine) и возвращает полноценную HTML-страницу 404
 * (~212 КБ) — так 8 битых фото отзывов и блока Instagram добавляли ~1.7 МБ к первому заходу
 * на главную с телефона (ноябрь 2026). .gitignore игнорирует *.jpg/*.png/*.webp, поэтому
 * картинка, положенная без исключения, локально есть, а на проде — нет.
 */
class HomeStaticImagesTest extends TestCase
{
    private const VIEWS = [
        'home.blade.php',
        'includes/header.blade.php',
        'includes/footer.blade.php',
        'includes/carousel.blade.php',
    ];

    private const STYLES = [
        'sections/review.scss',
        'sections/insta.scss',
    ];

    public function test_static_images_on_home_page_exist_and_are_tracked(): void
    {
        $root = dirname(__DIR__, 2);

        $candidates = [];
        foreach (self::VIEWS as $view) {
            $blade = file_get_contents($root . '/resources/views/' . $view);
            $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
            preg_match_all('/<img\b[^>]*>/is', $blade, $tags);
            foreach ($tags[0] as $tag) {
                if (preg_match('/\bsrc="([^"]*)"/i', $tag, $m)) {
                    $candidates[] = $m[1];
                }
            }
        }
        foreach (self::STYLES as $style) {
            $css = file_get_contents($root . '/resources/sass/' . $style);
            $css = preg_replace('#^\s*//.*$#m', '', $css);
            preg_match_all('/url\(["\']?([^"\')]+)["\']?\)/i', $css, $m);
            $candidates = array_merge($candidates, $m[1]);
        }

        $srcs = [];
        foreach ($candidates as $src) {
            if (strpos($src, '{{') !== false || strpos($src, '{!!') !== false) {
                continue; // динамический путь из БД — не проверяем
            }
            $src = preg_replace('#^https?://(www\.)?tiktak\.by#i', '', $src);
            if (strpos($src, '/') !== 0 || strpos($src, '//') === 0) {
                continue; // внешний ресурс
            }
            $srcs[] = strtok($src, '?#');
        }

        $this->assertNotEmpty($srcs, 'Не найдено ни одной статической картинки.');

        $gitUsable = true;
        foreach (array_unique($srcs) as $src) {
            $rel = ltrim($src, '/');
            $this->assertFileExists($root . '/' . $rel, "Картинка $src на главной не существует (на проде будет 404).");

            // Часть старых картинок (public/png/*.png и др.) исторически залита на прод вручную,
            // мимо git. Требовать git только от новых папок public/img/home/ и public/img/site/.
            if (strpos($rel, 'public/img/home/') !== 0 && strpos($rel, 'public/img/site/') !== 0) {
                continue;
            }

            $tracked = $this->isTracked($root, $rel);
            if ($tracked === null) {
                $gitUsable = false;
                continue;
            }
            $this->assertTrue($tracked, "Картинка $src не отслеживается git — на прод не попадёт (нужно исключение в .gitignore или git add -f).");
        }

        if (!$gitUsable) {
            $this->markTestSkipped('git недоступен: проверено только существование файлов.');
        }
    }

    /**
     * true — файл отслеживается git; false — нет; null — git не сработал.
     * См. LayoutIconLinksTest::isTracked() — тот же приём с подменой HOME для docker.
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
