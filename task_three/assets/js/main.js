function scrollHeader() {
    const header = document.querySelector("header");
    if (this.scrollY >= 50) header.classList.add("scroll-header");
    else header.classList.remove("scroll-header");
}
window.addEventListener("scroll", scrollHeader);

let swiperPopular = new Swiper(".popular__container", {
    loop: true,
    spaceBetween: 24,
    slidesPerView: 'auto',
    gapCursor: true,
    pagination: {
        el: ".swiper-pagination",
        dynamicBullets: true,
    },
    breakpoints: {
        768: {
            slidesPerView: 3,
        },
        1024: {
            spaceBetween: 48,
        },
    },
});

document.addEventListener('DOMContentLoaded', function () {
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;
    startDateInput.setAttribute('min', minDate);
    endDateInput.setAttribute('min', minDate);
    startDateInput.addEventListener('change', function () {
        endDateInput.setAttribute('min', this.value);
    });

    const modal = document.getElementById('modal');
    const modalTriggers = document.querySelectorAll('.modal-trigger');
    const closeButton = document.querySelector('.close-button');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
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

    const toggleServices = document.getElementById('toggle-services');
    const servicesForm = document.getElementById('services-form');
    const modalContent = document.querySelector('.modal-content');
    const totalCostResult = document.getElementById('total-cost-result');

    toggleServices.addEventListener('click', function () {
        servicesForm.classList.toggle('hidden');
        servicesForm.classList.toggle('visible');
        toggleServices.classList.toggle('active');
        if (servicesForm.classList.contains('visible')) {
            modalContent.style.maxHeight = '700px';
        } else {
            modalContent.style.maxHeight = 'none';
        }
    });

    const startPicker = flatpickr("#start-date", {
        theme: "dark",
        minDate: "today",
        onChange: function (selectedDates, dateStr, instance) {
            endPicker.set('minDate', dateStr);
        }
    });
    const endPicker = flatpickr("#end-date", {
        theme: "dark",
        minDate: "today"
    });

    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = this.getAttribute('data-product-price');
            document.getElementById('selected-product-id').value = productId;
            document.getElementById('selected-product-name').value = productName;
            document.getElementById('selected-product-price').value = productPrice;
            modal.style.display = 'flex';
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

    $('#calculate-button').click(function () {
        const productId = $('#selected-product-id').val();
        const startDate = $('#start-date').val();
        const endDate = $('#end-date').val();
        if (new Date(endDate) < new Date(startDate)) {
            alert('Конечная дата не может быть раньше начальной даты.');
            return;
        }
        const services = [];
        $('input[name="services[]"]:checked').each(function () {
            services.push($(this).val());
        });
        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = (end - start) / (1000 * 60 * 60 * 24);
        $.post('calculate.php', { product: productId, days: days, services: services }, function (response) {
            const result = JSON.parse(response);
            if (result.error) {
                alert(result.error);
            } else {
                totalCostResult.textContent = `${result.total_cost}`;
            }
            modal.style.display = 'none';
        });
    });

    function getPastDates() {
        const today = new Date();
        const pastDates = [];
        for (let d = new Date(1970, 0, 1); d < today; d.setDate(d.getDate() + 1)) {
            pastDates.push(new Date(d));
        }
        return pastDates;
    }
});