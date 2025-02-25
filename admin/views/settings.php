<div class="wrap">
    <h1><?php esc_html_e('Impostazioni Plugin Tornei', 'eto'); ?></h1>
    
    <form method="post" action="options.php">
        <?php 
        settings_fields('eto_settings_group');
        do_settings_sections('eto_settings_page');
        submit_button(); 
        ?>
    </form>
</div>