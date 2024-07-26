
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Car Website</title>
    <link rel="shortcut icon" href="assets/img/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js" defer></script>
    <script src="assets/js/swiper-bundle.min.js" defer></script>
    <script src="assets/js/main.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
</head>
<body>
<img src="assets/img/logo2.png" alt="Logo" class="logo">
<main class="main">
    <div class="total-cost-container">
        <div id="total-cost-message" class="total-cost-message">Итоговая стоимость:</div>
        <div id="total-cost-result" class="total-cost-result">-</div>
    </div>
    <section class="popular section" id="popular">
        <h2 class="section__title">Прокат</h2>
        <div class="popular__container container swiper">
            <div class="swiper-wrapper">
                <?php
                require_once 'backend/sdbh.php';
                $dbh = new sdbh();
                $products = $dbh->mselect_rows('a25_products', [], 0, 100, 'ID');
                $services = unserialize($dbh->mselect_rows('a25_settings', ['set_key' => 'services'], 0, 1, 'ID')[0]['set_value']);
                foreach ($products as $index => $product) :
                    ?>
                    <article class="popular__card swiper-slide">
                        <div class="shape shape__smaller"></div>
                        <h1 class="popular__title"><?= htmlspecialchars($product['NAME']) ?></h1>
                        <h3 class="popular__subtitle"><?= htmlspecialchars($product['PRICE']) ?> за день</h3>
                        <img src="assets/img/popular<?= $index + 1 ?>.png" alt="<?= htmlspecialchars($product['NAME']) ?>" class="popular__img">
                        <div class="popular__data">
                            <div class="popular__data-group">
                                <i class="ri-dashboard-3-line"></i> 3.7 Sec
                            </div>
                            <div class="popular__data-group">
                                <i class="ri-funds-box-line"></i> 356 Km/h
                            </div>
                            <div class="popular__data-group">
                                <i class="ri-charging-pile-2-line"></i> Something
                            </div>
                        </div>
                        <h3 class="popular__price"><?= htmlspecialchars($product['PRICE']) ?></h3>
                        <button class="button popular__button modal-trigger" data-product-id="<?= $product['ID'] ?>" data-product-name="<?= htmlspecialchars($product['NAME']) ?>" data-product-price="<?= htmlspecialchars($product['PRICE']) ?>">
                            <b><p class="text">Выбрать</p></b>
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>
</main>
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2 class="left-align">Детали</h2><br>
        <div class="rental__container container">
            <div class="rental__dates">
                <div class="rental__date">
                    <label for="start-date">Дата начала:</label>
                    <input type="text" id="start-date" class="rental__input datepicker">
                </div>
                <div class="rental__date">
                    <label for="end-date">Дата конца:</label>
                    <input type="text" id="end-date" class="rental__input datepicker">
                </div>
            </div>
            <div>
                <h4 id="toggle-services" class="toggle-services">Дополнительные услуги <span class="arrow">&#9662;</span></h4>
                <form id="services-form" class="hidden">
                    <div class="form-check-container">
                        <?php foreach ($services as $service_name => $service_price) : ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="services[]" value="<?= htmlspecialchars($service_price) ?>" id="service-<?= htmlspecialchars($service_name) ?>">
                                <label class="form-check-label" for="service-<?= htmlspecialchars($service_name) ?>">
                                    <?= htmlspecialchars($service_name) ?> за <?= htmlspecialchars($service_price) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="button-container">
            <button type="button" class="btn btn-primary home__button" id="calculate-button">Рассчитать</button>
        </div>
    </div>
</div>
<section class="form section">
    <div class="container">
        <form id="calculation-form">
            <input type="hidden" id="selected-product-id" name="product_id">
            <input type="hidden" id="selected-product-name" name="product_name">
            <input type="hidden" id="selected-product-price" name="product_price">
        </form>
        <div id="result"></div>
    </div>
</section>
</body>
</html>