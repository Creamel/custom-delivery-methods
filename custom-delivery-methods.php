<?php
/**
 * Plugin Name: Custom Delivery Methods for WooCommerce
 * Plugin URI: https://tart-shop.ru
 * Description: Adds custom delivery methods with datetime selection for WooCommerce, integrated with MoySklad.
 * Version: 2.3.22
 * Author: Your Name
 * Author URI: https://tart-shop.ru
 * Text Domain: custom-delivery-methods
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

// Константы
define('CUSTOM_DELIVERY_METHODS_VERSION', '2.3.22');
define('CUSTOM_DELIVERY_METHODS_DIR', plugin_dir_path(__FILE__));
define('CUSTOM_DELIVERY_METHODS_URL', plugin_dir_url(__FILE__));

// Подключение файлов
require_once CUSTOM_DELIVERY_METHODS_DIR . 'includes/class-pickup-delivery.php';
require_once CUSTOM_DELIVERY_METHODS_DIR . 'includes/admin-settings.php';

// Проверка зависимостей и инициализация
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__('Custom Delivery требует активного плагина WooCommerce.', 'custom-delivery-methods') . '</p></div>';
        });
        return;
    }

    // Подключение метода доставки
    add_action('woocommerce_shipping_init', function() {
        require_once CUSTOM_DELIVERY_METHODS_DIR . 'includes/class-shipping-method.php';
        if (class_exists('WC_Custom_Delivery')) {
            add_filter('woocommerce_shipping_methods', function($methods) {
                $methods['custom_delivery'] = 'WC_Custom_Delivery';
                return $methods;
            });
        }
    });

    // Инициализация классов
    if (class_exists('Pickup_Delivery')) {
        new Pickup_Delivery();
    }
    if (class_exists('Custom_Delivery_Settings')) {
        new Custom_Delivery_Settings();
    }
});

// Инициализация переводов
add_action('plugins_loaded', function() {
    load_plugin_textdomain('custom-delivery-methods', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Подключение скриптов и стилей
add_action('wp_enqueue_scripts', function() {
    if (is_checkout()) {
        wp_enqueue_script(
            'custom-delivery-methods',
            CUSTOM_DELIVERY_METHODS_URL . 'assets/js/custom-delivery-methods.js',
            ['jquery'],
            CUSTOM_DELIVERY_METHODS_VERSION,
            true
        );
        wp_enqueue_style(
            'custom-delivery-methods',
            CUSTOM_DELIVERY_METHODS_URL . 'assets/css/custom-delivery-methods.css',
            [],
            CUSTOM_DELIVERY_METHODS_VERSION
        );
    }
});

// Подключение стилей для админки
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'woocommerce_page_custom-delivery-settings') {
        wp_enqueue_style(
            'custom-delivery-admin',
            CUSTOM_DELIVERY_METHODS_URL . 'assets/css/admin-settings.css',
            [],
            CUSTOM_DELIVERY_METHODS_VERSION
        );
    }
});
?>