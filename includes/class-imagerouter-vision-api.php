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

        $body = array(
            'prompt' => $message,
            'model' => $this->model
        );

        $image_uploader = new AI_Chat_Image_Uploader();

        foreach ($attachments as $index => $attachment) {
            $base64_image = $image_uploader->convert_image_to_base64($attachment['file_url']);

            if ($base64_image === false) {
                error_log('Failed to convert image to base64: ' . $attachment['file_url']);
                continue;
            }

            $body['image'][$index] = $base64_image;
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . trim($api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
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
