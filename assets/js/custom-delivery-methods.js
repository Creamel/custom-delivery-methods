jQuery(document).ready(function($) {
    // Управление видимостью полей даты/времени
    function toggleDatetimeFields() {
        $('.shipping-datetime').hide();
        var chosenMethod = $('input[name="shipping_method[0]"]:checked').val();
        if (chosenMethod) {
            var methodId = chosenMethod.replace(/:/g, '\\:');
            var $datetime = $('[data-method-id="' + methodId + '"]');
            if ($datetime.length) {
                $datetime.show();
                console.log('Showing datetime for method: ' + methodId);
            } else {
                console.log('No datetime found for method: ' + methodId);
            }
        }
    }

    // Перемещение полей даты/времени перед описанием
    function moveDatetimeFields() {
        $('.shipping-datetime').each(function() {
            var $this = $(this);
            var $parent = $this.closest('li');
            var $description = $parent.find('.shipping-description');
            if ($description.length) {
                $this.insertBefore($description);
                console.log('Moved datetime before description for method: ' + $this.data('method-id'));
            } else {
                console.log('No description found for method: ' + $this.data('method-id') + ', parent classes: ' + ($parent.attr('class') || 'no-class'));
            }
        });
    }

    // Инициализация с задержкой
    setTimeout(function() {
        toggleDatetimeFields();
        moveDatetimeFields();
        console.log('Initial structure:', $('.woocommerce-shipping-methods').html());
    }, 500);

    // Обновление при выборе метода или обновлении checkout
    $(document.body).on('change', 'input[name="shipping_method[0]"]', function() {
        toggleDatetimeFields();
        moveDatetimeFields();
    });
    $(document.body).on('updated_checkout', function() {
        setTimeout(function() {
            toggleDatetimeFields();
            moveDatetimeFields();
        }, 500);
    });
});