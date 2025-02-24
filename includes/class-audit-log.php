<?php
/**
 * Classe per registrare log di azioni (Audit Log)
 */
class ETO_Audit_Log {
    /**
     * Registra un'azione nel log.
     *
     * @param string $action Il nome o la tipologia dell'azione.
     * @param mixed $details Dati aggiuntivi da salvare (può essere una stringa o un array).
     * @return int|false L'ID dell'inserimento o false in caso di errore.
     */
    public static function log($action, $details = '') {
        global $wpdb;
        $user_id = get_current_user_id();
        $result = $wpdb->insert(
            "{$wpdb->prefix}eto_audit_logs",
            array(
                'user_id'     => $user_id,
                'action_type' => sanitize_text_field($action),
                'details'     => wp_json_encode($details),
                'ip_address'  => $_SERVER['REMOTE_ADDR'],
                'created_at'  => current_time('mysql')
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
        return $result ? $wpdb->insert_id : false;
    }
}
?>
