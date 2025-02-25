<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $subject; ?></title>
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
        <h2 style="color: #2B547E;"><?php _e('Promemoria Check-in Torneo', 'eto'); ?></h2>
        
        <p><?php printf(__('Ciao capitano di %s,', 'eto'), $team_name); ?></p>
        
        <p><?php printf(__('Il torneo %s inizierà il %s alle %s.'), 
            $tournament_name, 
            $start_date, 
            $start_time); ?>
        </p>

        <p><?php _e('Completa il check-in ora per garantire la partecipazione del tuo team:', 'eto'); ?></p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?php echo $checkin_link; ?>" 
               style="background-color: #2B547E; color: white; padding: 12px 25px; 
                      text-decoration: none; border-radius: 4px;">
                <?php _e('Effettua Check-in', 'eto'); ?>
            </a>
        </div>

        <p><?php _e('Cordiali saluti,<br>Lo Staff del Torneo', 'eto'); ?></p>
    </div>
</body>
</html>