<?php
/**
 * Gestione avanzata degli upload
 * @package eSports Tournament Organizer
 * @since 2.1.0
 */

class ETO_Uploads {
    const UPLOADS_DIR = 'eto_uploads';
    const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];

    /**
     * Processa l'upload di uno screenshot
     */
    public static function handle_screenshot_upload($file) {
        try {
            // Verifica errori di sistema
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            // Validazione base
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(__('Errore nel caricamento del file', 'eto'));
            }

            // Validazione tipo file
            $file_info = wp_check_filetype($file['name']);
            if (!array_key_exists($file_info['type'], self::ALLOWED_MIME_TYPES)) {
                throw new Exception(__('Formato file non supportato', 'eto'));
            }

            // Validazione dimensione
            if ($file['size'] > 5 * 1024 * 1024) { // 5MB
                throw new Exception(__('Dimensione massima consentita: 5MB', 'eto'));
            }

            // Crea directory dedicata
            $wp_upload_dir = wp_upload_dir();
            $target_dir = path_join($wp_upload_dir['basedir'], self::UPLOADS_DIR);
            
            if (!file_exists($target_dir)) {
                if (!wp_mkdir_p($target_dir)) {
                    throw new Exception(__('Impossibile creare la directory di destinazione', 'eto'));
                }
            }

            // Sposta il file
            $filename = sanitize_file_name($file['name']);
            $target_path = path_join($target_dir, $filename);
            
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception(__('Errore nel salvataggio del file', 'eto'));
            }

            return [
                'url' => path_join($wp_upload_dir['baseurl'], self::UPLOADS_DIR . '/' . $filename),
                'path' => $target_path
            ];

        } catch (Exception $e) {
            return new WP_Error('upload_failed', $e->getMessage());
        }
    }

    /**
     * Processa l'upload del logo di un team
     */
    public static function handle_team_logo_upload($file) {
        try {
            // Verifica permessi utente
            if (!current_user_can('upload_files')) {
                throw new Exception(__('Permessi insufficienti per l\'upload', 'eto'));
            }

            // Validazione tipo file
            $file_info = wp_check_filetype($file['name']);
            if (!in_array($file_info['type'], ['image/jpeg', 'image/png'])) {
                throw new Exception(__('Solo formati JPG e PNG sono consentiti', 'eto'));
            }

            // Elabora con WordPress
            $upload = wp_handle_upload($file, [
                'test_form' => false,
                'unique_filename_callback' => function($dir, $name, $ext) {
                    return 'team-logo-' . md5($name) . $ext;
                }
            ]);

            if (isset($upload['error'])) {
                throw new Exception($upload['error']);
            }

            return $upload['url'];

        } catch (Exception $e) {
            return new WP_Error('logo_upload_error', $e->getMessage());
        }
    }
}
