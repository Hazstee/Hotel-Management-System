document.addEventListener('DOMContentLoaded', function () {
    var checkIn  = document.getElementById('check_in');
    var checkOut = document.getElementById('check_out');
    var type     = document.getElementById('room_type');
    var guests   = document.getElementById('guests');
    var navToggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');

    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            var isOpen = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!isOpen));
            nav.classList.toggle('is-open', !isOpen);
        });
    }

    if (type) {
        var requestedType = new URLSearchParams(window.location.search).get('room_type');
        if (requestedType === '1' || requestedType === '2' || requestedType === '3') {
            for (var i = 0; i < type.options.length; i++) {
                if (type.options[i].value === requestedType) {
                    type.selectedIndex = i;
                    break;
                }
            }
        }
    }

    if (!checkIn || !checkOut) {
        syncGuests();
        return;
    }

    function iso(d) {
        var m = d.getMonth() + 1, day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }

    function syncCheckOut() {
        if (!checkIn.value) { return; }
        var d = new Date(checkIn.value + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        checkOut.min = iso(d);
        if (checkOut.value && checkOut.value < checkOut.min) { checkOut.value = checkOut.min; }
    }

    function syncGuests() {
        if (!type || !guests) { return; }
        var opt = type.options[type.selectedIndex];
        var max = opt ? opt.getAttribute('data-max') : null;
        if (max) {
            guests.max = max;
            if (parseInt(guests.value, 10) > parseInt(max, 10)) { guests.value = max; }
        }
    }

    checkIn.addEventListener('change', syncCheckOut);
    if (type) { type.addEventListener('change', syncGuests); }
    syncCheckOut();
    syncGuests();
});
