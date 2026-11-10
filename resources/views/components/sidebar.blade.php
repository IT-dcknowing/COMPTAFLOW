@php
    $isInAccountantSpace = request()->routeIs('accountant.space');
    $isSwitched = session('switched_company_id') || session('is_super_admin_bypassing');

    // Responsable de la comptabilite ouverte : createur, admin de l'entreprise
    // ou personne affectee comme admin. Il doit pouvoir ouvrir un exercice,
    // sans quoi la comptabilite reste inutilisable.
    $estResponsableCompta = isset($currentCompany) && $currentCompany
        ? $currentCompany->estResponsable(auth()->user())
        : false;

    if ($isInAccountantSpace) {
        $currentCompany = null;
        $isComptaAccountActive = false;
    }
@endphp

@if(!$isInAccountantSpace)
    @include('components.navbar_horizontal')
@endif


<style>
    /* Styles pour le nouveau design */
    .sidebar-new {
        position: fixed;
        left: 0;
        top: 0;
        width: 288px;
        height: 100vh;
        background: white;
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }

    .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .brand-container {
        display: flex;
        align-items: center;
    }

    .brand-logo {
        width: 40px;
        height: 40px;
        background: #1e40af;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .brand-logo-image { width: 48px; height: 48px; object-fit: contain; flex-shrink: 0; }

    .brand-logo i {
        color: white;
        font-size: 20px;
    }

    .brand-text {
        margin-left: 12px;
    }

    .brand-title {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        font-family: 'Inter', sans-serif;
    }

    .brand-subtitle {
        font-size: 12px;
        color: #6b7280;
        margin-top: 2px;
        font-family: 'Inter', sans-serif;
    }

    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 16px 12px;
        height: calc(100vh - 80px) !important;
        max-height: calc(100vh - 80px) !important;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.5) transparent;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.5);
        border-radius: 4px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.8);
    }

    .menu-section {
        margin-bottom: 24px;
    }

    .menu-section-header {
        padding: 8px 16px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .menu-link-new {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        color: #374151;
        border-radius: 8px;
        margin-bottom: 4px;
        text-decoration: none;
        transition: all 0.2s;
        font-family: 'Inter', sans-serif;
    }

    .menu-link-new:hover {
        background: #eff6ff;
        color: #1e40af;
    }

    .menu-link-new.active {
        background: #1e40af;
        color: white;
    }

    .menu-link-quick {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        color: #374151;
        border-radius: 8px;
        margin-bottom: 6px;
        text-decoration: none;
        transition: all 0.2s;
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        font-size: 13px;
    }

    .menu-link-quick:hover {
        background: #eff6ff;
        color: #1e40af;
    }

    .menu-link-quick.active {
        background: #1e40af;
        color: white;
    }

    .menu-link-new i {
        width: 20px;
        margin-right: 12px;
        font-size: 18px;
    }

    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .user-profile-mini {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px;
        border-radius: 8px;
        transition: background 0.2s;
        text-decoration: none;
        color: inherit;
    }

    .user-profile-mini:hover {
        background: #f3f4f6;
    }

    .user-avatar-sidebar {
        width: 36px;
        height: 36px;
        background: #1e40af;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }

    .user-info-sidebar {
        flex: 1;
        min-width: 0;
    }

    .user-name-sidebar {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role-sidebar {
        font-size: 11px;
        color: #6b7280;
        display: block;
    }

    .logout-btn-sidebar {
        color: #dc2626;
        padding: 8px;
        border-radius: 6px;
        transition: background 0.2s;
    }

    .logout-btn-sidebar:hover {
        background: #fef2f2;
    }

    .help-box {
        background: #eff6ff;
        border-radius: 8px;
        padding: 12px;
    }

    .help-box-content {
        display: flex;
        align-items: flex-start;
    }

    .help-box i {
        color: #1e40af;
        font-size: 18px;
        margin-top: 4px;
    }

    .help-box-text {
        margin-left: 12px;
    }

    .help-box-title {
        font-size: 12px;
        font-weight: 500;
        color: #111827;
        margin-bottom: 4px;
    }

    .help-box-desc {
        font-size: 12px;
        color: #6b7280;
    }

    .menu-param a { color:#0d6efd !important; }
    .menu-param a:hover { background:#e7f1ff !important; border-radius:8px; }
    .menu-trait a { color:#fd7e14 !important; }
    .menu-trait a:hover { background:#fff3e6 !important; border-radius:8px; }
    .menu-rapport a { color:#198754 !important; }
    .menu-rapport a:hover { background:#e9f7ef !important; border-radius:8px; }

    .company-name-sidebar {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 2px;
        font-weight: 600;
        display: block;
        line-height: 1.2;
    }

    .role-badge-sidebar {
        font-size: 0.65rem;
        color: #1e40af;
        background: #eff6ff;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.025em;
        display: inline-block;
        margin-top: 4px;
    }

    .layout-menu {
        display: none;
    }
</style>

{{--
    Modal "saisieRedirectModal" retiré : la page combinée (accounting_entry_real) gère
    désormais elle-même Journal/Mois/Scanner directement dans son en-tête, sans passer
    par ce modal intermédiaire. Fichier components/modal_saisie_direct.blade.php conservé
    de côté (non supprimé) le temps de valider la nouvelle page en usage réel.
--}}





<!-- Nouveau Sidebar Design -->

@if (auth()->check() && auth()->user()->isSuperAdmin() && !$isSwitched)
    @include('components.superadmin_sidebar')
@else
<div class="sidebar-new">
    <div class="sidebar-header">
        <div class="brand-container">
            <img src="data:image/png;base64,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" alt="Logo COMPTAFLOW" class="brand-logo-image" width="48" height="48" style="width:48px;height:48px;object-fit:contain;display:block;flex-shrink:0;">
            <div class="brand-text">
                <h1 class="brand-title">Flow Compta</h1>
                @php
                    // Le cabinet d'appartenance : celui qu'on gère, sinon celui qui nous accueille.
                    $monCabinet = \App\Models\Cabinet::where('user_id', auth()->id())->first()
                        ?? \App\Models\Cabinet::whereIn('id', function ($q) {
                                $q->select('cabinet_id')->from('cabinet_user')->where('user_id', auth()->id());
                            })->first();
                @endphp
                @if($monCabinet)
                    <div class="company-name-sidebar" title="Code : {{ $monCabinet->code }}">
                        {{ $monCabinet->nom }}
                    </div>
                @endif
                @if ($currentCompany)
                    <div class="company-name-sidebar">
                        {{ $currentCompany->company_name }}
                    </div>
                    <div class="role-badge-sidebar">
                        @if($isSwitched && auth()->user()->isSuperAdmin())
                            Administrateur
                        @else
                            {{-- Ce qu'on est SUR CE DOSSIER : le créateur l'administre, même sans rôle enregistré. --}}
                            {{ auth()->user()->titreSurLaComptabiliteCourante() }}
                        @endif
                    </div>
                @else
                    @php
                        $roleLabel = match(auth()->user()->role) {
                            'admin'       => 'Gérant',
                            'super_admin' => '',   // Ne jamais afficher "Super Admin" ici
                            // Aucun rôle enregistré : on n'invente pas de titre.
                            default       => '',
                        };
                    @endphp
                    @if($roleLabel)
                        <div class="role-badge-sidebar mt-1">{{ $roleLabel }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Bouton "Mon Espace" : chacun y retrouve ses comptabilites, offre comprise --}}
    @if(auth()->user()->aAccesMonEspace())
    <div style="padding: 10px 16px; border-bottom: 1px solid #f1f5f9;">
        <a href="{{ route('accountant.space') }}" class="d-flex align-items-center gap-2 text-decoration-none"
           style="background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%); border: 1px solid #c7d2fe; border-radius: 10px; padding: 9px 12px; transition: all 0.2s;"
           onmouseover="this.style.boxShadow='0 4px 12px -4px rgba(99,102,241,0.3)'"
           onmouseout="this.style.boxShadow='none'">
            <div style="width:30px; height:30px; border-radius:8px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fa-solid fa-grid-2" style="color:white; font-size:0.8rem;"></i>
            </div>
            <div>
                <div style="font-size:0.78rem; font-weight:700; color:#3730a3; line-height:1.1;">Mon Espace</div>
                <div style="font-size:0.62rem; color:#6366f1; font-weight:600;">Gérer mes entreprises</div>
            </div>
            <i class="fa-solid fa-chevron-right ms-auto" style="color:#a5b4fc; font-size:0.65rem;"></i>
        </a>
    </div>
    @endif

    <nav class="sidebar-nav">
        @if(!$isInAccountantSpace)
        {{-- SÉLECTEUR D'EXERCICE (NOUVEAU) --}}
        @if(isset($exercices) && $exercices->count() > 0 && $isComptaAccountActive)
            <div class="px-3 mb-4">
                <div class="dropdown">
                    @php
                        $exerciceEnContexte = session('current_exercice_id') ? true : false;
                    @endphp
                    <button class="btn btn-white w-100 text-start d-flex align-items-center justify-content-between border shadow-sm rounded-lg py-2 px-3 {{ $exerciceEnContexte ? 'border-primary' : '' }}" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.85rem;">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-calendar-check {{ $exerciceEnContexte ? 'text-primary' : 'text-primary' }} me-2"></i>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark">{{ $exerciceActif->intitule ?? 'Exercice non défini' }}</span>
                                {{-- <span class="text-muted" style="font-size: 0.7rem;">
                                    @if($exerciceEnContexte)
                                        <i class="fa-solid fa-lock me-1"></i>Exercice Sélectionné
                                    @else
                                        Exercice Actif (Défaut)
                                    @endif
                                </span> --}}
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-muted" style="font-size: 0.7rem;"></i>
                    </button>
                    <ul class="dropdown-menu w-100 shadow-lg border-0 rounded-lg p-2">
                        <li class="px-2 py-1 text-xs text-muted font-bold text-uppercase">Changer d'exercice</li>
                        @foreach($exercices as $exo)
                            <li>
                                <a class="dropdown-item rounded-md py-2 d-flex align-items-center justify-content-between {{ ($exerciceActif && $exerciceActif->id === $exo->id) ? 'active bg-primary text-white' : '' }}" 
                                   href="{{ route('exercice_comptable.switch', $exo->id) }}"
                                   data-exercice-switch="{{ $exo->id }}">
                                   <span>{{ $exo->intitule }}</span>
                                   @if($exerciceActif && $exerciceActif->id === $exo->id)
                                       {{-- Exercice actuellement sélectionné (session ou actif) --}}
                                       <span class="badge bg-success text-white ms-2" style="font-size: 0.6rem;">ACTIVÉ</span>
                                   @elseif($exo->is_active)
                                       {{-- Exercice par défaut global (non sélectionné actuellement) --}}
                                       <span class="badge bg-warning text-dark ms-2" style="font-size: 0.6rem;">PAR DÉFAUT</span>
                                   @endif
                                </a>
                            </li>
                        @endforeach
                        @if($exerciceEnContexte)
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-success font-bold rounded-md py-2" 
                                   href="{{ route('exercice_comptable.switch', 0) }}"
                                   data-exercice-switch="0">
                                    <i class="fa-solid fa-arrow-rotate-left me-2"></i> Quitter le contexte
                                </a>
                            </li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-primary font-bold" href="{{ route('exercice_comptable') }}">
                                <i class="fa-solid fa-cog me-2"></i> Gérer les exercices
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        @endif

            {{-- SECTION 1 : PILOTAGE --}}
        <div class="menu-section">
            @php
                $showPilotage = auth()->user()->hasPermission('compta.dashboard') || auth()->user()->hasPermission('admin.performance') || auth()->user()->hasPermission('tasks.view_daily');
            @endphp
            
            @if($showPilotage)
                <div class="menu-section-header">Pilotage</div>
                
                @php
                    // Celui qui TIENT la comptabilité ouverte va au tableau de
                    // bord d'administration : le créateur du dossier en fait
                    // partie, même si son compte ne porte aucun rôle.
                    $dashboardRoute = route('admin.dashboard');
                    if (!auth()->user()->gereLaComptabiliteCourante()) {
                        $dashboardRoute = route('comptable.comptdashboard');
                    } elseif ($isComptaAccountActive) {
                        $dashboardRoute = route('compta.dashboard');
                    }
                @endphp

                @if(auth()->user()->hasPermission('admin.performance'))
                    <a href="{{ route('admin.performance') }}" class="menu-link-new {{ request()->routeIs('admin.performance', 'admin.dashboard') ? 'active' : '' }}">
                        <i class="fa-solid fa-rocket"></i>
                        <span>Tableau de bord Admin</span>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('compta.dashboard'))
                    <a href="{{ $dashboardRoute }}" class="menu-link-new {{ request()->routeIs('comptable.comptdashboard', 'compta.dashboard') ? 'active' : '' }}">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Tableau de bord personnel</span>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('notifications.index'))
                <a href="{{ route('notifications.index') }}" class="menu-link-new {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                    @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                        <span class="badge bg-danger rounded-pill ms-auto">{{ $unreadNotificationsCount }}</span>
                    @endif
                </a>
                @endif



            @endif
        </div>

            {{-- SECTION 2 : CONFIGURATION ENTREPRISE --}}
            {{-- (Inchangé) --}}
            @php
                $showConfig = (auth()->user()->hasPermission('compagny_information') ||
                               auth()->user()->hasPermission('admin.config.hub') || 
                               auth()->user()->hasPermission('admin.config.plan_comptable') || 
                               auth()->user()->hasPermission('admin.config.plan_tiers') || 
                               auth()->user()->hasPermission('admin.config.journals') ||
                               auth()->user()->hasPermission('admin.config.external_import')) && !session('sidebar_config_hidden', false);
                
                $showExport = auth()->user()->hasPermission('admin.export.hub') && !session('sidebar_config_hidden', false);
            @endphp

            @if($showConfig && !session('sidebar_admin_hidden', false))
            <div class="menu-section" data-section-id="configuration">
                <div class="menu-section-header">Configuration Entreprise</div>
                @if(auth()->user()->hasPermission('compagny_information'))
                <a href="{{ route('compagny_information') }}" class="menu-link-new {{ request()->routeIs('compagny_information') ? 'active' : '' }}">
                    <i class="fa-solid fa-building"></i>
                    <span>Fiche Entreprise</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.config.hub'))
                <a href="{{ route('admin.config.hub') }}" class="menu-link-new {{ request()->routeIs('admin.config.hub') ? 'active' : '' }}">
                    <i class="fa-solid fa-gears"></i>
                    <span>Dossier de Configuration</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.config.plan_comptable'))
                <a href="{{ route('admin.config.plan_comptable') }}" class="menu-link-new {{ request()->routeIs('admin.config.plan_comptable') ? 'active' : '' }}">
                    <i class="fa-solid fa-book-bookmark"></i>
                    <span>Modèle de Plan</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.config.plan_tiers'))
                <a href="{{ route('admin.config.plan_tiers') }}" class="menu-link-new {{ request()->routeIs('admin.config.plan_tiers') ? 'active' : '' }}">
                    <i class="fa-solid fa-address-book"></i>
                    <span>Modèle de Tiers</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.config.journals'))
                <a href="{{ route('admin.config.journals') }}" class="menu-link-new {{ request()->routeIs('admin.config.journals') ? 'active' : '' }}">
                    <i class="fa-solid fa-swatchbook"></i>
                    <span>Modèle des Journaux</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.config.tresorerie_posts'))
                <a href="{{ route('admin.config.tresorerie_posts') }}" class="menu-link-new {{ request()->routeIs('admin.config.tresorerie_posts') ? 'active' : '' }}">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Postes de Trésorerie</span>
                </a>
                @endif
                {{-- @if(auth()->user()->hasPermission('admin.config.treasury_categories'))
                <a href="{{ route('admin.config.treasury_categories') }}" class="menu-link-new {{ request()->routeIs('admin.config.treasury_categories') ? 'active' : '' }}">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Catégories de Trésorerie</span>
                </a>
                @endif --}}
            </div>
            @endif

            {{-- SECTION IMPORT / EXPORT (Combiné) --}}
            @if((auth()->user()->hasPermission('admin.config.external_import') || $showExport) && !session('sidebar_admin_hidden', false))
            <div class="menu-section" data-section-id="import_export">
                <div class="menu-section-header">Import / Export</div>
                @if(auth()->user()->hasPermission('admin.config.external_import'))
                <a href="{{ route('admin.config.external_import') }}" class="menu-link-new {{ request()->routeIs('admin.config.external_import') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-import"></i>
                    <span>Importation de données</span>
                </a>
                @endif
                @if($showExport)
                <a href="{{ route('admin.export.hub') }}" class="menu-link-new {{ request()->routeIs('admin.export.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-export"></i>
                    <span>Exportation de données</span>
                </a>
                @endif
            </div>
            @endif

            {{-- SECTION FUSION (Sous-entreprises uniquement) --}}
            @if(isset($currentCompany) && $currentCompany->parent_company_id && !session('sidebar_admin_hidden', false) && auth()->user()->hasPermission('admin.fusion.index'))
            <div class="menu-section" data-section-id="fusion">
                <div class="menu-section-header">Fusion & Démarrage</div>
                <a href="{{ route('admin.fusion.index') }}" class="menu-link-new {{ request()->routeIs('admin.fusion.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-bolt text-warning"></i>
                    <span>Fusion Données Mère</span>
                </a>
            </div>
            @endif

            {{-- SECTION 3 : GOUVERNANCE (Avec Opérations intégrées) --}}
            @php
                $hasGouvernance = auth()->user()->hasPermission('user_management') || 
                                  auth()->user()->hasPermission('admin.habilitations.index') ||
                                  auth()->user()->hasPermission('admin.switch') ||
                                  auth()->user()->hasPermission('admin.audit') ||
                                  auth()->user()->hasPermission('admin.access') ||
                                  auth()->user()->hasPermission('tasks.assign') ||
                                  auth()->user()->hasPermission('tasks.view_daily') ||
                                  auth()->user()->hasPermission('admin.approvals') ||
                                  auth()->user()->hasPermission('admin.tasks.index') ||
                                  auth()->user()->isAdmin();
            @endphp
            @if($hasGouvernance && !session('sidebar_admin_hidden', false))
            <div class="menu-section" data-section-id="gouvernance">
                <div class="menu-section-header">Gouvernance</div>
                @if(auth()->user()->hasPermission('user_management'))
                <a href="{{ route('user_management') }}" class="menu-link-new {{ request()->routeIs('user_management') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Équipe & Permissions</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.habilitations.index'))
                <a href="{{ route('admin.habilitations.index') }}" class="menu-link-new {{ request()->routeIs('admin.habilitations.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Modification Habilitation</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.switch'))
                <a href="{{ route('admin.switch') }}" class="menu-link-new {{ request()->routeIs('admin.switch') ? 'active' : '' }}">
                    <i class="fa-solid fa-repeat"></i>
                    <span>Switch Comptabilité</span>
                </a>
                @endif

                {{-- Intégration des Opérations --}}
                @if(auth()->user()->hasPermission('admin.audit'))
                <a href="{{ route('admin.audit') }}" class="menu-link-new {{ request()->routeIs('admin.audit') ? 'active' : '' }}">
                    <i class="fa-solid fa-history"></i>
                    <span>Traçabilité & Activités</span>
                </a>
                <a href="{{ route('admin.ia.dashboard') }}" class="menu-link-new {{ request()->routeIs('admin.ia.dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-microchip"></i>
                    <span>Audit de l'IA</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.audit'))
                <a href="{{ route('admin.archives') }}" class="menu-link-new {{ request()->routeIs('admin.archives') ? 'active' : '' }}">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>Archive des suppressions</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('admin.access'))
                <a href="{{ route('admin.access') }}" class="menu-link-new {{ request()->routeIs('admin.access') ? 'active' : '' }}">
                    <i class="fa-solid fa-lock-open"></i>
                    <span>Contrôle d'Accès</span>
                </a>
                @endif

                {{-- Quick Actions Sub-Section (Épurée) --}}
                @php
                    // La creation d'entreprise se fait depuis Mon Espace, plus depuis le menu.
                    $hasQuickActions = auth()->user()->hasPermission('admin.admins.create') ||
                                      auth()->user()->hasPermission('admin.secondary_admins.create');
                @endphp
                @if($hasQuickActions)
                <div class="mt-2 pt-2 border-top border-light">
                    <small class="text-muted text-uppercase px-3 mb-2 d-block" style="font-size: 0.65rem;">Création Rapide</small>
                    @if(auth()->user()->hasPermission('admin.admins.create'))
                    <a href="{{ route('admin.admins.create') }}" class="menu-link-quick {{ request()->routeIs('admin.admins.create') ? 'active' : '' }}">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Créer Administrateur</span>
                    </a>
                    @endif
                    @if(auth()->user()->hasPermission('admin.secondary_admins.create'))
                    <a href="{{ route('admin.secondary_admins.create') }}" class="menu-link-quick {{ request()->routeIs('admin.secondary_admins.create') ? 'active' : '' }}">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>Créer Admin Sécondaire</span>
                    </a>
                    @endif
                </div>
                @endif

                {{-- Gestion des Tâches (intégrée dans Gouvernance) --}}
                @php
                    $hasTasks = auth()->user()->hasPermission('tasks.assign') || 
                                auth()->user()->hasPermission('tasks.view_daily') ||
                                auth()->user()->hasPermission('admin.approvals') ||
                                auth()->user()->hasPermission('admin.tasks.index');
                @endphp
                @if($hasTasks)
                <div class="mt-2 pt-2 border-top border-light">
                    <small class="text-muted text-uppercase px-3 mb-2 d-block" style="font-size: 0.65rem;">Gestion des Tâches</small>
                    @if(auth()->user()->hasPermission('tasks.assign') || auth()->user()->hasPermission('admin.tasks.index'))
                    <a href="{{ route('admin.tasks.index') }}" class="menu-link-new {{ request()->routeIs('admin.tasks.index') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-pen"></i>
                        <span>Assigner Tâche</span>
                        @if(isset($tasksSentCount) && $tasksSentCount > 0)
                            <span class="badge bg-soft-primary text-primary rounded-pill ms-auto">{{ $tasksSentCount }}</span>
                        @endif
                    </a>
                    @endif
                    @if(auth()->user()->hasPermission('tasks.view_daily'))
                    <a href="{{ route('admin.tasks.daily') }}" class="menu-link-new {{ request()->routeIs('admin.tasks.daily') ? 'active' : '' }}">
                        <i class="fa-solid fa-list-check"></i>
                        <span>Tâches Quotidiennes</span>
                        @if(isset($tasksReceivedCompletedCount) && $tasksReceivedCompletedCount > 0)
                            <span class="badge bg-soft-success text-success rounded-pill ms-auto">{{ $tasksReceivedCompletedCount }}</span>
                        @endif
                    </a>
                    @endif
                    @if(auth()->user()->hasPermission('admin.approvals'))
                    <a href="{{ route('admin.approvals') }}" class="menu-link-new {{ request()->routeIs('admin.approvals') ? 'active' : '' }}">
                        <i class="fa-solid fa-stamp"></i>
                        <span>Approbations</span>
                        @if(isset($pendingApprovalsCount) && $pendingApprovalsCount > 0)
                            <span class="badge bg-soft-warning text-warning ms-auto">
                                {{ $pendingApprovalsCount }}
                            </span>
                        @endif
                    </a>
                    @endif
                </div>
                @endif
            </div>
            @endif


        @if ($isComptaAccountActive && (!auth()->user()->isSuperAdmin() || $isSwitched))
            {{-- MODE COMPTABILITÉ ACTIVE --}}

            {{-- Paramétrage masqué car doublon de Configuration Entreprise --}}

            {{-- Traitement --}}
            @php
                $showTraitement = auth()->user()->hasPermission('modal_saisie_direct') || 
                                  auth()->user()->hasPermission('accounting_entry_list') || 
                                  auth()->user()->hasPermission('brouillons.index') || 
                                  auth()->user()->hasPermission('immobilisations.index') || 
                                  auth()->user()->hasPermission('exercice_comptable');
            @endphp
            @if ($showTraitement)
            <div class="menu-section" data-section-id="traitement">
                <div class="menu-section-header">Traitement</div>
                @if(auth()->user()->hasPermission('modal_saisie_direct'))
                <a href="{{ route('accounting_entry_real', ['open' => 1]) }}" class="menu-link-new {{ request()->routeIs('accounting_entry_real') ? 'active' : '' }}">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Nouvelle saisie</span>
                </a>
                @endif
                <a href="{{ route('ecriture.bulk-scan') }}" class="menu-link-new {{ request()->routeIs('ecriture.bulk-scan') ? 'active' : '' }}">
                    <i class="fa-solid fa-copy"></i>
                    <span>Centre de Scan</span>
                </a>
                <a href="{{ route('excel_ia.index') }}" class="menu-link-new {{ request()->routeIs('excel_ia.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-robot text-primary"></i>
                    <span>Analyse Comptable IA</span>
                </a>
                <a href="{{ route('admin.ia.chat') }}" class="menu-link-new {{ request()->routeIs('admin.ia.chat') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-astronaut text-info"></i>
                    <span>Assistant IA Premium</span>
                </a>
                @if(auth()->user()->hasPermission('accounting_entry_list'))
                <a href="{{ route('ecriture.hub') }}" class="menu-link-new {{ request()->routeIs('ecriture.hub') ? 'active' : '' }}">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Hub des Journaux</span>
                </a>
                @if(auth()->user()->hasPermission('plan_tiers'))
                <a href="{{ route('plan_tiers.hub') }}" class="menu-link-new {{ request()->routeIs('plan_tiers.hub') ? 'active' : '' }}">
                    <i class="fa-solid fa-address-book"></i>
                    <span>Hub des Tiers</span>
                </a>
                @endif
                <a href="{{ route('accounting_entry_list') }}" class="menu-link-new {{ request()->routeIs('accounting_entry_list') ? 'active' : '' }}">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Liste des écritures</span>
                </a>
                <a href="{{ route('lettrage.index') }}" class="menu-link-new {{ request()->routeIs('lettrage.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-link"></i>
                    <span>Lettrage des Tiers</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('ecriture.rejected'))
                <a href="{{ route('ecriture.rejected') }}" class="menu-link-new {{ request()->routeIs('ecriture.rejected') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-circle-xmark"></i>
                    <span>Écritures rejetées</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('brouillons.index'))
                <a href="{{ route('brouillons.index') }}" class="menu-link-new {{ request()->routeIs('brouillons.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-pen"></i>
                    <span>Brouillons</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('exercice_comptable'))
                <a href="{{ route('exercice_comptable') }}" class="menu-link-new {{ request()->routeIs('exercice_comptable') ? 'active' : '' }}">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Exercice comptable</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('immobilisations.index'))
                <a href="{{ route('immobilisations.index') }}" class="menu-link-new {{ request()->routeIs('immobilisations.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-city"></i>
                    <span>Immobilisations</span>
                </a>
                @endif

                {{-- Rapprochement Bancaire déplacé dans Traitement --}}
                <a href="{{ route('rapprochement.index') }}" class="menu-link-new {{ request()->routeIs('rapprochement.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-balance-scale text-emerald-600"></i>
                    <span>Rapprochement Bancaire</span>
                </a>
            </div>
            @endif

            {{-- SECTION : CORRECTION ÉCRITURE --}}
            <div class="menu-section">
                <div class="menu-section-header">Correction Écriture</div>
                <a href="{{ route('adjustment.duplicates') }}" class="menu-link-new {{ request()->routeIs('adjustment.duplicates') ? 'active' : '' }}">
                    <i class="fa-solid fa-clone"></i>
                    <span>Détection de doublons</span>
                </a>
                <a href="{{ route('adjustment.bulk_edit') }}" class="menu-link-new {{ request()->routeIs('adjustment.bulk_edit') ? 'active' : '' }}">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Modification par lot</span>
                </a>
                <a href="{{ route('adjustment.reimputation') }}" class="menu-link-new {{ request()->routeIs('adjustment.reimputation') ? 'active' : '' }}">
                    <i class="fa-solid fa-right-left"></i>
                    <span>Réimputation</span>
                </a>
                <a href="{{ route('adjustment.copie') }}" class="menu-link-new {{ request()->routeIs('adjustment.copie') ? 'active' : '' }}">
                    <i class="fa-solid fa-copy"></i>
                    <span>Copie d'écritures</span>
                </a>
            </div>

            {{-- ANALYTIQUE --}}
            @php
                $showAnalytique = auth()->user()->hasPermission('analytique.axes.index') || 
                                  auth()->user()->hasPermission('analytique.sections.index') || 
                                  auth()->user()->hasPermission('analytique.regles.index') || 
                                  auth()->user()->hasPermission('analytique.balance');
            @endphp
            @if ($showAnalytique)
            <div class="menu-section">
                <div class="menu-section-header">Analytique</div>
                @if(auth()->user()->hasPermission('analytique.axes.index'))
                <a href="{{ route('analytique.axes.index') }}" class="menu-link-new {{ request()->routeIs('analytique.axes.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Axes analytiques</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('analytique.sections.index'))
                <a href="{{ route('analytique.sections.index') }}" class="menu-link-new {{ request()->routeIs('analytique.sections.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-shapes"></i>
                    <span>Sections analytiques</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('analytique.regles.index'))
                <a href="{{ route('analytique.regles.index') }}" class="menu-link-new {{ request()->routeIs('analytique.regles.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-gears"></i>
                    <span>Règles de ventilation</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('analytique.balance'))
                <a href="{{ route('analytique.balance') }}" class="menu-link-new {{ request()->routeIs('analytique.balance') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Balance analytique</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('analytique.grand_livre'))
                <a href="{{ route('analytique.grand_livre') }}" class="menu-link-new {{ request()->routeIs('analytique.grand_livre') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-contract"></i>
                    <span>Grand livre analytique</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('analytique.resultat') || auth()->user()->hasPermission('analytique.balance'))
                <a href="{{ route('analytique.resultat') }}" class="menu-link-new {{ request()->routeIs('analytique.resultat') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Résultat analytique</span>
                </a>
                @endif
            </div>
            @endif

            {{-- Rapports Comptables --}}
            @php
                $showRapports = auth()->user()->hasPermission('accounting_ledger') || 
                                auth()->user()->hasPermission('accounting_balance');
            @endphp
            @if ($showRapports)
            <div class="menu-section">
                <div class="menu-section-header">Rapports</div>
                @if(auth()->user()->hasPermission('accounting_ledger'))
                <a href="{{ route('accounting_ledger') }}" class="menu-link-new {{ request()->is('accounting_ledger') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Grand livre</span>
                </a>
                <a href="{{ route('accounting_ledger_tiers') }}" class="menu-link-new {{ request()->is('accounting_ledger_tiers') ? 'active' : '' }}">
                    <i class="fa-solid fa-address-book"></i>
                    <span>Grand livre des tiers</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('accounting_balance'))
                <a href="{{ route('accounting_balance') }}" class="menu-link-new {{ request()->is('accounting_balance') ? 'active' : '' }}">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <span>Balance</span>
                </a>
                <a href="{{ route('accounting_balance_tiers') }}" class="menu-link-new {{ request()->is('accounting_balance_tiers') ? 'active' : '' }}">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <span>Balance des tiers</span>
                </a>
                @endif
            </div>
            @endif

            {{-- États Financiers --}}
            @php
                $showEtatsFinanciers = auth()->user()->hasPermission('bilan') || 
                                       auth()->user()->hasPermission('compte_resultat');
            @endphp
            @if ($showEtatsFinanciers)
            <div class="menu-section">
                <div class="menu-section-header">ETATS FINANCIERS</div>
                @if(auth()->user()->hasPermission('bilan'))
                <a href="{{ route('reporting.bilan') }}" class="menu-link-new {{ request()->routeIs('reporting.bilan') ? 'active' : '' }}">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Bilan Actif/Passif</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('compte_resultat'))
                <a href="{{ route('reporting.resultat') }}" class="menu-link-new {{ request()->routeIs('reporting.resultat') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Compte de Résultat</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('compte_resultat'))
                <a href="{{ route('reporting.monthly_resultat') }}" class="menu-link-new {{ request()->routeIs('reporting.monthly_resultat') ? 'active' : '' }}">
                    <i class="fa-solid fa-calendar-week"></i>
                    <span>Compte d'Exp. Mensuel</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('bilan'))
                <a href="{{ route('reporting.tft') }}" class="menu-link-new {{ request()->routeIs('reporting.tft') ? 'active' : '' }}">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                    <span>Flux de Trésorerie (TFT)</span>
                </a>
                @endif
                @if(auth()->user()->hasPermission('bilan'))
                <a href="{{ route('reporting.tft_personalized') }}" class="menu-link-new {{ request()->routeIs('reporting.tft_personalized') ? 'active' : '' }}">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>TFT Mensuel</span>
                </a>
                @endif
            </div>
            @endif

            {{-- DOCUMENT COMPTABLE --}}
            @php
                $showDocumentComptable = auth()->user()->hasPermission('bilan');
            @endphp
            @if ($showDocumentComptable)
            <div class="menu-section">
                <div class="menu-section-header">DOCUMENT COMPTABLE</div>
                {{-- Liasse Système Normal (SN) — CA > 50 M FCFA --}}
                <a href="{{ route('reporting.liasse.regime', ['regime' => 'sn']) }}"
                   class="menu-link-new {{ request()->is('reporting/liasse/sn*') ? 'active' : '' }}"
                   title="Système Normal — Entreprises CA > 50M FCFA">
                    <i class="fa-solid fa-file-invoice" style="{{ request()->is('reporting/liasse/sn*') ? '' : 'color:#2563eb' }}"></i>
                    <span>Liasse Fiscale <span class="badge bg-primary ms-1" style="font-size:0.6rem;vertical-align:middle;">SN</span></span>
                </a>
                {{-- Liasse Système Minimal de Trésorerie (SMT) — CA ≤ 50 M FCFA --}}
                <a href="{{ route('reporting.liasse.regime', ['regime' => 'smt']) }}"
                   class="menu-link-new {{ request()->is('reporting/liasse/smt*') ? 'active' : '' }}"
                   title="Système Minimal de Trésorerie — Entreprises CA ≤ 50M FCFA">
                    <i class="fa-solid fa-file-lines" style="{{ request()->is('reporting/liasse/smt*') ? '' : 'color:#d97706' }}"></i>
                    <span>Liasse Fiscale <span class="badge ms-1" style="font-size:0.6rem;vertical-align:middle;background:#d97706;color:#fff;">SMT</span></span>
                </a>
            </div>
            @endif


        @else
            {{-- MODE GOUVERNANCE (Pas de compte actif) --}}
            @if (auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin())
            <div class="menu-section">
                <div class="menu-section-header">Organisation</div>
                <a href="{{ route('compta_accounts.index') }}" class="menu-link-new {{ request()->routeIs('compta_accounts.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-building"></i>
                    <span>Mes Compagnies</span>
                </a>
                @if(auth()->user()->aAccesMonEspace())
                <a href="{{ route('accountant.space', ['page' => 'companies']) }}" class="menu-link-new">
                    <i class="fa-solid fa-briefcase"></i>
                    <span>Mon Espace</span>
                </a>
                @endif
                <a href="{{ route('user_management') }}" class="menu-link-new {{ request()->routeIs('user_management') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-group"></i>
                    <span>Équipe & Permissions</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-header">Support</div>
                <a href="#" class="menu-link-new text-muted" title="Bientôt disponible">
                    <i class="fa-solid fa-history"></i>
                    <span>Traçabilité & Activités</span>
                    <span class="badge bg-soft-primary text-primary ms-auto" style="font-size: 10px;">Pro</span>
                </a>
            </div>
            @endif

            @if (auth()->user()->isSuperAdmin())
                {{-- Garder l'existant pour Super Admin si non géré par superadmin_sidebar --}}
                <div class="menu-section">
                    <div class="menu-section-header">Paramétrage SuperAdmin</div>
                    <a href="{{ route('superadmin.liaisons.index') }}" class="menu-link-new {{ request()->routeIs('superadmin.liaisons.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-link"></i>
                        <span>Liaisons SELFLOW</span>
                    </a>
                    <a href="{{ route('plan_comptable') }}" class="menu-link-new">
                        <i class="fa-solid fa-book"></i>
                        <span>Plan comptable</span>
                    </a>
                </div>
            @endif
        @endif
        @else
            {{-- MENU POUR MON ESPACE (GÉRANT / COMPTABLE) --}}
            <div class="menu-section">
                <div class="menu-section-header">Mon Espace</div>
                
                <a href="{{ route('accountant.space') }}?page=dashboard" 
                   class="menu-link-new {{ (!request('page') || request('page') === 'dashboard') ? 'active' : '' }}"
                   data-page-link="dashboard"
                   onclick="if(window.showSection) { showSection('dashboard'); return false; }">
                    <i class="fa-solid fa-th-large"></i>
                    <span>Tableau de bord</span>
                </a>

                <a href="{{ route('accountant.space') }}?page=companies" 
                   class="menu-link-new {{ (request('page') === 'companies') ? 'active' : '' }}"
                   data-page-link="companies"
                   onclick="if(window.showSection) { showSection('companies'); return false; }">
                    <i class="fa-solid fa-building"></i>
                    <span>Mes Sociétés</span>
                </a>

                <a href="{{ route('accountant.space') }}?page=collaborators" 
                   class="menu-link-new {{ (request('page') === 'collaborators') ? 'active' : '' }}"
                   data-page-link="collaborators"
                   onclick="if(window.showSection) { showSection('collaborators'); return false; }">
                    <i class="fa-solid fa-users"></i>
                    <span>Collaborateurs</span>
                </a>

                @if(auth()->user()->peutFusionner())
                <a href="{{ route('accountant.space') }}?page=fusion" 
                   class="menu-link-new {{ (request('page') === 'fusion') ? 'active' : '' }}"
                   data-page-link="fusion"
                   onclick="if(window.showSection) { showSection('fusion'); return false; }">
                    <i class="fa-solid fa-code-branch"></i>
                    <span>Fusion & Déversement</span>
                </a>
                @endif

                <a href="{{ route('accountant.space') }}?page=informations"
                   class="menu-link-new {{ (request('page') === 'informations') ? 'active' : '' }}"
                   data-page-link="informations"
                   onclick="if(window.showSection) { showSection('informations'); return false; }">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Informations</span>
                </a>

                <a href="{{ route('accountant.space') }}?page=chat" 
                   class="menu-link-new {{ (request('page') === 'chat') ? 'active' : '' }}"
                   data-page-link="chat"
                   onclick="if(window.showSection) { showSection('chat'); return false; }">
                    <i class="fa-solid fa-comments"></i>
                    <span>Messagerie</span>
                </a>
            </div>

            <div class="menu-section" style="margin-top: auto; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                <form method="POST" action="{{ route('accountant.space.bulk_generate') }}" onsubmit="return confirm('Générer automatiquement les codes pour toutes les entreprises sans code ?');">
                    @csrf
                    <button type="submit" class="menu-link-new text-success w-100 border-0 bg-transparent text-start py-2" style="font-size: 13px; color: #10b981;">
                        <i class="fa-solid fa-magic"></i>
                        <span>Auto-générer codes</span>
                    </button>
                </form>
                @if(auth()->user()->peutCreerDesSocietes())
                <button onclick="document.getElementById('modal-new-company').classList.add('show')" class="menu-link-new text-primary w-100 border-0 bg-transparent text-start py-2" style="font-size: 13px; color: #1e40af;">
                    <i class="fa-solid fa-plus"></i>
                    <span>Nouvelle société</span>
                </button>
                @endif
                <button onclick="document.getElementById('modal-new-member').classList.add('show')" class="menu-link-new text-violet w-100 border-0 bg-transparent text-start py-2" style="font-size: 13px; color: #8b5cf6;">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Nouveau membre</span>
                </button>
            </div>
        @endif
    </nav>

    @auth
    <div class="sidebar-footer">
        <div class="flex items-center justify-between">
            <a href="#" class="user-profile-mini">
                <div class="user-avatar-sidebar">
                    {{ auth()->user()->initiales }}
                </div>
                <div class="user-info-sidebar">
                    <span class="user-name-sidebar">@if($isSwitched && auth()->user()->isSuperAdmin()) Administrateur @else {{ auth()->user()->name }} @endif</span>
                    <span class="user-role-sidebar">@if($isSwitched && auth()->user()->isSuperAdmin()) Administrateur @else {{ auth()->user()->role }} @endif</span>
                </div>
            </a>
            <a href="#" class="logout-btn-sidebar" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fa-solid fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
    @endauth
</div>

<script>
// Intercepteur pour forcer le rechargement complet après changement d'exercice
document.addEventListener('DOMContentLoaded', function() {
    const exerciceSwitchLinks = document.querySelectorAll('[data-exercice-switch]');
    
    exerciceSwitchLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            
            // Afficher un indicateur de chargement
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Changement en cours...';
            this.style.pointerEvents = 'none';
            
            // Faire la requête de switch
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                // Recharger complètement la page pour appliquer le nouveau contexte
                window.location.reload();
            })
            .catch(error => {
                console.error('Erreur lors du changement d\'exercice:', error);
                // Fallback: redirection classique
                window.location.href = url;
            });
        });
    });
});
</script>
@endif

@include('components.navigation_instantanee')

<!-- Ancien sidebar (supprimé car doublon inutile) -->

