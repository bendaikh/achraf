(function () {
    'use strict';

    window.changeTablePerPage = function (value) {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', value);
        url.searchParams.delete('page');
        var next = url.toString();
        if (window.SoftNav && typeof window.SoftNav.navigate === 'function') {
            window.SoftNav.navigate(next);
            return;
        }
        window.location.href = next;
    };
})();
