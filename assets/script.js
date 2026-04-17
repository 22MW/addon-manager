/**
 * Addon Manager admin interactions.
 *
 * Handles addon toggle requests and redirects to preserve server-side notices.
 *
 * @param {jQuery} $ jQuery instance.
 * @returns {void}
 */
jQuery(document).ready(function($) {
    // La subida de addons de usuario se maneja por admin-post (backend),
    // para asegurar avisos consistentes incluso en entornos locales.

    /**
     * Toggle addon activation state from the admin cards.
     *
     * @returns {void}
     */
    $('.addon-toggle').on('change', function() {
        var $checkbox = $(this);
        var addon = $checkbox.data('addon');
        var previousState = !$checkbox.is(':checked');
        var currentTab = new URLSearchParams(window.location.search).get('tab') || 'wp';

        $checkbox.prop('disabled', true);

        $.ajax({
            url: addonManager.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_addon',
                addon: addon,
                nonce: addonManager.nonce,
                current_tab: currentTab
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                        return;
                    }
                    location.reload();
                    return;
                }

                $checkbox.prop('checked', previousState);
                if (response.data && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                    return;
                }
                var message = response.data && response.data.message ? response.data.message : response.data;
                $('#addon-message').html('<div class="notice notice-error"><p>' + message + '</p></div>').show();
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
