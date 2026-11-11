<!doctype html>
<html lang="fr" class="layout-menu-fixed layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

@include('components.head')

<style>
    body {
        background-color: #f8fafc;
        font-family: 'Inter', sans-serif;
        color: #1a1a1a;
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
    .text-gradient {
        background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .nav-button {
        transition: all 0.2s;
        border: 1px solid #e2e8f0;
    }
    .nav-button:hover {
        border-color: #1e40af;
        background-color: #eff6ff;
        color: #1e40af;
    }
    .pulse {
        animation: pulse-animation 2s infinite;
    }
    @keyframes pulse-animation {
        0% { box-shadow: 0 0 0 0px rgba(30, 64, 175, 0.2); }
        100% { box-shadow: 0 0 0 10px rgba(30, 64, 175, 0); }
    }

    /* Layout adjustments to match the theme */
    .layout-page {
        background-color: #f8fafc !important;
    }
    .content-wrapper {
        padding: 1.5rem !important;
    }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')

            <div class="layout-page">
                @include('components.header', [
                    'page_title' => 'Tableau de <span class="text-gradient">Bord</span>',
                    'company_name' => $currentCompany->company_name ?? 'Espace Comptable'
                ])

                <div class="content-wrapper">
                    <div class="max-w-7xl mx-auto">

                        <!-- En-tête Centré Dynamique -->
                        <!-- En-tête Centré Dynamique -->
                        <div class="text-center mb-8">
                            <p class="text-base font-medium text-slate-500">Bienvenue, voici l'état actuel de votre exercice comptable.</p>
                        </div>

                        @if(isset($exerciceEnCours) && $exerciceEnCours)
                        <div class="glass-card p-4 mb-6 d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div><span class="text-xs text-slate-400 fw-bold text-uppercase">Exercice comptable actif</span>
                                <div class="fw-bold text-slate-800">{{ $exerciceEnCours->intitule }}</div>
                                <small class="text-slate-500">{{ \\Carbon\\Carbon::parse($exerciceEnCours->date_debut)->format('d/m/Y') }} au {{ \\Carbon\\Carbon::parse($exerciceEnCours->date_fin)->format('d/m/Y') }}</small>
                            </div>
                            <span class="badge bg-success-subtle text-success px-3 py-2">Actif</span>
                        </div>
                        @endif

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                            <!-- Section Actions Rapides (Modules) -->
                            <div class="lg:col-span-2 space-y-8">
                                <div class="glass-card p-6 mt-6">
                                    <h3 class="text-base font-bold text-slate-800 mb-6 flex items-center gap-2">
                                        <i class="fas fa-th-large text-blue-700"></i> Vos Modules de Travail
                                    </h3>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-2">
                                        <!-- Traitement -->
                                        @php
                                            $showTraitement = auth()->user()->hasPermission('modal_saisie_direct') || 
                                                              auth()->user()->hasPermission('accounting_entry_list') || 
                                                              auth()->user()->hasPermission('ecriture.rejected') || 
                                                              auth()->user()->hasPermission('brouillons.index') || 
                                                              auth()->user()->hasPermission('immobilisations.index') || 
                                                              auth()->user()->hasPermission('exercice_comptable');
                                        @endphp
                                        @if($showTraitement)
                                        <div class="space-y-3">
                                            <h5 class="text-sm font-bold text-slate-400 uppercase tracking-widest border-b pb-2">Traitement</h5>
                                            @if(auth()->user()->hasPermission('modal_saisie_direct'))
                                            <button type="button" data-bs-toggle="modal" data-bs-target="#saisieRedirectModal" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold pulse bg-blue-700 text-white border-none transition-all hover:bg-blue-800">
                                                <i class="fas fa-keyboard w-5"></i> Nouvelle Saisie
                                            </button>
                                            @endif
                                            @if(auth()->user()->hasPermission('accounting_entry_list'))
                                            <a href="{{ route('accounting_entry_list') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-list-ul w-5"></i> Écritures
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('ecriture.rejected'))
                                            <a href="{{ route('ecriture.rejected') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-file-circle-xmark w-5"></i> Écritures rejetées
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('brouillons.index'))
                                            <a href="{{ route('brouillons.index') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-file-pen w-5"></i> Brouillons
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('immobilisations.index'))
                                            <a href="{{ route('immobilisations.index') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-building-circle-check w-5"></i> Immobilisations
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('exercice_comptable'))
                                            <a href="{{ route('exercice_comptable') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-history w-5"></i> Exercice
                                            </a>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- Paramétrage -->
                                        @php
                                            $showParametrage = auth()->user()->hasPermission('plan_comptable') || 
                                                               auth()->user()->hasPermission('plan_tiers') || 
                                                               auth()->user()->hasPermission('accounting_journals');
                                        @endphp
                                        @if($showParametrage)
                                        <div class="space-y-3">
                                            <h5 class="text-sm font-bold text-slate-400 uppercase tracking-widest border-b pb-2">Paramétrage</h5>
                                            @if(auth()->user()->hasPermission('plan_comptable'))
                                            <a href="{{ route('plan_comptable') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-book w-5"></i> Plan Comptable
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('plan_tiers'))
                                            <a href="{{ route('plan_tiers') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-address-book w-5"></i> Plan Tiers
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('accounting_journals'))
                                            <a href="{{ route('accounting_journals') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-journal-whills w-5"></i> Journaux
                                            </a>
                                            @endif
                                        </div>
                                        @endif

                                        <!-- Rapports -->
                                        @php
                                            $showRapports = auth()->user()->hasPermission('accounting_ledger') || 
                                                            auth()->user()->hasPermission('accounting_balance');
                                        @endphp
                                        @if($showRapports)
                                        <div class="space-y-3">
                                            <h5 class="text-sm font-bold text-slate-400 uppercase tracking-widest border-b pb-2">Rapports</h5>
                                            @if(auth()->user()->hasPermission('accounting_ledger'))
                                            <a href="{{ route('accounting_ledger') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-file-invoice w-5"></i> Grand Livre
                                            </a>
                                            @endif
                                            @if(auth()->user()->hasPermission('accounting_balance'))
                                            <a href="{{ route('accounting_balance') }}" class="nav-button w-full flex items-center gap-3 p-3 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                                                <i class="fas fa-balance-scale w-5"></i> Balance
                                            </a>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Activité Récente / Journaux de Trésorerie -->
                                <div class="glass-card p-6">
                                    <div class="flex items-center justify-between mb-6">
                                        <h3 class="text-lg font-bold text-slate-800">Derniers Journaux de Trésorerie</h3>
                                        <button class="text-sm text-blue-700 font-bold hover:underline">Voir tout</button>
                                    </div>
                                    <div class="space-y-4">
                                        @forelse($recentTreasuryEntries ?? [] as $entry)
                                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 transition-hover hover:bg-slate-100">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-blue-700 shadow-sm">
                                                    <i class="fas fa-{{ $entry['icon'] ?? 'university' }}"></i>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-800">{{ $entry['title'] }}</p>
                                                    <p class="text-xs text-slate-500">Poste : {{ $entry['poste'] }}</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-black {{ $entry['amount'] > 0 ? 'text-slate-800' : 'text-red-600' }}">
                                                    {{ $entry['amount'] > 0 ? '+' : '' }} {{ number_format($entry['amount'], 0, ',', ' ') }}
                                                </p>
                                                <p class="text-[10px] text-slate-400">{{ $entry['date'] }}</p>
                                            </div>
                                        </div>
                                        @empty
                                        <div class="text-center py-6 text-slate-400 italic">
                                            Aucune opération de trésorerie récente.
                                        </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <!-- Barre Latérale Droite : Alertes & Statut -->
                            <div class="space-y-8">
                                <div class="glass-card p-6 bg-slate-900 text-white border-none shadow-xl shadow-blue-900/20">
                                    <h3 class="text-base font-bold mb-4">Statut Clôture</h3>
                                    <div class="w-full bg-slate-800 rounded-full h-2 mb-2">
                                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-1000" style="width: {{ $exerciceProgress ?? 0 }}%"></div>
                                    </div>
                                    <p class="text-xs text-slate-400">Exercice {{ $exerciceYear ?? date('Y') }} complété à {{ $exerciceProgress ?? 0 }}%</p>
                                    <button class="w-full mt-6 py-3 bg-white/10 hover:bg-white/20 rounded-xl text-sm font-bold transition">
                                        Générer Pré-Bilan
                                    </button>
                                </div>

                                <div class="glass-card p-6">
                                    <h3 class="text-base font-bold text-slate-800 mb-4">Notifications</h3>
                                    <div class="space-y-6">
                                        @forelse($alerts ?? [] as $alert)
                                        <div class="flex gap-4">
                                            <div class="mt-1">
                                                <span class="flex h-2 w-2 rounded-full bg-{{ $alert['priority'] == 'high' ? 'red' : 'blue' }}-600"></span>
                                            </div>
                                            <p class="text-xs text-slate-600 leading-relaxed">
                                                <span class="font-bold text-slate-900">{{ $alert['title'] }} :</span> {{ $alert['description'] }}
                                            </p>
                                        </div>
                                        @empty
                                        <div class="text-xs text-slate-500 italic">
                                            Aucune notification pour le moment.
                                        </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    @include('components.footer')
                </div>
            </div>
        </div>
        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    @include('components.modal_saisie_direct')
</body>
</html>
