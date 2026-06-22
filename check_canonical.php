<?php
$sitemap = file_get_contents("https://tiktak.by/sitemap.xml");
preg_match_all("/<loc>(.*?)<\/loc>/", $sitemap, $matches);
$urls = $matches[1];
$count = 0;
foreach($urls as $url) {
    if(strpos($url, ".jpg") !== false || strpos($url, ".png") !== false) continue;
    $html = @file_get_contents($url);
    if($html && preg_match("/^\s*<link rel=[\"']canonical/", $html)) {
        echo "FOUND ON $url\n";
    }
    $count++;
    if($count > 100) break;
}
echo "Checked $count URLs.\n";
