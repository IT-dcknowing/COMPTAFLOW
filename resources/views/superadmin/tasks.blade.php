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
    .priority-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-weight: 600;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-weight: 600;
    }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')

            <div class="layout-page">
                @include('components.header', ['page_title' => 'Gestion des Tâches Administratives'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Header Standardisé -->
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Gestion des Tâches Administratives</h5>
                                <p class="text-muted small mb-0">Créez, assignez et suivez les tâches administratives de la plateforme.</p>
                            </div>
                            <a href="{{ route('superadmin.tasks.create') }}" class="btn btn-primary rounded-pill px-4">
                                <i class="fa-solid fa-plus me-2"></i> Nouvelle Tâche
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

                    <!-- KPIs Grid (4 Colonnes) -->
                    <div class="kpi-grid mb-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                        <div class="glass-card p-4 border-l-4 border-l-primary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Tâches</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $tasks->total() }}</h3>
                                </div>
                                <div class="p-3 bg-blue-50 text-primary rounded-2xl">
                                    <i class="fa-solid fa-tasks text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Plateforme</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-warning">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">En Attente</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $tasks->where('status', 'pending')->count() }}</h3>
                                </div>
                                <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                                    <i class="fa-solid fa-clock text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Tâches ouvertes</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-purple">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">En Cours</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $tasks->where('status', 'in_progress')->count() }}</h3>
                                </div>
                                <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl">
                                    <i class="fa-solid fa-spinner text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">En traitement</p>
                        </div>

                        <div class="glass-card p-4 border-l-4 border-l-success">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Complétées</p>
                                    <h3 class="text-2xl font-black text-slate-800 mb-0">{{ $tasks->where('status', 'completed')->count() }}</h3>
                                </div>
                                <div class="p-3 bg-green-50 text-success rounded-2xl">
                                    <i class="fa-solid fa-check-circle text-lg"></i>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase mb-0">Succès</p>
                        </div>
                    </div>

                    <!-- Tableau des tâches avec Filtres Colonnes Instantanés -->
                    <div class="glass-card overflow-hidden mb-4">
                        @include('components.filtre_colonnes', [
                            'corps' => '#corpsTasks',
                            'nom' => 'tâches',
                            'filtres' => [
                                ['cle' => 'titre', 'libelle' => 'Titre / Sujet', 'type' => 'texte'],
                                ['cle' => 'assigne', 'libelle' => 'Assigné à', 'type' => 'liste'],
                                ['cle' => 'priorite', 'libelle' => 'Priorité', 'type' => 'liste'],
                                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'liste'],
                            ],
                        ])
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="fw-semibold">Titre</th>
                                        <th class="fw-semibold">Assigné à</th>
                                        <th class="fw-semibold">Entreprise</th>
                                        <th class="fw-semibold">Priorité</th>
                                        <th class="fw-semibold">Statut</th>
                                        <th class="fw-semibold">Date d'échéance</th>
                                        <th class="fw-semibold text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="corpsTasks">
                                    @forelse($tasks as $task)
                                        <tr data-f-titre="{{ $task->title }}"
                                            data-f-assigne="{{ $task->assignedUser->name ?? 'Non assigné' }}"
                                            data-f-priorite="{{ ucfirst($task->priority) }}"
                                            data-f-statut="{{ ucfirst($task->status) }}">
                                            <td>
                                                <div>
                                                    <span class="fw-medium">{{ $task->title }}</span>
                                                    @if($task->description)
                                                        <p class="text-muted small mb-0">{{ Str::limit($task->description, 50) }}</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($task->assignedTo)
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm bg-primary text-white rounded-circle me-2">
                                                            {{ $task->assignedTo->initiales }}
                                                        </div>
                                                        <span>{{ $task->assignedTo->name }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">Non assignée</span>
                                                @endif
                                            </td>
                                            <td>{{ $task->company->company_name ?? 'N/A' }}</td>
                                            <td>
                                                @if($task->priority === 'urgent')
                                                    <span class="priority-badge bg-danger text-white">
                                                        <i class="fa-solid fa-exclamation-triangle"></i> Urgente
                                                    </span>
                                                @elseif($task->priority === 'high')
                                                    <span class="priority-badge bg-warning text-dark">
                                                        <i class="fa-solid fa-arrow-up"></i> Haute
                                                    </span>
                                                @elseif($task->priority === 'medium')
                                                    <span class="priority-badge bg-info text-white">
                                                        <i class="fa-solid fa-minus"></i> Moyenne
                                                    </span>
                                                @else
                                                    <span class="priority-badge bg-secondary text-white">
                                                        <i class="fa-solid fa-arrow-down"></i> Basse
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($task->status === 'completed')
                                                    <span class="status-badge bg-success text-white">
                                                        <i class="fa-solid fa-check"></i> Complétée
                                                    </span>
                                                @elseif($task->status === 'in_progress')
                                                    <span class="status-badge bg-primary text-white">
                                                        <i class="fa-solid fa-spinner"></i> En Cours
                                                    </span>
                                                @elseif($task->status === 'cancelled')
                                                    <span class="status-badge bg-dark text-white">
                                                        <i class="fa-solid fa-ban"></i> Annulée
                                                    </span>
                                                @else
                                                    <span class="status-badge bg-warning text-dark">
                                                        <i class="fa-solid fa-clock"></i> En Attente
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($task->due_date)
                                                    {{ \Carbon\Carbon::parse($task->due_date)->format('d/m/Y') }}
                                                    @if(\Carbon\Carbon::parse($task->due_date)->isPast() && $task->status !== 'completed')
                                                        <span class="badge bg-danger ms-1">En retard</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('superadmin.tasks.show', $task->id) }}" class="btn btn-outline-info" title="Voir">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('superadmin.tasks.edit', $task->id) }}" class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fa-solid fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('superadmin.tasks.destroy', $task->id) }}" 
                                                          method="POST" 
                                                          class="d-inline"
                                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')">
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
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-tasks fa-2x mb-2"></i>
                                                <p class="mb-0">Aucune tâche trouvée</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if($tasks->hasPages())
                            <div class="p-4 border-top">
                                {{ $tasks->links() }}
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
