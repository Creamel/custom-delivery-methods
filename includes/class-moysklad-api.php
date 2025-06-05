<?php
/**
 * Custom Delivery Methods for WooCommerce - MoySklad API
 * Version: 2.2.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class MoySklad_API {
    private $api_url = 'https://api.moysklad.ru/api/remap/1.2/';

    public function get_services() {
        // Проверяем наличие функции WooMS\request
        if (!function_exists('WooMS\request')) {
            error_log('WooMS: Функция WooMS\request не найдена. Проверьте, активен ли плагин WooMS.');
            return ['error' => 'Плагин WooMS не активен или функция request не найдена'];
        }

        try {
            // Формируем относительный путь для запроса
            $path = 'entity/service';
            $full_url = $this->api_url . $path;
            error_log('WooMS: Формируемый URL для запроса: ' . $full_url);

            // Запрос к МойСклад через WooMS\request
            $response = \WooMS\request($full_url);

            if (is_array($response) && !isset($response['errors'])) {
                error_log('WooMS: Услуги успешно получены из МойСклад.');
                return $response;
            } else {
                $error_message = isset($response['errors']) ? $response['errors'][0]['error'] : 'Ошибка запроса к МойСклад';
                error_log('WooMS: Ошибка получения услуг: ' . $error_message);
                return ['error' => $error_message];
            }
        } catch (Exception $e) {
            error_log('WooMS: Исключение при запросе к МойСклад: ' . $e->getMessage());
            return ['error' => 'Ошибка подключения к МойСклад: ' . $e->getMessage()];
        }
    }
}
?>