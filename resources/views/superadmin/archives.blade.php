<!doctype html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

@include('components.head')

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('components.sidebar')

        <div class="layout-page">
            @include('components.header', ['page_title' => 'Archive des suppressions'])

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">
<div class="container-fluid px-4 py-4">

    <div class="mb-4">
        <h1 class="h3 fw-bolder text-slate-900 mb-1">Archive des <span class="text-primary">suppressions</span></h1>
        <p class="text-muted mb-0" style="font-size:.85rem;">
            Tout ce qui a été supprimé dans l'application, tous dossiers confondus, gardé
            {{ $stats['retention'] }} jours. Les dossiers eux-mêmes supprimés figurent ici : c'est le seul
            endroit où leurs archives restent joignables.
            <br>
            <strong>Pour récupérer</strong> : le bouton <i class="fa-solid fa-rotate-left"></i> remet la ligne
            en place sous son identifiant d'origine, et propose de remettre tout le lot si la suppression était
            groupée. Ce qui a été supprimé <em>avant</em> la mise en place de l'archive n'y figure pas.
        </p>
    </div>

    {{-- Les quatre chiffres qui disent l'état de l'archive --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Lignes conservées', $stats['total'], 'fa-box-archive', '#1e40af'],
            ['Opérations', $stats['lots'], 'fa-layer-group', '#0f766e'],
            ['Expirent sous 7 jours', $stats['expire_7j'], 'fa-hourglass-half', '#b45309'],
            ['Dossiers supprimés', $stats['orphelines'], 'fa-building-circle-xmark', '#b91c1c'],
            ['Remises en place', $stats['remises'], 'fa-rotate-left', '#15803d'],
        ] as [$titre, $valeur, $icone, $couleur])
        <div class="col-6 col-lg">
            <div class="p-3" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid {{ $icone }}" style="color:{{ $couleur }};"></i>
                    <span class="text-uppercase" style="font-size:.62rem;font-weight:800;letter-spacing:.05em;color:#64748b;">{{ $titre }}</span>
                </div>
                <div class="fw-bolder" style="font-size:1.6rem;color:{{ $couleur }};">
                    {{ number_format($valeur, 0, ',', ' ') }}
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">

        <form method="GET" class="p-3 d-flex flex-wrap gap-2 align-items-end"
              style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <div>
                <label class="d-block text-uppercase" style="font-size:.62rem;font-weight:800;color:#64748b;">Dossier</label>
                <select name="company_id" class="form-select form-select-sm" style="min-width:230px;border-radius:10px;">
                    <option value="">Tous les dossiers</option>
                    @foreach($parDossier as $d)
                        <option value="{{ $d['company_id'] }}" @selected(request('company_id') == $d['company_id'])>
                            {{ $d['nom'] ?? 'Dossier supprimé (#' . $d['company_id'] . ')' }} — {{ $d['lignes'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="d-block text-uppercase" style="font-size:.62rem;font-weight:800;color:#64748b;">Type</label>
                <select name="module" class="form-select form-select-sm" style="min-width:170px;border-radius:10px;">
                    <option value="">Tous les types</option>
                    @foreach($modules as $m)
                        <option value="{{ $m }}" @selected(request('module') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-grow-1" style="max-width:280px;">
                <label class="d-block text-uppercase" style="font-size:.62rem;font-weight:800;color:#64748b;">Libellé</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                       style="border-radius:10px;" placeholder="Chercher dans tout l'historique…">
            </div>
            <div class="form-check mb-2 ms-1">
                <input class="form-check-input" type="checkbox" name="orphelines" value="1" id="orphelines"
                       @checked(request()->boolean('orphelines'))>
                <label class="form-check-label" for="orphelines" style="font-size:.78rem;">Dossiers supprimés seulement</label>
            </div>
            <button class="btn btn-primary btn-sm" style="border-radius:10px;padding:.4rem 1rem;font-weight:700;">
                <i class="fa-solid fa-filter me-1"></i>Filtrer
            </button>
            <a href="{{ route('superadmin.archives') }}" class="btn btn-light btn-sm" style="border-radius:10px;">Effacer</a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead style="background:#f8fafc;">
                    <tr class="text-uppercase" style="font-size:.62rem;font-weight:800;letter-spacing:.05em;color:#64748b;">
                        <th class="px-4 py-3">Supprimé le</th>
                        <th class="px-4 py-3">Dossier</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Libellé</th>
                        <th class="px-4 py-3">Par</th>
                        <th class="px-4 py-3">Expire le</th>
                        <th class="px-4 py-3 text-end">Contenu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($archives as $a)
                    <tr>
                        <td class="px-4 py-3" style="font-size:.82rem;white-space:nowrap;">
                            {{ optional($a->deleted_at)->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3" style="font-size:.82rem;">
                            @if(isset($vivantes[$a->company_id]))
                                {{ $vivantes[$a->company_id] }}
                            @elseif(isset($enCorbeille[$a->company_id]))
                                {{ $enCorbeille[$a->company_id] }}
                                <span class="d-block" style="color:#b45309;font-weight:700;font-size:.7rem;">en corbeille</span>
                            @else
                                <span style="color:#b91c1c;font-weight:700;">Dossier supprimé</span>
                                <span class="text-muted">(#{{ $a->company_id }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1" style="background:#eff6ff;color:#1d4ed8;border-radius:6px;font-size:.68rem;font-weight:700;">
                                {{ class_basename($a->model_type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3" style="font-size:.82rem;">{{ $a->label }}</td>
                        <td class="px-4 py-3" style="font-size:.8rem;color:#64748b;">
                            {{ trim(($a->user->name ?? '') . ' ' . ($a->user->last_name ?? '')) ?: 'Inconnu' }}
                        </td>
                        <td class="px-4 py-3" style="font-size:.8rem;color:#64748b;white-space:nowrap;">
                            {{ optional($a->expires_at)->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-end">
                            @if($a->restored_at)
                                <span style="color:#15803d;font-size:.72rem;font-weight:700;" title="Remise en place le {{ $a->restored_at->format('d/m/Y H:i') }}">
                                    <i class="fa-solid fa-rotate-left me-1"></i>remise en place
                                </span>
                            @else
                            <button type="button" class="btn btn-sm btn-light remettre-archive"
                                    data-apercu="{{ route('superadmin.archives.apercu_restauration', $a->id) }}"
                                    data-libelle="{{ $a->label }}"
                                    data-action="{{ route('superadmin.archives.restaurer', $a->id) }}"
                                    data-lot="{{ $a->batch_id }}"
                                    style="border-radius:8px;" title="Remettre en place">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            @endif
                            <button type="button" class="btn btn-sm btn-light voir-archive"
                                    data-url="{{ route('superadmin.archives.show', $a->id) }}"
                                    style="border-radius:8px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted" style="font-size:.85rem;">
                            Aucune suppression conservée pour ces critères.
                            @if($stats['total'] > 0)
                                <div class="mt-2">
                                    L'archive contient pourtant {{ number_format($stats['total'], 0, ',', ' ') }} ligne(s) :
                                    ce sont les filtres qui les écartent.
                                    <a href="{{ route('superadmin.archives') }}" class="fw-bold">Les voir toutes</a>.
                                </div>
                            @else
                                <div class="mt-2">
                                    Rien n'a été supprimé depuis {{ $stats['retention'] }} jours — ou tout a déjà
                                    été purgé. Ce qui a été supprimé AVANT la mise en place de l'archive n'y figure
                                    pas : il n'en reste aucune trace à remettre.
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($archives->hasPages())
        <div class="px-4 py-3" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
            {{ $archives->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Le contenu complet d'une ligne supprimée --}}
<div class="modal fade" id="modaleArchive" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border:0;border-radius:18px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bolder" id="archiveTitre">Donnée supprimée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="archiveEntete" class="mb-3" style="font-size:.8rem;color:#64748b;"></div>
                <pre id="archiveContenu" style="background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:12px;
                     font-size:.75rem;max-height:52vh;overflow:auto;margin:0;"></pre>
            </div>
        </div>
    </div>
</div>

{{-- Remettre en place : on annonce ce qui va revenir avant de l'écrire --}}
<div class="modal fade" id="modaleRemise" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:18px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bolder">Remettre en place</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="fw-bold mb-2" id="remiseLibelle" style="font-size:.9rem;color:#0f172a;"></div>
                <div id="remiseRaison" class="p-3 mb-3" style="background:#f8fafc;border-radius:12px;font-size:.8rem;color:#334155;"></div>

                <div id="remiseLot" class="d-none p-3 mb-3" style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;font-size:.8rem;color:#92400e;">
                    Cette ligne fait partie d'une suppression groupée de
                    <strong id="remiseLotNombre">0</strong> ligne(s) encore absente(s).
                    Les remettre ensemble rend la suppression telle qu'elle était.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:10px;">Annuler</button>

                <form method="POST" id="remiseLotFormulaire" action="{{ route('superadmin.archives.restaurer_lot') }}" class="d-none">
                    @csrf
                    <input type="hidden" name="batch_id" id="remiseLotId">
                    <button class="btn btn-warning" style="border-radius:10px;font-weight:700;">
                        <i class="fa-solid fa-layer-group me-1"></i>Remettre tout le lot
                    </button>
                </form>

                <form method="POST" id="remiseFormulaire">
                    @csrf
                    <button class="btn btn-primary" id="remiseValider" style="border-radius:10px;font-weight:700;">
                        <i class="fa-solid fa-rotate-left me-1"></i>Remettre cette ligne
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const cadre = document.getElementById('modaleRemise');
    if (!cadre || typeof bootstrap === 'undefined') return;

    const boite = new bootstrap.Modal(cadre);
    const libelle = document.getElementById('remiseLibelle');
    const raison = document.getElementById('remiseRaison');
    const bloc = document.getElementById('remiseLot');
    const nombre = document.getElementById('remiseLotNombre');
    const formulaire = document.getElementById('remiseFormulaire');
    const valider = document.getElementById('remiseValider');
    const lotFormulaire = document.getElementById('remiseLotFormulaire');
    const lotId = document.getElementById('remiseLotId');

    document.querySelectorAll('.remettre-archive').forEach(function (b) {
        b.addEventListener('click', function () {
            libelle.textContent = b.dataset.libelle || "";
            raison.textContent = "Vérification…";
            bloc.classList.add("d-none");
            lotFormulaire.classList.add("d-none");
            valider.disabled = true;
            formulaire.action = b.dataset.action;
            boite.show();

            fetch(b.dataset.apercu, { headers: { "Accept": "application/json" } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) {
                        raison.textContent = "Impossible de vérifier : réessayez.";
                        return;
                    }

                    raison.textContent = d.raison || "";
                    valider.disabled = !d.possible;

                    if (d.lot && d.lignes_du_lot > 1) {
                        nombre.textContent = d.lignes_du_lot;
                        bloc.classList.remove("d-none");
                        lotId.value = d.lot;
                        lotFormulaire.classList.remove("d-none");
                    }
                })
                .catch(function () { raison.textContent = "Impossible de vérifier : réessayez."; });
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fenetre = document.getElementById('modaleArchive');
    if (!fenetre || typeof bootstrap === 'undefined') return;

    const boite = new bootstrap.Modal(fenetre);
    const titre = document.getElementById('archiveTitre');
    const entete = document.getElementById('archiveEntete');
    const contenu = document.getElementById('archiveContenu');

    document.querySelectorAll('.voir-archive').forEach(function (b) {
        b.addEventListener('click', function () {
            titre.textContent = 'Chargement…';
            entete.textContent = '';
            contenu.textContent = '';
            boite.show();

            fetch(b.dataset.url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) { titre.textContent = 'Contenu indisponible'; return; }
                    titre.textContent = d.libelle || d.modele;
                    entete.textContent = d.modele + ' · ' + d.entreprise
                        + ' · supprimé le ' + d.supprime_le + ' par ' + d.par
                        + ' · expire le ' + d.expire_le;
                    contenu.textContent = JSON.stringify(d.contenu, null, 2);
                })
                .catch(function () { titre.textContent = 'Contenu indisponible'; });
        });
    });
});
</script>

                </div>
                @include('components.footer')
            </div>
        </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
</div>
</body>
</html>
