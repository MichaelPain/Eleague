<div class="wrap">
    <h1><?php echo $tournament ? esc_html__('Modifica Torneo', 'eto') : esc_html__('Crea Nuovo Torneo', 'eto'); ?></h1>

    <form method="post" class="eto-admin-form">
        <?php wp_nonce_field('eto_create_tournament_action', '_wpnonce_create_tournament'); ?>

        <!-- SEZIONE DATI BASE -->
        <div class="eto-form-section">
            <h2><?php esc_html_e('Informazioni Base', 'eto'); ?></h2>
            
            <div class="form-field">
                <label><?php esc_html_e('Nome Torneo', 'eto'); ?> *</label>
                <input type="text" name="name" required 
                    value="<?php echo $tournament ? esc_attr($tournament->name) : ''; ?>">
            </div>

            <!-- CAMPO FORMATO DINAMICO -->
            <div class="form-field">
                <label><?php esc_html_e('Formato Torneo', 'eto'); ?></label>
                <select name="format">
                    <?php foreach ($allowed_formats as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" 
                            <?php selected($tournament ? $tournament->format : 'single_elimination', $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CAMPO GIOCO DINAMICO -->
            <div class="form-field">
                <label><?php esc_html_e('Tipo di Gioco', 'eto'); ?></label>
                <select name="game_type">
                    <?php foreach ($allowed_games as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"
                            <?php selected($tournament ? $tournament->game_type : 'lol', $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- SEZIONE DATE -->
        <div class="eto-form-section">
            <h2><?php esc_html_e('Date del Torneo', 'eto'); ?></h2>
            
            <div class="form-field">
                <label><?php esc_html_e('Data Inizio', 'eto'); ?> *</label>
                <input type="datetime-local" name="start_date" required 
                    value="<?php echo $tournament ? esc_attr(date('Y-m-d\TH:i', strtotime($tournament->start_date))) : ''; ?>">
            </div>

            <div class="form-field">
                <label><?php esc_html_e('Data Fine', 'eto'); ?> *</label>
                <input type="datetime-local" name="end_date" required 
                    value="<?php echo $tournament ? esc_attr(date('Y-m-d\TH:i', strtotime($tournament->end_date))) : ''; ?>">
            </div>
        </div>

        <!-- SEZIONE TEAM -->
        <div class="eto-form-section">
            <h2><?php esc_html_e('Impostazioni Team', 'eto'); ?></h2>
            
            <div class="form-field">
                <label><?php esc_html_e('Giocatori Minimi per Team', 'eto'); ?></label>
                <input type="number" name="min_players" min="<?php echo ETO_Tournament::MIN_PLAYERS; ?>" 
                    max="<?php echo ETO_Tournament::MAX_PLAYERS; ?>" 
                    value="<?php echo $tournament ? esc_attr($tournament->min_players) : '5'; ?>">
            </div>

            <div class="form-field">
                <label><?php esc_html_e('Giocatori Massimi per Team', 'eto'); ?></label>
                <input type="number" name="max_players" min="<?php echo ETO_Tournament::MIN_PLAYERS; ?>" 
                    max="<?php echo ETO_Tournament::MAX_PLAYERS; ?>" 
                    value="<?php echo $tournament ? esc_attr($tournament->max_players) : '10'; ?>">
            </div>

            <div class="form-field">
                <label><?php esc_html_e('Numero Massimo Team', 'eto'); ?></label>
                <input type="number" name="max_teams" min="2" 
                    max="<?php echo ETO_Tournament::MAX_TEAMS; ?>" 
                    value="<?php echo $tournament ? esc_attr($tournament->max_teams) : '16'; ?>">
            </div>
        </div>

        <!-- SEZIONE OPZIONI AVANZATE -->
        <div class="eto-form-section">
            <h2><?php esc_html_e('Opzioni Avanzate', 'eto'); ?></h2>
            
            <div class="form-field checkbox-field">
                <label>
                    <input type="checkbox" name="checkin_enabled" 
                        <?php checked($tournament ? $tournament->checkin_enabled : 0, 1); ?>>
                    <?php esc_html_e('Abilita Check-in', 'eto'); ?>
                </label>
            </div>

            <div class="form-field checkbox-field">
                <label>
                    <input type="checkbox" name="third_place_match" 
                        <?php checked($tournament ? $tournament->third_place_match : 0, 1); ?>>
                    <?php esc_html_e('Finale 3° Posto', 'eto'); ?>
                </label>
            </div>
        </div>

        <?php submit_button($tournament ? __('Aggiorna Torneo', 'eto') : __('Crea Torneo', 'eto')); ?>
    </form>
</div>
