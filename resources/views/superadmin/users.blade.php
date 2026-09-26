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
                @include('components.header', ['page_title' => 'Administration des Utilisateurs'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Header Standardisé -->
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Administration des Utilisateurs</h5>
                                <p class="text-muted small mb-0">Gérez les accès et les rôles de tous les collaborateurs de la plateforme.</p>
                            </div>
                            <a href="{{ route('superadmin.users.create') }}" class="btn btn-primary rounded-pill px-4">
                                <i class="fa-solid fa-plus me-2"></i> Nouvel Utilisateur
                            </a>
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

                    <style>
                        .glass-card {
                            background: #ffffff;
                            border: 1px solid #e2e8f0;
                            border-radius: 16px;
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                            transition: transform 0.2s, box-shadow 0.2s;
                        }
                        .glass-card:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                        }
                        .kpi-grid {
                            display: grid;
                            grid-template-columns: repeat(4, 1fr);
                            gap: 1rem;
                        }
                        @media (max-width: 992px) {
                            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
                        }
                        @media (max-width: 576px) {
                            .kpi-grid { grid-template-columns: 1fr; }
                        }
                    </style>

                    <!-- Statistiques rapides (Grille horizontale 4 colonnes) -->
                    <div class="kpi-grid mb-4">
                        <div class="glass-card p-4 border-l-4 border-l-primary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Utilisateurs</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalUsers }}</h3>
                                </div>
                                <div class="p-3 bg-blue-50 text-primary rounded-2xl">
                                    <i class="fa-solid fa-users text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-3 font-bold uppercase mb-0">Tous rôles confondus</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-success">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Administrateurs</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalAdmins }}</h3>
                                </div>
                                <div class="p-3 bg-green-50 text-success rounded-2xl">
                                    <i class="fa-solid fa-user-shield text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-green-600 mt-3 font-bold uppercase mb-0">Gestionnaires d'entités</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-purple-600">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Comptables</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalComptables }}</h3>
                                </div>
                                <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl">
                                    <i class="fa-solid fa-calculator text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-purple-600 mt-3 font-bold uppercase mb-0">Opérateurs saisie</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-warning">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Utilisateurs Actifs</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalActive }}</h3>
                                </div>
                                <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-2xl">
                                    <i class="fa-solid fa-user-check text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-warning mt-3 font-bold uppercase mb-0">Comptes opérationnels</p>
                        </div>
                    </div>

                    <!-- Filtres Instantanés (Sans bouton submit) -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
                        <form method="GET" action="{{ route('superadmin.users') }}" id="filterForm">
                            <div class="row g-3 align-items-end">

                                {{-- Recherche --}}
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted mb-1">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i>Recherche
                                    </label>
                                    <input type="text" name="search" id="filter_search"
                                           class="form-control form-control-sm"
                                           placeholder="Nom, prénom, email…"
                                           value="{{ request('search') }}">
                                </div>

                                {{-- Rôle --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-muted mb-1">
                                        <i class="fa-solid fa-user-tag me-1"></i>Rôle
                                    </label>
                                    <select name="role" id="filter_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Tous les rôles</option>
                                        <option value="admin"       {{ request('role') === 'admin'       ? 'selected' : '' }}>Admin</option>
                                        <option value="comptable"   {{ request('role') === 'comptable'   ? 'selected' : '' }}>Comptable</option>
                                        <option value="user"        {{ request('role') === 'user'        ? 'selected' : '' }}>Utilisateur</option>
                                    </select>
                                </div>

                                {{-- Entreprise --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-muted mb-1">
                                        <i class="fa-solid fa-building me-1"></i>Entreprise
                                    </label>
                                    <select name="company_id" id="filter_company" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Toutes les entreprises</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                                {{ $company->company_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Statut --}}
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold small text-muted mb-1">
                                        <i class="fa-solid fa-toggle-on me-1"></i>Statut
                                    </label>
                                    <div class="d-flex gap-2">
                                        <select name="is_active" id="filter_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">Tous</option>
                                            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Actif</option>
                                            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Bloqué</option>
                                        </select>
                                        @if(request()->hasAny(['search','role','company_id','is_active']))
                                            <a href="{{ route('superadmin.users') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center" style="min-width:32px;" title="Réinitialiser">
                                                <i class="fa-solid fa-eraser"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>

                            </div>

                            {{-- Badge résultats --}}
                            @if(request()->hasAny(['search','role','company_id','is_active']))
                                <div class="mt-2 pt-2 border-top d-flex align-items-center gap-2">
                                    <span class="badge bg-primary rounded-pill">{{ $users->total() }} résultat(s)</span>
                                    <span class="text-muted small">filtre(s) actif(s)</span>
                                </div>
                            @endif
                        </form>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const searchInput = document.getElementById('filter_search');
                            const filterForm = document.getElementById('filterForm');
                            if (searchInput && filterForm) {
                                let timer;
                                searchInput.addEventListener('input', function() {
                                    clearTimeout(timer);
                                    timer = setTimeout(function() {
                                        filterForm.submit();
                                    }, 400);
                                });
                            }
                        });
                    </script>

                    <!-- Tableau des utilisateurs -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-4 border-bottom">
                            <h5 class="fw-semibold mb-0">Liste des utilisateurs</h5>
                        </div>

                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="fw-semibold">Nom</th>
                                        <th class="fw-semibold">Email</th>
                                        <th class="fw-semibold">Entreprise</th>
                                        <th class="fw-semibold">Rôle</th>
                                        <th class="fw-semibold">Statut</th>
                                        <th class="fw-semibold">Créé le</th>
                                        <th class="fw-semibold text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm bg-primary text-white rounded-circle me-2">
                                                        {{ $user->initiales }}
                                                    </div>
                                                    <span class="fw-medium">{{ $user->name }}</span>
                                                </div>
                                            </td>
                                            <td class="text-muted small">{{ $user->email_adresse }}</td>
                                            <td>
                                                <div>
                                                    <div class="fw-medium text-slate-700">{{ $user->company->company_name ?? 'N/A' }}</div>
                                                    @if($user->company && $user->company->parent)
                                                        <div class="text-[10px] font-bold text-blue-500 uppercase tracking-tighter mt-1">
                                                            <i class="fa-solid fa-link me-1"></i>Filiale de {{ $user->company->parent->company_name }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($user->role === 'admin')
                                                    <span class="badge bg-success">Admin</span>
                                                @elseif($user->role === 'comptable')
                                                    <span class="badge bg-primary">Comptable</span>
                                                @elseif($user->role === 'super_admin')
                                                    <span class="badge bg-danger">Super Admin</span>
                                                @else
                                                    <span class="badge bg-secondary">Utilisateur</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->is_active)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="fa-solid fa-circle-check me-1"></i>Actif
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                        <i class="fa-solid fa-ban me-1"></i>Bloqué
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-muted small">{{ $user->created_at ? $user->created_at->format('d/m/Y') : '—' }}</td>
                                            <td class="text-end">
                                                @if($user->role !== 'super_admin')
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('superadmin.users.edit', $user->id) }}" class="btn btn-outline-primary" title="Modifier">
                                                            <i class="fa-solid fa-edit"></i>
                                                        </a>
                                                        <form action="{{ route('superadmin.users.destroy', $user->id) }}" 
                                                              method="POST" 
                                                              class="d-inline"
                                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger" title="Supprimer">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">Protégé</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-users fa-2x mb-2"></i>
                                                <p class="mb-0">Aucun utilisateur trouvé</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($users->hasPages())
                            <div class="p-4 border-top">
                                {{ $users->links() }}
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
