<?php
require_once 'backend/sdbh.php';
$dbh = new sdbh();

// Получение товаров
$products = $dbh->mselect_rows('a25_products', [], 0, 100, 'ID');

// Получение услуг
$services = unserialize($dbh->mselect_rows('a25_settings', ['set_key' => 'services'], 0, 1, 'ID')[0]['set_value']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="assets/css/style_form.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
</head>
<body>
<div class="container">
    <div class="row row-header">
        <div class="col-12">
            <img src="assets/img/logo.png" alt="logo" style="max-height:50px"/>
            <h1>Прокат</h1>
        </div>
    </div>
    <div class="row row-body">
        <div class="col-12">
            <h4>Форма расчета:</h4>
            <div class="row row-body">
                <div class="col-3">
                    <span style="text-align: center">Форма обратной связи</span>
                    <i class="bi bi-activity"></i>
                </div>
                <div class="col-9">
                    <form id="calculation-form">
                        <label class="form-label" for="product">Выберите продукт:</label>
                        <select class="form-select" name="product" id="product">
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['ID'] ?>"><?= $product['NAME'] ?> за <?= $product['PRICE'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="days" class="form-label">Количество дней:</label>
                        <input type="number" class="form-control" id="days" name="days" min="1" max="30" required>
                        <label for="services" class="form-label">Дополнительно:</label>
                        <?php foreach ($services as $service_name => $service_price): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="services[]" value="<?= $service_price ?>" id="service-<?= $service_name ?>">
                                <label class="form-check-label" for="service-<?= $service_name ?>">
                                    <?= $service_name ?> за <?= $service_price ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-primary" id="calculate-button">Рассчитать</button>
                    </form>
                    <div id="result"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        $('#calculate-button').click(function() {
            $.ajax({
                url: 'calculate.php',
                type: 'POST',
                data: $('#calculation-form').serialize(),
                success: function(response) {
                    var result = JSON.parse(response);
                    $('#result').html('Итоговая стоимость: ' + result.total_cost);
                }
            });
        });
    });
</script>
</body>
</html>