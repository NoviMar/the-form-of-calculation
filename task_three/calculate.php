<?php
require_once 'backend/sdbh.php';
$dbh = new sdbh();

$product_id = $_POST['product'];
$days = $_POST['days'];
$services = isset($_POST['services']) ? $_POST['services'] : [];

$product = $dbh->mselect_rows('a25_products', ['ID' => $product_id], 0, 1, 'ID')[0];

$tarif = isset($product['TARIF']) ? unserialize($product['TARIF']) : null;
$product_cost = floatval($product['PRICE']);

if ($tarif && is_array($tarif)) {
    foreach ($tarif as $days_range => $price) {
        if ($days >= $days_range) {
            $product_cost = floatval($price);
        }
    }
}

$total_cost = $product_cost * intval($days);

foreach ($services as $service_cost) {
    $total_cost += floatval($service_cost) * intval($days);
}

echo json_encode(['total_cost' => $total_cost]);
?>