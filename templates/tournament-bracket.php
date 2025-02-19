<?php  
/**  
 * Template per la visualizzazione del bracket del torneo  
 */  
if (!defined('ABSPATH')) exit;  

// Dati passati dallo shortcode  
$bracket_data = isset($args['bracket_data']) ? $args['bracket_data'] : [];  
?>  

<div class="eto-tournament-bracket">  
    <h3>Bracket del Torneo</h3>  
    <div id="bracket-<?php echo esc_attr($args['tournament_id']); ?>"></div>  
</div>  

<script>  
jQuery(document).ready(function($) {  
    $('#bracket-<?php echo esc_js($args['tournament_id']); ?>').bracket({  
        init: <?php echo json_encode($bracket_data); ?>  
    });  
});  
</script>  