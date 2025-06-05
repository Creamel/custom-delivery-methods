<?php
/**
 * Custom Delivery Methods for WooCommerce - Pickup Delivery
 * Version: 2.3.14
 */

if (!defined('ABSPATH')) {
    exit;
}

class Pickup_Delivery {
    public function __construct() {
        add_action('woocommerce_after_shipping_rate', [$this, 'add_pickup_fields'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_pickup_datetime'], 10, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'display_pickup_details']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('wooms_order_data', [$this, 'add_moysklad_pickup_data'], 10, 2);
        add_filter('wooms_order_positions', [$this, 'add_moysklad_pickup_position'], 10, 2);
    }

    public function add_pickup_fields($method, $index) {
        if ($method->method_id === 'local_pickup') {
            $pickup_settings = get_option('pickup_delivery_settings', [
                'time_start' => '11:00',
                'time_end' => '21:00',
                'moysklad_service_id' => '',
                'description' => ''
            ]);

            // Вывод описания (всегда видно)
            if (!empty($pickup_settings['description'])) {
                echo '<div class="shipping-description" style="font-size: 0.9em; color: #666; margin-top: 5px;">' . esc_html($pickup_settings['description']) . '</div>';
            }

            // Вывод полей даты и времени (скрыты по умолчанию, управляются JavaScript)
            $time_start = !empty($pickup_settings['time_start']) ? $pickup_settings['time_start'] : '11:00';
            $time_end = !empty($pickup_settings['time_end']) ? $pickup_settings['time_end'] : '21:00';
            $start_hour = (int) substr($time_start, 0, 2);
            $end_hour = (int) substr($time_end, 0, 2);
            ?>
            <div class="shipping-datetime" style="margin: 10px 0; display: none;" data-method-id="<?php echo esc_attr($method->id); ?>">
                <p style="font-weight: bold;"><?php _e('Выберите дату и время самовывоза:', 'custom-delivery-methods'); ?></p>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <p style="margin: 0;">
                        <label for="pickup_date_<?php echo esc_attr($method->id); ?>" style="display: inline-block; margin-right: 8px;"><?php _e('Дата:', 'custom-delivery-methods'); ?></label>
                        <input type="date" name="pickup_date[<?php echo esc_attr($method->id); ?>]" id="pickup_date_<?php echo esc_attr($method->id); ?>" min="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </p>
                    <p style="margin: 0;">
                        <label for="pickup_time_<?php echo esc_attr($method->id); ?>" style="display: inline-block; margin-right: 8px;"><?php _e('Время:', 'custom-delivery-methods'); ?></label>
                        <select name="pickup_time[<?php echo esc_attr($method->id); ?>]" id="pickup_time_<?php echo esc_attr($method->id); ?>" class="shipping-time-select">
                            <option value=""><?php _e('Выберите время самовывоза', 'custom-delivery-methods'); ?></option>
                            <?php for ($hour = $start_hour; $hour <= $end_hour; $hour++): ?>
                                <option value="<?php echo esc_attr(sprintf('%02d:00', $hour)); ?>">
                                    <?php echo esc_html(sprintf('%02d:00', $hour)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </p>
                </div>
            </div>
            <?php
        }
    }

    public function save_pickup_datetime($order_id, $data) {
        $order = wc_get_order($order_id);
        error_log('Saving pickup datetime for order: ' . $order_id);

        foreach ($order->get_shipping_methods() as $shipping_method) {
            if ($shipping_method->get_method_id() === 'local_pickup') {
                update_post_meta($order_id, '_pickup_shipping_method_id', $shipping_method->get_method_id());
                update_post_meta($order_id, '_pickup_shipping_name', $shipping_method->get_method_title());
            }
        }

        if (isset($_POST['pickup_date']) && is_array($_POST['pickup_date'])) {
            foreach ($_POST['pickup_date'] as $method_id => $date) {
                if (!empty($date)) {
                    update_post_meta($order_id, '_pickup_date_' . $method_id, sanitize_text_field($date));
                }
            }
        }
        if (isset($_POST['pickup_time']) && is_array($_POST['pickup_time'])) {
            foreach ($_POST['pickup_time'] as $method_id => $time) {
                if (!empty($time)) {
                    update_post_meta($order_id, '_pickup_time_' . $method_id, sanitize_text_field($time));
                }
            }
        }
    }

    public function display_pickup_details($order) {
        $order_id = $order->get_id();
        $method_id = get_post_meta($order_id, '_pickup_shipping_method_id', true);
        if ($method_id === 'local_pickup') {
            $pickup_date = get_post_meta($order_id, '_pickup_date_' . $method_id, true);
            $pickup_time = get_post_meta($order_id, '_pickup_time_' . $method_id, true);
            if ($pickup_date || $pickup_time) {
                echo '<p><strong>' . __('Дата и время самовывоза:', 'custom-delivery-methods') . '</strong> ' . esc_html($pickup_date . ' ' . $pickup_time) . '</p>';
            }
        }
    }

    public function add_moysklad_pickup_data($data, $order) {
        $order_id = $order->get_id();
        $method_id = get_post_meta($order_id, '_pickup_shipping_method_id', true);
        if ($method_id === 'local_pickup') {
            $pickup_date = get_post_meta($order_id, '_pickup_date_' . $method_id, true);
            $pickup_time = get_post_meta($order_id, '_pickup_time_' . $method_id, true);
            $shipping_name = get_post_meta($order_id, '_pickup_shipping_name', true);

            if ($shipping_name) {
                $data['description'] .= "\nМетод доставки: " . $shipping_name;
                $data['description'] .= "\nСтоимость доставки: Бесплатно";
            }
            if ($pickup_date || $pickup_time) {
                $data['description'] .= "\nДата и время самовывоза: " . $pickup_date . ' ' . $pickup_time;
                $data['attributes'] = $data['attributes'] ?? [];
                $data['attributes'][] = [
                    'name' => 'Дата и время самовывоза',
                    'value' => $pickup_date . ' ' . $pickup_time,
                ];
            }
        }
        return $data;
    }

    public function add_moysklad_pickup_position($positions, $data) {
        $order = wc_get_order($data['order_id']);
        $method_id = get_post_meta($order->get_id(), '_pickup_shipping_method_id', true);

        if ($method_id === 'local_pickup') {
            $pickup_settings = get_option('pickup_delivery_settings', [
                'time_start' => '11:00',
                'time_end' => '21:00',
                'moysklad_service_id' => ''
            ]);
            $moysklad_service_id = !empty($pickup_settings['moysklad_service_id']) ? $pickup_settings['moysklad_service_id'] : '';

            if ($moysklad_service_id) {
                $positions[] = [
                    'quantity' => 1,
                    'price' => 0,
                    'assortment' => [
                        'meta' => [
                            'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/service/' . $moysklad_service_id,
                            'type' => 'service',
                        ],
                    ],
                ];
            }
        }
        return $positions;
    }

    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('jquery');
        }
    }
}
?>