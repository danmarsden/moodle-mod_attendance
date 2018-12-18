define(['jquery'], function ($) {
    return {
        init: function () {

            var adjustHeight = function () {
                // attendance module username height adjust
                $('tr').each(function () {
                    var tr = this;
                    $(this).children('.left.headcol.cell').each(function () {
                        var maxHeight = 0;
                        $(this).children('*').each(function () {
                            var height = $(this).outerHeight(true);
                            if ($(this).height() > maxHeight) {
                                maxHeight = height;
                            }
                        });
                        $(tr).height(maxHeight+10);
                    });
                });
            };

            adjustHeight();
        }
    };
});