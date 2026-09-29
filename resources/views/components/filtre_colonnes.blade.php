{{--
    Filtre par colonne, instantané.

    Chaque colonne du tableau a son champ : une saisie pour les numéros et les
    libellés, une liste déroulante pour les valeurs répétées (type, catégorie…).
    Le tableau se filtre à chaque frappe et à chaque choix, sans bouton.

    Les lignes portent leurs valeurs dans des attributs « data-f-<clé> ». Les
    listes se remplissent toutes seules avec les valeurs présentes dans le tableau.

    Variables :
      $corps   — sélecteur du <tbody> filtré
      $filtres — liste de ['cle', 'libelle', 'type' => 'texte'|'liste', 'mode' => 'debut'|'contient',
                           'serveur' => '<nom du paramètre>']
      $nom     — ce que comptent les lignes (« comptes », « tiers »…)

    Un filtre marqué « serveur » ne filtre pas l'écran : il recharge la page avec
    son paramètre. C'est indispensable sur un tableau paginé, où l'écran ne porte
    qu'une page : chercher « DC » ne devait plus répondre « 0 / 20 » parce que le
    dossier se trouve page 2.
--}}
@php
    $idFiltre = 'filtre_' . substr(md5($corps), 0, 8);
    $nom = $nom ?? 'lignes';
@endphp

<style>
    #{{ $idFiltre }} {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.75rem;
        padding: 1rem 2rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        align-items: end;
    }
    #{{ $idFiltre }} label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        margin-bottom: 0.3rem;
    }
    #{{ $idFiltre }} .form-control,
    #{{ $idFiltre }} .form-select {
        border-radius: 10px;
        border-color: #e2e8f0;
        font-size: 0.85rem;
        height: 38px;
    }
    #{{ $idFiltre }} .filtre-actif { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15); }
    #{{ $idFiltre }} .filtre-pied {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        height: 38px;
    }
    #{{ $idFiltre }} .filtre-compte { font-size: 0.8rem; font-weight: 700; color: #334155; white-space: nowrap; }
    #{{ $idFiltre }} .filtre-effacer {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0 0.8rem;
        height: 38px;
        white-space: nowrap;
    }
    #{{ $idFiltre }} .filtre-effacer:hover { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .filtre-aucun td { text-align: center; color: #94a3b8; font-weight: 600; padding: 2rem !important; }

    /* ─── Liste deroulante avec recherche ─── */
    #{{ $idFiltre }} .liste-cherchable { position: relative; }
    #{{ $idFiltre }} .liste-bouton {
        text-align: left;
        width: 100%;
        background-color: #fff;
        cursor: pointer;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #{{ $idFiltre }} .liste-volet {
        position: absolute;
        z-index: 1200;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        min-width: 220px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16);
        padding: 0.5rem;
    }
    #{{ $idFiltre }} .liste-volet[hidden] { display: none; }
    #{{ $idFiltre }} .liste-recherche { height: 32px; font-size: 0.8rem; margin-bottom: 0.4rem; }
    #{{ $idFiltre }} .liste-options { max-height: 220px; overflow-y: auto; }
    #{{ $idFiltre }} .liste-option {
        display: block;
        width: 100%;
        text-align: left;
        border: 0;
        background: transparent;
        border-radius: 8px;
        padding: 0.35rem 0.55rem;
        font-size: 0.82rem;
        color: #334155;
    }
    #{{ $idFiltre }} .liste-option:hover { background: #eff6ff; }
    #{{ $idFiltre }} .liste-option.choisie { background: #2563eb; color: #fff; font-weight: 700; }
    #{{ $idFiltre }} .liste-vide { padding: 0.5rem; font-size: 0.78rem; color: #94a3b8; text-align: center; }
</style>

