<?php
/**
 * Template email: Risultato confermato
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>">
    <title><?php echo esc_html($subject); ?></title>
</head>
<body>
    <h2>Risultato confermato - <?php echo esc_html($tournament_name); ?></h2>
    
    <p>Ciao capitano del team <?php echo esc_html($team_name); ?>,</p>
    
    <p>La partita contro <strong><?php echo esc_html($opponent_name); ?></strong> è stata registrata come <strong><?php echo $result; ?></strong>.</p>
    
    <p>Dettagli partita:</p>
    <ul>
        <li>Torneo: <?php echo esc_html($tournament_name); ?></li>
        <li>Esito: <?php echo ucfirst($result); ?></li>
        <li>Data conferma: <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></li>
    </ul>

    <p>Per domande o contestazioni, rispondi a questa email.</p>
    
    <p>Saluti,<br>
    Lo staff di <?php echo get_bloginfo('name'); ?></p>
</body>
</html>