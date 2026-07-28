<?php

namespace App\Http\Controllers;

use App\MyClasses\CatMainPage;
use App\MyClasses\L2ModelWeb;
use bb\classes\Model;
use bb\classes\ModelWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use PhpParser\Node\Expr\AssignOp\Mod;

class SearchController extends Controller
{
    /** Кол-во карточек на страницу листинга (как в каталоге, MainPage). */
    private const LISTING_LIMIT = 24;

    /**
     * Строит карточки только для одной страницы пагинации (срез по LISTING_LIMIT),
     * чтобы не плодить N+1 на сотнях моделей. Возвращает общее число найденных моделей.
     */
    private function buildPageModels(CatMainPage $p, $modelIdArray, int $page): int
    {
        $ids = is_array($modelIdArray) ? array_values($modelIdArray) : [];
        $slice = array_slice($ids, ($page - 1) * self::LISTING_LIMIT, self::LISTING_LIMIT);
        foreach ($slice as $mid) {
            if ($l2m = L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
        }
        return count($ids);
    }

    public function search($lang, Request $req) {
        $text = trim($req->input('search'));
        $p = new CatMainPage();

        $p->setPageTitle('Результаты поиска детских товаров напрокат в Минске:');
        $p->setH1Title('Результаты поиска по запросу: "'.$text.'"');
        $p->addBreadCrumbItem('поиск', '');

        $modelIdArray = ModelWeb::getModelIdsFullTextSearch($text);
        $page = max(1, (int) $req->input('page', 1));
        $total = $this->buildPageModels($p, $modelIdArray, $page);

        // Результаты внутреннего поиска не индексируем (служебная страница),
        // но ссылочный вес пропускаем на карточки товаров.
        return view('search', [
            'p' => $p,
            'robots' => 'noindex,follow',
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / self::LISTING_LIMIT),
            'paginationBase' => '/ru/search?search=' . urlencode($text),
        ]);
    }

    public function ageFilter($lang, Request $req) {
        $from = intval($req->input('age_from'));
        $to = intval($req->input('age_to'));

        $p = new CatMainPage();

        $p->setPageTitle('Детские товары напрокат в Минске: от '.$from.' мес., до '.$to.' мес.');

        switch ($from) {
            case 0;
                $p->setH1Title('от 0 до 6 месяцев');
            break;
            case 6:
                $p->setH1Title('от 6 месяцев до года');
                break;
            case 12:
                $p->setH1Title('от года до 1.5 лет');
                break;
            case 18:
                $p->setH1Title('от 1.5 до 2 лет');
                break;
            case 24:
                $p->setH1Title('от 2 до 3 лет');
                break;
            case 36:
                $p->setH1Title('3+ лет');
                break;
            default:
                $p->setH1Title('от '.$from.' до '.$to.' месяцев');
                break;
        }
        $p->addBreadCrumbItem('по возрасту', '');
        $p->setShowAgeFilter(1);

        $modelIdArray = Model::getModelIdsArrayByAge($from, $to);
        $page = max(1, (int) $req->input('page', 1));
        $total = $this->buildPageModels($p, $modelIdArray, $page);

        $viewData = [
            'p' => $p,
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / self::LISTING_LIMIT),
            'paginationBase' => '/ru/filter?age_from=' . $from . '&age_to=' . $to,
        ];
        if (($req->has('age_from') || $req->has('age_to')) && $page === 1) {
            // Индексируемый лендинг по возрасту (1-я стр.): self-canonical в точной
            // форме ссылки (как в шаблонах: /ru/filter?age_from=X&age_to=Y).
            $viewData['canonical_url'] = 'https://tiktak.by/ru/filter?age_from=' . $from . '&age_to=' . $to;
        } else {
            // Голый /ru/filter без параметров либо страница пагинации >1 — не индексируем.
            $viewData['robots'] = 'noindex,follow';
        }

        return view('search', $viewData);
    }

    /**
     * Переименованные производители: старое написание => каноничное.
     *
     * Лендинг производителя индексируется (self-canonical ниже), а
     * `CheckRedirects` матчит только путь, без query-строки — через модуль
     * редиректов такой адрес не перенаправить. Поэтому 301 отдаём здесь.
     * Ключ — нормализованная форма (нижний регистр + trim), чтобы разом
     * покрыть и вариант с ведущим пробелом, который коллация БД не склеивает.
     *
     * @see database/migrations/2026_07_28_170000_normalize_producer_spellings.php
     */
    private const PRODUCER_RENAMED = [
        'simple parenting' => 'Simple Parenting Doona',
        'i love mum, рф'   => 'I love mum',
    ];

    public function producerFilter($lang, Request $req){
        $producer = $req->input('producer');
        //echo 'ddd'.$producer;

        $renamedTo = self::PRODUCER_RENAMED[mb_strtolower(trim((string) $producer))] ?? null;
        if ($renamedTo !== null && $renamedTo !== $producer) {
            return redirect('/ru/producer?producer=' . urlencode($renamedTo), 301);
        }

        $p = new CatMainPage();

        $p->setPageTitle('Детские товары '.$producer);
        $p->addBreadCrumbItem('по возрасту', '');

        $modelIdArray = Model::getModelIdsArrayByProducer($producer);
        $page = max(1, (int) $req->input('page', 1));
        $total = $this->buildPageModels($p, $modelIdArray, $page);

        $viewData = [
            'p' => $p,
            'currentPage' => $page,
            'totalPages' => (int) ceil($total / self::LISTING_LIMIT),
            'paginationBase' => '/ru/producer?producer=' . urlencode((string) $producer),
        ];
        if ($producer !== null && $producer !== '' && $total > 0 && $page === 1) {
            // Индексируемый лендинг производителя (1-я стр.): self-canonical.
            // urlencode() повторяет ту же кодировку, что и ссылки в шаблонах
            // (Producer::getNameUrlEncoded()), чтобы canonical точно совпал с href.
            $viewData['canonical_url'] = 'https://tiktak.by/ru/producer?producer=' . urlencode($producer);
        } else {
            // Пусто (нет производителя/товаров) либо страница пагинации >1 — не индексируем.
            $viewData['robots'] = 'noindex,follow';
        }

        return view('search', $viewData);
    }
}
