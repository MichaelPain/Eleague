jQuery(document).ready(function($) {
    $('#bracket').bracket({
        init: <?php echo json_encode($bracket_data); ?>
    });
});

jQuery(document).ready(function($) {
    $('#bracket').bracket({
        init: window.etoBracketData
    });
});