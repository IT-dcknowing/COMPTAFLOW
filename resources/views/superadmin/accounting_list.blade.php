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
                @include('components.header', ['page_title' => 'Gestion des Comptabilités'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Header Standardisé -->
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Gestion des Comptabilités</h5>
                                <p class="text-muted small mb-0">Pilotez et surveillez l'ensemble des exercices comptables du réseau.</p>
                            </div>
                            <a href="{{ route('superadmin.accounting.create') }}" class="btn btn-primary rounded-pill px-4">
                                <i class="fa-solid fa-plus me-2"></i> Nouvel Exercice
                            </a>
                        </div>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @php
                        $totalEx = $exercices->count();
                        $openEx = $exercices->where('cloturer', false)->count();
                        $closedEx = $totalEx - $openEx;
                    @endphp

                    <!-- KPIs Grid (4 Colonnes) -->
                    <div class="kpi-grid mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                        <div class="glass-card p-4 border-l-4 border-l-primary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Exercices</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalEx }}</h3>
                                </div>
                                <div class="p-3 bg-blue-50 text-primary rounded-2xl">
                                    <i class="fa-solid fa-calculator text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Tous exercices</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-success">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Exercices Ouverts</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $openEx }}</h3>
                                </div>
                                <div class="p-3 bg-green-50 text-success rounded-2xl">
                                    <i class="fa-solid fa-folder-open text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">En cours de saisie</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-secondary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Clôturés</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $closedEx }}</h3>
                                </div>
                                <div class="p-3 bg-slate-100 text-slate-600 rounded-2xl">
                                    <i class="fa-solid fa-lock text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Exercices fermés</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-warning">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Taux d'Activité</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $totalEx > 0 ? round(($openEx / $totalEx) * 100) : 0 }}%</h3>
                                </div>
                                <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                                    <i class="fa-solid fa-chart-pie text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Exercices actifs</p>
                        </div>
                    </div>

                    <!-- Carte Tableau avec Filtres Colonnes -->
                    <div class="glass-card overflow-hidden mb-4">
                        @include('components.filtre_colonnes', [
                            'corps' => '#corpsAccounting',
                            'nom' => 'exercices',
                            'filtres' => [
                                ['cle' => 'intitule', 'libelle' => 'Intitulé', 'type' => 'texte'],
                                ['cle' => 'compagnie', 'libelle' => 'Entreprise', 'type' => 'texte'],
                                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'liste'],
                            ],
                        ])
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="fw-semibold">Intitulé</th>
                                        <th class="fw-semibold">Entreprise</th>
                                        <th class="fw-semibold">Administrateur</th>
                                        <th class="fw-semibold">Période</th>
                                        <th class="fw-semibold">Statut</th>
                                        <th class="fw-semibold text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="corpsAccounting">
                                    @forelse($exercices as $exercice)
                                        <tr data-f-intitule="{{ $exercice->intitule }}"
                                            data-f-compagnie="{{ $exercice->company->company_name ?? 'N/A' }}"
                                            data-f-statut="{{ $exercice->cloturer ? 'Clôturé' : 'Ouvert' }}">
                                            <td class="fw-bold text-primary">{{ $exercice->intitule }}</td>
                                            <td>{{ $exercice->company->company_name ?? 'N/A' }}</td>
                                            <td>{{ $exercice->user->name ?? 'N/A' }} {{ $exercice->user->last_name ?? '' }}</td>
                                            <td>
                                                <small class="d-block text-muted">Du {{ \Carbon\Carbon::parse($exercice->date_debut)->format('d/m/Y') }}</small>
                                                <small class="d-block text-muted">Au {{ \Carbon\Carbon::parse($exercice->date_fin)->format('d/m/Y') }}</small>
                                            </td>
                                            <td>
                                                @if($exercice->cloturer)
                                                    <span class="badge bg-secondary">Clôturé</span>
                                                @else
                                                    <span class="badge bg-success">Ouvert</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('superadmin.accounting.edit', $exercice->id) }}" class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fa-solid fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('superadmin.accounting.destroy', $exercice->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cet exercice ?')">
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
                                            <td colspan="6" class="text-center py-4 text-muted">Aucun exercice comptable trouvé.</td>
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
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
</body>
</html>
