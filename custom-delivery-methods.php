<?php
/**
 * Plugin Name: Custom Delivery Methods
 * Description: Custom delivery methods with time slots for WooCommerce
 * Version: 2.3.25
 * Author: Your Name
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CUSTOM_DELIVERY_METHODS_VERSION', '2.3.25');

class Custom_Delivery_Methods_Plugin {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        require_once plugin_dir_path(__FILE__) . 'includes/class-shipping-method.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-pickup-delivery.php';
        require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

        new Custom_Delivery_Settings();

        add_filter('woocommerce_shipping_methods', [$this, 'register_shipping_methods']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_get_delivery_time_options', [$this, 'get_delivery_time_options']);
        add_action('wp_ajax_nopriv_get_delivery_time_options', [$this, 'get_delivery_time_options']);
    }

    public function register_shipping_methods($methods) {
        $methods['custom_delivery'] = 'Custom_Delivery_Method';
        $methods['pickup_delivery'] = 'Pickup_Delivery_Method';
        return $methods;
    }

    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script(
                'custom-delivery-methods',
                plugin_dir_url(__FILE__) . 'assets/js/custom-delivery-methods.js',
                ['jquery'],
                CUSTOM_DELIVERY_METHODS_VERSION,
                true
            );

            wp_localize_script(
                'custom-delivery-methods',
                'customDeliveryMethods',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('custom_delivery_nonce'),
                    'i18n' => [
                        'select_time' => __('Select delivery time', 'custom-delivery-methods'),
                    ],
                ]
            );

            wp_enqueue_style(
                'custom-delivery-methods',
                plugin_dir_url(__FILE__) . 'assets/css/custom-delivery-methods.css',
                [],
                CUSTOM_DELIVERY_METHODS_VERSION
            );
        }

        if (is_admin()) {
            wp_enqueue_style(
                'custom-delivery-admin',
                plugin_dir_url(__FILE__) . 'assets/css/admin-settings.css',
                [],
                CUSTOM_DELIVERY_METHODS_VERSION
            );
        }
    }

    public function get_delivery_time_options() {
        check_ajax_referer('custom_delivery_nonce', '_wpnonce');

        $method_id = sanitize_text_field($_POST['method_id'] ?? '');
        $date = sanitize_text_field($_POST['date'] ?? '');
        $is_weekend = filter_var($_POST['is_weekend'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($method_id) || empty($date)) {
            wp_send_json_error(['message' => __('Invalid data', 'custom-delivery-methods')]);
        }

        $options = [];
        if (strpos($method_id, 'local_pickup') === 0) {
            $pickup_settings = get_option('pickup_delivery_settings', [
                'time_start' => '11:00',
                'time_end' => '21:00',
            ]);
            $start = strtotime($pickup_settings['time_start']);
            $end = strtotime($pickup_settings['time_end']);
            while ($start <= $end) {
                $time = date('H:i', $start);
                $options[] = [
                    'value' => $time,
                    'text' => $time,
                ];
                $start = strtotime('+1 hour', $start);
            }
        } else {
            $method_index = str_replace('custom_delivery_', '', $method_id);
            $methods = get_option('custom_delivery_methods', []);
            if (!isset($methods[$method_index])) {
                wp_send_json_error(['message' => __('Method not found', 'custom-delivery-methods')]);
            }

            $method = $methods[$method_index];
            if (!empty($method['allow_exact_time'])) {
                $time_start = $is_weekend ? ($method['weekend_time_start'] ?? '09:00') : ($method['weekday_time_start'] ?? '09:00');
                $time_end = $is_weekend ? ($method['weekend_time_end'] ?? '21:00') : ($method['weekday_time_end'] ?? '21:00');
                $start = strtotime($time_start);
                $end = strtotime($time_end);
                while ($start <= $end) {
                    $time = date('H:i', $start);
                    $options[] = [
                        'value' => $time,
                        'text' => $time,
                    ];
                    $start = strtotime('+1 hour', $start);
                }
            } elseif (!empty($method['allow_intervals'])) {
                $intervals = $is_weekend ? ($method['weekend_intervals'] ?? []) : ($method['weekday_intervals'] ?? []);
                foreach ($intervals as $interval) {
                    $text = $interval['start'] . ($interval['start'] !== $interval['end'] ? ' - ' . $interval['end'] : '');
                    $options[] = [
                        'value' => $interval['start'],
                        'text' => $text,
                    ];
                }
            }
        }

        wp_send_json_success(['options' => $options]);
    }
}

new Custom_Delivery_Methods_Plugin();
?>