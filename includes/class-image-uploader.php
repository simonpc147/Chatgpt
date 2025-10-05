<?php
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Image_Uploader
{
    private $upload_dir;
    private $allowed_types = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
    private $max_file_size = 10485760;
    private $max_width = 1920;
    private $max_height = 1920;
    private $quality = 85;
    private $thumbnail_size = 400;

    public function __construct()
    {
        $wp_upload_dir = wp_upload_dir();
        $this->upload_dir = $wp_upload_dir['basedir'] . '/ai-chat-images';

        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            $this->create_htaccess();
        }
    }

    public function upload_images($files, $user_id, $project_id = null, $conversation_id = null)
    {
        $uploaded_files = array();
        $errors = array();

        foreach ($files['name'] as $key => $name) {
            $file = array(
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            );

            $result = $this->upload_single_image($file, $user_id, $project_id, $conversation_id);

            if (isset($result['error'])) {
                $errors[] = $result['error'];
            } else {
                $uploaded_files[] = $result;
            }
        }

        return array(
            'success' => count($uploaded_files) > 0,
            'files' => $uploaded_files,
            'errors' => $errors
        );
    }

    private function upload_single_image($file, $user_id, $project_id = null, $conversation_id = null)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('error' => 'Error al subir archivo: ' . $this->get_upload_error_message($file['error']));
        }

        if (!in_array($file['type'], $this->allowed_types)) {
            return array('error' => 'Tipo de archivo no permitido: ' . $file['type']);
        }

        if ($file['size'] > $this->max_file_size) {
            return array('error' => 'Archivo muy grande. Máximo 10MB.');
        }

        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return array('error' => 'El archivo no es una imagen válida');
        }

        $user_dir = $this->create_user_directory($user_id, $project_id, $conversation_id);
        if (is_wp_error($user_dir)) {
            return array('error' => $user_dir->get_error_message());
        }

        $extension = 'jpg';
        $filename = time() . '_' . wp_generate_password(8, false) . '.' . $extension;
        $file_path = $user_dir . '/' . $filename;

        $compressed = $this->compress_image($file['tmp_name'], $file['type'], $file_path);
        if ($compressed !== true) {
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                return array('error' => 'Error al guardar archivo');
            }
        }

        $thumbnail_filename = 'thumb_' . $filename;
        $thumbnail_path = $user_dir . '/' . $thumbnail_filename;
        $this->create_thumbnail($file_path, $thumbnail_path);

        $wp_upload_dir = wp_upload_dir();
        $relative_path = str_replace($wp_upload_dir['basedir'], '', $file_path);
        $file_url = $wp_upload_dir['baseurl'] . $relative_path;

        $relative_thumb_path = str_replace($wp_upload_dir['basedir'], '', $thumbnail_path);
        $thumbnail_url = file_exists($thumbnail_path) ? $wp_upload_dir['baseurl'] . $relative_thumb_path : $file_url;

        return array(
            'file_path' => $relative_path,
            'file_url' => $file_url,
            'thumbnail_url' => $thumbnail_url,
            'file_name' => sanitize_file_name($file['name']),
            'file_size' => filesize($file_path),
            'mime_type' => 'image/jpeg',
            'uploaded_at' => current_time('mysql')
        );
    }

    private function compress_image($source, $mime_type, $destination)
    {
        switch ($mime_type) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($source);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($source);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($source);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $this->max_width || $height > $this->max_height) {
            $ratio = min($this->max_width / $width, $this->max_height / $height);
            $new_width = floor($width * $ratio);
            $new_height = floor($height * $ratio);

            $resized = imagecreatetruecolor($new_width, $new_height);

            imagecopyresampled(
                $resized,
                $image,
                0,
                0,
                0,
                0,
                $new_width,
                $new_height,
                $width,
                $height
            );

            imagedestroy($image);
            $image = $resized;
        }

        $saved = imagejpeg($image, $destination, $this->quality);
        imagedestroy($image);

        if ($saved) {
            @chmod($destination, 0644);
        }

        return $saved;
    }

    private function create_thumbnail($source, $destination)
    {
        $image = @imagecreatefromjpeg($source);
        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $ratio = min($this->thumbnail_size / $width, $this->thumbnail_size / $height);
        $thumb_width = floor($width * $ratio);
        $thumb_height = floor($height * $ratio);

        $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);

        imagecopyresampled(
            $thumbnail,
            $image,
            0,
            0,
            0,
            0,
            $thumb_width,
            $thumb_height,
            $width,
            $height
        );

        imagejpeg($thumbnail, $destination, 70);

        imagedestroy($image);
        imagedestroy($thumbnail);

        @chmod($destination, 0644);

        return true;
    }

    private function create_user_directory($user_id, $project_id = null, $conversation_id = null)
    {
        $path = $this->upload_dir . '/user-' . $user_id;

        if ($project_id) {
            $path .= '/project-' . $project_id;
        }

        if ($conversation_id) {
            $path .= '/conversation-' . $conversation_id;
        }

        if (!file_exists($path)) {
            if (!wp_mkdir_p($path)) {
                return new WP_Error('mkdir_failed', 'No se pudo crear el directorio');
            }
        }

        return $path;
    }

    private function get_file_extension($filename)
    {
        $parts = explode('.', $filename);
        return strtolower(end($parts));
    }

    private function get_upload_error_message($error_code)
    {
        $errors = array(
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el límite del servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el límite del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir archivo',
            UPLOAD_ERR_EXTENSION => 'Extensión de PHP detuvo la subida'
        );

        return isset($errors[$error_code]) ? $errors[$error_code] : 'Error desconocido';
    }

    private function create_htaccess()
    {
        $htaccess_file = $this->upload_dir . '/.htaccess';
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<FilesMatch '\.(jpg|jpeg|png|gif|webp)$'>\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</FilesMatch>";

        file_put_contents($htaccess_file, $htaccess_content);
    }

    public function convert_image_to_base64($file_url)
    {
        $wp_upload_dir = wp_upload_dir();
        $file_path = str_replace($wp_upload_dir['baseurl'], $wp_upload_dir['basedir'], $file_url);

        if (!file_exists($file_path)) {
            return false;
        }

        $image_data = file_get_contents($file_path);
        if ($image_data === false) {
            return false;
        }

        $mime_type = mime_content_type($file_path);
        $base64 = base64_encode($image_data);

        return 'data:' . $mime_type . ';base64,' . $base64;
    }

    public function delete_image($file_path)
    {
        $wp_upload_dir = wp_upload_dir();
        $full_path = $wp_upload_dir['basedir'] . $file_path;

        if (file_exists($full_path)) {
            return unlink($full_path);
        }

        return false;
    }
}
