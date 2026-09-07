(function () {
    'use strict';

    function schliessen(overlay, aufEscape) {
        overlay.remove();
        document.removeEventListener('keydown', aufEscape);
    }

    function oeffnen(quelle, altText) {
        var overlay = document.createElement('div');
        overlay.className = 'lightbox-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', altText || 'Foto vergrößert');

        var bild = document.createElement('img');
        bild.src = quelle;
        bild.alt = altText || '';
        overlay.appendChild(bild);

        function aufEscape(ereignis) {
            if (ereignis.key === 'Escape') {
                schliessen(overlay, aufEscape);
            }
        }

        overlay.addEventListener('click', function () {
            schliessen(overlay, aufEscape);
        });
        document.addEventListener('keydown', aufEscape);

        document.body.appendChild(overlay);
    }

    document.addEventListener('click', function (ereignis) {
        var ziel = ereignis.target.closest('.foto-zoombar');
        if (ziel && ziel.tagName === 'IMG') {
            oeffnen(ziel.src, ziel.alt);
        }
    });
})();
