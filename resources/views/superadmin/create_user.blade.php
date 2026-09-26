<!DOCTYPE html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free">

@include('components.head')

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')

            <div class="layout-page">
                @include('components.header', ['page_title' => $title ?? 'Créer un Comptable'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <!-- Header Standardisé -->
                        <div class="d-flex justify-content-between align-items-center mb-6">
                            <div>
                                <h5 class="mb-1 text-premium-gradient">Gouvernance / Créer un Comptable</h5>
                                <p class="text-muted small mb-0">Définissez un nouveau profil utilisateur et attribuez des habilitations.</p>
                            </div>
                        </div>

                        <form action="{{ route('superadmin.users.store') }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Section 1: Profil et Identité -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                                    <h5 class="fw-bold mb-4 text-primary border-bottom pb-2">
                                        <i class="fa-solid fa-user-circle me-2"></i>Identité de l'Utilisateur
                                    </h5>
                                    
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label fw-semibold">Prénom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="last_name" class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                                            @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-12">
                                            <label for="email_adresse" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email_adresse') is-invalid @enderror" id="email_adresse" name="email_adresse" value="{{ old('email_adresse') }}" required>
                                            @error('email_adresse') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-12">
                                            <label for="password" class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Assignation et Rôle -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                                    <h5 class="fw-bold mb-4 text-primary border-bottom pb-2">
                                        <i class="fa-solid fa-briefcase me-2"></i>Assignation et Rôle
                                    </h5>
                                    
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="company_id" class="form-label fw-semibold">Entreprise <span class="text-danger">*</span></label>
                                            <select class="form-select @error('company_id') is-invalid @enderror" id="company_id" name="company_id" required>
                                                <option value="">Sélectionner une entreprise</option>
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->id }}" data-is-sub="{{ $company->parent_company_id ? 'true' : 'false' }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                                        {{ $company->company_name }} 
                                                        @if($company->parent) (Filiale de : {{ $company->parent->company_name }}) @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('company_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="role" class="form-label fw-semibold">Rôle Plateforme <span class="text-danger">*</span></label>
                                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                                <option value="comptable" {{ old('role', 'comptable') == 'comptable' ? 'selected' : '' }}>Sur habilitations (à cocher ci-dessous)</option>
                                                <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>Utilisateur Standard</option>
                                                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Administrateur Entreprise</option>
                                            </select>
                                            @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="pack_id" class="form-label fw-semibold">Pack d'Abonnement</label>
                                            <select class="form-select @error('pack_id') is-invalid @enderror" id="pack_id" name="pack_id">
                                                <option value="">Aucun Pack spécifique</option>
                                                @foreach($packs as $pack)
                                                    <option value="{{ $pack->id }}" {{ old('pack_id') == $pack->id ? 'selected' : '' }}>
                                                        {{ $pack->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('pack_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="is_active" class="form-label fw-semibold">Statut Compte <span class="text-danger">*</span></label>
                                            <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active" required>
                                                <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Actif</option>
                                                <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Suspendu</option>
                                            </select>
                                            @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Habilitations (Permissions) -->
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                    <h5 class="fw-bold mb-4 text-primary border-bottom pb-2 d-flex justify-content-between align-items-center">
                                        <span><i class="fa-solid fa-shield-halved me-2"></i>Habilitations Spécifiques</span>
                                        @include('components.tout_cocher', ['cible' => 'grilleSaCreationUtilisateur'])
                                    </h5>
                                    
                                    <div class="row g-3" id="grilleSaCreationUtilisateur">
                                        @foreach($permissions as $section => $groupPermissions)
                                            @if(!str_contains($section, 'Super Admin'))
                                                <div class="col-12 permission-section mb-4" data-section-name="{{ $section }}">
                                                    <div class="mb-2">
                                                        <h6 class="text-[10px] font-black uppercase text-slate-500 tracking-wider border-bottom pb-1" style="color: #475569;">{{ $section }}</h6>
                                                    </div>
                                                    <div class="row g-2">
                                                        @foreach($groupPermissions as $key => $label)
                                                            <div class="col-md-6 col-lg-4">
                                                                <label class="d-flex align-items-center gap-2 p-2 bg-white rounded-lg border border-slate-200/70 hover:border-blue-300 cursor-pointer shadow-2xs w-100">
                                                                    <input class="form-check-input mt-0 permission-checkbox" type="checkbox" name="habilitations[{{ $key }}]" value="1" id="hab_{{ $key }}" 
                                                                        {{ is_array(old('habilitations')) && isset(old('habilitations')[$key]) ? 'checked' : '' }} style="width: 1.1rem; height: 1.1rem; cursor: pointer;">
                                                                    <span class="text-xs font-semibold text-slate-700 mb-0" style="line-height: 1.2;">{{ $label }}</span>
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="bg-blue-50 rounded-xl border border-blue-200 p-6 sticky-top" style="top: 20px;">
                                    <h6 class="fw-bold text-blue-900 mb-3 d-flex align-items-center">
                                        <i class="fa-solid fa-info-circle me-2"></i>Actions
                                    </h6>
                                    <p class="text-xs text-blue-800 mb-4 lh-lg">
                                        L'utilisateur recevra ses accès immédiatement après la création. Assurez-vous d'avoir configuré les habilitations correctement.
                                    </p>
                                    <div class="d-grid gap-3">
                                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                            <i class="fa-solid fa-user-plus me-2"></i>Créer l'utilisateur
                                        </button>
                                        <a href="{{ route('superadmin.users') }}" class="btn btn-white border shadow-sm">
                                            <i class="fa-solid fa-times me-2"></i>Annuler
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>

                @include('components.footer')
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const companySelect = document.getElementById('company_id');
            const checkboxes = document.querySelectorAll('.permission-checkbox');
            const sections = document.querySelectorAll('.permission-section');

            function updatePermissions() {
                const role = roleSelect.value;
                const selectedOption = companySelect.options[companySelect.selectedIndex];
                const isSubCompany = selectedOption ? selectedOption.getAttribute('data-is-sub') === 'true' : false;
                
                sections.forEach(section => {
                    const sectionName = section.getAttribute('data-section-name');
                    const sectionCheckboxes = section.querySelectorAll('.permission-checkbox');
                    let hasAllowedPermission = false;

                    sectionCheckboxes.forEach(cb => {
                        const labelElem = cb.closest('label');
                        const key = cb.id.replace('hab_', '');
                        
                        let isRestricted = false;
                        let forceUnchecked = false;

                        // Restriction Fusion (Sub-companies only)
                        if (sectionName.includes('Fusion & Démarrage') && !isSubCompany) {
                            isRestricted = true;
                            forceUnchecked = true;
                        }

                        if (isRestricted) {
                            if (forceUnchecked) cb.checked = false;
                            cb.disabled = true;
                            if (labelElem) labelElem.style.display = 'none';
                        } else {
                            cb.disabled = false;
                            if (labelElem) labelElem.style.display = 'flex';
                            hasAllowedPermission = true;
                        }
                    });

                    if (!hasAllowedPermission) {
                        section.style.display = 'none';
                    } else {
                        section.style.display = 'block';
                    }
                });
            }

            roleSelect.addEventListener('change', updatePermissions);
            companySelect.addEventListener('change', updatePermissions);
            updatePermissions();
        });
    </script>

            companySelect.addEventListener('change', updatePermissions);
            
            updatePermissions();
        });
    </script>
</body>
</html>
