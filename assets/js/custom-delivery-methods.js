jQuery(document).ready(function($) {
    function toggleDeliveryDatetime() {
        $('.shipping-datetime').hide();
        var selectedMethod = $('input[name="shipping_method[0]"]:checked').val();
        if (selectedMethod) {
            $('.shipping-datetime[data-method-id="' + selectedMethod + '"]').show();
        }
    }

    function updateDeliveryTimeOptions(methodId, $dateInput) {
        var $timeSelect = $('#custom_delivery_time_' + methodId + ', #pickup_time_' + methodId);
        $timeSelect.empty().append('<option value="">' + customDeliveryMethods.i18n.select_time + '</option>');

        if (!$dateInput.val()) {
            return;
        }

        $.ajax({
            url: customDeliveryMethods.ajax_url,
            type: 'POST',
            data: {
                action: 'get_delivery_time_options',
                method_id: methodId,
                date: $dateInput.val(),
                is_weekend: isWeekend($dateInput.val()),
                _wpnonce: customDeliveryMethods.nonce,
            },
            success: function(response) {
                if (response.success) {
                    $.each(response.data.options, function(i, option) {
                        $timeSelect.append($('<option>').val(option.value).text(option.text));
                    });
                }
            }
        });
    }

    function isWeekend(dateStr) {
        var date = new Date(dateStr);
        return date.getDay() === 0 || date.getDay() === 6;
    }

    toggleDeliveryDatetime();

    $(document.body).on('change', 'input[name="shipping_method[0]"]', function() {
        toggleDeliveryDatetime();
    });

    $(document.body).on('change', 'input[name^="custom_delivery_date"], input[name^="pickup_date"]', function() {
        var $dateInput = $(this);
        var methodId = $dateInput.attr('name').match(/\[([^\]]*)\]/)[1];
        updateDeliveryTimeOptions(methodId, $dateInput);
    });

    $(document.body).on('updated_checkout', function() {
        toggleDeliveryDatetime();
    });
});