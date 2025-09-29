<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Models_Manager
{

    private $models_config;

    public function __construct()
    {
        $this->init_models_config();
    }

    private function init_models_config()
    {
        $this->models_config = array(
            'openai/gpt-4' => array(
                'name' => 'GPT-4',
                'provider' => 'OpenAI',
                'description' => 'Modelo más avanzado y preciso de OpenAI',
                'max_tokens' => 8192,
                'context_window' => 128000,
                'cost_per_1k_tokens' => 0.03,
                'plans' => array('premium', 'enterprise'),
                'capabilities' => array('text', 'analysis', 'coding', 'creative'),
                'limits' => array(
                    'free' => 0,
                    'premium' => 100,
                    'enterprise' => 1000
                )
            ),
            'openai/gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'provider' => 'OpenAI',
                'description' => 'Rápido y eficiente para tareas generales',
                'max_tokens' => 4096,
                'context_window' => 16385,
                'cost_per_1k_tokens' => 0.002,
                'plans' => array('free', 'premium', 'enterprise'),
                'capabilities' => array('text', 'chat', 'basic_coding'),
                'limits' => array(
                    'free' => 50,
                    'premium' => 500,
                    'enterprise' => 2000
                )
            ),
            'anthropic/claude-3-sonnet' => array(
                'name' => 'Claude 3 Sonnet',
                'provider' => 'Anthropic',
                'description' => 'Equilibrio perfecto entre velocidad y capacidad',
                'max_tokens' => 4096,
                'context_window' => 200000,
                'cost_per_1k_tokens' => 0.015,
                'plans' => array('premium', 'enterprise'),
                'capabilities' => array('text', 'analysis', 'reasoning', 'creative'),
                'limits' => array(
                    'free' => 0,
                    'premium' => 200,
                    'enterprise' => 1500
                )
            ),
            'anthropic/claude-3-haiku' => array(
                'name' => 'Claude 3 Haiku',
                'provider' => 'Anthropic',
                'description' => 'Rápido y eficiente para respuestas instantáneas',
                'max_tokens' => 4096,
                'context_window' => 200000,
                'cost_per_1k_tokens' => 0.0008,
                'plans' => array('free', 'premium', 'enterprise'),
                'capabilities' => array('text', 'chat', 'quick_tasks'),
                'limits' => array(
                    'free' => 30,
                    'premium' => 300,
                    'enterprise' => 1000
                )
            ),
            'google/gemini-pro' => array(
                'name' => 'Gemini Pro',
                'provider' => 'Google',
                'description' => 'Modelo multimodal avanzado de Google',
                'max_tokens' => 2048,
                'context_window' => 32768,
                'cost_per_1k_tokens' => 0.0005,
                'plans' => array('free', 'premium', 'enterprise'),
                'capabilities' => array('text', 'multimodal', 'analysis'),
                'limits' => array(
                    'free' => 40,
                    'premium' => 400,
                    'enterprise' => 1200
                )
            ),
            'meta-llama/llama-3-70b-instruct' => array(
                'name' => 'Llama 3 70B',
                'provider' => 'Meta',
                'description' => 'Modelo open source potente y versátil',
                'max_tokens' => 4096,
                'context_window' => 8192,
                'cost_per_1k_tokens' => 0.0009,
                'plans' => array('premium', 'enterprise'),
                'capabilities' => array('text', 'coding', 'reasoning'),
                'limits' => array(
                    'free' => 0,
                    'premium' => 150,
                    'enterprise' => 800
                )
            )
        );
    }

    public function get_models_for_user($user_id)
    {
        $user_plan = ai_chat_get_user_plan($user_id);
        $available_models = array();

        foreach ($this->models_config as $model_id => $config) {
            if (in_array($user_plan, $config['plans'])) {
                $available_models[$model_id] = $config;
                $available_models[$model_id]['remaining_uses'] = $this->get_remaining_uses($user_id, $model_id, $user_plan);
            }
        }

        return $available_models;
    }

    public function get_model_config($model_id)
    {
        return isset($this->models_config[$model_id]) ? $this->models_config[$model_id] : null;
    }

    public function validate_model_for_user($user_id, $model_id)
    {
        $user_plan = ai_chat_get_user_plan($user_id);
        $model_config = $this->get_model_config($model_id);

        if (!$model_config) {
            return false;
        }

        if (!in_array($user_plan, $model_config['plans'])) {
            return false;
        }

        $remaining_uses = $this->get_remaining_uses($user_id, $model_id, $user_plan);

        return $remaining_uses > 0;
    }

    private function get_remaining_uses($user_id, $model_id, $user_plan)
    {
        $model_config = $this->get_model_config($model_id);

        if (!$model_config) {
            return 0;
        }

        $limit = $model_config['limits'][$user_plan] ?? 0;

        if ($limit === 0) {
            return 0;
        }

        if ($user_plan === 'enterprise') {
            return 9999;
        }

        $used_today = $this->get_daily_usage($user_id, $model_id);

        return max(0, $limit - $used_today);
    }

    private function get_daily_usage($user_id, $model_id)
    {
        global $wpdb;

        $today = date('Y-m-d');

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->posts} c ON p.post_parent = c.ID
            WHERE p.post_type = 'ai_message'
            AND pm.meta_key = 'model'
            AND pm.meta_value = %s
            AND c.post_author = %d
            AND DATE(p.post_date) = %s
            AND pm.meta_key = 'role'
            AND pm.meta_value = 'assistant'
        ", $model_id, $user_id, $today));

        return intval($count);
    }

    public function increment_usage($user_id, $model_id)
    {
        $usage_key = 'ai_chat_usage_' . $user_id . '_' . date('Y-m-d');
        $current_usage = get_transient($usage_key) ?: array();

        if (!isset($current_usage[$model_id])) {
            $current_usage[$model_id] = 0;
        }

        $current_usage[$model_id]++;

        set_transient($usage_key, $current_usage, DAY_IN_SECONDS);

        return $current_usage[$model_id];
    }

    public function get_user_stats($user_id)
    {
        $user_plan = ai_chat_get_user_plan($user_id);
        $available_models = $this->get_models_for_user($user_id);

        $stats = array(
            'user_plan' => $user_plan,
            'total_models' => count($available_models),
            'daily_usage' => array(),
            'limits' => array()
        );

        foreach ($available_models as $model_id => $config) {
            $daily_usage = $this->get_daily_usage($user_id, $model_id);
            $limit = $config['limits'][$user_plan] ?? 0;

            $stats['daily_usage'][$model_id] = $daily_usage;
            $stats['limits'][$model_id] = $limit;
        }

        return $stats;
    }

    public function get_models_by_provider()
    {
        $providers = array();

        foreach ($this->models_config as $model_id => $config) {
            $provider = $config['provider'];

            if (!isset($providers[$provider])) {
                $providers[$provider] = array();
            }

            $providers[$provider][$model_id] = $config;
        }

        return $providers;
    }

    public function get_featured_models($user_plan)
    {
        $featured = array();

        foreach ($this->models_config as $model_id => $config) {
            if (in_array($user_plan, $config['plans']) && in_array('featured', $config['capabilities'] ?? array())) {
                $featured[$model_id] = $config;
            }
        }

        if (empty($featured)) {
            $featured = array_slice($this->get_models_for_user_by_plan($user_plan), 0, 3, true);
        }

        return $featured;
    }

    private function get_models_for_user_by_plan($user_plan)
    {
        $models = array();

        foreach ($this->models_config as $model_id => $config) {
            if (in_array($user_plan, $config['plans'])) {
                $models[$model_id] = $config;
            }
        }

        return $models;
    }
}
