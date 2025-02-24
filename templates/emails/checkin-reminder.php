<?php
/**
 * Template email: Promemoria check-in
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>">
    <title><?php echo esc_html($subject); ?></title>
</head>
<body>
    <h2>Check-in obbligatorio per <?php echo esc_html($tournament_name); ?></h2>
    
    <p>Ciao <?php echo esc_html($user_name); ?>,</p>
    
    <p>Il check-in per il torneo <strong><?php echo esc_html($tournament_name); ?></strong> aprirà alle <strong><?php echo $checkin_start; ?></strong>.</p>
    
    <p>Per confermare la partecipazione del tuo team <strong><?php echo esc_html($team_name); ?></strong>, accedi al sito e completa il check-in entro l'orario indicato:</p>
    
    <p><a href="<?php echo esc_url($tournament_link); ?>">Vai alla pagina del torneo</a></p>
    
    <p>Saluti,<br>
    Lo staff di <?php echo get_bloginfo('name'); ?></p>
</body>
</html>
