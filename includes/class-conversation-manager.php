<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Conversation_Manager
{

    public function __construct()
    {
        add_action('init', array($this, 'register_conversation_post_types'));
    }

    public function register_conversation_post_types()
    {
        register_post_type('ai_conversation', array(
            'labels' => array(
                'name' => 'Conversaciones AI',
                'singular_name' => 'Conversación AI'
            ),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'author'),
            'has_archive' => false,
            'query_var' => false,
            'can_export' => false,
            'rewrite' => false
        ));

        register_post_type('ai_message', array(
            'labels' => array(
                'name' => 'Mensajes AI',
                'singular_name' => 'Mensaje AI'
            ),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('editor', 'author'),
            'has_archive' => false,
            'query_var' => false,
            'can_export' => false,
            'rewrite' => false
        ));
    }

    public function create_conversation($user_id, $title = null, $project_id = null)
    {
        if (!$title) {
            $title = 'Conversación ' . date('Y-m-d H:i:s');
        }

        $conversation_data = array(
            'post_type' => 'ai_conversation',
            'post_status' => 'private',
            'post_author' => $user_id,
            'post_title' => sanitize_text_field($title),
            'meta_input' => array(
                'user_id' => $user_id,
                'project_id' => $project_id,
                'message_count' => 0,
                'last_message_at' => current_time('mysql'),
                'created_at' => current_time('mysql')
            )
        );

        $conversation_id = wp_insert_post($conversation_data);

        if (is_wp_error($conversation_id)) {
            return false;
        }

        if ($project_id) {
            $projects_manager = new AI_Chat_Projects_Manager();
            $projects_manager->update_project_activity($project_id);
        }

        return $conversation_id;
    }

    public function get_user_conversations($user_id, $project_id = null, $limit = 50)
    {
        $args = array(
            'post_type' => 'ai_conversation',
            'author' => $user_id,
            'posts_per_page' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => 'last_message_at',
            'order' => 'DESC',
            'post_status' => 'private'
        );

        if ($project_id) {
            $args['meta_query'] = array(
                array(
                    'key' => 'project_id',
                    'value' => $project_id
                )
            );
        }

        $conversations = get_posts($args);

        $formatted_conversations = array();

        foreach ($conversations as $conversation) {
            $message_count = get_post_meta($conversation->ID, 'message_count', true) ?: 0;
            $last_message = $this->get_last_message($conversation->ID);

            $formatted_conversations[] = array(
                'id' => $conversation->ID,
                'title' => $conversation->post_title,
                'project_id' => get_post_meta($conversation->ID, 'project_id', true),
                'created_at' => $conversation->post_date,
                'updated_at' => $conversation->post_modified,
                'message_count' => $message_count,
                'last_message_at' => get_post_meta($conversation->ID, 'last_message_at', true),
                'last_message_preview' => $last_message ? substr(strip_tags($last_message->post_content), 0, 100) : ''
            );
        }

        return $formatted_conversations;
    }

    public function get_conversation_messages($conversation_id, $user_id, $limit = 100)
    {
        $conversation = get_post($conversation_id);

        if (!$conversation || $conversation->post_author != $user_id) {
            return array();
        }

        $messages = get_posts(array(
            'post_type' => 'ai_message',
            'post_parent' => $conversation_id,
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'ASC'
        ));

        $formatted_messages = array();

        foreach ($messages as $message) {
            $attachments_json = get_post_meta($message->ID, 'attachments', true);
            $attachments = !empty($attachments_json) ? json_decode($attachments_json, true) : null;

            $formatted_messages[] = array(
                'id' => $message->ID,
                'role' => get_post_meta($message->ID, 'role', true),
                'content' => $message->post_content,
                'model' => get_post_meta($message->ID, 'model', true),
                'timestamp' => $message->post_date,
                'token_count' => get_post_meta($message->ID, 'token_count', true),
                'cost' => get_post_meta($message->ID, 'cost', true),
                'attachments' => $attachments
            );
        }

        return $formatted_messages;
    }

    public function add_message($conversation_id, $role, $content, $model = null, $metadata = array())
    {
        $conversation = get_post($conversation_id);

        if (!$conversation) {
            return false;
        }

        $message_data = array(
            'post_type' => 'ai_message',
            'post_content' => wp_kses_post($content),
            'post_status' => 'private',
            'post_parent' => $conversation_id,
            'post_author' => $conversation->post_author,
            'meta_input' => array(
                'role' => sanitize_text_field($role),
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('mysql')
            )
        );

        if ($model) {
            $message_data['meta_input']['model'] = sanitize_text_field($model);
        }

        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                $message_data['meta_input'][sanitize_key($key)] = $value;
            }
        }

        $message_id = wp_insert_post($message_data);

        if (is_wp_error($message_id)) {
            return false;
        }

        $this->update_conversation_stats($conversation_id);

        return $message_id;
    }

    public function update_conversation_title($conversation_id, $user_id, $new_title)
    {
        $conversation = get_post($conversation_id);

        if (!$conversation || $conversation->post_author != $user_id) {
            return false;
        }

        $result = wp_update_post(array(
            'ID' => $conversation_id,
            'post_title' => sanitize_text_field($new_title)
        ));

        return !is_wp_error($result);
    }

    public function delete_conversation($conversation_id, $user_id)
    {
        $conversation = get_post($conversation_id);

        if (!$conversation || $conversation->post_author != $user_id) {
            return false;
        }

        $messages = get_posts(array(
            'post_type' => 'ai_message',
            'post_parent' => $conversation_id,
            'posts_per_page' => -1
        ));

        foreach ($messages as $message) {
            wp_delete_post($message->ID, true);
        }

        $project_id = get_post_meta($conversation_id, 'project_id', true);

        $result = wp_delete_post($conversation_id, true);

        if ($result && $project_id) {
            $projects_manager = new AI_Chat_Projects_Manager();
            $projects_manager->update_project_activity($project_id);
        }

        return $result !== false;
    }

    private function update_conversation_stats($conversation_id)
    {
        $message_count = get_posts(array(
            'post_type' => 'ai_message',
            'post_parent' => $conversation_id,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        update_post_meta($conversation_id, 'message_count', count($message_count));
        update_post_meta($conversation_id, 'last_message_at', current_time('mysql'));

        $project_id = get_post_meta($conversation_id, 'project_id', true);
        if ($project_id) {
            $projects_manager = new AI_Chat_Projects_Manager();
            $projects_manager->update_project_activity($project_id);
        }
    }

    private function get_last_message($conversation_id)
    {
        $messages = get_posts(array(
            'post_type' => 'ai_message',
            'post_parent' => $conversation_id,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        return !empty($messages) ? $messages[0] : null;
    }

    public function get_conversation_stats($conversation_id, $user_id)
    {
        $conversation = get_post($conversation_id);

        if (!$conversation || $conversation->post_author != $user_id) {
            return false;
        }

        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN pm1.meta_value = 'user' THEN 1 ELSE 0 END) as user_messages,
                SUM(CASE WHEN pm1.meta_value = 'assistant' THEN 1 ELSE 0 END) as ai_messages,
                AVG(CASE WHEN pm2.meta_key = 'token_count' THEN pm2.meta_value END) as avg_tokens
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'role'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'token_count'
            WHERE p.post_type = 'ai_message' 
            AND p.post_parent = %d
        ", $conversation_id));

        return array(
            'total_messages' => intval($stats->total_messages),
            'user_messages' => intval($stats->user_messages),
            'ai_messages' => intval($stats->ai_messages),
            'average_tokens' => round(floatval($stats->avg_tokens), 2)
        );
    }
}
