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
                <select id="{{ $idFiltre }}_{{ $filtre['cle'] }}" class="form-select" data-filtre="{{ $filtre['cle'] }}" data-type="liste">
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
    const lignes = () => Array.from(corps.querySelectorAll('tr:not(.filtre-aucun)'));

    // Les listes proposent les valeurs réellement présentes dans le tableau.
    champs.filter(c => c.dataset.type === 'liste').forEach(function (liste) {
        const cle = 'f' + liste.dataset.filtre.charAt(0).toUpperCase() + liste.dataset.filtre.slice(1);
        const valeurs = [...new Set(lignes().map(l => (l.dataset[cle] || '').trim()).filter(Boolean))]
            .sort((a, b) => a.localeCompare(b, 'fr', { numeric: true }));
        valeurs.forEach(v => liste.add(new Option(v, v)));
    });

    let aucun = null;

    function filtrer() {
        const actifs = champs
            .map(c => ({
                cle: 'f' + c.dataset.filtre.charAt(0).toUpperCase() + c.dataset.filtre.slice(1),
                type: c.dataset.type,
                mode: c.dataset.mode,
                valeur: normaliser(c.value),
                champ: c,
            }));
        actifs.forEach(a => a.champ.classList.toggle('filtre-actif', a.valeur !== ''));
        const utiles = actifs.filter(a => a.valeur !== '');

        let visibles = 0;
        const toutes = lignes();
        toutes.forEach(function (ligne) {
            const garde = utiles.every(function (a) {
                const cellule = normaliser(ligne.dataset[a.cle]);
                if (a.type === 'liste') return cellule === a.valeur;
                return a.mode === 'debut' ? cellule.startsWith(a.valeur) : cellule.includes(a.valeur);
            });
            ligne.style.display = garde ? '' : 'none';
            if (garde) visibles++;
        });

        if (compte) {
            compte.textContent = utiles.length
                ? visibles + ' / ' + toutes.length + ' ' + nom
                : toutes.length + ' ' + nom;
        }

        if (!visibles && toutes.length) {
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

    champs.forEach(c => c.addEventListener(c.dataset.type === 'liste' ? 'change' : 'input', filtrer));

    document.getElementById(@json($idFiltre) + '__effacer').addEventListener('click', function () {
        champs.forEach(c => { c.value = ''; });
        filtrer();
    });

    filtrer();
});
</script>
