<?php
/**
 * Admin Settings for Custom Delivery Methods
 * Version: 2.3.25
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Delivery_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_fetch_moysklad_services', [$this, 'fetch_moysklad_services']);
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
        register_setting('custom_delivery_settings', 'custom_delivery_methods', [$this, 'sanitize_settings']);
        register_setting('custom_delivery_settings', 'pickup_delivery_settings', [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    $sanitized[$key] = $this->sanitize_settings($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }
        return $sanitized;
    }

    public function fetch_moysklad_services() {
        check_ajax_referer('custom_delivery_nonce', '_wpnonce');

        $response = wp_remote_get('https://api.moysklad.ru/api/remap/1.2/entity/service', [
            'headers' => [
                'Authorization' => 'Bearer ' . get_option('moysklad_api_key', ''),
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Failed to fetch Moysklad services', 'custom-delivery-methods')]);
        }

        $services = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success(['services' => $services['rows'] ?? []]);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Delivery Settings', 'custom-delivery-methods'); ?></h1>
            <form method="post" action="options.php" class="delivery-settings-form">
                <?php
                settings_fields('custom_delivery_settings');
                $methods = get_option('custom_delivery_methods', []);
                $pickup_settings = get_option('pickup_delivery_settings', [
                    'time_start' => '11:00',
                    'time_end' => '21:00',
                ]);
                ?>
                <h2><?php _e('Delivery Methods', 'custom-delivery-methods'); ?></h2>
                <div id="delivery-methods">
                    <?php foreach ($methods as $index => $method) : ?>
                        <div class="method-block">
                            <div class="column">
                                <p>
                                    <label><?php _e('Name', 'custom-delivery-methods'); ?></label><br>
                                    <input type="text" name="custom_delivery_methods[<?php echo $index; ?>][name]" value="<?php echo esc_attr($method['name'] ?? ''); ?>" style="width: 100%;">
                                </p>
                                <p>
                                    <label><?php _e('Cost', 'custom-delivery-methods'); ?></label><br>
                                    <input type="number" step="0.01" name="custom_delivery_methods[<?php echo $index; ?>][cost]" value="<?php echo esc_attr($method['cost'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Free Above', 'custom-delivery-methods'); ?></label><br>
                                    <input type="number" step="0.01" name="custom_delivery_methods[<?php echo $index; ?>][free_above]" value="<?php echo esc_attr($method['free_above'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Description', 'custom-delivery-methods'); ?></label><br>
                                    <textarea name="custom_delivery_methods[<?php echo $index; ?>][description]" style="width: 100%;"><?php echo esc_textarea($method['description'] ?? ''); ?></textarea>
                                </p>
                                <p>
                                    <label><input type="checkbox" name="custom_delivery_methods[<?php echo $index; ?>][allow_exact_time]" value="1" class="exact-time-checkbox" <?php checked($method['allow_exact_time'] ?? 0, 1); ?>> <?php _e('Allow Exact Time', 'custom-delivery-methods'); ?></label>
                                </p>
                                <p>
                                    <label><input type="checkbox" name="custom_delivery_methods[<?php echo $index; ?>][allow_intervals]" value="1" class="intervals-checkbox" <?php checked($method['allow_intervals'] ?? 0, 1); ?>> <?php _e('Allow Intervals', 'custom-delivery-methods'); ?></label>
                                </p>
                            </div>
                            <div class="column">
                                <p>
                                    <label><?php _e('Weekday Time Start', 'custom-delivery-methods'); ?></label><br>
                                    <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekday_time_start]" value="<?php echo esc_attr($method['weekday_time_start'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Weekday Time End', 'custom-delivery-methods'); ?></label><br>
                                    <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekday_time_end]" value="<?php echo esc_attr($method['weekday_time_end'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Weekend Time Start', 'custom-delivery-methods'); ?></label><br>
                                    <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekend_time_start]" value="<?php echo esc_attr($method['weekend_time_start'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Weekend Time End', 'custom-delivery-methods'); ?></label><br>
                                    <input type="time" name="custom_delivery_methods[<?php echo $index; ?>][weekend_time_end]" value="<?php echo esc_attr($method['weekend_time_end'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Moysklad Service ID (Paid)', 'custom-delivery-methods'); ?></label><br>
                                    <input type="text" name="custom_delivery_methods[<?php echo $index; ?>][moysklad_service_id_paid]" value="<?php echo esc_attr($method['moysklad_service_id_paid'] ?? ''); ?>">
                                </p>
                                <p>
                                    <label><?php _e('Moysklad Service ID (Free)', 'custom-delivery-methods'); ?></label><br>
                                    <input type="text" name="custom_delivery_methods[<?php echo $index; ?>][moysklad_service_id_free]" value="<?php echo esc_attr($method['moysklad_service_id_free'] ?? ''); ?>">
                                </p>
                            </div>
                            <button type="button" class="remove-method button"><?php _e('Remove Method', 'custom-delivery-methods'); ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="moysklad-integration">
                    <h3><?php _e('Moysklad Integration', 'custom-delivery-methods'); ?></h3>
                    <p>
                        <button type="button" id="fetch-moysklad-services" class="button"><?php _e('Fetch Moysklad Services', 'custom-delivery-methods'); ?></button>
                    </p>
                    <div id="moysklad-services"></div>
                </div>
                <button type="button" id="add-method" class="button"><?php _e('Add Method', 'custom-delivery-methods'); ?></button>

                <h2><?php _e('Pickup Settings', 'custom-delivery-methods'); ?></h2>
                <div class="pickup-settings">
                    <div class="column">
                        <p>
                            <label><?php _e('Time Start', 'custom-delivery-methods'); ?></label><br>
                            <input type="time" name="pickup_delivery_settings[time_start]" value="<?php echo esc_attr($pickup_settings['time_start']); ?>">
                        </p>
                    </div>
                    <div class="column">
                        <p>
                            <label><?php _e('Time End', 'custom-delivery-methods'); ?></label><br>
                            <input type="time" name="pickup_delivery_settings[time_end]" value="<?php echo esc_attr($pickup_settings['time_end']); ?>">
                        </p>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
            <script>
                jQuery(document).ready(function($) {
                    // Moysklad services fetch
                    $('#fetch-moysklad-services').click(function() {
                        $.ajax({
                            url: customDeliveryMethods.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'fetch_moysklad_services',
                                _wpnonce: customDeliveryMethods.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var services = '<ul>';
                                    $.each(response.data.services, function(i, service) {
                                        services += '<li>' + service.name + ' (ID: ' + service.id + ')</li>';
                                    });
                                    services += '</ul>';
                                    $('#moysklad-services').html(services);
                                } else {
                                    $('#moysklad-services').html('<p style="color: red;">' + response.data.message + '</p>');
                                }
                            }
                        });
                    });

                    // Add new method
                    $('#add-method').click(function() {
                        var index = $('.method-block').length;
                        var html = '<div class="method-block">' +
                            '<div class="column">' +
                            '<p><label><?php _e('Name', 'custom-delivery-methods'); ?></label><br><input type="text" name="custom_delivery_methods[' + index + '][name]" style="width: 100%;"></p>' +
                            '<p><label><?php _e('Cost', 'custom-delivery-methods'); ?></label><br><input type="number" step="0.01" name="custom_delivery_methods[' + index + '][cost]"></p>' +
                            '<p><label><?php _e('Free Above', 'custom-delivery-methods'); ?></label><br><input type="number" step="0.01" name="custom_delivery_methods[' + index + '][free_above]"></p>' +
                            '<p><label><?php _e('Description', 'custom-delivery-methods'); ?></label><br><textarea name="custom_delivery_methods[' + index + '][description]" style="width: 100%;"></textarea></p>' +
                            '<p><label><input type="checkbox" name="custom_delivery_methods[' + index + '][allow_exact_time]" value="1" class="exact-time-checkbox"> <?php _e('Allow Exact Time', 'custom-delivery-methods'); ?></label></p>' +
                            '<p><label><input type="checkbox" name="custom_delivery_methods[' + index + '][allow_intervals]" value="1" class="intervals-checkbox"> <?php _e('Allow Intervals', 'custom-delivery-methods'); ?></label></p>' +
                            '</div>' +
                            '<div class="column">' +
                            '<p><label><?php _e('Weekday Time Start', 'custom-delivery-methods'); ?></label><br><input type="time" name="custom_delivery_methods[' + index + '][weekday_time_start]"></p>' +
                            '<p><label><?php _e('Weekday Time End', 'custom-delivery-methods'); ?></label><br><input type="time" name="custom_delivery_methods[' + index + '][weekday_time_end]"></p>' +
                            '<p><label><?php _e('Weekend Time Start', 'custom-delivery-methods'); ?></label><br><input type="time" name="custom_delivery_methods[' + index + '][weekend_time_start]"></p>' +
                            '<p><label><?php _e('Weekend Time End', 'custom-delivery-methods'); ?></label><br><input type="time" name="custom_delivery_methods[' + index + '][weekend_time_end]"></p>' +
                            '<p><label><?php _e('Moysklad Service ID (Paid)', 'custom-delivery-methods'); ?></label><br><input type="text" name="custom_delivery_methods[' + index + '][moysklad_service_id_paid]"></p>' +
                            '<p><label><?php _e('Moysklad Service ID (Free)', 'custom-delivery-methods'); ?></label><br><input type="text" name="custom_delivery_methods[' + index + '][moysklad_service_id_free]"></p>' +
                            '</div>' +
                            '<button type="button" class="remove-method button"><?php _e('Remove Method', 'custom-delivery-methods'); ?></button>' +
                            '</div>';
                        $('#delivery-methods').append(html);
                    });

                    // Remove method
                    $(document).on('click', '.remove-method', function() {
                        $(this).closest('.method-block').remove();
                    });

                    // Mutual exclusion for checkboxes
                    $(document).on('change', '.exact-time-checkbox', function() {
                        var $methodBlock = $(this).closest('.method-block');
                        var $intervalsCheckbox = $methodBlock.find('.intervals-checkbox');
                        if ($(this).is(':checked')) {
                            $intervalsCheckbox.prop('checked', false).prop('disabled', true);
                        } else {
                            $intervalsCheckbox.prop('disabled', false);
                        }
                    });

                    $(document).on('change', '.intervals-checkbox', function() {
                        var $methodBlock = $(this).closest('.method-block');
                        var $exactTimeCheckbox = $methodBlock.find('.exact-time-checkbox');
                        if ($(this).is(':checked')) {
                            $exactTimeCheckbox.prop('checked', false).prop('disabled', true);
                        } else {
                            $exactTimeCheckbox.prop('disabled', false);
                        }
                    });

                    // Initialize checkbox states
                    $('.method-block').each(function() {
                        var $methodBlock = $(this);
                        var $exactTimeCheckbox = $methodBlock.find('.exact-time-checkbox');
                        var $intervalsCheckbox = $methodBlock.find('.intervals-checkbox');
                        if ($exactTimeCheckbox.is(':checked')) {
                            $intervalsCheckbox.prop('disabled', true);
                        } else if ($intervalsCheckbox.is(':checked')) {
                            $exactTimeCheckbox.prop('disabled', true);
                        }
                    });
                });
            </script>
        </div>
        <?php
    }
}
?>