{{--
    Échelle d'affichage : à 100 % dans le navigateur, l'interface a la densité
    qu'elle avait à 75 % (application) ou 80 % (accueil, connexion, inscription).

    Variable : $echelle (0.75 par défaut).

    Le zoom CSS réduit aussi les unités d'écran : un bloc de « 100vh » ne
    couvre plus que 75 % de la hauteur, une largeur de « 100vw » que 75 % de
    la largeur. Les éléments pleine page (menu latéral, barre du haut, fond des
    fenêtres, aperçu plein écran…) reçoivent donc leurs dimensions corrigées,
    via --vw100 et --vh100.

    Les listes Select2 et les calendriers sont placés par calcul à partir de la
    position affichée ; sous zoom, ce calcul serait réduit une seconde fois et
    la liste s'ouvrirait à côté du champ. On corrige leur position après coup.

    En dessous de 1200 px de large (tablette, téléphone), rien ne change.
--}}
@php $echelle = $echelle ?? 0.75; @endphp
<style>
    :root { --echelle-app: 1; --vw100: 100vw; --vh100: 100vh; }

    @media (min-width: 1200px) {
        :root {
            zoom: {{ $echelle }};
            --echelle-app: {{ $echelle }};
            --vw100: calc(100vw / {{ $echelle }});
            --vh100: calc(100vh / {{ $echelle }});
            width: var(--vw100) !important;
            min-height: var(--vh100) !important;
        }

        html body,
        html body .layout-wrapper,
        html body .layout-container {
            width: var(--vw100) !important;
            max-width: var(--vw100) !important;
            min-height: var(--vh100) !important;
        }

        html body .navbar-horizontal { width: var(--vw100) !important; }

        html body .layout-page {
            width: calc(var(--vw100) - 288px) !important;
            max-width: calc(var(--vw100) - 288px) !important;
            min-height: var(--vh100) !important;
        }
        html body:has(.navbar-horizontal) .layout-page { min-height: calc(var(--vh100) - 70px) !important; }
        html body.sidebar-collapsed .layout-page {
            width: var(--vw100) !important;
            max-width: var(--vw100) !important;
        }
        html body .content-wrapper { min-height: var(--vh100) !important; }

        html body .sidebar-new { height: var(--vh100) !important; }
        html body:has(.navbar-horizontal) .sidebar-new { height: calc(var(--vh100) - 70px) !important; }
        html body .sidebar-nav {
            height: calc(var(--vh100) - 80px) !important;
            max-height: calc(var(--vh100) - 80px) !important;
        }

        html body .modal-backdrop,
        html body .offcanvas-backdrop,
        html body .content-backdrop {
            width: var(--vw100) !important;
            height: var(--vh100) !important;
        }
        /* À l'ouverture d'une fenêtre, Bootstrap compense la barre de défilement
           en mesurant « largeur de l'écran − largeur du document ». Sous zoom,
           l'écart n'est pas une barre mais le zoom lui-même (450 px sur un écran
           de 1366) : la fenêtre et la page étaient poussées vers la gauche. */
        html body.modal-open,
        html body .modal,
        html body .fixed-top,
        html body .fixed-bottom,
        html body .sticky-top,
        html body .navbar-horizontal {
            padding-right: 0 !important;
        }
        html body .modal { padding-left: 0 !important; }

        /* Couches fixes pleine page : fenêtres, alertes. */
        html body .modal,
        html body:not(.swal2-toast-shown) .swal2-container {
            width: var(--vw100) !important;
            height: var(--vh100) !important;
        }
        html body .modal-fullscreen,
        html body .vw-100 { width: var(--vw100) !important; }
        html body .min-vw-100 { min-width: var(--vw100) !important; }
        html body .vh-100 { height: var(--vh100) !important; }
        html body .min-vh-100 { min-height: var(--vh100) !important; }
    }
</style>

<script>
(function () {
    function echelle() {
        const z = parseFloat(getComputedStyle(document.documentElement).zoom);
        return z > 0 && z < 1 ? z : 1;
    }

    // Recale une liste flottante sur son champ, en mesurant les positions
    // réellement affichées : la liste se colle sous le champ (ou au-dessus
    // quand elle s'ouvre vers le haut), à la même gauche et à la même largeur.
    function recalerSur(liste, champ, auDessus) {
        const z = echelle();
        if (z === 1 || !liste || !champ) return;
        const c = champ.getBoundingClientRect();
        let l = liste.getBoundingClientRect();
        if (l.width && c.width) {
            const largeur = parseFloat(liste.style.width);
            if (!isNaN(largeur)) liste.style.width = (largeur * c.width / l.width) + 'px';
            l = liste.getBoundingClientRect();
        }
        const haut = parseFloat(liste.style.top) || 0;
        const gauche = parseFloat(liste.style.left) || 0;
        const cibleHaut = auDessus ? c.top - l.height : c.bottom;
        liste.style.top = (haut + (cibleHaut - l.top) / z) + 'px';
        liste.style.left = (gauche + (c.left - l.left) / z) + 'px';
    }

    function patcherSelect2() {
        const $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2 || !$.fn.select2.amd) return false;
        const AttachBody = $.fn.select2.amd.require('select2/dropdown/attachBody');
        if (AttachBody.prototype.__echelleApp) return true;
        const placer = AttachBody.prototype._positionDropdown;
        AttachBody.prototype._positionDropdown = function () {
            placer.apply(this, arguments);
            const dessus = this.$dropdown && this.$dropdown.hasClass('select2-dropdown--above');
            recalerSur(this.$dropdownContainer && this.$dropdownContainer[0], this.$container && this.$container[0], dessus);
        };
        const dimensionner = AttachBody.prototype._resizeDropdown;
        AttachBody.prototype._resizeDropdown = function () {
            dimensionner.apply(this, arguments);
            const dessus = this.$dropdown && this.$dropdown.hasClass('select2-dropdown--above');
            recalerSur(this.$dropdownContainer && this.$dropdownContainer[0], this.$container && this.$container[0], dessus);
        };
        AttachBody.prototype.__echelleApp = true;
        return true;
    }

    function patcherFlatpickr() {
        if (!window.flatpickr || !window.flatpickr.defaultConfig || window.flatpickr.__echelleApp) return !!(window.flatpickr && window.flatpickr.__echelleApp);
        const hooks = [].concat(window.flatpickr.defaultConfig.onOpen || []);
        hooks.push(function (dates, texte, instance) {
            if (instance.config.static || instance.config.inline) return;
            // Le calendrier se place juste après cet évènement : on recale ensuite.
            setTimeout(function () {
                recalerSur(instance.calendarContainer, instance.altInput || instance.input, instance.calendarContainer.classList.contains('arrowBottom'));
            }, 0);
        });
        window.flatpickr.defaultConfig.onOpen = hooks;
        window.flatpickr.__echelleApp = true;
        return true;
    }

    // Les bibliothèques arrivent parfois après ce script (chargement différé) :
    // on réessaie jusqu'à la fin du chargement de la page.
    function essayer() { patcherSelect2(); patcherFlatpickr(); }
    essayer();
    document.addEventListener('DOMContentLoaded', essayer);
    window.addEventListener('load', essayer);
})();
</script>
