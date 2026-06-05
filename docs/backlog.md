# Backlog (доработки и техдолг)

Список отложенных задач и техдолга. Заводится по ходу работы. Связанные находки — в [docs/db_notes.md](db_notes.md).

## Легаси для чистки (мёртвый код, найдено 2026-06-05)

Подтверждено grep'ом — нигде не подключается / уже несовместимо со схемой:

- [ ] `bb/bron.php` — глобальный `class bron` без namespace; живой класс — `bb\classes\bron`. Никто не require'ит.
- [ ] `bb/l_3_br.php` — старая схема INSERT; шаблоны постят на `includes/l_3_br.php` (живой).
- [ ] `bb/classes/old_bron.php` — старая схема (меньше колонок), не подключается.
- [ ] `bb/bron/rent_orders.php` — старая схема, не подключается.
- [ ] `includes/zvonok.php` — позиционный INSERT в `zvonki` (11 значений vs 14 колонок) → уже несовместим; живой путь — `ZvonokController`. Статические `.html`-хедеры, которые на него постят, переадресованы.
- [ ] `includes/*.html` — старые статические хедеры (`header.html`, `header_karnaval.html`, `header_igrushki.html`, `arch/*`). Все переадресованы; кандидаты на удаление.
- [ ] Прочие `* copy.php` (`bb/scanner_tovar copy.php`, `bb/top_menu copy.php`) — проверить и удалить.

## Технические улучшения (отложено)

- [ ] Перевести **остальные** позиционные `INSERT ... VALUES` по проекту на явные колонки (шире, чем редизайн заявок) — устранить gotcha №1 в db_notes.
- [ ] Заявки: напоминания/дайджест по `planned_date` (после редизайна, мелкой доработкой).
- [ ] Аналитика: исключить `z_status='spam'` из отчётов по спросу (`reports.php`, MCP) — опционально.

## В работе

- Редизайн заявок — ветка `feature/zayavki-redesign`, спека [docs/superpowers/specs/2026-06-05-zayavki-redesign-design.md](superpowers/specs/2026-06-05-zayavki-redesign-design.md).
