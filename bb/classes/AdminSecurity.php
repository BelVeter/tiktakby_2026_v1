<?php

namespace bb\classes;

/**
 * Защита страниц админки bb/ на уровне браузера. Вызывается из auto_prepend_file
 * (bb/classes/PageVisitTracker.php) — до session_start() любой страницы bb/.
 *
 * Зачем: в базе бывают тексты посетителей с HTML (спам с <img>/<a>, ноябрь 2026). Если такой
 * текст где-то выведут без экранирования, браузер сотрудника не должен:
 *  - отдавать скрипту на странице cookie входа в админку (HttpOnly);
 *  - сообщать чужому сайту адрес страницы админки (Referrer-Policy);
 *  - показывать админку внутри чужого сайта (frame-ancestors / X-Frame-Options);
 *  - грузить картинки и отправлять данные на чужие сайты (CSP img-src/connect-src).
 *
 * CSP пока в режиме Report-Only: браузер ничего не блокирует, а только присылает отчёты
 * на /csp-report (storage/logs/csp-*.log). Когда отчёты покажут, что легальным страницам
 * хватает списка ниже, политику переводим в обязательную (см. docs/prod_pending.md).
 */
class AdminSecurity
{
    /** Внешние библиотеки, которые подключают страницы bb/ (CDN, шрифты, TinyMCE, Google Charts). */
    private const CDN = 'https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com'
        . ' https://ajax.googleapis.com https://maxcdn.bootstrapcdn.com https://stackpath.bootstrapcdn.com'
        . ' https://code.jquery.com https://cdn.tiny.cloud https://www.gstatic.com';

    public static function apply(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            if (self::isHttps($_SERVER)) {
                ini_set('session.cookie_secure', '1');
            }
        }

        if (headers_sent()) {
            return;
        }
        foreach (self::headers() as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    public static function headers(): array
    {
        return [
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            // То, что не может сломать ни одну страницу админки, включаем сразу.
            'Content-Security-Policy' => "frame-ancestors 'self'; object-src 'none'; base-uri 'self'",
            'Content-Security-Policy-Report-Only' => self::reportOnlyPolicy(),
        ];
    }

    public static function reportOnlyPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . self::CDN,
            "style-src 'self' 'unsafe-inline' " . self::CDN . ' https://fonts.googleapis.com https://fonts.cdnfonts.com',
            "font-src 'self' data: " . self::CDN . ' https://fonts.gstatic.com https://fonts.cdnfonts.com',
            // Главное: картинки только свои (+ тайлы карты клиентов и иконки TinyMCE).
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://cdn.tiny.cloud",
            "connect-src 'self'",
            "media-src 'self' blob:",
            "frame-src 'self'",
            "form-action 'self'",
            'report-uri /csp-report',
        ]);
    }

    /** За nginx Apache может не видеть HTTPS напрямую — смотрим и заголовок прокси. */
    public static function isHttps(array $server): bool
    {
        return (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off')
            || strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string)($server['SERVER_PORT'] ?? '') === '443';
    }
}
