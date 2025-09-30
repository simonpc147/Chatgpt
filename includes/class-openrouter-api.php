<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_OpenRouter_API
{

    private $base_url = 'https://openrouter.ai/api/v1/chat/completions';

    public function send_message($api_key, $model, $messages, $user_id)
    {
        if (empty($api_key)) {
            return array('error' => 'API Key no configurada');
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url(),
            'X-Title' => get_bloginfo('name')
        );

        $body = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $this->get_max_tokens_for_user($user_id),
            'temperature' => 0.7
        );

        error_log('=== OPENROUTER REQUEST ===');
        error_log('Model: ' . $model);
        error_log('Messages count: ' . count($messages));
        error_log('Body: ' . json_encode($body));

        $response = wp_remote_post($this->base_url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            error_log('WP Error: ' . $response->get_error_message());
            return array('error' => 'Error de conexión: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $response_body = trim($response_body);

        error_log('=== OPENROUTER RESPONSE ===');
        error_log('Code: ' . $response_code);
        error_log('Body: ' . $response_body);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);

            if ($response_code == 429) {
                $error_message = 'El modelo está temporalmente saturado. Intenta con otro modelo o espera unos minutos.';
            } elseif (isset($error_data['error']['metadata']['raw'])) {
                $error_message = $error_data['error']['metadata']['raw'];
            } elseif (isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
            } else {
                $error_message = 'Error API: ' . $response_code;
            }

            error_log('Error Message: ' . $error_message);
            return array('error' => $error_message);
        }

        $data = json_decode($response_body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            error_log('Invalid Response Structure');
            error_log('JSON Error: ' . json_last_error_msg());
            return array('error' => 'Respuesta inválida de la API');
        }

        return array(
            'success' => true,
            'content' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? null
        );
    }

    private function get_max_tokens_for_user($user_id)
    {
        $plan = ai_chat_get_user_plan($user_id);

        switch ($plan) {
            case 'enterprise':
                return 4000;
            case 'premium':
                return 2000;
            default:
                return 2000;
        }
    }

    public function get_available_models()
    {
        return array(
            'google/gemini-2.5-flash' => array(
                'name' => 'Gemini 2.5 Flash',
                'description' => 'Modelo rápido de Google',
                'plans' => array('free', 'premium', 'enterprise')
            ),
            'openai/gpt-4o-mini' => array(
                'name' => 'GPT-4o Mini',
                'description' => 'Rápido y económico',
                'plans' => array('free', 'premium', 'enterprise')
            ),
            'anthropic/claude-3.5-sonnet' => array(
                'name' => 'Claude 3.5 Sonnet',
                'description' => 'Equilibrio perfecto de velocidad y calidad',
                'plans' => array('premium', 'enterprise')
            ),
            'anthropic/claude-opus-4' => array(
                'name' => 'Claude Opus 4',
                'description' => 'Modelo más avanzado de Anthropic',
                'plans' => array('enterprise')
            ),
            'openai/gpt-4o' => array(
                'name' => 'GPT-4o',
                'description' => 'Modelo más reciente de OpenAI',
                'plans' => array('premium', 'enterprise')
            ),
            'google/gemini-2.5-pro-preview' => array(
                'name' => 'Gemini 2.5 Pro',
                'description' => 'Modelo avanzado de Google',
                'plans' => array('premium', 'enterprise')
            )
        );
    }

    public function get_models_for_user($user_id)
    {
        $user_plan = ai_chat_get_user_plan($user_id);
        $all_models = $this->get_available_models();
        $available_models = array();

        foreach ($all_models as $model_id => $model_info) {
            if (in_array($user_plan, $model_info['plans'])) {
                $available_models[$model_id] = $model_info;
            }
        }

        return $available_models;
    }
}
