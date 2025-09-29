<?php
if (!defined('ABSPATH')) {
    exit;
}

function ai_chat_create_user_keys_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_user_keys';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        api_key varchar(255) NOT NULL,
        plan varchar(50) DEFAULT 'free',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function ai_chat_get_user_data($user_id)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_user_keys';

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
    );
}

function ai_chat_update_user_key($user_id, $api_key, $plan = null)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_user_keys';

    // Verificar si el usuario ya tiene un registro
    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT id FROM $table_name WHERE user_id = %d", $user_id)
    );

    if ($existing) {
        // UPDATE si existe
        $update_data = array('api_key' => $api_key);

        if ($plan !== null) {
            $update_data['plan'] = $plan;
        }

        return $wpdb->update(
            $table_name,
            $update_data,
            array('user_id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );
    } else {
        // INSERT si no existe
        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'api_key' => $api_key,
                'plan' => $plan ?: 'free'
            ),
            array('%d', '%s', '%s')
        );
    }
}


function ai_chat_insert_user_key($user_id, $api_key, $plan = 'free')
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_user_keys';

    return $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'api_key' => $api_key,
            'plan' => $plan
        ),
        array('%d', '%s', '%s')
    );
}

function ai_chat_get_all_users_with_keys()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_user_keys';

    return $wpdb->get_results("
        SELECT u.ID, u.user_login, u.user_email, u.user_registered, 
               k.api_key, k.plan, k.created_at, k.updated_at
        FROM {$wpdb->users} u
        LEFT JOIN $table_name k ON u.ID = k.user_id
        ORDER BY u.user_registered DESC
    ");
}

function ai_chat_create_usage_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_usage_log';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        model varchar(100) NOT NULL,
        tokens_used int(11) DEFAULT 0,
        cost decimal(10,6) DEFAULT 0.000000,
        conversation_id bigint(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY model (model),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function ai_chat_log_usage($user_id, $model, $tokens_used, $cost, $conversation_id = null)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_usage_log';

    return $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'model' => $model,
            'tokens_used' => $tokens_used,
            'cost' => $cost,
            'conversation_id' => $conversation_id
        ),
        array('%d', '%s', '%d', '%f', '%d')
    );
}

function ai_chat_get_user_usage_stats($user_id, $days = 30)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai_usage_log';

    return $wpdb->get_results($wpdb->prepare("
        SELECT model, 
               COUNT(*) as requests,
               SUM(tokens_used) as total_tokens,
               SUM(cost) as total_cost
        FROM $table_name 
        WHERE user_id = %d 
        AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY model
        ORDER BY total_cost DESC
    ", $user_id, $days));
}
