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