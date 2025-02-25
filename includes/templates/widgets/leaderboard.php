<div class="eto-leaderboard-container">
    <table class="eto-leaderboard-table">
        <thead>
            <tr>
                <th><?php _e('Posizione', 'eto'); ?></th>
                <th><?php _e('Team', 'eto'); ?></th>
                <th><?php _e('Vittorie', 'eto'); ?></th>
                <th><?php _e('Sconfitte', 'eto'); ?></th>
                <th><?php _e('Diff. Punti', 'eto'); ?></th>
                <th><?php _e('Partite', 'eto'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($teams as $index => $team) : ?>
                <tr>
                    <td><?php echo (int)($index + 1); ?></td>
                    <td><?php echo esc_html($team->name); ?></td>
                    <td><?php echo absint($team->wins); ?></td>
                    <td><?php echo absint($team->losses); ?></td>
                    <td><?php echo number_format_i18n((int)$team->points_diff, 0); ?></td>
                    <td><?php echo absint($team->matches_played); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>