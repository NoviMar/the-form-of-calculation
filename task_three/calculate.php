<?php
require_once 'backend/sdbh.php';
$dbh = new sdbh();
if (!isset($_POST['product'])) {
    echo json_encode(['error' => 'Product not selected']);
    exit;
}

$product_id = $_POST['product'];
$days = isset($_POST['days']) ? intval($_POST['days']) : 0;
$services = isset($_POST['services']) ? $_POST['services'] : [];

$product = $dbh->mselect_rows('a25_products', ['ID' => $product_id], 0, 1, 'ID');

if (empty($product)) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $product[0];
$tarif = isset($product['TARIF']) ? unserialize($product['TARIF']) : null;
$product_cost = floatval($product['PRICE']);

if ($tarif && is_array($tarif)) {
    foreach ($tarif as $days_range => $price) {
        if ($days >= $days_range) {
            $product_cost = floatval($price);
        }
    }
}

$total_cost = $product_cost * $days;

foreach ($services as $service_cost) {
    $total_cost += floatval($service_cost) * $days;
}

echo json_encode(['total_cost' => $total_cost]);
?>
