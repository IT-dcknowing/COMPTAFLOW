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
    .glass-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .glass-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')

            <div class="layout-page">
                @include('components.header', ['page_title' => 'Gestion des Super Admins Secondaires'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Header Standardisé -->
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Administration Interne</h5>
                                <p class="text-muted small mb-0">Gérez les super administrateurs secondaires et leurs périmètres de supervision.</p>
                            </div>
                            <a href="{{ route('superadmin.secondary.create') }}" class="btn btn-primary rounded-pill px-4">
                                <i class="fa-solid fa-plus me-2"></i> Nouveau Super Admin Secondaire
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

                        @php
                            $totalAdmins = $admins->total();
                        @endphp

                        <!-- KPIs Grid (4 Colonnes) -->
                        <div class="kpi-grid mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                            <div class="glass-card p-4 border-l-4 border-l-primary">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Super Admins</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalAdmins }}</h3>
                                    </div>
                                    <div class="p-3 bg-blue-50 text-primary rounded-2xl">
                                        <i class="fa-solid fa-user-shield text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Admins secondaires</p>
                            </div>

                            <div class="glass-card p-4 border-l-4 border-l-success">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Statut Compte</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalAdmins }}</h3>
                                    </div>
                                    <div class="p-3 bg-green-50 text-success rounded-2xl">
                                        <i class="fa-solid fa-user-check text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Actifs sur le réseau</p>
                            </div>

                            <div class="glass-card p-4 border-l-4 border-l-purple">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Rôle</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">Sub-Admin</h3>
                                    </div>
                                    <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl">
                                        <i class="fa-solid fa-user-gear text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Délégation de pouvoir</p>
                            </div>

                            <div class="glass-card p-4 border-l-4 border-l-warning">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Périmètre</p>
                                        <h3 class="text-2xl font-black text-slate-800 mb-0">Définis</h3>
                                    </div>
                                    <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                                        <i class="fa-solid fa-building text-lg"></i>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Supervision ciblée</p>
                            </div>
                        </div>

                    <!-- Tableau des utilisateurs avec Filtres Colonnes -->
                    <div class="glass-card overflow-hidden mb-4">
                        @include('components.filtre_colonnes', [
                            'corps' => '#corpsSecondary',
                            'nom' => 'administrateurs',
                            'filtres' => [
                                ['cle' => 'nom', 'libelle' => 'Nom / Prénom', 'type' => 'texte'],
                                ['cle' => 'email', 'libelle' => 'Email', 'type' => 'texte'],
                            ],
                        ])
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="fw-semibold">Nom</th>
                                        <th class="fw-semibold">Email</th>
                                        <th class="fw-semibold">Entreprises Supervisées</th>
                                        <th class="fw-semibold">Créé le</th>
                                        <th class="fw-semibold text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="corpsSecondary">
                                    @forelse($admins as $admin)
                                        <tr data-f-nom="{{ $admin->name }} {{ $admin->last_name }}"
                                            data-f-email="{{ $admin->email_adresse }}">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm bg-indigo-600 text-white rounded-circle me-2">
                                                        {{ $admin->initiales }}
                                                    </div>
                                                    <span class="fw-medium">{{ $admin->name }} {{ $admin->last_name }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $admin->email_adresse }}</td>
                                            <td>
                                                @php
                                                    $supervisedIds = $admin->supervised_companies ?? [];
                                                    $count = count($supervisedIds);
                                                @endphp
                                                @if($count > 0)
                                                    <span class="badge bg-label-primary">{{ $count }} entreprise(s)</span>
                                                @else
                                                    <span class="badge bg-label-secondary">Aucune</span>
                                                @endif
                                            </td>
                                            <td>{{ $admin->created_at->format('d/m/Y') }}</td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    {{-- Note: Update route doesn't exist yet for edit but used for update in controller --}}
                                                    {{-- Assuming we might add an edit view later --}}
                                                    <form action="{{ route('superadmin.secondary.destroy', $admin->id) }}" 
                                                            method="POST" 
                                                            class="d-inline"
                                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce super admin secondaire ?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" title="Supprimer">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-user-tie fa-2x mb-2"></i>
                                                <p class="mb-0">Aucun super admin secondaire trouvé</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($admins->hasPages())
                            <div class="p-4 border-top">
                                {{ $admins->links() }}
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
