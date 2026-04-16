jQuery(document).ready(function($) {
    var pendingChanges = false;
    
    // Bindear evento del botón ANTES
    $(document).on('click', '#apply-changes', function() {
        location.reload();
    });
    
$('.addon-toggle').on('change', function() {
    var $checkbox = $(this);
    var addon = $checkbox.data('addon');
    var $card = $checkbox.closest('.addon-card');
    
    $.ajax({
        url: addonManager.ajax_url,
        type: 'POST',
        data: {
            action: 'toggle_addon',
            addon: addon,
            nonce: addonManager.nonce
        },
        success: function(response) {
            if (response.success) {
                $card.toggleClass('active');
                pendingChanges = true;
                $('#addon-message').html('<div class="notice notice-success"><p>' + response.data + ' <button id="apply-changes" class="button button-primary">Aplicar cambios</button></p></div>').show();
            } else {
                $('#addon-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        }
    });
});
});