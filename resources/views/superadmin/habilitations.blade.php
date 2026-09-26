<!DOCTYPE html>
<html lang="fr" class="layout-compact">
@include('components.head')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    .glass-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .premium-modal-content {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 1);
        border-radius: 20px;
        box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.1);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .btn-premium {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        color: white !important;
        border: none;
        border-radius: 12px;
        padding: 0.6rem 1.2rem;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .btn-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(30, 64, 175, 0.3);
    }

    .user-select-btn.active {
        background: #eff6ff !important;
        border-left: 4px solid #1e40af !important;
    }

    .restricted-permission {
        opacity: 0.5;
        pointer-events: none;
        background-color: #f8fafc;
    }

    .custom-switch-premium .form-check-input:checked {
        background-color: #1e40af;
        border-color: #1e40af;
    }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')
            <div class="layout-page">
                @include('components.header', ['page_title' => 'Gestion Globale des Habilitations'])
                
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Habilitations Système</h5>
                                <p class="text-muted small mb-0">Supervisez et modifiez les accès de tous les utilisateurs du réseau.</p>
                            </div>
                        </div>

                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="row">
                            <!-- Liste des utilisateurs -->
                            <div class="col-md-4 mb-4">
                                <div class="card glass-card h-100">
                                    <div class="card-header border-bottom py-3">
                                        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-users me-2 text-primary"></i>Tous les Utilisateurs</h6>
                                    </div>
                                    <div class="list-group list-group-flush user-list overflow-auto" style="max-height: 700px;">
                                        @foreach($users as $user)
                                            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center p-3 user-select-btn" 
                                               data-user-id="{{ $user->id }}"
                                               data-user-name="{{ $user->name }} {{ $user->last_name }}"
                                               data-user-role="{{ $user->role }}"
                                               data-user-email="{{ $user->email_adresse }}"
                                               data-company-name="{{ $user->company->company_name ?? 'N/A' }}"
                                               data-permissions='{{ json_encode($user->getHabilitations()) }}'>
                                                <div class="avatar avatar-sm me-3">
                                                    <span class="avatar-initial rounded-circle bg-label-primary">
                                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 text-dark small">{{ $user->name }} {{ $user->last_name }}</h6>
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <span class="badge {{ $user->isSuperAdmin() ? 'bg-danger' : ($user->isAdmin() ? 'bg-primary' : 'bg-success') }} text-[9px] py-0.5">
                                                            {{ ucfirst($user->role) }}
                                                        </span>
                                                        <small class="text-muted text-[10px]">{{ $user->company->company_name ?? 'Freelance/System' }}</small>
                                                    </div>
                                                </div>
                                                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="card-footer py-2 border-top bg-light/50">
                                        {{ $users->links() }}
                                    </div>
                                </div>
                            </div>

                            <!-- Panneau des permissions -->
                            <div class="col-md-8 mb-4">
                                <div class="card glass-card h-100" id="permissions-panel" style="display:none;">
                                    <div class="card-header border-bottom d-flex justify-content-between align-items-center py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar bg-soft-primary text-primary p-2 rounded-circle" id="selected-user-avatar">
                                                <i class="fa-solid fa-user-shield fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold" id="selected-user-name">Permissions...</h6>
                                                <small class="text-muted" id="selected-user-info"></small>
                                            </div>
                                        </div>
                                        <button type="submit" form="permissions-form" class="btn btn-premium btn-sm px-4">
                                            <i class="fa-solid fa-save me-2"></i>Mettre à jour
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <form id="permissions-form" method="POST" action="">
                                            @csrf
                                            
                                            <div id="permissions-container" class="p-4 overflow-auto" style="max-height: 700px;">
                                                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                                                    <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Périmètre des Habilitations</span>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-xs btn-outline-primary rounded-lg font-bold" style="font-size: 0.75rem; padding: 3px 10px;" id="btn-check-all">Tout cocher</button>
                                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-lg font-bold" style="font-size: 0.75rem; padding: 3px 10px;" id="btn-uncheck-all">Tout décocher</button>
                                                    </div>
                                                </div>
                                                @foreach($modules as $groupName => $permissions)
                                                    <div class="mb-4 permission-section" data-section-name="{{ $groupName }}" data-is-superadmin-section="{{ str_contains($groupName, 'Super Admin') ? 'true' : 'false' }}">
                                                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-1">
                                                            <h6 class="text-xs font-bold text-slate-600 uppercase mb-0">{{ $groupName }}</h6>
                                                            <span class="badge bg-slate-100 text-slate-500 rounded-pill px-2 py-0.5 text-[10px]">{{ count($permissions) }} permissions</span>
                                                        </div>
                                                        <div class="row g-2">
                                                            @foreach($permissions as $key => $label)
                                                                <div class="col-md-6 col-lg-4">
                                                                    <label class="d-flex align-items-center gap-2 p-2 bg-white rounded-lg border border-slate-200/70 hover:border-blue-300 cursor-pointer shadow-2xs w-100 permission-card">
                                                                        <input class="form-check-input mt-0 permission-checkbox" type="checkbox" name="habilitations[{{ $key }}]" value="1" id="perm_{{ $key }}" style="width: 1.1rem; height: 1.1rem; cursor: pointer;">
                                                                        <span class="text-xs font-semibold text-slate-700 ms-1" style="line-height: 1.2;">{{ $label }}</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Empty State -->
                                <div class="card glass-card h-100 d-flex justify-content-center align-items-center text-center p-5 border-0 shadow-sm" id="empty-state">
                                    <div class="py-5">
                                        <div class="avatar avatar-xl bg-blue-50 text-blue-600 rounded-circle mb-4 mx-auto d-flex align-items-center justify-content-center">
                                            <i class="fa-solid fa-key fs-1"></i>
                                        </div>
                                        <h4 class="fw-bold text-slate-900 border-0">Gestion Centrale des Accès</h4>
                                        <p class="text-slate-500 mb-0" style="max-width: 400px;">Sélectionnez un administrateur ou un utilisateur dans la liste de gauche pour configurer ses habilitations globales sur le système.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    @include('components.footer')
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userButtons = document.querySelectorAll('.user-select-btn');
            const permissionsPanel = document.getElementById('permissions-panel');
            const emptyState = document.getElementById('empty-state');
            const userNameTitle = document.getElementById('selected-user-name');
            const userInfoText = document.getElementById('selected-user-info');
            const form = document.getElementById('permissions-form');
            const checkboxes = document.querySelectorAll('.permission-checkbox');

            const currentUserId = {{ auth()->id() }};
            const isPrimarySA = {{ auth()->user()->isPrimarySuperAdmin() ? 'true' : 'false' }};

            document.getElementById('btn-check-all')?.addEventListener('click', () => {
                document.querySelectorAll('.permission-section').forEach(sec => {
                    if (sec.style.display !== 'none') {
                        sec.querySelectorAll('.permission-checkbox:not(:disabled)').forEach(cb => cb.checked = true);
                    }
                });
            });

            document.getElementById('btn-uncheck-all')?.addEventListener('click', () => {
                document.querySelectorAll('.permission-section').forEach(sec => {
                    if (sec.style.display !== 'none') {
                        sec.querySelectorAll('.permission-checkbox:not(:disabled)').forEach(cb => cb.checked = false);
                    }
                });
            });

            userButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    userButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    emptyState.style.display = 'none';
                    permissionsPanel.style.display = 'block';

                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    const userRole = this.getAttribute('data-user-role');
                    const company = this.getAttribute('data-company-name');
                    const email = this.getAttribute('data-user-email');

                    userNameTitle.textContent = userName;
                    userInfoText.textContent = `${userRole} | ${company} | ${email}`;
                    
                    form.action = `/superadmin/habilitations/update/${userId}`;

                    // Parse current permissions
                    let userHasPermissions = {};
                    try {
                        const dataHabs = this.getAttribute('data-permissions');
                        userHasPermissions = dataHabs ? JSON.parse(dataHabs) : {};
                    } catch (err) {
                        console.error("Erreur parsing permissions:", err);
                    }

                    const sections = document.querySelectorAll('.permission-section');
                    sections.forEach(section => {
                        const isSaSection = section.getAttribute('data-is-superadmin-section') === 'true';
                        
                        // Masquer les sections Super Admin si l'utilisateur ciblé n'est pas Super Admin
                        if (isSaSection && userRole !== 'super_admin') {
                            section.style.display = 'none';
                        } else {
                            section.style.display = 'block';
                        }

                        const sectionCheckboxes = section.querySelectorAll('.permission-checkbox');
                        sectionCheckboxes.forEach(cb => {
                            const key = cb.name.match(/habilitations\[(.+)\]/)[1];
                            const card = cb.closest('.permission-card');
                            
                            // Cocher / Décocher
                            if (userHasPermissions[key] == "1" || userHasPermissions[key] === true || userHasPermissions[key] === 1) {
                                cb.checked = true;
                            } else {
                                cb.checked = false;
                            }

                            // Désactivation éventuelle
                            let isForbidden = false;
                            if (userId == currentUserId) {
                                isForbidden = true;
                            } else if (userRole === 'super_admin' && !isPrimarySA) {
                                isForbidden = true;
                            }

                            if (isForbidden) {
                                cb.disabled = true;
                                if (card) card.classList.add('opacity-50', 'pointer-events-none');
                            } else {
                                cb.disabled = false;
                                if (card) card.classList.remove('opacity-50', 'pointer-events-none');
                            }
                        });
                    });
                });
            });
        });
    </script>
</body>
</html>
