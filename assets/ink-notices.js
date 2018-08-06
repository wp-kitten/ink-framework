jQuery(function ($) {
    "use strict";

    if (typeof(window.InkFw) === 'undefined') {
        return;
    }
    var locale = window.InkFw;
    var noticeDismissButtons = $('.js-ink-notice-dismiss');
    if (noticeDismissButtons && noticeDismissButtons.length > 0) {
        noticeDismissButtons.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var self = $(this),
                hash = '',
                type = '';
            if (typeof(self.attr('data-hash')) !== 'undefined' && typeof(self.attr('data-type')) !== 'undefined') {
                hash = self.attr('data-hash');
                type = self.attr('data-type');
                var nonceName = locale[type]['nonce_name'],
                    ajaxConfig = {
                        url: ajaxurl,
                        type: 'POST',
                        timeout: 5000,
                        cache: false,
                        data: {
                            action: 'ink_check_delete_notice',
                            ink_notice: hash,
                            type: type
                        }
                    };
                ajaxConfig.data[nonceName] = locale[type]['nonce'];

                $.ajax(ajaxConfig).done(function (r) {
                    if (r) {
                        if (r.success) {
                            var notice = self.parents('.ink-notice').first();
                            if (notice) {
                                notice.fadeOut('fast');
                                notice.remove();
                            }
                        }
                    }
                }).fail(function (x, s, e) {
                    alert(e);
                });
            }
            return false;
        });
    }
});
