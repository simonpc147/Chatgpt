<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Handler
{

    private $openrouter_api;
    private $image_handler;


    public function __construct()
    {
        $this->openrouter_api = new AI_Chat_OpenRouter_API();
        $this->image_handler = new AI_Chat_Image_Handler();
    }

    public function process_message($user_id, $message, $model, $conversation_id = null)
    {
        if (!$this->validate_user_permissions($user_id)) {
            return array('error' => 'Usuario sin permisos');
        }

        if (!$this->validate_message($message)) {
            return array('error' => 'Mensaje inválido');
        }

        $api_key = ai_chat_get_user_api_key($user_id);

        if (!$api_key) {
            return array('error' => 'API Key no configurada. Contacta al administrador.');
        }

        if (!$conversation_id) {
            return array('error' => 'ID de conversación requerido');
        }

        $conversation_manager = new AI_Chat_Conversation_Manager();
        $conversation_manager->add_message($conversation_id, 'user', $message);

        if ($this->image_handler->should_generate_image($message)) {
            $prompt = $this->image_handler->extract_image_prompt($message);

            error_log('Generating image for: ' . $prompt);

            $imagerouter_key = ai_chat_get_imagerouter_api_key($user_id);

            if (empty($imagerouter_key)) {
                return array('error' => 'ImageRouter API Key no configurada');
            }

            $image_result = $this->image_handler->generate_image($imagerouter_key, $prompt);

            if (isset($image_result['error'])) {
                return $image_result;
            }

            $conversation_manager->add_message($conversation_id, 'assistant', $image_result['url'], 'image-generator');

            return array(
                'success' => true,
                'type' => 'image',
                'content' => $image_result['url'],
                'conversation_id' => $conversation_id
            );
        }

        if (!$this->validate_model_for_user($user_id, $model)) {
            return array('error' => 'Modelo no disponible para tu plan');
        }

        $conversation_history = $this->get_conversation_history($conversation_id);
        $messages = $this->prepare_messages($conversation_history, $message);

        $response = $this->openrouter_api->send_message($api_key, $model, $messages, $user_id);

        if (isset($response['error'])) {
            return $response;
        }

        $conversation_manager->add_message($conversation_id, 'assistant', $response['content'], $model);

        return array(
            'success' => true,
            'type' => 'text',
            'content' => $response['content'],
            'conversation_id' => $conversation_id,
            'usage' => $response['usage'] ?? null
        );
    }

    private function validate_user_permissions($user_id)
    {
        // TEMPORAL: Permitir sin key para testing
        return true;

        $user = get_user_by('ID', $user_id);
        return $user && ai_chat_user_has_key($user_id);
    }

    private function validate_message($message)
    {
        return !empty(trim($message)) && strlen($message) <= 4000;
    }

    private function validate_model_for_user($user_id, $model)
    {
        $available_models = $this->openrouter_api->get_models_for_user($user_id);
        return array_key_exists($model, $available_models);
    }

    private function create_new_conversation($user_id)
    {
        $conversation = wp_insert_post(array(
            'post_type' => 'ai_conversation',
            'post_status' => 'private',
            'post_author' => $user_id,
            'post_title' => 'Conversación ' . date('Y-m-d H:i:s'),
            'meta_input' => array(
                'user_id' => $user_id,
                'created_at' => current_time('mysql')
            )
        ));

        return $conversation;
    }

    private function get_conversation_history($conversation_id)
    {
        $messages = get_posts(array(
            'post_type' => 'ai_message',
            'post_parent' => $conversation_id,
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => 'conversation_id',
                    'value' => $conversation_id
                )
            )
        ));

        $history = array();
        foreach ($messages as $message) {
            $role = get_post_meta($message->ID, 'role', true);
            $content = $message->post_content;

            $history[] = array(
                'role' => $role,
                'content' => $content
            );
        }

        return $history;
    }

    private function prepare_messages($history, $new_message)
    {
        $messages = $history;

        $messages[] = array(
            'role' => 'user',
            'content' => $new_message
        );

        return $messages;
    }

    private function save_user_message($conversation_id, $message)
    {
        wp_insert_post(array(
            'post_type' => 'ai_message',
            'post_content' => $message,
            'post_status' => 'private',
            'post_parent' => $conversation_id,
            'meta_input' => array(
                'role' => 'user',
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('mysql')
            )
        ));
    }

    private function save_ai_message($conversation_id, $message, $model)
    {
        wp_insert_post(array(
            'post_type' => 'ai_message',
            'post_content' => $message,
            'post_status' => 'private',
            'post_parent' => $conversation_id,
            'meta_input' => array(
                'role' => 'assistant',
                'model' => $model,
                'conversation_id' => $conversation_id,
                'timestamp' => current_time('mysql')
            )
        ));
    }

    public function get_user_conversations($user_id)
    {
        return get_posts(array(
            'post_type' => 'ai_conversation',
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
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

        return wp_delete_post($conversation_id, true);
    }
}
