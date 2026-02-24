<?php
$html = file_get_contents('https://tiktak.by/ru/prokat-detskih-tovarov/detskaya-komnata/elektrokacheli-i-shezlongi');
preg_match_all('/src="(\/public\/rent\/images\/[^"]+\.(?:jpg|webp))"/i', $html, $matches);
echo "Shezlongi:\n";
print_r(array_unique($matches[1]));

$html = file_get_contents('https://tiktak.by/ru/prokat-detskih-tovarov/detskaya-komnata/kolybeli');
preg_match_all('/src="(\/public\/rent\/images\/[^"]+\.(?:jpg|webp))"/i', $html, $matches);
echo "\nKolybeli:\n";
print_r(array_unique($matches[1]));

$html = file_get_contents('https://tiktak.by/ru/prokat-detskih-tovarov/ukhod-i-razvitie/vannochki-dlya-kupaniya');
preg_match_all('/src="(\/public\/rent\/images\/[^"]+\.(?:jpg|webp))"/i', $html, $matches);
echo "\nVannochka:\n";
print_r(array_unique($matches[1]));

$html = file_get_contents('https://tiktak.by/ru/prokat-detskih-tovarov/ukhod-i-razvitie/detskie-vesy');
preg_match_all('/src="(\/public\/rent\/images\/[^"]+\.(?:jpg|webp))"/i', $html, $matches);
echo "\nVesy:\n";
print_r(array_unique($matches[1]));

$html = file_get_contents('https://tiktak.by/ru/prokat-detskih-tovarov/ukhod-i-razvitie/radio-i-videonyani');
preg_match_all('/src="(\/public\/rent\/images\/[^"]+\.(?:jpg|webp))"/i', $html, $matches);
echo "\nRadio:\n";
print_r(array_unique($matches[1]));
