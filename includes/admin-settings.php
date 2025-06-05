<?php
/**
 * Custom Delivery Methods for WooCommerce - Admin Settings
 * Version: 2.3.21
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Delivery_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_get_moysklad_services', [$this, 'ajax_get_moysklad_services']);
        add_action('woocommerce_checkout_create_order_shipping_item', [$this, 'add_moysklad_service_id_to_order'], 10, 4);
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Custom Delivery Settings', 'custom-delivery-methods'),
            __('Custom Delivery', 'custom-delivery-methods'),
            'manage_woocommerce',
            'custom-delivery-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('custom_delivery_methods_group', 'custom_delivery_methods', [$this, 'sanitize_methods']);
        register_setting('pickup_delivery_settings_group', 'pickup_delivery_settings', [$this, 'sanitize_pickup_settings']);
    }

    public function sanitize_methods($input) {
        $sanitized = [];
        foreach ($input as $method) {
            $sanitized[] = [
                'name' => sanitize_text_field($method['name']),
                'description' => sanitize_textarea_field($method['description']),
                'cost' => floatval($method['cost']),
                'free_above' => floatval($method['free_above']),
                'requires_datetime' => !empty($method['requires_datetime']),
                'allow_exact_time' => !empty($method['allow_exact_time']),
                'time_start' => preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $method['time_start']) ? $method['time_start'] : '09:00',
                'time_end' => preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $method['time_end']) ? $method['time_end'] : '21:00',
                'weekday_intervals' => array_map(function($interval) {
                    return [
                        'start' => sanitize_text_field($interval['start']),
                        'end' => sanitize_text_field($interval['end'])
                    ];
                }, $method['weekday_intervals'] ?? []),
                'weekend_intervals' => array_map(function($interval) {
                    return [
                        'start' => sanitize_text_field($interval['start']),
                        'end' => sanitize_text_field($interval['end'])
                    ];
                }, $method['weekend_intervals'] ?? []),
                'moysklad_service_id_paid' => sanitize_text_field($method['moysklad_service_id_paid'] ?? ''),
                'moysklad_service_id_free' => sanitize_text_field($method['moysklad_service_id_free'] ?? ''),
            ];
        }
        return $sanitized;
    }

    public function sanitize_pickup_settings($input) {
        return [
            'time_start' => preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $input['time_start']) ? $input['time_start'] : '11:00',
            'time_end' => preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $input['time_end']) ? $input['time_end'] : '21:00',
            'moysklad_service_id' => sanitize_text_field($input['moysklad_service_id'] ?? ''),
            'description' => sanitize_textarea_field($input['description'] ?? '')
        ];
    }

    public function ajax_get_moysklad_services() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Нет доступа', 'custom-delivery-methods')]);
        }

        error_log('Custom Delivery: Attempting to fetch MoySklad services via AJAX');

        // Проверка активности плагина WooMS
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        $wooms_plugin = 'wooms/wooms.php'; // Предполагаемый путь к плагину WooMS
        if (!is_plugin_active($wooms_plugin) && !function_exists('WooMS\request')) {
            error_log('Custom Delivery: WooMS plugin not active or WooMS\request function not found');
            wp_send_json_error(['message' => __('Плагин WooMS не установлен или не активен', 'custom-delivery-methods')]);
        }

        // Проверка наличия функции WooMS\request
        if (!function_exists('WooMS\request')) {
            error_log('Custom Delivery: WooMS\request function not found');
            wp_send_json_error(['message' => __('Функция WooMS\request не найдена', 'custom-delivery-methods')]);
        }

        error_log('Custom Delivery: WooMS\request function detected');

        // Запрос к МойСклад
        try {
            $api_url = 'https://api.moysklad.ru/api/remap/1.2/';
            $path = 'entity/service';
            $full_url = $api_url . $path;
            error_log('Custom Delivery: Requesting MoySklad services from ' . $full_url);

            $response = \WooMS\request($full_url);

            if (is_array($response) && !isset($response['errors'])) {
                error_log('Custom Delivery: MoySklad services fetched successfully');
            } else {
                $error_message = isset($response['errors']) ? $response['errors'][0]['error'] : 'Ошибка запроса к МойСклад';
                error_log('Custom Delivery: MoySklad error: ' . $error_message);
                wp_send_json_error(['message' => __('Ошибка запроса к МойСклад: ', 'custom-delivery-methods') . $error_message]);
            }
        } catch (Exception $e) {
            error_log('Custom Delivery: Exception during MoySklad request: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Ошибка подключения к МойСклад: ', 'custom-delivery-methods') . $e->getMessage()]);
        }

        $services = $response['rows'] ?? [];

        if (empty($services)) {
            error_log('Custom Delivery: No services found in MoySklad response');
            wp_send_json_error(['message' => __('Услуги не найдены', 'custom-delivery-methods')]);
        }

        $output = '<ul>';
        foreach ($services as $service) {
            $output .= '<li>' . esc_html($service['name']) . ' (ID: <span class="moysklad-service-id" onclick="navigator.clipboard.writeText(\'' . esc_js($service['id']) . '\')">' . esc_html($service['id']) . '</span>)</li>';
        }
        $output .= '</ul>';

        error_log('Custom Delivery: Successfully fetched ' . count($services) . ' MoySklad services');

        wp_send_json_success(['html' => $output]);
    }

    public function add_moysklad_service_id_to_order($item, $item_id, $order, $legacy) {
        if ($item->get_method_id() === 'custom_delivery') {
            $meta_data = $item->get_meta_data();
            $cart_total = $order->get_subtotal();
            $method_index = null;
            $moysklad_service_id = '';

            foreach ($meta_data as $meta) {
                if ($meta->key === 'method_index') {
                    $method_index = $meta->value;
                } elseif ($meta->key === 'moysklad_service_id_paid') {
                    $moysklad_service_id_paid = $meta->value;
                } elseif ($meta->key === 'moysklad_service_id_free') {
                    $moysklad_service_id_free = $meta->value;
                }
            }

            $methods = get_option('custom_delivery_methods', []);
            if ($method_index !== null && isset($methods[$method_index])) {
                $method = $methods[$method_index];
                $moysklad_service_id = (!empty($method['free_above']) && $cart_total >= $method['free_above'])
                    ? $moysklad_service_id_free
                    : $moysklad_service_id_paid;
            }

            if ($moysklad_service_id) {
                $item->add_meta_data('moysklad_service_id', $moysklad_service_id, true);
                error_log('Custom Delivery: Added moysklad_service_id ' . $moysklad_service_id . ' to order item ' . $item_id);
            }
        }
    }

    public function render_settings_page() {
        $methods = get_option('custom_delivery_methods', []);
        $pickup_settings = get_option('pickup_delivery_settings', [
            'time_start' => '11:00',
            'time_end' => '21:00',
            'moysklad_service_id' => '',
            'description' => ''
        ]);
        ?>
        <div class="wrap custom-delivery-settings">
            <h1><?php _e('Custom Delivery Settings', 'custom-delivery-methods'); ?></h1>
            <div class="settings-container">
                <div class="settings-left">
                    <!-- Блок настроек самовывоза -->
                    <div class="settings-block">
                        <h2><?php _e('Настройки самовывоза', 'custom-delivery-methods'); ?></h2>
                        <form method="post" action="options.php">
                            <?php settings_fields('pickup_delivery_settings_group'); ?>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php _e('Время начала:', 'custom-delivery-methods'); ?></label></th>
                                    <td><input type="time" name="pickup_delivery_settings[time_start]" value="<?php echo esc_attr($pickup_settings['time_start']); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label><?php _e('Время окончания:', 'custom-delivery-methods'); ?></label></th>
                                    <td><input type="time" name="pickup_delivery_settings[time_end]" value="<?php echo esc_attr($pickup_settings['time_end']); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label><?php _e('Описание:', 'custom-delivery-methods'); ?></label></th>
                                    <td><textarea name="pickup_delivery_settings[description]" style="width: 100%;"><?php echo esc_textarea($pickup_settings['description']); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label><?php _e('ID услуги МойСклад:', 'custom-delivery-methods'); ?></label></th>
                                    <td><input type="text" name="pickup_delivery_settings[moysklad_service_id]" value="<?php echo esc_attr($pickup_settings['moysklad_service_id']); ?>"></td>
                                </tr>
                            </table>
                            <?php submit_button(__('Сохранить настройки самовывоза', 'custom-delivery-methods')); ?>
                        </form>
                    </div>

                    <!-- Блок методов доставки -->
                    <div class="settings-block">
                        <h2><?php _e('Методы доставки', 'custom-delivery-methods'); ?></h2>
                        <form method="post" action="options.php">
                            <?php settings_fields('custom_delivery_methods_group'); ?>
                            <div id="delivery-methods">
                                <?php foreach ($methods as $index => $method): ?>
                                    <div class="delivery-method" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                                        <h3><?php _e('Метод доставки', 'custom-delivery-methods'); ?> #<?php echo $index + 1; ?></h3>
                                        <p>
                                            <label><?php _e('Название:', 'custom-delivery-methods'); ?></label><br>
                                            <input type="text" name="custom_delivery_methods[<?php echo $index; ?>][name]" value="<?php echo esc_attr($method['name']); ?>" style="width: 100%;">
                                        </p>
                                        <p>
                                            <label><?php _e('Описание:', 'custom-delivery-methods'); ?></label><br>
                                            <textarea name="custom_delivery_methods[<?php echo $index; ?>][description]" style="width: 100%;"><?php echo esc_textarea($method['description']); ?></textarea>
                                        </p>
                                        <p>
                                            <label><?php _e('Стоимость:', 'custom-delivery-methods'); ?></label><br>
                                            <input type="number" step="0.01" name="custom_delivery_methods[<?php echo $index; ?>][cost]" value="<?php echo esc_attr($method['cost']); ?>">
                                        </p>
                                        <p>
                                            <label><?php _e('Бесплатно от:', 'custom-delivery-methods'); ?></label><br>
                                            <input type="number" step="0.01" name="custom_delivery_methods[<?php echo $index; ?>][free_above]" value="<?php echo esc_attr($method['free_above']); ?>">
                                        </p>
                                        <p>
                                            <label><input type="checkbox" name="custom_delivery_methods[<?php echo $index; ?>][requires_datetime]" class="requires-datetime" <?php checked($method['requires_datetime']); ?>> <?php _e('Требуется выбор даты/времени', 'custom-delivery-methods'); ?></label>
                                        </p>
                                        <div class="datetime-settings" style="display: <?php echo $method['requires_datetime'] ? 'block' : 'none'; ?>;">
                                            <p>
                                                <label><input type="checkbox" name="custom_delivery_methods[<?php echo $index; ?>][allow_exact_time]" <?php checked($method['allow_exact_time']); ?>> <?php _e('Разрешить выбор точного времени', 'custom-delivery-methods'); ?></label>
                                            </p>
                                            <p>
                                                <label><?php _e('Время начала:', 'custom-delivery-methods'); ?></label><br>
                                                <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][time_start]" value="<?php echo esc_attr($method['time_start'] ?? '09:00'); ?>">
                                            </p>
                                            <p>
                                                <label><?php _e('Время окончания:', 'custom-delivery-methods'); ?></label><br>
                                                <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][time_end]" value="<?php echo esc_attr($method['time_end'] ?? '21:00'); ?>">
                                            </p>
                                            <h4><?php _e('Интервалы для будних дней', 'custom-delivery-methods'); ?></h4>
                                            <div class="weekday-intervals">
                                                <?php foreach ($method['weekday_intervals'] ?? [] as $interval_index => $interval): ?>
                                                    <p>
                                                        <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekday_intervals][<?php echo $interval_index; ?>][start]" value="<?php echo esc_attr($interval['start']); ?>">
                                                        <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekday_intervals][<?php echo $interval_index; ?>][end]" value="<?php echo esc_attr($interval['end']); ?>">
                                                        <button type="button" class="remove-interval"><?php _e('Удалить', 'custom-delivery-methods'); ?></button>
                                                    </p>
                                                <?php endforeach; ?>
                                                <p><button type="button" class="add-weekday-interval"><?php _e('Добавить интервал', 'custom-delivery-methods'); ?></button></p>
                                            </div>
                                            <h4><?php _e('Интервалы для выходных дней', 'custom-delivery-methods'); ?></h4>
                                            <div class="weekend-intervals">
                                                <?php foreach ($method['weekend_intervals'] ?? [] as $interval_index => $interval): ?>
                                                    <p>
                                                        <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekend_intervals][<?php echo $interval_index; ?>][start]" value="<?php echo esc_attr($interval['start']); ?>">
                                                        <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekend_intervals][<?php echo $interval_index; ?>][end]" value="<?php echo esc_attr($interval['end']); ?>">
                                                        <button type="button" class="remove-interval"><?php _e('Удалить', 'custom-delivery-methods'); ?></button>
                                                    </p>
                                                <?php endforeach; ?>
                                                <p><button type="button" class="add-weekend-interval"><?php _e('Добавить интервал', 'custom-delivery-methods'); ?></button></p>
                                            </div>
                                        </div>
                                        <p>
                                            <label><?php _e('ID услуги МойСклад (платная):', 'custom-delivery-methods'); ?></label><br>
                                            <input type="text" name="custom_delivery_methods[<?php echo $index; ?>][moysklad_service_id_paid]" value="<?php echo esc_attr($method['moysklad_service_id_paid'] ?? ''); ?>">
                                        </p>
                                        <p>
                                            <label><?php _e('ID услуги МойСклад (бесплатная):', 'custom-delivery-methods'); ?></label><br>
                                            <input type="text" name="custom_delivery_methods[<?php echo $index; ?>][moysklad_service_id_free]" value="<?php echo esc_attr($method['moysklad_service_id_free'] ?? ''); ?>">
                                        </p>
                                        <p><button type="button" class="remove-method"><?php _e('Удалить метод', 'custom-delivery-methods'); ?></button></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p>
                                <button type="button" class="button" id="add-method"><?php _e('Добавить метод', 'custom-delivery-methods'); ?></button>
                            </p>
                            <?php submit_button(); ?>
                        </form>
                    </div>
                </div>
                <div class="settings-right">
                    <!-- Блок МойСклад -->
                    <div class="settings-block">
                        <h2><?php _e('Услуги МойСклад', 'custom-delivery-methods'); ?></h2>
                        <p>
                            <button type="button" class="button" id="get-moysklad-services"><?php _e('Получить услуги МойСклад', 'custom-delivery-methods'); ?></button>
                        </p>
                        <div id="moysklad-services-output"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Добавить метод доставки
                $('#add-method').click(function() {
                    var index = $('.delivery-method').length;
                    var html = `
                        <div class="delivery-method" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                            <h3><?php _e('Метод доставки', 'custom-delivery-methods'); ?> #${index + 1}</h3>
                            <p>
                                <label><?php _e('Название:', 'custom-delivery-methods'); ?></label><br>
                                <input type="text" name="custom_delivery_methods[${index}][name]" style="width: 100%;">
                            </p>
                            <p>
                                <label><?php _e('Описание:', 'custom-delivery-methods'); ?></label><br>
                                <textarea name="custom_delivery_methods[${index}][description]" style="width: 100%;"></textarea>
                            </p>
                            <p>
                                <label><?php _e('Стоимость:', 'custom-delivery-methods'); ?></label><br>
                                <input type="number" step="0.01" name="custom_delivery_methods[${index}][cost]" value="0">
                            </p>
                            <p>
                                <label><?php _e('Бесплатно от:', 'custom-delivery-methods'); ?></label><br>
                                <input type="number" step="0.01" name="custom_delivery_methods[${index}][free_above]" value="0">
                            </p>
                            <p>
                                <label><input type="checkbox" name="custom_delivery_methods[${index}][requires_datetime]" class="requires-datetime"> <?php _e('Требуется выбор даты/времени', 'custom-delivery-methods'); ?></label>
                            </p>
                            <div class="datetime-settings" style="display: none;">
                                <p>
                                    <label><input type="checkbox" name="custom_delivery_methods[${index}][allow_exact_time]"> <?php _e('Разрешить выбор точного времени', 'custom-delivery-methods'); ?></label>
                                </p>
                                <p языка="javascript">
                                    <label><?php echo esc_html__('Время начала:', 'custom-delivery-methods'); ?></label><br>
                                    <input type="time" name="custom_delivery_methods[${index}][time_start]" value="09:00">
                                </p>
                                <p>
                                    <label><?php echo esc_html__('Время окончания:', 'custom-delivery-methods'); ?></label><br>
                                    <input type="time" name="custom_delivery_methods[${index}][time_end]" value="21:00">
                                </p>
                                <h4><?php echo esc_html__('Интервалы для будних дней', 'custom-delivery-methods'); ?></h4>
                                <div class="weekday-intervals">
                                    <p><button type="button" class="add-weekday-interval"><?php echo esc_html__('Добавить интервал', 'custom-delivery-methods'); ?></button></p>
                                </div>
                                <h4><?php echo esc_html__('Интервалы для выходных дней', 'custom-delivery-methods'); ?></h4>
                                <div class="weekend-intervals">
                                    <p><button type="button" class="add-weekend-interval"><?php echo esc_html__('Добавить интервал', 'custom-delivery-methods'); ?></button></p>
                                </div>
                            </div>
                            <p>
                                <label><?php _e('ID услуги МойСклад (платная):', 'custom-delivery-methods'); ?></label><br>
                                <input type="text" name="custom_delivery_methods[${index}][moysklad_service_id_paid]">
                            </p>
                            <p>
                                <label><?php _e('ID услуги МойСклад (бесплатная):', 'custom-delivery-methods'); ?></label><br>
                                <input type="text" name="custom_delivery_methods[${index}][moysklad_service_id_free]">
                            </p>
                            <p><button type="button" class="remove-method"><?php _e('Удалить метод', 'custom-delivery-methods'); ?></button></p>
                        </div>
                    `;
                    $('#delivery-methods').append(html);
                });

                // Показать/скрыть настройки времени
                $(document).on('change', '.requires-datetime', function() {
                    $(this).closest('.delivery-method').find('.datetime-settings').toggle(this.checked);
                });

                // Добавить интервал для будних дней
                $(document).on('click', '.add-weekday-interval', function() {
                    var methodIndex = $(this).closest('.delivery-method').index();
                    var intervalIndex = $(this).closest('.weekday-intervals').find('p').length;
                    var html = `
                        <p>
                            <input type="time" name="custom_delivery_methods[${methodIndex}][weekday_intervals][${intervalIndex}][start]" value="09:00">
                            <input type="time" name="custom_delivery_methods[${methodIndex}][weekday_intervals][${intervalIndex}][end]" value="10:00">
                            <button type="button" class="remove-interval"><?php _e('Удалить', 'custom-delivery-methods'); ?></button>
                        </p>
                    `;
                    $(this).closest('.weekday-intervals').find('p:last').before(html);
                });

                // Добавить интервал для выходных дней
                $(document).on('click', '.add-weekend-interval', function() {
                    var methodIndex = $(this).closest('.delivery-method').index();
                    var intervalIndex = $(this).closest('.weekend-intervals').find('p').length;
                    var html = `
                        <p>
                            <input type="time" name="custom_delivery_methods[${methodIndex}][weekend_intervals][${intervalIndex}][start]" value="09:00">
                            <input type="time" name="custom_delivery_methods[${methodIndex}][weekend_intervals][${intervalIndex}][end]" value="10:00">
                            <button type="button" class="remove-interval"><?php _e('Удалить', 'custom-delivery-methods'); ?></button>
                        </p>
                    `;
                    $(this).closest('.weekend-intervals').find('p:last').before(html);
                });

                // Удалить интервал
                $(document).on('click', '.remove-interval', function() {
                    $(this).closest('p').remove();
                });

                // Удалить метод доставки
                $(document).on('click', '.remove-method', function() {
                    $(this).closest('.delivery-method').remove();
                });

                // Получить услуги МойСклад
                $('#get-moysklad-services').click(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_moysklad_services',
                            _wpnonce: '<?php echo wp_create_nonce('moysklad_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#moysklad-services-output').html(response.data.html);
                            } else {
                                $('#moysklad-services-output').html('<p>' + response.data.message + '</p>');
                            }
                        },
                        error: function() {
                            $('#moysklad-services-output').html('<p><?php _e('Ошибка запроса', 'custom-delivery-methods'); ?></p>');
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
new Custom_Delivery_Settings();
?>