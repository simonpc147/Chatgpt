<?php
if (!defined('ABSPATH')) {
    exit;
}

function ai_chat_set_user_default_key($user_id)
{
    $default_openrouter_key = get_option('ai_chat_default_key', '');
    $default_imagerouter_key = get_option('ai_chat_imagerouter_default_key', '');

    if (!empty($default_openrouter_key)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_user_keys';

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'api_key' => $default_openrouter_key,
                'imagerouter_api_key' => $default_imagerouter_key,
                'plan' => 'free'
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
}

function ai_chat_get_user_api_key($user_id)
{
    $user_data = ai_chat_get_user_data($user_id);
    return $user_data ? $user_data->api_key : '';
}

function ai_chat_get_imagerouter_api_key($user_id)
{
    $user_data = ai_chat_get_user_data($user_id);
    return $user_data && isset($user_data->imagerouter_api_key) ? $user_data->imagerouter_api_key : '';
}

function ai_chat_get_user_plan($user_id)
{
    $user_data = ai_chat_get_user_data($user_id);
    return $user_data ? $user_data->plan : 'free';
}

function ai_chat_user_has_key($user_id)
{
    $user_data = ai_chat_get_user_data($user_id);
    return $user_data && !empty($user_data->api_key);
}

function ai_chat_validate_api_key($api_key)
{
    return !empty($api_key) && strlen($api_key) > 10;
}

function ai_chat_validate_and_sanitize_key($api_key)
{
    $key = sanitize_text_field($api_key);

    if (empty($key)) {
        return false;
    }

    if (strlen($key) < 20) {
        return false;
    }

    return $key;
}

function ai_chat_validate_plan($plan)
{
    $valid_plans = array('free', 'premium', 'enterprise');
    return in_array($plan, $valid_plans) ? $plan : 'free';
}
