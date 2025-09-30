<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Image_Handler
{
    private $api_url = 'https://api.imagerouter.io/v1/openai/images/generations';
    private $model = 'google/gemini-2.5-flash';

    public function generate_image($api_key, $prompt)
    {
        if (empty($api_key)) {
            return array('error' => 'API Key no configurada');
        }

        $api_key = trim($api_key);

        $body = array(
            'prompt' => "Professional clean illustration: " . $prompt . ", no text, no letters, modern style, high quality",
            'model' => $this->model,
            'quality' => 'auto'
        );

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        );

        error_log('=== IMAGE REQUEST ===');
        error_log('Prompt: ' . $prompt);

        error_log('=== API KEY DEBUG ===');
        error_log('Length: ' . strlen($api_key));
        error_log('First 10 chars: ' . substr($api_key, 0, 10));
        error_log('Full Authorization: Bearer ' . $api_key);
        error_log('Authorization Length: ' . strlen('Bearer ' . $api_key));

        $response = wp_remote_request($this->api_url, $args);

        if (is_wp_error($response)) {
            error_log('Image Error: ' . $response->get_error_message());
            return array('error' => 'Error generando imagen: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('=== IMAGE RESPONSE ===');
        error_log('Code: ' . $response_code);
        error_log('Body: ' . substr($response_body, 0, 200));

        if ($response_code !== 200) {
            return array('error' => 'Error en API de imágenes: ' . $response_code);
        }

        $result = json_decode($response_body, true);

        if (isset($result['data']['url'])) {
            return array('success' => true, 'url' => $result['data']['url']);
        }

        if (isset($result['data'][0]['url'])) {
            return array('success' => true, 'url' => $result['data'][0]['url']);
        }

        return array('error' => 'No se pudo obtener URL de imagen');
    }

    public function should_generate_image($message)
    {
        $image_keywords = array(
            'imagen',
            'foto',
            'picture',
            'image',
            'draw',
            'dibuja',
            'dibujo',
            'genera',
            'crea',
            'create',
            'muestra',
            'show me',
            'ilustra',
            'illustration',
            'visual',
            'gráfico',
            'graphic'
        );

        $message_lower = strtolower($message);

        foreach ($image_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    public function extract_image_prompt($message)
    {
        $patterns = array(
            '/(?:genera|crea|dibuja|muestra)(?:\s+una?)?\s+(?:imagen|foto|dibujo)\s+(?:de|sobre|con)?\s*(.+)/i',
            '/(?:image|picture|photo)\s+(?:of|about|with)?\s*(.+)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }

        return trim($message);
    }
}
