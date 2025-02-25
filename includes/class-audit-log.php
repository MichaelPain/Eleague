<?php
class ETO_Audit_Log {
    public static function add($data) {
        global $wpdb;

        $defaults = [
            'user_id' => get_current_user_id(),
            'action_type' => 'general',
            'object_type' => '',
            'object_id' => 0,
            'details' => '',
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'])
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            "{$wpdb->prefix}eto_audit_logs",
            [
                'user_id' => absint($data['user_id']),
                'action_type' => sanitize_text_field($data['action_type']),
                'object_type' => sanitize_text_field($data['object_type']),
                'object_id' => absint($data['object_id']),
                'details' => sanitize_textarea_field(wp_json_encode($data['details'])),
                'ip_address' => $data['ip_address'],
                'created_at' => current_time('mysql')
            ],
            [
                '%d',  // user_id
                '%s',  // action_type
                '%s',  // object_type
                '%d',  // object_id
                '%s',  // details
                '%s',  // ip_address
                '%s'   // created_at
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    // Registra il nuovo tipo di log per i privilegi
    public static function register_privilege_log() {
        add_action('eto_privileges_granted', function($user_id) {
            self::add([
                'user_id' => $user_id,
                'action_type' => 'super_installer',
                'object_type' => 'user',
                'object_id' => $user_id,
                'details' => 'Privilegi concessi automaticamente'
            ]);
        });
    }
}

// Inizializza il sistema di logging
add_action('plugins_loaded', [ETO_Audit_Log::class, 'register_privilege_log']);