<!doctype html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

@include('components.head')

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('components.sidebar')

        <div class="layout-page">
            @include('components.header', ['page_title' => 'Corbeille'])

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">

                    @if(session('success'))
                        <div class="alert alert-success" style="border-radius:12px;">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger" style="border-radius:12px;">{{ session('error') }}</div>
                    @endif

                    <div class="mb-4">
                        <h1 class="h3 fw-bolder text-slate-900 mb-1">
                            Corbeille des <span class="text-primary">entreprises</span>
                        </h1>
                        <p class="text-muted mb-0" style="font-size:.85rem;">
                            Une entreprise supprimée garde sa comptabilité intacte pendant
                            <strong>{{ \App\Models\Company::CORBEILLE_JOURS }} jours</strong>. Elle n'apparaît plus
                            nulle part dans l'application, mais rien n'est perdu : la remettre en place la restitue
                            avec ses écritures, ses exercices et son plan comptable.
                        </p>
                    </div>

                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background:#f8fafc;">
                                    <tr class="text-uppercase" style="font-size:.62rem;font-weight:800;letter-spacing:.05em;color:#64748b;">
                                        <th class="px-4 py-3">Entreprise</th>
                                        <th class="px-4 py-3">Supprimée le</th>
                                        <th class="px-4 py-3 text-center">Comptabilité</th>
                                        <th class="px-4 py-3">Effacement définitif</th>
                                        <th class="px-4 py-3 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($entreprises as $e)
                                    @php $c = $e['modele']; $urgent = $e['jours_restants'] <= 7; @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold text-slate-800">{{ $c->company_name }}</div>
                                            <div class="text-muted" style="font-size:.72rem;">
                                                {{ $c->juridique_form }} @if($c->company_code) · {{ $c->company_code }} @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3" style="font-size:.82rem;white-space:nowrap;">
                                            {{ $c->deleted_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 text-center" style="font-size:.8rem;">
                                            <span class="px-2 py-1" style="background:#eff6ff;color:#1d4ed8;border-radius:6px;font-weight:700;">
                                                {{ number_format($e['ecritures'], 0, ',', ' ') }} écriture(s)
                                            </span>
                                            <div class="text-muted mt-1" style="font-size:.7rem;">{{ $e['exercices'] }} exercice(s)</div>
                                        </td>
                                        <td class="px-4 py-3" style="font-size:.82rem;white-space:nowrap;">
                                            <span style="color:{{ $urgent ? '#b91c1c' : '#64748b' }};font-weight:{{ $urgent ? 800 : 500 }};">
                                                dans {{ $e['jours_restants'] }} jour(s)
                                            </span>
                                            <div class="text-muted" style="font-size:.7rem;">le {{ $e['expire_le']->format('d/m/Y') }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <form method="POST" action="{{ route('superadmin.corbeille.restaurer', $c->id) }}">
                                                    @csrf
                                                    <button class="btn btn-sm" style="background:#dcfce7;color:#166534;border-radius:8px;font-weight:700;">
                                                        <i class="fa-solid fa-rotate-left me-1"></i>Remettre en place
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('superadmin.corbeille.definitif', $c->id) }}"
                                                      class="form-effacement" data-nom="{{ $c->company_name }}"
                                                      data-ecritures="{{ $e['ecritures'] }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#b91c1c;border-radius:8px;font-weight:700;">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted" style="font-size:.85rem;">
                                            La corbeille est vide.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                @include('components.footer')
            </div>
        </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
</div>

{{-- L'effacement définitif, lui, ne se rattrape pas : on le confirme. --}}
<div class="modal fade" id="modaleEffacement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:20px;padding:2rem;">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:56px;height:56px;border-radius:16px;background:#fee2e2;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#b91c1c;font-size:1.4rem;"></i>
                </div>
                <h1 class="h4 fw-bolder text-slate-900 mb-0">
                    Effacer <span style="color:#b91c1c;" id="effNom"></span>
                </h1>
            </div>

            <div class="p-3 mb-4" style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;">
                <p class="mb-0" style="font-size:.8rem;color:#7f1d1d;line-height:1.6;">
                    <strong id="effEcritures">0</strong> écriture(s) partiront définitivement, avec les exercices,
                    les journaux et le plan comptable. <strong>Cette action ne se rattrape pas.</strong>
                    Les lignes resteront visibles 30 jours dans l'archive des suppressions, sans possibilité de
                    remettre l'entreprise en place.
                </p>
            </div>

            <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr;">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"
                        style="padding:.75rem;border-radius:12px;font-weight:700;color:#64748b;">Annuler</button>
                <button type="button" id="effConfirmer" class="btn"
                        style="padding:.75rem;border-radius:12px;font-weight:700;background:#b91c1c;color:#fff;">
                    Effacer définitivement
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fenetre = document.getElementById('modaleEffacement');
    if (!fenetre || typeof bootstrap === 'undefined') return;

    const boite = new bootstrap.Modal(fenetre);
    let formulaire = null;

    document.querySelectorAll('.form-effacement').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            formulaire = f;
            document.getElementById('effNom').textContent = f.dataset.nom || '';
            document.getElementById('effEcritures').textContent =
                new Intl.NumberFormat('fr-FR').format(f.dataset.ecritures || 0);
            boite.show();
        });
    });

    document.getElementById('effConfirmer').addEventListener('click', function () {
        if (formulaire) formulaire.submit();
    });
});
</script>
</body>
</html>
