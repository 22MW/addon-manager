/**
 * Addon Manager admin interactions.
 *
 * Handles addon upload and addon toggle requests.
 *
 * @param {jQuery} $ jQuery instance.
 * @returns {void}
 */
jQuery(document).ready(function($) {
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    function showMessage(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + noticeClass + ' is-dismissible" style="margin-top:12px;"><p>' + escapeHtml(message) + '</p></div>';
        $('#addon-message').html(html).show();
        $('#am-upload-message').html(html).show();
    }

    $('#am-user-addon-upload-form').on('submit', function(event) {
        event.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var formData = new FormData(this);

        formData.set('action', 'addon_manager_upload_user_addon');
        formData.set('nonce', $form.data('nonce'));

        $button.prop('disabled', true);
        $('#addon-message').html('<div class="notice notice-info" style="margin-top:12px;"><p>Subiendo addon...</p></div>').show();

        $.ajax({
            url: addonManager.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 15000,
            success: function(response) {
                var message = response && response.data && response.data.message ? response.data.message : 'Respuesta recibida.';

                if (response && response.success) {
                    showMessage('success', message);
                    window.setTimeout(function() {
                        window.location.reload();
                    }, 700);
                    return;
                }

                window.console && window.console.error && window.console.error('Addon upload failed:', response);
                alert(message);
                showMessage('error', message);
            },
            error: function(xhr, textStatus) {
                var message = 'Error de conexión. Inténtalo de nuevo.';

                if (textStatus === 'timeout') {
                    message = 'La subida tardó demasiado. Inténtalo de nuevo.';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    message = xhr.responseText.toString().substring(0, 180);
                }

                window.console && window.console.error && window.console.error('Addon upload ajax error:', {
                    status: xhr.status,
                    textStatus: textStatus,
                    responseText: xhr.responseText
                });
                alert(message);
                showMessage('error', message);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

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
                showMessage('error', message);
            },
            error: function() {
                $checkbox.prop('checked', previousState);
                showMessage('error', 'Error de conexión. Inténtalo de nuevo.');
            },
            complete: function() {
                $checkbox.prop('disabled', false);
            }
        });
    });
});
