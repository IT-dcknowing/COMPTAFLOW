{{--
    Filtre par colonne, instantané.

    Chaque colonne du tableau a son champ : une saisie pour les numéros et les
    libellés, une liste déroulante pour les valeurs répétées (type, catégorie…).
    Le tableau se filtre à chaque frappe et à chaque choix, sans bouton.

    Les lignes portent leurs valeurs dans des attributs « data-f-<clé> ». Les
    listes se remplissent toutes seules avec les valeurs présentes dans le tableau.

    Variables :
      $corps   — sélecteur du <tbody> filtré
      $filtres — liste de ['cle', 'libelle', 'type' => 'texte'|'liste', 'mode' => 'debut'|'contient']
      $nom     — ce que comptent les lignes (« comptes », « tiers »…)
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
</style>

<div id="{{ $idFiltre }}">
    @foreach($filtres as $filtre)
        <div>
            <label for="{{ $idFiltre }}_{{ $filtre['cle'] }}">{{ $filtre['libelle'] }}</label>
            @if(($filtre['type'] ?? 'texte') === 'liste')
                {{-- « no-search » : la mise en forme Select2 de l'application ne doit pas
                     remplacer cette liste, sinon le choix n'arrive jamais au filtre. --}}
                <select id="{{ $idFiltre }}_{{ $filtre['cle'] }}" class="form-select no-search" data-filtre="{{ $filtre['cle'] }}" data-type="liste">
                    <option value="">Tous</option>
                </select>
            @else
                <input type="text" id="{{ $idFiltre }}_{{ $filtre['cle'] }}" class="form-control" autocomplete="off"
                       data-filtre="{{ $filtre['cle'] }}" data-type="texte" data-mode="{{ $filtre['mode'] ?? 'contient' }}"
                       placeholder="{{ ($filtre['mode'] ?? 'contient') === 'debut' ? 'Commence par…' : 'Contient…' }}">
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
        champs.forEach(function (c) {
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

    // Filtrage immédiat, à chaque frappe comme à chaque choix dans une liste.
    champs.forEach(function (c) {
        c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrer);
    });

    document.getElementById(@json($idFiltre) + '__effacer').addEventListener('click', function () {
        champs.forEach(function (c) {
            if (c.tagName === 'SELECT') { c.selectedIndex = 0; } else { c.value = ''; }
        });
        filtrer();
        champs[0]?.focus();
    });

    filtrer();
});
</script>
