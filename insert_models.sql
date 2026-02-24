INSERT INTO tovar_rent 
(tovar_rent_cat_id, producer, model, `set`, color, agr_price, agr_price_cur, lom_srok, cr_ch_date, user, model_addr, ph_addr, age_from, age_to, weight_from, weight_to, ny, zv, tale, rez1, rez2, collateral, m_sex, price_new) 
VALUES 
(27, 'TikTak', 'Подписка Premium Start - Базовый', 'стандартный', 'multicolor', 0, 'BYN', 0, UNIX_TIMESTAMP(), 'admin', '', '', 0, 36, 0, 99, 0, 0, 0, 0, 0, 0, 'u', 0);
SET @m1 = LAST_INSERT_ID();

INSERT INTO tovar_rent_items 
(cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size, item_rost1, item_rost2, item_set, buy_date, buy_price, buy_price_cur, exch_to_byr, seller, item_info, cr_ch_date, user, status, active_deal_id, item_color, item_place, br_time, state, to_move, qr_yn) 
VALUES 
(27, 'TikTak', @m1, 1, @m1*1000+1, 'u', 'n/a', 'n/a', 0, 99, 'стандартный', UNIX_TIMESTAMP(), 0, 'BYN', 1.0, 'owner', 'virtual item for subscriptions', UNIX_TIMESTAMP(), 'admin', 'to_rent', 0, 'multicolor', 0, 0, 0, 0, 0);


INSERT INTO tovar_rent 
(tovar_rent_cat_id, producer, model, `set`, color, agr_price, agr_price_cur, lom_srok, cr_ch_date, user, model_addr, ph_addr, age_from, age_to, weight_from, weight_to, ny, zv, tale, rez1, rez2, collateral, m_sex, price_new) 
VALUES 
(27, 'TikTak', 'Подписка Premium Start - Оптимальный', 'стандартный', 'multicolor', 0, 'BYN', 0, UNIX_TIMESTAMP(), 'admin', '', '', 0, 36, 0, 99, 0, 0, 0, 0, 0, 0, 'u', 0);
SET @m2 = LAST_INSERT_ID();

INSERT INTO tovar_rent_items 
(cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size, item_rost1, item_rost2, item_set, buy_date, buy_price, buy_price_cur, exch_to_byr, seller, item_info, cr_ch_date, user, status, active_deal_id, item_color, item_place, br_time, state, to_move, qr_yn) 
VALUES 
(27, 'TikTak', @m2, 1, @m2*1000+1, 'u', 'n/a', 'n/a', 0, 99, 'стандартный', UNIX_TIMESTAMP(), 0, 'BYN', 1.0, 'owner', 'virtual item for subscriptions', UNIX_TIMESTAMP(), 'admin', 'to_rent', 0, 'multicolor', 0, 0, 0, 0, 0);


INSERT INTO tovar_rent 
(tovar_rent_cat_id, producer, model, `set`, color, agr_price, agr_price_cur, lom_srok, cr_ch_date, user, model_addr, ph_addr, age_from, age_to, weight_from, weight_to, ny, zv, tale, rez1, rez2, collateral, m_sex, price_new) 
VALUES 
(27, 'TikTak', 'Подписка Premium Start - Премиум', 'стандартный', 'multicolor', 0, 'BYN', 0, UNIX_TIMESTAMP(), 'admin', '', '', 0, 36, 0, 99, 0, 0, 0, 0, 0, 0, 'u', 0);
SET @m3 = LAST_INSERT_ID();

INSERT INTO tovar_rent_items 
(cat_id, producer, model_id, item_n, item_inv_n, sex, item_size, real_item_size, item_rost1, item_rost2, item_set, buy_date, buy_price, buy_price_cur, exch_to_byr, seller, item_info, cr_ch_date, user, status, active_deal_id, item_color, item_place, br_time, state, to_move, qr_yn) 
VALUES 
(27, 'TikTak', @m3, 1, @m3*1000+1, 'u', 'n/a', 'n/a', 0, 99, 'стандартный', UNIX_TIMESTAMP(), 0, 'BYN', 1.0, 'owner', 'virtual item for subscriptions', UNIX_TIMESTAMP(), 'admin', 'to_rent', 0, 'multicolor', 0, 0, 0, 0, 0);

SELECT @m1 AS 'Базовый', @m2 AS 'Оптимальный', @m3 AS 'Премиум';
