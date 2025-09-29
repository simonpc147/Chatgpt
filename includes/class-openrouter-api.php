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

        $response = wp_remote_post($this->base_url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array('error' => 'Error de conexión: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return array('error' => 'Error API: ' . $response_code);
        }

        $data = json_decode($response_body, true);

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
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
                return 1000;
        }
    }

    public function get_available_models()
    {
        return array(
            'openai/gpt-4' => array(
                'name' => 'GPT-4',
                'description' => 'Modelo más avanzado de OpenAI',
                'plans' => array('premium', 'enterprise')
            ),
            'openai/gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'description' => 'Rápido y eficiente',
                'plans' => array('free', 'premium', 'enterprise')
            ),
            'anthropic/claude-3-sonnet' => array(
                'name' => 'Claude 3 Sonnet',
                'description' => 'Equilibrio perfecto',
                'plans' => array('premium', 'enterprise')
            ),
            'google/gemini-pro' => array(
                'name' => 'Gemini Pro',
                'description' => 'Modelo de Google',
                'plans' => array('free', 'premium', 'enterprise')
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
