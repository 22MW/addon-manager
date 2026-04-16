jQuery(document).ready(function($) {
    $('.addon-toggle').on('change', function() {
        var $checkbox = $(this);
        var addon = $checkbox.data('addon');
        var previousState = !$checkbox.is(':checked');

        $checkbox.prop('disabled', true);

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
                    location.reload();
                    return;
                }

                $checkbox.prop('checked', previousState);
                $('#addon-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            },
            error: function() {
                $checkbox.prop('checked', previousState);
                $('#addon-message').html('<div class="notice notice-error"><p>Error de conexión. Inténtalo de nuevo.</p></div>').show();
            },
            complete: function() {
                $checkbox.prop('disabled', false);
            }
        });
    });
});
