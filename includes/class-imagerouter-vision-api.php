<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_ImageRouter_Vision_API
{
    private $api_url = 'https://api.imagerouter.io/v1/openai/images/generations';
    private $model = 'google/gemini-2.5-flash';

    public function send_vision_request($api_key, $message, $attachments)
    {
        if (empty($api_key)) {
            return array('error' => 'ImageRouter API Key no configurada');
        }

        if (empty($attachments)) {
            return array('error' => 'No se enviaron imágenes');
        }

        if (!function_exists('download_url')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $boundary = wp_generate_password(24, false);

        $body_parts = array();

        $body_parts[] = "--{$boundary}";
        $body_parts[] = 'Content-Disposition: form-data; name="prompt"';
        $body_parts[] = '';
        $body_parts[] = $message;

        $body_parts[] = "--{$boundary}";
        $body_parts[] = 'Content-Disposition: form-data; name="model"';
        $body_parts[] = '';
        $body_parts[] = $this->model;

        $attachment = $attachments[0];
        $file_path = $attachment['file_url'];

        if (filter_var($file_path, FILTER_VALIDATE_URL)) {
            $temp_file = download_url($file_path);
            if (is_wp_error($temp_file)) {
                error_log('Failed to download image: ' . $file_path);
                return array('error' => 'No se pudo descargar la imagen');
            }
            $file_path = $temp_file;
        }

        if (!file_exists($file_path)) {
            error_log('File not found: ' . $file_path);
            return array('error' => 'Archivo no encontrado');
        }

        $file_content = file_get_contents($file_path);

        if ($file_content === false) {
            error_log('Failed to read file: ' . $file_path);
            if (isset($temp_file)) {
                @unlink($temp_file);
            }
            return array('error' => 'No se pudo leer el archivo');
        }

        $file_name = basename($file_path);
        $file_type = mime_content_type($file_path);

        $body_parts[] = "--{$boundary}";
        $body_parts[] = 'Content-Disposition: form-data; name="image"; filename="' . $file_name . '"';
        $body_parts[] = 'Content-Type: ' . $file_type;
        $body_parts[] = '';
        $body_parts[] = $file_content;

        if (isset($temp_file)) {
            @unlink($temp_file);
        }

        $body_parts[] = "--{$boundary}--";
        $body_parts[] = '';

        $body = implode("\r\n", $body_parts);

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . trim($api_key),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary
            ),
            'body' => $body,
            'timeout' => 120
        );

        error_log('=== VISION REQUEST ===');
        error_log('Model: ' . $this->model);
        error_log('Prompt: ' . $message);
        error_log('Images count: ' . count($attachments));

        $response = wp_remote_request($this->api_url, $args);

        if (is_wp_error($response)) {
            error_log('Vision API Error: ' . $response->get_error_message());
            return array('error' => 'Error en Vision API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('=== VISION RESPONSE ===');
        error_log('Code: ' . $response_code);
        error_log('Body: ' . $response_body);

        if ($response_code !== 200) {
            return array('error' => 'Error en Vision API: ' . $response_code);
        }

        $result = json_decode($response_body, true);

        if (isset($result['data']['url'])) {
            return array(
                'success' => true,
                'content' => $result['data']['url'],
                'model' => $this->model,
                'type' => 'image'
            );
        }

        if (isset($result['data'][0]['url'])) {
            return array(
                'success' => true,
                'content' => $result['data'][0]['url'],
                'model' => $this->model,
                'type' => 'image'
            );
        }

        return array('error' => 'No se pudo obtener respuesta del modelo');
    }
}
