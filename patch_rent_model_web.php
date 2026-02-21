<?php

// Script to patch rent_model_web table after initial migration missed it.
// It will look for L2, L3, and logo fields and update the hardcoded paths 
// pointing to legacy root folders to instead point to /public/rent/images/...

require_once 'bb/Db.php';

$mysqli = \bb\Db::getInstance()->getConnection();

echo "Starting rent_model_web patch...\n";

// Fields to check and their purpose
$target_fields = [
    'l2_pic' => 'L2 Image',
    'm_pic_big' => 'L3 Main Image',
    'logo' => 'Producer Logo'
];

$updates_count = 0;
$scanned_count = 0;

$query = "SELECT web_id, l2_pic, m_pic_big, logo FROM rent_model_web";
$res = $mysqli->query($query);

if (!$res) {
    die("Error fetching from rent_model_web: " . $mysqli->error);
}

// Prepare update statement for safety
$stmt = $mysqli->prepare("UPDATE rent_model_web SET l2_pic = ?, m_pic_big = ?, logo = ? WHERE web_id = ?");

while ($row = $res->fetch_assoc()) {
    $scanned_count++;

    $web_id = $row['web_id'];
    $l2_pic = $row['l2_pic'];
    $m_pic_big = $row['m_pic_big'];
    $logo = $row['logo'];

    $needs_update = false;

    // We only process fields that start with a slash and are not already migrated
    foreach (['l2_pic' => &$l2_pic, 'm_pic_big' => &$m_pic_big, 'logo' => &$logo] as $field_name => &$val) {
        if (!empty($val) && strpos($val, '/public/rent/images/') !== 0 && strpos($val, '/') === 0) {

            // It's a legacy root path like "/avtokresla/img/..."
            $new_val = '/public/rent/images' . $val;

            echo "Patching [ID $web_id] $field_name: $val -> $new_val\n";
            $val = $new_val;
            $needs_update = true;
        }
    }

    if ($needs_update) {
        $stmt->bind_param("sssi", $l2_pic, $m_pic_big, $logo, $web_id);
        if ($stmt->execute()) {
            $updates_count++;
        } else {
            echo "Failed to update web_id $web_id: " . $stmt->error . "\n";
        }
    }
}

echo "\nPatch completed.\n";
echo "Rows scanned: $scanned_count\n";
echo "Rows updated: $updates_count\n";
