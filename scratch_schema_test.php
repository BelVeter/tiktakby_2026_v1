<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Let's find a product and build its schema
$modelWebs = \Illuminate\Support\Facades\DB::table('rent_model_web')->where('status', 'not like', '%hide%')->take(5)->get();
foreach($modelWebs as $mw) {
    app()->setLocale('ru');
    $l3Page = new \App\MyClasses\L3Page($mw->model_id);
    echo "Product ID: " . $mw->model_id . "\n";
    $schema = $l3Page->getSchemaJsonLd();
    echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}
