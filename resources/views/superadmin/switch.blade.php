@include('components.head')

<style>
    body {
        background-color: #f8fafc;
        font-family: 'Inter', sans-serif;
    }
    .text-premium-gradient {
        background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
    }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')

            <div class="layout-page">
                @include('components.header', ['page_title' => 'Gouvernance / Switch Entreprise'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Header Standardisé -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Gouvernance / Switch Entreprise</h5>
                                <p class="text-muted small mb-0">Basculez entre les contextes entreprises ou incarnez un collaborateur.</p>
                            </div>
                        </div>
                    


                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Statut actuel -->
                    @if($currentSwitchedCompany || $currentSwitchedUser)
                        <div class="alert alert-info d-flex align-items-center mb-4">
                            <i class="fa-solid fa-info-circle fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <strong>Mode Switch Actif</strong>
                                <p class="mb-0">
                                    @if($currentSwitchedUser)
                                        @php $user = \App\Models\User::find($currentSwitchedUser); @endphp
                                        Vous êtes actuellement connecté en tant que : <strong>{{ $user->name ?? 'N/A' }}</strong>
                                    @endif
                                    @if($currentSwitchedCompany)
                                        @php $company = \App\Models\Company::find($currentSwitchedCompany); @endphp
                                        (Entreprise : <strong>{{ $company->company_name ?? 'N/A' }}</strong>)
                                    @endif
                                </p>
                            </div>
                            <form action="{{ route('superadmin.switch.return') }}" method="POST" class="ms-3">
                                @csrf
                                <button type="submit" class="btn btn-warning">
                                    <i class="fa-solid fa-arrow-left me-2"></i>Retour Super Admin
                                </button>
                            </form>
                        </div>
                    @endif

                    @php
                        $totalSwitchComp = $companies->total();
                    @endphp

                    <!-- KPIs Grid (4 Colonnes) -->
                    <div class="kpi-grid mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                        <div class="glass-card p-4 border-l-4 border-l-primary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Entreprises</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalSwitchComp }}</h3>
                                </div>
                                <div class="p-3 bg-blue-50 text-primary rounded-2xl">
                                    <i class="fa-solid fa-building text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Toutes entités</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-success">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Sociétés Mères</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $companies->where('parent_company_id', null)->count() }}</h3>
                                </div>
                                <div class="p-3 bg-green-50 text-success rounded-2xl">
                                    <i class="fa-solid fa-crown text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Sièges autonomes</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-purple">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Sous-entités</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $companies->whereNotNull('parent_company_id')->count() }}</h3>
                                </div>
                                <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl">
                                    <i class="fa-solid fa-sitemap text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Filiales & branches</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-warning">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Actives</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $companies->where('is_active', true)->count() }}</h3>
                                </div>
                                <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                                    <i class="fa-solid fa-check-circle text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Opérationnelles</p>
                        </div>
                    </div>

                    <!-- Carte Liste des Entreprises avec Filtres Colonnes -->
                    <div class="glass-card overflow-hidden mb-4">
                        @include('components.filtre_colonnes', [
                            'corps' => '#corpsSwitch',
                            'nom' => 'entreprises',
                            'filtres' => [
                                ['cle' => 'compagnie', 'libelle' => 'Entreprise', 'type' => 'texte'],
                                ['cle' => 'type', 'libelle' => 'Type', 'type' => 'liste'],
                                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'liste'],
                            ],
                        ])

                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="fw-semibold">Entreprise</th>
                                        <th class="fw-semibold">Type</th>
                                        <th class="fw-semibold">Utilisateurs</th>
                                        <th class="fw-semibold">Date de création</th>
                                        <th class="fw-semibold">Statut</th>
                                        <th class="fw-semibold text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="corpsSwitch">
                                    @forelse($companies as $company)
                                        <tr data-f-compagnie="{{ $company->company_name }}"
                                            data-f-type="{{ is_null($company->parent_company_id) ? 'Siège' : 'Sous-entité' }}"
                                            data-f-statut="{{ $company->is_active ? 'Active' : 'Inactive' }}">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm bg-primary text-white rounded-circle me-2">
                                                        {{ strtoupper(substr($company->company_name, 0, 2)) }}
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium">{{ $company->company_name }}</span>
                                                        @if($company->is_blocked)
                                                            <span class="badge bg-danger ms-2">Bloqué</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if(is_null($company->parent_company_id))
                                                    <span class="badge bg-label-primary">Siège</span>
                                                @else
                                                    <span class="badge bg-label-info">Sous-entité</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $membres = $company->membres(); @endphp
                                                <span class="badge bg-secondary">{{ $membres->count() }} utilisateur{{ $membres->count() > 1 ? 's' : '' }}</span>
                                            </td>
                                            <td>
                                                @if($company->created_at)
                                                    <span class="text-muted">{{ $company->created_at->format('d/m/y') }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($company->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <form action="{{ route('superadmin.switch.company', $company->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-primary" 
                                                                @if($company->is_blocked) disabled title="Entreprise bloquée" @endif>
                                                            <i class="fa-solid fa-sign-in-alt me-1"></i>Accéder
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#users-{{ $company->id }}">
                                                        <i class="fa-solid fa-users"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Ligne dépliable pour les utilisateurs -->
                                        <tr class="collapse" id="users-{{ $company->id }}">
                                            <td colspan="6" class="bg-light">
                                                <div class="p-3">
                                                    <h6 class="fw-semibold mb-3">Utilisateurs de {{ $company->company_name }}</h6>
                                                    <div class="row g-2">
                                                        @forelse($company->membres() as $user)
                                                            <div class="col-md-6">
                                                                <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                                                    <div>
                                                                        <strong>{{ $user->name }}</strong>
                                                                        <span class="badge bg-{{ $user->role === 'admin' ? 'success' : ($user->role === 'comptable' ? 'primary' : 'secondary') }} ms-2">
                                                                            {{ ucfirst($user->role) }}
                                                                        </span>
                                                                        @if($company->user_id === $user->id)
                                                                            <span class="badge bg-label-warning ms-1" title="A créé cette entreprise">Responsable</span>
                                                                        @endif
                                                                        @if($user->company_id !== $company->id)
                                                                            <span class="badge bg-label-info ms-1" title="Rattaché à plusieurs comptabilités">Affecté</span>
                                                                        @endif
                                                                        @if($user->is_blocked)
                                                                            <span class="badge bg-danger ms-1">Bloqué</span>
                                                                        @endif
                                                                    </div>
                                                                    <form action="{{ route('superadmin.switch.user', $user->id) }}" method="POST">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-sm btn-outline-primary"
                                                                                @if($user->is_blocked) disabled title="Utilisateur bloqué" @endif>
                                                                            <i class="fa-solid fa-user-check me-1"></i>Se connecter
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @empty
                                                            <div class="col-12">
                                                                <p class="text-muted mb-0">Aucun utilisateur dans cette entreprise</p>
                                                            </div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-building fa-2x mb-2"></i>
                                                <p class="mb-0">
                                                    @if(request()->hasAny(['search', 'company_id']))
                                                        Aucune entreprise ne correspond à ce filtre
                                                    @else
                                                        Aucune entreprise trouvée
                                                    @endif
                                                </p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($companies->hasPages())
                            <div class="p-4 border-top">
                                {{ $companies->links() }}
                            </div>
                        @endif
                    </div>

                </div>

                @include('components.footer')
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
</body>
</html>
