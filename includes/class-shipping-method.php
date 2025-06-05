<?php
/**
 * Custom Delivery Methods for WooCommerce - Shipping Method
 * Version: 2.3.20
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Custom_Delivery extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'custom_delivery';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Кастомная доставка', 'custom-delivery-methods');
        $this->method_description = __('Кастомные методы доставки, настроенные через плагин', 'custom-delivery-methods');
        $this->supports = ['shipping-zones'];
        $this->init();
    }

    public function init() {
        $this->init_settings();
        $this->title = $this->get_option('title', __('Кастомная доставка', 'custom-delivery-methods'));
        add_action('woocommerce_after_shipping_rate', [$this, 'add_custom_delivery_fields'], 10, 2);
    }

    public function calculate_shipping($package = []) {
        $methods = get_option('custom_delivery_methods', []);
        $cart_total = WC()->cart ? WC()->cart->get_cart_contents_total() : 0;

        error_log('Custom Delivery: Calculating shipping rates. Methods: ' . print_r($methods, true));

        foreach ($methods as $index => $method) {
            $cost = $method['cost'];
            if (!empty($method['free_above']) && $cart_total >= $method['free_above']) {
                $cost = 0;
            }

            $meta_data = [
                'description' => $method['description'],
                'note' => $method['note'] ?? '',
                'moysklad_service_id_paid' => $method['moysklad_service_id_paid'] ?? '',
                'moysklad_service_id_free' => $method['moysklad_service_id_free'] ?? '',
                'requires_datetime' => $method['requires_datetime'] ?? false,
                'allow_exact_time' => $method['allow_exact_time'] ?? false,
                'time_start' => $method['time_start'] ?? '09:00',
                'time_end' => $method['time_end'] ?? '21:00',
                'method_index' => $index,
            ];

            if (!empty($method['weekday_intervals'])) {
                $meta_data['weekday_intervals'] = $method['weekday_intervals'];
            }
            if (!empty($method['weekend_intervals'])) {
                $meta_data['weekend_intervals'] = $method['weekend_intervals'];
            }

            $this->add_rate([
                'id' => $this->id . '_' . $index,
                'label' => $method['name'] ?: sprintf(__('Кастомная доставка #%d', 'custom-delivery-methods'), $index + 1),
                'cost' => $cost,
                'meta_data' => $meta_data,
            ]);

            error_log('Custom Delivery: Added rate for method ' . ($method['name'] ?? 'Unnamed') . ', cost: ' . $cost);
        }
    }

    public function add_custom_delivery_fields($method, $index) {
        if ($method->method_id !== 'custom_delivery') {
            return;
        }

        $meta_data = $method->get_meta_data();

        error_log('Custom Delivery: Rendering fields for method ' . ($meta_data['method_index'] ?? 'Unknown') . ': ' . print_r($meta_data, true));

        if (!empty($meta_data['description'])) {
            echo '<div class="shipping-description" style="font-size: 0.9em; color: #666; margin-top: 5px;">' . esc_html($meta_data['description']) . '</div>';
        }

        if (empty($meta_data['requires_datetime']) && empty($meta_data['allow_exact_time'])) {
            return;
        }

        $intervals = [];

        if (!empty($meta_data['requires_datetime'])) {
            $weekday_intervals = $meta_data['weekday_intervals'] ?? [];
            $weekend_intervals = $meta_data['weekend_intervals'] ?? [];

            echo '<script>
                jQuery(document).ready(function($) {
                    $("#custom_delivery_date_' . esc_js($method->id) . '").on("change", function() {
                        var selectedDate = new Date($(this).val());
                        var isWeekend = (selectedDate.getDay() === 0 || selectedDate.getDay() === 6);
                        var timeSelect = $("#custom_delivery_time_' . esc_js($method->id) . '");
                        timeSelect.empty();
                        timeSelect.append("<option value=\"\">' . esc_js(__('Выберите время доставки', 'custom-delivery-methods')) . '</option>");
                        var intervals = isWeekend ? ' . json_encode($weekend_intervals) . ' : ' . json_encode($weekday_intervals) . ';
                        $.each(intervals, function(i, interval) {
                            var text = interval.start + (interval.start !== interval.end ? " - " + interval.end : "");
                            timeSelect.append("<option value=\"" + interval.start + "\">" + text + "</option>");
                        });
                    });
                });
            </script>';

            $intervals = $weekday_intervals;
        }

        if (empty($intervals) && !empty($meta_data['allow_exact_time'])) {
            $start_hour = (int) substr($meta_data['time_start'], 0, 2);
            $end_hour = (int) substr($meta_data['time_end'], 0, 2);

            for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
                $time_str = sprintf('%02d:00', $hour);
                $intervals[] = [
                    'start' => $time_str,
                    'end' => $time_str
                ];
            }
        }

        if (empty($intervals)) {
            error_log('Custom Delivery: No intervals for method ' . ($meta_data['method_index'] ?? 'Unknown') . ', allow_exact_time: ' . ($meta_data['allow_exact_time'] ? 'true' : 'false'));
            return;
        }

        ?>
        <div class="shipping-datetime" style="margin: 10px 0; display: none;" data-method-id="<?php echo esc_attr($method->id); ?>">
            <p style="font-weight: bold;"><?php _e('Выберите дату и время доставки:', 'custom-delivery-methods'); ?></p>
            <div style="display: flex; align-items: center; gap: 16px;">
                <p style="margin: 0;">
                    <label for="custom_delivery_date_<?php echo esc_attr($method->id); ?>" style="display: inline-block; margin-right: 8px;"><?php _e('Дата:', 'custom-delivery-methods'); ?></label>
                    <input type="date" name="custom_delivery_date[<?php echo esc_attr($method->id); ?>]" id="custom_delivery_date_<?php echo esc_attr($method->id); ?>" min="<?php echo esc_attr(date('Y-m-d')); ?>">
                </p>
                <p style="margin: 0;">
                    <label for="custom_delivery_time_<?php echo esc_attr($method->id); ?>" style="display: inline-block; margin-right: 8px;"><?php _e('Время:', 'custom-delivery-methods'); ?></label>
                    <select name="custom_delivery_time[<?php echo esc_attr($method->id); ?>]" id="custom_delivery_time_<?php echo esc_attr($method->id); ?>" class="shipping-time-select">
                        <option value=""><?php _e('Выберите время доставки', 'custom-delivery-methods'); ?></option>
                        <?php foreach ($intervals as $interval): ?>
                            <option value="<?php echo esc_attr($interval['start']); ?>">
                                <?php echo esc_html($interval['start'] . ($interval['start'] !== $interval['end'] ? ' - ' . $interval['end'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>
        </div>
        <?php
    }
}
?>