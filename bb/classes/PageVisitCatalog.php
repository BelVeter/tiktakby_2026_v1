<?php

namespace bb\classes;

/**
 * Какие файлы bb/*.php — реальные страницы, а какие технические (ajax/api/badge
 * запросы) или чистые классы-библиотеки (namespace bb; class X — подключаются
 * через require_once другими файлами, никогда не открываются напрямую по URL).
 *
 * Используется в трёх местах: PageVisitTracker.php (что не логировать),
 * bb/page_track.php (отчёт по страницам) и Mcp/StaffController.php (тот же
 * список для ИИ-агента). bb\classes\ объявлен в composer.json psr-4, поэтому
 * из Laravel класс доступен через обычный `use`; из голых bb/-скриптов —
 * через require_once (в bb/ автозагрузка composer не используется).
 */
class PageVisitCatalog
{
    /**
     * Топ-уровневые bb/*.php файлы, которые физически являются классами
     * (namespace bb; class X {...}), а не страницами. Выверено вручную
     * 03.09.2026 по признаку «объявляет class И нигде не вызывает
     * session_start()» — верно для всех настоящих страниц bb/. Новый файл
     * такого рода придётся добавить сюда руками.
     */
    private const LIBRARY_FILES = [
        'base_lowercase.php',
        'client.php',
        'Db.php',
        'DealRow.php',
        'Delivery.php',
        'DeliveryPage.php',
        'DohRash.php',
        'KarnavalBron.php',
        'Kassa.php',
        'KBron.php',
        'KBronForm.php',
        'Office.php',
        'Payment.php',
        'Schedule.php',
        'Signature.php',
        'tovar.php',
        'User.php',
    ];

    /**
     * true — технический/служебный файл: не считается «страницей» ни для
     * логирования, ни для отчёта/API.
     */
    public static function isTechnical(string $filename): bool
    {
        if (strpos($filename, 'ajax_') === 0) {
            return true;
        }
        foreach (['_api.php', '_badge.php'] as $suffix) {
            if (substr($filename, -strlen($suffix)) === $suffix) {
                return true;
            }
        }
        return in_array($filename, self::LIBRARY_FILES, true);
    }

    /**
     * Отсортированный список реальных страниц bb/*.php (верхний уровень, без
     * подкаталогов) минус технические/библиотечные файлы.
     *
     * @return string[]
     */
    public static function listTrackablePages(): array
    {
        $files = glob(__DIR__ . '/../*.php') ?: [];
        $pages = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!self::isTechnical($name)) {
                $pages[] = $name;
            }
        }
        sort($pages);
        return $pages;
    }
}
