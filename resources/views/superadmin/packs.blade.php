<!DOCTYPE html>
<html lang="fr" class="layout-menu-fixed layout-compact">
@include('components.head')
<style>
    .pack-table thead th {
        background: #f8fafc;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
        border-top: none;
    }
    .pack-row:hover { background-color: rgba(59, 130, 246, 0.02) !important; }
    .pack-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .pack-cabinet { background: #eff6ff; color: #2563eb; }
    .pack-entreprise { background: #f0fdf4; color: #16a34a; }
    .role-tag { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
    .kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px 24px; }
</style>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')
            <div class="layout-page">
                @include('components.header', ['page_title' => 'Offres & <span class="text-primary">Abonnements</span>'])
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show rounded-xl" role="alert">
                                <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show rounded-xl" role="alert">
                                <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif
                        @if(session('info'))
                            <div class="alert alert-info alert-dismissible fade show rounded-xl" role="alert">
                                <i class="fa-solid fa-circle-info me-2"></i>{{ session('info') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <!-- Action Bar & Titre -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="bg-white p-4 rounded-[20px] shadow-sm d-flex align-items-center justify-content-between border border-slate-100 flex-wrap gap-3">
                                    <div>
                                        <h4 class="font-black mb-1 text-slate-800">Offres & Abonnements</h4>
                                        <p class="text-slate-500 mb-0 text-sm">
                                            Montées et descentes en gamme des comptes souscripteurs.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPIs Grid Horizontale (4 colonnes) -->
                        <div class="kpi-grid mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                            <div class="glass-card p-4 border-l-4 border-l-success">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pack Entreprise</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">{{ number_format($stats['entreprise'], 0, ',', ' ') }}</h3>
                                    </div>
                                    <div class="p-3 bg-green-50 text-success rounded-2xl">
                                        <i class="fa-solid fa-building text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Une seule comptabilité</p>
                            </div>

                            <div class="glass-card p-4 border-l-4 border-l-primary">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pack Cabinet</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">{{ number_format($stats['cabinet'], 0, ',', ' ') }}</h3>
                                    </div>
                                    <div class="p-3 bg-blue-50 text-primary rounded-2xl">
                                        <i class="fa-solid fa-briefcase text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Multi-dossiers illimité</p>
                            </div>

                            <div class="glass-card p-4 border-l-4 border-l-purple">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Comptabilités</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">{{ number_format($stats['comptas'], 0, ',', ' ') }}</h3>
                                    </div>
                                    <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl">
                                        <i class="fa-solid fa-calculator text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Toutes offres confondues</p>
                            </div>

                            <div class="glass-card p-4 border-l-4 border-l-warning">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Abonnés</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">{{ number_format($comptes->total(), 0, ',', ' ') }}</h3>
                                    </div>
                                    <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                                        <i class="fa-solid fa-users text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Titulaires actifs</p>
                            </div>
                        </div>

                        <!-- Carte Tableau avec Filtres en Ligne Instantanés -->
                        <div class="glass-card overflow-hidden mb-4">

                            @include('components.filtre_colonnes', [
                                'corps' => '#corpsPacks',
                                'nom' => 'comptes',
                                'filtres' => [
                                    ['cle' => 'compte', 'libelle' => 'Recherche Compte', 'type' => 'texte'],
                                    ['cle' => 'offre', 'libelle' => 'Offre', 'type' => 'liste'],
                                    ['cle' => 'role', 'libelle' => 'Rôle', 'type' => 'liste'],
                                    ['cle' => 'nouvelles', 'libelle' => 'Nouvelles sociétés', 'type' => 'liste'],
                                ],
                            ])

                            <div class="table-responsive">
                                <table class="table pack-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Compte</th>
                                            <th>Offre</th>
                                            <th>Rôle</th>
                                            <th class="text-center">Comptabilités</th>
                                            <th class="text-center">Nouvelles sociétés</th>
                                            <th>Inscription</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpsPacks">
                                        @forelse($comptes as $compte)
                                            @php $estEntreprise = ($compte->pack ?: 'cabinet') === 'entreprise'; @endphp
                                            <tr class="pack-row"
                                                data-f-compte="{{ $compte->name }} {{ $compte->last_name }} {{ $compte->email_adresse }}"
                                                data-f-offre="{{ $compte->libellePack() }}"
                                                data-f-role="{{ $compte->role === 'admin' ? 'Gérant' : ucfirst($compte->role) }}"
                                                data-f-nouvelles="{{ $compte->peutCreerDesSocietes() ? 'Oui' : 'Non' }}">
                                                <td>
                                                    <div class="fw-bold text-slate-800">{{ $compte->name }} {{ $compte->last_name }}</div>
                                                    <div class="text-slate-400" style="font-size:0.78rem;">{{ $compte->email_adresse }}</div>
                                                </td>
                                                <td>
                                                    <span class="pack-badge {{ $estEntreprise ? 'pack-entreprise' : 'pack-cabinet' }}">
                                                        <i class="fa-solid {{ $estEntreprise ? 'fa-building' : 'fa-briefcase' }}"></i>
                                                        {{ $compte->libellePack() }}
                                                    </span>
                                                </td>
                                                <td><span class="role-tag">{{ $compte->role === 'admin' ? 'Gérant' : ucfirst($compte->role) }}</span></td>
                                                <td class="text-center">
                                                    <div class="fw-bold text-slate-800">{{ $compte->nb_gerees }}</div>
                                                    <div class="text-slate-400" style="font-size:0.72rem;">dont {{ $compte->nb_creees }} créée(s)</div>
                                                </td>
                                                <td class="text-center">
                                                    @if($compte->peutCreerDesSocietes())
                                                        <span class="text-success fw-bold">Oui</span>
                                                    @else
                                                        <span class="text-slate-400 fw-bold">Non</span>
                                                    @endif
                                                </td>
                                                <td class="text-slate-500" style="font-size:0.82rem;">
                                                    {{ $compte->created_at ? $compte->created_at->format('d/m/Y') : '—' }}
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-xl fw-bold"
                                                            data-bs-toggle="modal" data-bs-target="#modalOffre{{ $compte->id }}">
                                                        Changer d'offre
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-slate-400 py-5">Aucun compte ne correspond à ces filtres.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-6 border-top border-slate-100 d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="text-slate-500 text-sm">
                                    @if($comptes->total())
                                        {{ $comptes->firstItem() }}&ndash;{{ $comptes->lastItem() }} sur <strong>{{ number_format($comptes->total(), 0, ',', ' ') }}</strong> compte(s)
                                    @else
                                        Aucun compte
                                    @endif
                                </div>
                                <div>{{ $comptes->links() }}</div>
                            </div>
                        </div>

                        @foreach($comptes as $compte)
                            @php
                                $estEntreprise = ($compte->pack ?: 'cabinet') === 'entreprise';
                                $cible = $estEntreprise ? 'cabinet' : 'entreprise';
                            @endphp
                            <div class="modal fade" id="modalOffre{{ $compte->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content rounded-[20px] border-0">
                                        <form action="{{ route('superadmin.packs.update', $compte->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-black text-slate-800">
                                                    {{ $compte->name }} {{ $compte->last_name }}
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-slate-500 mb-4" style="font-size:0.9rem;">
                                                    Offre actuelle : <strong>{{ $compte->libellePack() }}</strong>,
                                                    {{ $compte->nb_gerees }} comptabilité(s) gérée(s).
                                                </p>

                                                <div class="mb-3">
                                                    <label class="form-label font-bold text-xs text-slate-500 uppercase">Nouvelle offre</label>
                                                    <select name="pack" class="form-select border-slate-200 rounded-xl py-2.5">
                                                        <option value="cabinet" {{ $cible === 'cabinet' ? 'selected' : '' }}>Pack Cabinet — espace multi-dossiers</option>
                                                        <option value="entreprise" {{ $cible === 'entreprise' ? 'selected' : '' }}>Pack Entreprise — une seule comptabilité</option>
                                                    </select>
                                                </div>

                                                <div class="bg-slate-50 rounded-xl p-3 text-slate-600" style="font-size:0.82rem;">
                                                    <div class="mb-2"><strong>Ce que le changement applique</strong></div>
                                                    <ul class="mb-0 ps-3">
                                                        <li>Pack Cabinet : création de sociétés et fusion, sans limite de dossiers.</li>
                                                        <li>Pack Entreprise : une seule comptabilité, ni création ni fusion.</li>
                                                        <li>Le titulaire reste gérant de ses comptabilités dans les deux offres.</li>
                                                        <li>Toutes les habilitations métier sont conservées dans les deux sens.</li>
                                                        <li>Le retour au Pack Entreprise est refusé si le compte gère plusieurs comptabilités.</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-outline-secondary rounded-xl" data-bs-dismiss="modal">Annuler</button>
                                                <button type="submit" class="btn btn-primary rounded-xl fw-bold">Appliquer l'offre</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                    </div>
                    @include('components.footer')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
