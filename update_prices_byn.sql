UPDATE tovar_rent
SET agr_price = agr_price * 3,
    agr_price_cur = 'BYN'
WHERE agr_price_cur = 'USD';
