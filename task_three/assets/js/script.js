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
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;
    startDateInput.setAttribute('min', minDate);
    endDateInput.setAttribute('min', minDate);
    startDateInput.addEventListener('change', function() {
        endDateInput.setAttribute('min', this.value);
    });

    // Modal functionality
    const modal = document.getElementById('modal');
    const modalTriggers = document.querySelectorAll('.modal-trigger');
    const closeButton = document.querySelector('.close-button');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = this.getAttribute('data-product-price');
            document.getElementById('selected-product-id').value = productId;
            document.getElementById('selected-product-name').value = productName;
            document.getElementById('selected-product-price').value = productPrice;
            modal.style.display = 'block';
        });
    });
    closeButton.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Form calculation functionality
    $('#calculate-button').click(function() {
        const productId = $('#selected-product-id').val();
        const startDate = $('#start-date').val();
        const endDate = $('#end-date').val();
        const services = [];
        $('input[name="services[]"]:checked').each(function() {
            services.push($(this).val());
        });

        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = (end - start) / (1000 * 60 * 60 * 24);

        $.post('calculate.php', {
            product: productId,
            days: days,
            services: services
        }, function(response) {
            const result = JSON.parse(response);
            if (result.error) {
                alert(result.error);
            } else {
                $('#result').text(`Итоговая стоимость: ${result.total_cost}`);
            }
            modal.style.display = 'none';
        });
    });
});