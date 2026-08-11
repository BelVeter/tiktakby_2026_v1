<?php

namespace Tests\Feature;

use bb\classes\WebOrderDeal;
use Tests\TestCase;

/**
 * bb\classes\WebOrderDeal пишет в денежные таблицы (rent_deals_act, rent_sub_deals_act,
 * clients) и выполняется без человека — на каждом заказе с доставкой. Проверить его
 * на живой базе из тестов нельзя, поэтому здесь ловим то, что ловится статически:
 * расхождение числа колонок и значений в INSERT и опечатки в именах колонок.
 *
 * Имена колонок сверяются со списками из легаси-запросов, которые перечисляют
 * таблицы целиком (bb/deals_arch.php, bb/cur_page2.php).
 */
class WebOrderDealSqlTest extends TestCase
{
    private const DEAL_COLUMNS = [
        'deal_id', 'client_id', 'item_inv_n', 'start_date', 'return_date', 'delivery_yn',
        'delivery_to_pay', 'delivery_paid', 'r_to_pay', 'r_paid', 'collateral_amount',
        'collateral_cur', 'deal_status', 'deal_info', 'acc_person_id', 'cr_who_id', 'cr_time',
        'last_sub_deal_ch_time', 'planned_return_date', 'deal_set', 'first_rent_place',
    ];

    private const SUB_DEAL_COLUMNS = [
        'sub_deal_id', 'deal_id', 'type', 'type_sort_n', 'from', 'to', 'tarif_id', 'tarif_step',
        'tarif_value', 'rent_tenor', 'r_to_pay', 'delivery_yn', 'delivery_to_pay', 'courier_id',
        'r_paid', 'delivery_paid', 'r_payment_type', 'del_payment_type', 'status', 'info',
        'cr_time', 'cr_who_id', 'ch_time', 'ch_who_id', 'link', 'acc_date', 'place', 'ch_num',
        'sd_cat_id', 'sd_model_id', 'sd_inv_n',
    ];

    private const CLIENT_COLUMNS = [
        'client_id', 'family', 'name', 'otch', 'city', 'str', 'dom', 'kv', 'pas_n', 'pas_ln',
        'pas_date', 'pas_who', 'reg_city', 'reg_str', 'reg_dom', 'reg_kv', 'phone_1', 'phone_2',
        'info', 'status', 'cr_time', 'arch_n', 'arch_amount', 'arch_l_date', 'cr_who', 'source',
    ];

    /** @return array<string, array{cols: string[], vals: int}> */
    private function inserts(): array
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/bb/classes/WebOrderDeal.php');

        // Условные куски deal_id склеиваются в один элемент списка и в колонках, и в значениях
        $src = str_replace(['$idCol ', '$idVal '], '', $src);

        preg_match_all(
            '~INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]*)\)\s*\n?\s*VALUES\s*\((.*?)\)"~s',
            $src,
            $m,
            PREG_SET_ORDER
        );

        $out = [];
        foreach ($m as $one) {
            $cols = array_values(array_filter(array_map(
                fn($c) => trim($c, " \t\n`"),
                explode(',', $one[2])
            )));
            $out[$one[1]] = ['cols' => $cols, 'vals' => substr_count($one[3], ',') + 1];
        }

        return $out;
    }

    public function test_every_insert_is_found(): void
    {
        $this->assertSame(
            ['clients', 'rent_deals_act', 'rent_sub_deals_act'],
            array_keys($this->inserts()),
            'изменился набор INSERT в WebOrderDeal — проверьте тест'
        );
    }

    public function test_column_and_value_counts_match(): void
    {
        foreach ($this->inserts() as $table => $ins) {
            $this->assertCount(
                $ins['vals'],
                $ins['cols'],
                "в INSERT INTO $table число колонок не совпало с числом значений"
            );
        }
    }

    public function test_column_names_exist_in_tables(): void
    {
        $known = [
            'clients' => self::CLIENT_COLUMNS,
            'rent_deals_act' => self::DEAL_COLUMNS,
            'rent_sub_deals_act' => self::SUB_DEAL_COLUMNS,
        ];

        foreach ($this->inserts() as $table => $ins) {
            foreach ($ins['cols'] as $col) {
                $this->assertContains(
                    $col,
                    $known[$table],
                    "колонки $col нет в таблице $table"
                );
            }
        }
    }

    public function test_courier_info_carries_what_the_courier_needs(): void
    {
        $text = WebOrderDeal::courierInfo(
            'Минск, ул.Ауэзова, 8/3, кв.169',
            '+375291234567',
            'Viber',
            'Будем с 15.00',
            true
        );

        $this->assertStringContainsString('Паспорт не заполнен', $text);
        $this->assertStringContainsString('Текущий адрес доставки: Минск, ул.Ауэзова, 8/3, кв.169', $text);
        $this->assertStringContainsString('Связь: Viber', $text);
        $this->assertStringContainsString('возврат курьером', $text);
        $this->assertStringContainsString('Будем с 15.00', $text);
    }

    public function test_courier_info_skips_empty_parts(): void
    {
        $text = WebOrderDeal::courierInfo('Минск, Немига 5', '', '', '', false);

        $this->assertStringNotContainsString('Связь:', $text);
        $this->assertStringNotContainsString('Телефон:', $text);
        $this->assertStringNotContainsString('Клиент просит:', $text);
        $this->assertStringNotContainsString('возврат курьером', $text);
    }

    /**
     * Заказать с сайта можно двумя путями — через корзину и прямо с карточки товара.
     * Если автоматику подключат только к одному, часть доставок молча не доедет
     * до страницы курьера, и заметят это не сразу.
     */
    public function test_both_order_paths_create_courier_trips(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['CartController', 'L3Controller'] as $controller) {
            $src = file_get_contents($root . '/app/Http/Controllers/' . $controller . '.php');
            $this->assertStringContainsString(
                'WebOrderDeal::createDealWithTrips',
                $src,
                "$controller больше не создаёт выезд курьера по заказу с доставкой"
            );
        }
    }

    public function test_tariff_resolution_without_tariffs_is_safe(): void
    {
        $this->assertSame(
            ['id' => '', 'step' => '', 'value' => '0.00'],
            WebOrderDeal::resolveTariff(null, 10)
        );
    }
}
