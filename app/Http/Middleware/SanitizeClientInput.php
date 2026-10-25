<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Гасит HTML в тексте, который посетитель присылает формами сайта (маршруты с 'client.input').
 *
 * Админка bb/ печатает этот текст (имя, телефон, комментарий, адрес) во многих местах без
 * экранирования, в том числе внутри value="…", — экранировать каждый вывод в легаси долго.
 * Поэтому на входе: теги удаляются, а оставшиеся < > " ' меняются на безопасные похожие
 * символы (‹ › ” ’), чтобы смысл текста («рост <100», «Мар'яна») не терялся.
 * Служебную разметку (<br>, <span class="bk-days">) контроллеры добавляют уже после.
 */
class SanitizeClientInput extends TransformsRequest
{
    protected $except = ['_token'];

    protected function transform($key, $value)
    {
        if (!is_string($value) || in_array($key, $this->except, true)) {
            return $value;
        }
        return $key === 'phone' ? self::cleanPhone($value) : self::cleanText($value);
    }

    public static function cleanText(string $s): string
    {
        $s = preg_replace('/<[a-zA-Z\/!?][^<>]*>/u', '', $s);
        return strtr($s, ['<' => '‹', '>' => '›', '"' => '”', "'" => '’']);
    }

    public static function cleanPhone(string $s): string
    {
        $s = preg_replace('/<[a-zA-Z\/!?][^<>]*>/u', '', $s);
        return preg_replace('/[^0-9+()\- ]/', '', $s);
    }
}
