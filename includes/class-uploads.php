<?php
class ETO_Uploads {
    // Gestione upload screenshot
    public static function handle_screenshot($file) {
        $upload_dir = wp_upload_dir();
        $private_dir = $upload_dir['basedir'] . '/eto_private';

        // Crea directory protetta con .htaccess
        if (!file_exists($private_dir)) {
            wp_mkdir_p($private_dir);
            file_put_contents($private_dir . '/.htaccess', 'Deny from all');
        }

        // Verifica tipo e dimensione file
        $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!array_key_exists($file['type'], $allowed_types) || $file['size'] > $max_size) {
            return new WP_Error('invalid_file', 'Formato non supportato o file troppo grande (max 2MB)');
        }

        // Genera nome univoco e salva
        $extension = $allowed_types[$file['type']];
        $filename = sanitize_file_name(sprintf(
            'match_%s_%s.%s',
            date('Ymd-His'),
            wp_generate_password(4, false),
            $extension
        ));

        if (!move_uploaded_file($file['tmp_name'], $private_dir . '/' . $filename)) {
            return new WP_Error('upload_failed', 'Errore nel salvataggio del file');
        }

        return $upload_dir['baseurl'] . '/eto_private/' . $filename;
    }

    // Elimina screenshot
    public static function delete_screenshot($url) {
        $upload_dir = wp_upload_dir();
        $path = str_replace($upload_dir['baseurl'] . '/eto_private/', '', $url);
        $full_path = $upload_dir['basedir'] . '/eto_private/' . $path;

        if (file_exists($full_path)) {
            return unlink($full_path);
        }
        return false;
    }
}
