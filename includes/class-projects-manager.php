<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Projects_Manager
{

    public function __construct()
    {
        add_action('init', array($this, 'register_project_post_type'));
    }

    public function register_project_post_type()
    {
        register_post_type('ai_project', array(
            'labels' => array(
                'name' => 'Proyectos AI',
                'singular_name' => 'Proyecto AI'
            ),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'editor', 'author'),
            'has_archive' => false,
            'query_var' => false,
            'can_export' => false,
            'rewrite' => false
        ));
    }

    public function create_project($user_id, $title, $description = '')
    {
        $project_data = array(
            'post_type' => 'ai_project',
            'post_status' => 'private',
            'post_author' => $user_id,
            'post_title' => sanitize_text_field($title),
            'post_content' => sanitize_textarea_field($description),
            'meta_input' => array(
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
                'conversation_count' => 0,
                'last_activity' => current_time('mysql')
            )
        );

        $project_id = wp_insert_post($project_data);

        if (is_wp_error($project_id)) {
            return false;
        }

        return $project_id;
    }

    public function get_user_projects($user_id, $limit = 20)
    {
        $projects = get_posts(array(
            'post_type' => 'ai_project',
            'author' => $user_id,
            'posts_per_page' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => 'last_activity',
            'order' => 'DESC',
            'post_status' => 'private'
        ));

        $formatted_projects = array();

        foreach ($projects as $project) {
            $formatted_projects[] = array(
                'id' => $project->ID,
                'title' => $project->post_title,
                'description' => $project->post_content,
                'created_at' => $project->post_date,
                'updated_at' => $project->post_modified,
                'conversation_count' => get_post_meta($project->ID, 'conversation_count', true) ?: 0,
                'last_activity' => get_post_meta($project->ID, 'last_activity', true)
            );
        }

        return $formatted_projects;
    }

    public function update_project($project_id, $user_id, $title = null, $description = null)
    {
        $project = get_post($project_id);

        if (!$project || $project->post_author != $user_id || $project->post_type !== 'ai_project') {
            return false;
        }

        $update_data = array('ID' => $project_id);

        if ($title !== null) {
            $update_data['post_title'] = sanitize_text_field($title);
        }

        if ($description !== null) {
            $update_data['post_content'] = sanitize_textarea_field($description);
        }

        $result = wp_update_post($update_data);

        if (!is_wp_error($result)) {
            update_post_meta($project_id, 'last_activity', current_time('mysql'));
            return true;
        }

        return false;
    }

    public function delete_project($project_id, $user_id)
    {
        $project = get_post($project_id);

        if (!$project || $project->post_author != $user_id || $project->post_type !== 'ai_project') {
            return false;
        }

        $conversations = get_posts(array(
            'post_type' => 'ai_conversation',
            'meta_query' => array(
                array(
                    'key' => 'project_id',
                    'value' => $project_id
                )
            ),
            'posts_per_page' => -1
        ));

        foreach ($conversations as $conversation) {
            $this->delete_conversation_messages($conversation->ID);
            wp_delete_post($conversation->ID, true);
        }

        return wp_delete_post($project_id, true) !== false;
    }

    private function delete_conversation_messages($conversation_id)
    {
        $messages = get_posts(array(
            'post_type' => 'ai_message',
            'post_parent' => $conversation_id,
            'posts_per_page' => -1
        ));

        foreach ($messages as $message) {
            wp_delete_post($message->ID, true);
        }
    }

    public function get_project_stats($project_id, $user_id)
    {
        $project = get_post($project_id);

        if (!$project || $project->post_author != $user_id) {
            return false;
        }

        $conversations_count = get_posts(array(
            'post_type' => 'ai_conversation',
            'meta_query' => array(
                array(
                    'key' => 'project_id',
                    'value' => $project_id
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        global $wpdb;
        $messages_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->posts} c ON p.post_parent = c.ID
            INNER JOIN {$wpdb->postmeta} pm ON c.ID = pm.post_id
            WHERE p.post_type = 'ai_message'
            AND c.post_type = 'ai_conversation'
            AND pm.meta_key = 'project_id'
            AND pm.meta_value = %d
        ", $project_id));

        return array(
            'conversations_count' => count($conversations_count),
            'messages_count' => intval($messages_count),
            'last_activity' => get_post_meta($project_id, 'last_activity', true)
        );
    }

    public function update_project_activity($project_id)
    {
        update_post_meta($project_id, 'last_activity', current_time('mysql'));

        $current_count = get_post_meta($project_id, 'conversation_count', true) ?: 0;

        $actual_count = get_posts(array(
            'post_type' => 'ai_conversation',
            'meta_query' => array(
                array(
                    'key' => 'project_id',
                    'value' => $project_id
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        update_post_meta($project_id, 'conversation_count', count($actual_count));
    }
}
