/**
 * Tainacan Journal Manager — Frontend
 */
(function ($) {
    'use strict';

    var config = window.tjmFrontend || {};

    $(document).ready(function () {
        bindLogin();
    });

    function bindLogin() {
        $('#tjm-login-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $msg  = $('#tjm-login-message');

            $msg.removeClass('tjm-message--error tjm-message--success').hide();

            $.post(config.ajaxUrl, {
                action: 'tjm_login',
                nonce: config.nonce,
                username: $form.find('[name="username"]').val(),
                password: $form.find('[name="password"]').val(),
                redirect_to: $form.find('[name="redirect_to"]').val()
            }).done(function (res) {
                if (res.success && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    $msg.addClass('tjm-message--error').text(res.data || config.i18n.error).show();
                }
            }).fail(function () {
                $msg.addClass('tjm-message--error').text(config.i18n.error).show();
            });
        });
    }

})(jQuery);
