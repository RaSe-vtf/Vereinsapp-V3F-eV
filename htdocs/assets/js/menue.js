document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.menu-toggle').forEach(function (knopf) {
        var menue = document.getElementById(knopf.getAttribute('aria-controls'));
        if (!menue) {
            return;
        }

        function schliessen() {
            menue.hidden = true;
            knopf.setAttribute('aria-expanded', 'false');
        }

        function oeffnen() {
            menue.hidden = false;
            knopf.setAttribute('aria-expanded', 'true');
        }

        knopf.addEventListener('click', function (ev) {
            ev.stopPropagation();
            if (menue.hidden) {
                oeffnen();
            } else {
                schliessen();
            }
        });

        document.addEventListener('click', function (ev) {
            if (!menue.hidden && !menue.contains(ev.target) && ev.target !== knopf) {
                schliessen();
            }
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !menue.hidden) {
                schliessen();
                knopf.focus();
            }
        });
    });
});