<div id="{{ $idFiltre }}">
    @foreach($filtres as $filtre)
        <div>
            <label for="{{ $idFiltre }}_{{ $filtre['cle'] }}">{{ $filtre['libelle'] }}</label>
            @if(($filtre['type'] ?? 'texte') === 'liste')
                {{-- Liste avec sa propre recherche : au-dela de quelques dizaines de
                     valeurs, derouler et chercher a l'oeil ne tient plus.
                     « no-search » : la mise en forme Select2 de l'application ne doit
                     pas remplacer cette liste, sinon le choix n'arrive jamais au filtre. --}}
                <div class="liste-cherchable" data-pour="{{ $idFiltre }}_{{ $filtre['cle'] }}">
                    <button type="button" class="form-select liste-bouton" aria-haspopup="listbox" aria-expanded="false">
                        <span class="liste-valeur">Tous</span>
                    </button>
                    <div class="liste-volet" role="listbox" hidden>
                        <input type="text" class="form-control liste-recherche" autocomplete="off" placeholder="Rechercher…">
                        <div class="liste-options"></div>
                    </div>
                    <select id="{{ $idFiltre }}_{{ $filtre['cle'] }}" class="no-search liste-source" data-filtre="{{ $filtre['cle'] }}" data-type="liste" hidden>
                        <option value="">Tous</option>
                    </select>
                </div>
            @else
                <input type="text" id="{{ $idFiltre }}_{{ $filtre['cle'] }}" class="form-control" autocomplete="off"
                       data-filtre="{{ $filtre['cle'] }}" data-type="texte" data-mode="{{ $filtre['mode'] ?? 'contient' }}"
                       @if(!empty($filtre['serveur'])) data-serveur="{{ $filtre['serveur'] }}"
                           value="{{ request($filtre['serveur']) }}" @endif
                       placeholder="{{ !empty($filtre['serveur']) ? 'Chercher partout…' : ((($filtre['mode'] ?? 'contient') === 'debut') ? 'Commence par…' : 'Contient…') }}">
            @endif
        </div>
    @endforeach
    <div class="filtre-pied">
        <span class="filtre-compte" id="{{ $idFiltre }}__compteur"></span>
        <button type="button" class="filtre-effacer" id="{{ $idFiltre }}__effacer">
            <i class="fa-solid fa-eraser me-1"></i> Effacer
        </button>
    </div>
</div>

<script>
// La barre est posée au-dessus du tableau : on attend que les lignes existent.
document.addEventListener('DOMContentLoaded', function () {
    const barre = document.getElementById(@json($idFiltre));
    const corps = document.querySelector(@json($corps));
    if (!barre || !corps) return;

    const champs = Array.from(barre.querySelectorAll('[data-filtre]'));
    const compte = document.getElementById(@json($idFiltre) + '__compteur');
    const nom = @json($nom);
    const colonnes = corps.closest('table')?.querySelectorAll('thead th').length || 1;

    const normaliser = v => (v || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const cleDe = c => 'f' + c.dataset.filtre.charAt(0).toUpperCase() + c.dataset.filtre.slice(1);

    // Les valeurs de chaque ligne sont lues une seule fois : à la frappe, on ne
    // fait plus que comparer des chaînes déjà prêtes.
    const lignes = Array.from(corps.querySelectorAll('tr')).map(function (tr) {
        const valeurs = {};
        champs.forEach(c => { valeurs[c.dataset.filtre] = normaliser(tr.dataset[cleDe(c)]); });
        return { tr: tr, valeurs: valeurs, visible: true };
    });

    // Les listes proposent « Tous » puis les valeurs présentes dans le tableau.
    champs.filter(c => c.dataset.type === 'liste').forEach(function (liste) {
        const cle = cleDe(liste);
        const valeurs = [...new Set(lignes.map(l => (l.tr.dataset[cle] || '').trim()).filter(Boolean))]
            .sort((a, b) => a.localeCompare(b, 'fr', { numeric: true }));
        valeurs.forEach(v => liste.add(new Option(v, v)));
    });

    let aucun = null;

    function filtrer() {
        const utiles = [];
        champs.filter(c => !c.dataset.serveur).forEach(function (c) {
            const valeur = normaliser(c.value);
            c.classList.toggle('filtre-actif', valeur !== '');
            if (valeur !== '') utiles.push({ cle: c.dataset.filtre, type: c.dataset.type, mode: c.dataset.mode, valeur: valeur });
        });

        let visibles = 0;
        for (const ligne of lignes) {
            let garde = true;
            for (const a of utiles) {
                const cellule = ligne.valeurs[a.cle];
                garde = a.type === 'liste' ? cellule === a.valeur
                    : (a.mode === 'debut' ? cellule.startsWith(a.valeur) : cellule.includes(a.valeur));
                if (!garde) break;
            }
            // On ne touche la ligne que si son état change : pas de travail inutile.
            if (garde !== ligne.visible) {
                ligne.tr.style.display = garde ? '' : 'none';
                ligne.visible = garde;
            }
            if (garde) visibles++;
        }

        if (compte) {
            compte.textContent = utiles.length
                ? visibles + ' / ' + lignes.length + ' ' + nom
                : lignes.length + ' ' + nom;
        }

        if (!visibles && lignes.length) {
            if (!aucun) {
                aucun = document.createElement('tr');
                aucun.className = 'filtre-aucun';
                aucun.innerHTML = '<td colspan="' + colonnes + '">Aucune ligne ne correspond aux filtres.</td>';
            }
            corps.appendChild(aucun);
        } else if (aucun && aucun.parentNode) {
            aucun.remove();
        }
    }

    // Un champ « serveur » cherche dans tout le tableau, pas seulement dans la
    // page affichée : il recharge avec son paramètre. On attend la fin de la
    // frappe pour ne pas recharger à chaque lettre.
    const champsServeur = champs.filter(c => c.dataset.serveur);

    function rechercherAuServeur() {
        const url = new URL(window.location.href);
        champsServeur.forEach(function (c) {
            const valeur = c.value.trim();
            if (valeur) { url.searchParams.set(c.dataset.serveur, valeur); }
            else { url.searchParams.delete(c.dataset.serveur); }
        });
        url.searchParams.delete('page');   // une nouvelle recherche repart de la première page
        window.location.href = url.toString();
    }

    let minuterieServeur = null;
    champsServeur.forEach(function (c) {
        c.classList.toggle('filtre-actif', c.value.trim() !== '');
        c.addEventListener('input', function () {
            clearTimeout(minuterieServeur);
            minuterieServeur = setTimeout(rechercherAuServeur, 450);
        });
        c.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(minuterieServeur); rechercherAuServeur(); }
        });
    });

    // Filtrage immédiat, à chaque frappe comme à chaque choix dans une liste.
    champs.filter(c => !c.dataset.serveur).forEach(function (c) {
        c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrer);
    });

    // ── Listes déroulantes : chercher dans la liste elle-même ──
    barre.querySelectorAll('.liste-cherchable').forEach(function (bloc) {
        const source = bloc.querySelector('.liste-source');
        const bouton = bloc.querySelector('.liste-bouton');
        const etiquette = bloc.querySelector('.liste-valeur');
        const volet = bloc.querySelector('.liste-volet');
        const recherche = bloc.querySelector('.liste-recherche');
        const options = bloc.querySelector('.liste-options');

        function dessiner() {
            const cherche = normaliser(recherche.value);
            const trouvees = Array.from(source.options)
                .filter(o => !cherche || normaliser(o.text).includes(cherche));

            options.innerHTML = '';
            if (!trouvees.length) {
                options.innerHTML = '<div class="liste-vide">Aucune valeur ne correspond.</div>';
                return;
            }

            trouvees.forEach(function (o) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'liste-option' + (o.value === source.value ? ' choisie' : '');
                b.textContent = o.text;
                b.addEventListener('click', function () {
                    source.value = o.value;
                    etiquette.textContent = o.text;
                    bouton.classList.toggle('filtre-actif', o.value !== '');
                    fermer();
                    filtrer();
                });
                options.appendChild(b);
            });
        }

        function ouvrir() {
            volet.hidden = false;
            bouton.setAttribute('aria-expanded', 'true');
            recherche.value = '';
            dessiner();
            recherche.focus();
        }

        function fermer() {
            volet.hidden = true;
            bouton.setAttribute('aria-expanded', 'false');
        }

        bouton.addEventListener('click', function (e) {
            e.stopPropagation();
            volet.hidden ? ouvrir() : fermer();
        });
        recherche.addEventListener('input', dessiner);
        recherche.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { fermer(); bouton.focus(); }
            if (e.key === 'Enter') { e.preventDefault(); options.querySelector('.liste-option')?.click(); }
        });
        volet.addEventListener('click', e => e.stopPropagation());
        document.addEventListener('click', fermer);

        // Le bouton « Effacer » remet la liste a « Tous ».
        source.addEventListener('change', function () {
            const choisie = source.options[source.selectedIndex];
            etiquette.textContent = choisie ? choisie.text : 'Tous';
            bouton.classList.toggle('filtre-actif', source.value !== '');
        });
    });

    document.getElementById(@json($idFiltre) + '__effacer').addEventListener('click', function () {
        champs.forEach(function (c) {
            if (c.tagName === 'SELECT') {
                c.selectedIndex = 0;
                c.dispatchEvent(new Event('change'));
            } else {
                c.value = '';
            }
        });
        filtrer();
        if (champsServeur.some(c => new URL(window.location.href).searchParams.has(c.dataset.serveur))) {
            rechercherAuServeur();
            return;
        }
        champs[0]?.focus();
    });

    filtrer();
});
</script>
