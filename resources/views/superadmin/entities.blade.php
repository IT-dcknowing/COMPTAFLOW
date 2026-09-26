<!doctype html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

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
        background: #ffffff;
        border: none;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .input-field-premium {
        transition: all 0.2s ease;
        border: 1.5px solid #cbd5e1 !important;
        background-color: #f8fafc !important;
        border-radius: 10px !important;
        padding: 0.65rem 0.9rem !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        color: #0f172a !important;
        width: 100%;
    }

    .input-field-premium:focus {
        border-color: #2563eb !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1) !important;
        outline: none !important;
    }

    .input-label-premium {
        font-size: 0.75rem !important;
        font-weight: 800 !important;
        color: #475569 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        margin-bottom: 0.4rem !important;
        display: block !important;
    }

    .btn-save-premium {
        padding: 0.65rem 1.5rem !important;
        border-radius: 10px !important;
        background-color: #1e40af !important;
        color: white !important;
        font-weight: 700 !important;
        transition: all 0.2s ease !important;
        border: none !important;
    }

    .btn-save-premium:hover {
        background-color: #1e3a8a !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
    }

    .btn-cancel-premium {
        padding: 0.65rem 1.5rem !important;
        border-radius: 10px !important;
        color: #64748b !important;
        font-weight: 700 !important;
        transition: all 0.2s ease !important;
        background: #f1f5f9 !important;
        border: 1px solid #cbd5e1 !important;
    }

    .btn-cancel-premium:hover {
        background-color: #e2e8f0 !important;
        color: #0f172a !important;
    }

    .text-gradient {
        background: linear-gradient(to right, #1e40af, #3b82f6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .status-badge.active {
        background-color: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .status-badge.inactive {
        background-color: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .status-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .status-badge.active .dot { background-color: #10b981; }
    .status-badge.inactive .dot { background-color: #ef4444; }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            @include('components.sidebar')

            <div class="layout-page">
                @include('components.header', ['page_title' => 'Gestion des Entités'])

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                    @if (session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert" id="successAlert">
                            <div class="flex items-center">
                                <i class="fa-solid fa-check-circle mr-2"></i>
                                {{ session('success') }}
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const successAlert = document.getElementById('successAlert');
                                if (successAlert) {
                                    setTimeout(() => {
                                        successAlert.style.display = 'none';
                                    }, 5000);
                                }
                            });
                        </script>
                    @endif

                    @if (session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert">
                            <div class="flex items-center">
                                <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                                <strong>Erreur :</strong> {{ session('error') }}
                            </div>
                        </div>
                    @endif

                    <!-- Action Bar compacte -->
                    <div class="d-flex align-items-center justify-content-between mb-3" style="gap: 1rem;">
                        <p class="text-slate-500 text-sm mb-0 font-medium">Pilotez les structures et les administrateurs du réseau.</p>
                        <a href="{{ route('superadmin.companies.create') }}" class="btn btn-primary rounded-pill px-4 py-2 text-sm font-bold shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Nouvelle Entité</span>
                        </a>
                    </div>

                    @php $companyModals = []; @endphp

                    <!-- TABLEAU DES ENTITÉS ET FILTRE EN LIGNE (Modèle de Plan) -->
                    <div class="glass-card overflow-hidden mb-4">

                        @include('components.filtre_colonnes', [
                            'corps' => '#corpsEntites',
                            'nom' => 'compagnies',
                            'filtres' => [
                                ['cle' => 'compagnie', 'libelle' => 'Recherche Compagnie', 'type' => 'liste'],
                                ['cle' => 'admin', 'libelle' => 'Administrateur', 'type' => 'liste'],
                                ['cle' => 'statut', 'libelle' => 'Statut', 'type' => 'liste'],
                                ['cle' => 'forme', 'libelle' => 'Forme Juridique', 'type' => 'liste'],
                                ['cle' => 'type', 'libelle' => 'Type d\'entité', 'type' => 'liste'],
                            ],
                        ])

                        <div class="table-responsive">
                            <table class="w-full text-left border-collapse table-fixed" id="entitiesTable">
                                <thead>
                                    <tr class="bg-slate-50/50 border-b border-slate-100 uppercase text-[11px] font-black tracking-widest text-slate-400">
                                        <th class="px-8 py-5 w-[35%]">Compagnie</th>
                                        <th class="px-8 py-5 w-[22%]">Administrateur</th>
                                        <th class="px-8 py-5 text-center w-[13%]">Statut</th>
                                        <th class="px-8 py-5 text-center w-[10%]">Utilisateurs</th>
                                        <th class="px-8 py-5 text-right w-[20%]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50" id="corpsEntites">
                                    @forelse ($rootCompanies as $company)
                                        @php
                                            $admin = $company->admin_user;
                                            $companyModals[] = $company;
                                        @endphp
                                        {{-- Ligne pour Compagnie Mère --}}
                                        <tr class="hover:bg-slate-50/80 transition-colors"
                                            data-f-compagnie="{{ $company->company_name }}"
                                            data-f-admin="{{ $admin ? trim($admin->name . ' ' . $admin->last_name) : 'Non assigné' }}"
                                            data-f-statut="{{ $company->is_active ? 'Actif' : 'Inactif' }}"
                                            data-f-forme="{{ $company->juridique_form ?: 'Non spécifié' }}"
                                            data-f-type="Compagnie Mère">
                                            <td class="px-8 py-5">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 border border-blue-100">
                                                        <i class="fa-solid fa-building"></i>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-slate-800 text-base">{{ $company->company_name }}</span>
                                                        <div class="flex items-center gap-2 mt-0.5">
                                                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider">Compagnie Mère</span>
                                                            @if($company->juridique_form)
                                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[9px] font-bold rounded">{{ $company->juridique_form }}</span>
                                                            @endif
                                                            @if($company->company_code)
                                                            <span class="text-[10px] text-slate-400 font-mono"><i class="fas fa-key me-1"></i>{{ $company->company_code }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-5">
                                                <div class="flex flex-col">
                                                    <span class="font-semibold text-slate-700">{{ $admin ? trim($admin->name . ' ' . $admin->last_name) : 'Non assigné' }}</span>
                                                    <span class="text-xs text-slate-400">{{ $admin ? $admin->email_adresse : '—' }}</span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                @if ($company->is_active)
                                                    <span class="status-badge active"><span class="dot"></span> ACTIF</span>
                                                @else
                                                    <span class="status-badge inactive"><span class="dot"></span> INACTIF</span>
                                                @endif
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[11px] font-bold rounded-md">{{ $company->users->count() }}</span>
                                            </td>
                                            <td class="px-8 py-5 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <form action="{{ route('toggle', $company->id) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="w-9 h-9 flex items-center justify-center rounded-lg border transition-all {{ $company->is_active ? 'border-red-100 text-red-600 hover:bg-red-600 hover:text-white' : 'border-green-100 text-green-600 hover:bg-green-600 hover:text-white' }}" title="{{ $company->is_active ? 'Désactiver' : 'Activer' }}">
                                                            <i class="fa-solid {{ $company->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="w-9 h-9 flex items-center justify-center rounded-lg border border-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white transition-all"
                                                        data-bs-toggle="modal" data-bs-target="#editCompanyModal{{ $company->id }}">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <form action="{{ route('superadmin.companies.destroy', $company->id) }}" method="POST" class="inline" onsubmit="return confirm('Souhaitez-vous vraiment supprimer {{ $company->company_name }} ?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="w-9 h-9 flex items-center justify-center rounded-lg border border-slate-100 text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Lignes pour Sous-compagnies --}}
                                        @foreach ($company->children as $subCompany)
                                            @php
                                                $subAdmin = $subCompany->admin_user;
                                                $companyModals[] = $subCompany;
                                            @endphp
                                            <tr class="bg-slate-50/30 hover:bg-slate-100/50 transition-colors"
                                                data-f-compagnie="{{ $subCompany->company_name }}"
                                                data-f-admin="{{ $subAdmin ? trim($subAdmin->name . ' ' . $subAdmin->last_name) : 'Non assigné' }}"
                                                data-f-statut="{{ $subCompany->is_active ? 'Actif' : 'Inactif' }}"
                                                data-f-forme="{{ $subCompany->juridique_form ?: 'Non spécifié' }}"
                                                data-f-type="Sous-entité">
                                                <td class="px-8 py-4 pl-20">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center text-slate-400 border border-slate-100">
                                                            <i class="fa-solid fa-arrow-turn-up fa-rotate-90 text-[10px]"></i>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="font-bold text-slate-700">{{ $subCompany->company_name }}</span>
                                                            <div class="flex items-center gap-2 mt-0.5">
                                                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Sous-entité</span>
                                                                @if($subCompany->juridique_form)
                                                                <span class="px-1.5 py-0.2 bg-slate-100 text-slate-500 text-[9px] font-semibold rounded">{{ $subCompany->juridique_form }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-8 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="font-semibold text-slate-600 text-sm">{{ $subAdmin ? trim($subAdmin->name . ' ' . $subAdmin->last_name) : 'Non assigné' }}</span>
                                                        <span class="text-[11px] text-slate-400">{{ $subAdmin ? $subAdmin->email_adresse : '—' }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-8 py-4 text-center">
                                                    @if ($subCompany->is_active)
                                                        <span class="status-badge active"><span class="dot"></span> ACTIF</span>
                                                    @else
                                                        <span class="status-badge inactive"><span class="dot"></span> INACTIF</span>
                                                    @endif
                                                </td>
                                                <td class="px-8 py-4 text-center">
                                                    <span class="px-2 py-0.5 bg-white text-slate-500 text-[10px] font-bold rounded border border-slate-200">{{ $subCompany->users->count() }}</span>
                                                </td>
                                                <td class="px-8 py-4 text-right">
                                                    <div class="flex justify-end gap-1">
                                                        <form action="{{ route('toggle', $subCompany->id) }}" method="POST" class="inline">
                                                            @csrf
                                                            @method('PUT')
                                                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg border transition-all {{ $subCompany->is_active ? 'border-red-100 text-red-500 hover:bg-red-500 hover:text-white' : 'border-green-100 text-green-500 hover:bg-green-500 hover:text-white' }}" title="{{ $subCompany->is_active ? 'Désactiver' : 'Activer' }}">
                                                                <i class="fa-solid {{ $subCompany->is_active ? 'fa-ban' : 'fa-check' }} text-[10px]"></i>
                                                            </button>
                                                        </form>
                                                        <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white transition-all"
                                                            data-bs-toggle="modal" data-bs-target="#editCompanyModal{{ $subCompany->id }}">
                                                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                                        </button>
                                                        <form action="{{ route('superadmin.companies.destroy', $subCompany->id) }}" method="POST" class="inline" onsubmit="return confirm('Souhaitez-vous vraiment supprimer {{ $subCompany->company_name }} ?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                                                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-8 py-10 text-center">
                                                <div class="flex flex-col items-center gap-2">
                                                    <i class="fa-solid fa-folder-open text-slate-200 text-4xl"></i>
                                                    <p class="text-slate-400 font-semibold italic">Aucune compagnie n'a encore été créée.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- MODALS D'ÉDITION DES COMPAGNIES -->
                    @if(!empty($companyModals))
                        @foreach (array_unique($companyModals, SORT_REGULAR) as $companyItem)
                            @php $adminItem = $companyItem->admin_user; @endphp
                            <div class="modal fade" id="editCompanyModal{{ $companyItem->id }}" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px);">
                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content premium-modal-content">
                                        
                                        <!-- Header de la Modal -->
                                        <div class="modal-header bg-slate-900 text-white px-6 py-4 rounded-t-2xl border-b border-slate-800">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-blue-600/20 text-blue-400 d-flex align-items-center justify-content-center border border-blue-500/30">
                                                    <i class="fa-solid fa-building text-lg"></i>
                                                </div>
                                                <div>
                                                    <h5 class="modal-title font-extrabold text-white mb-0" style="font-size: 1.15rem;">
                                                        Modifier <span class="text-blue-400">{{ $companyItem->company_name }}</span>
                                                    </h5>
                                                    <span class="text-xs text-slate-400 font-medium">Code : {{ $companyItem->company_code ?: 'Non généré' }}</span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                        </div>

                                        <form action="{{ route('superadmin.companies.update', $companyItem->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            
                                            <!-- Corps Défilant de la Modal -->
                                            <div class="modal-body p-6 bg-slate-50/50" style="max-height: 72vh; overflow-y: auto;">
                                                
                                                <!-- SECTION 1 : INFORMATIONS COMPAGNIE -->
                                                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm mb-5">
                                                    <h6 class="text-[11px] font-black uppercase tracking-widest text-blue-700 mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                                                        <i class="fa-solid fa-building text-sm"></i>
                                                        Informations de l'Entreprise
                                                    </h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Nom de l'entreprise *</label>
                                                            <input type="text" name="company_name" class="input-field-premium" value="{{ $companyItem->company_name }}" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Forme Juridique *</label>
                                                            <select name="juridique_form" class="input-field-premium" required>
                                                                @foreach(['SARL','SA','SAS','SASU','SCI','EIRL','EI','Auto-entrepreneur','Association','ONG'] as $forme)
                                                                    <option value="{{ $forme }}" {{ $companyItem->juridique_form == $forme ? 'selected' : '' }}>{{ $forme }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Activité *</label>
                                                            <input type="text" name="activity" class="input-field-premium" value="{{ $companyItem->activity }}" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Capital Social (FCFA)</label>
                                                            <input type="number" step="0.01" name="social_capital" class="input-field-premium" value="{{ $companyItem->social_capital }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Statut Compte *</label>
                                                            <select name="is_active" class="input-field-premium" required>
                                                                <option value="1" {{ $companyItem->is_active ? 'selected' : '' }}>Actif</option>
                                                                <option value="0" {{ !$companyItem->is_active ? 'selected' : '' }}>Inactif</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Ville</label>
                                                            <input type="text" name="city" class="input-field-premium" value="{{ $companyItem->city }}">
                                                        </div>
                                                        <div class="col-md-8">
                                                            <label class="input-label-premium">Adresse Complète</label>
                                                            <input type="text" name="adresse" class="input-field-premium" value="{{ $companyItem->adresse }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Code Postal</label>
                                                            <input type="text" name="code_postal" class="input-field-premium" value="{{ $companyItem->code_postal }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Pays</label>
                                                            <input type="text" name="country" class="input-field-premium" value="{{ $companyItem->country }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Téléphone</label>
                                                            <input type="text" name="phone_number" class="input-field-premium" value="{{ $companyItem->phone_number }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">Mail Société (facultatif)</label>
                                                            <input type="email" name="email_adresse" class="input-field-premium" value="{{ $companyItem->email_adresse }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="input-label-premium">TVA / N° Identification</label>
                                                            <input type="text" name="identification_TVA" class="input-field-premium" value="{{ $companyItem->identification_TVA }}">
                                                        </div>
                                                        <div class="col-md-8">
                                                            <label class="input-label-premium">Société Parente (si sous-entité)</label>
                                                            <select name="parent_company_id" class="input-field-premium">
                                                                <option value="">Aucune (Société Mère autonome)</option>
                                                                @foreach($companies->where('id', '!=', $companyItem->id) as $pComp)
                                                                    <option value="{{ $pComp->id }}" {{ $companyItem->parent_company_id == $pComp->id ? 'selected' : '' }}>{{ $pComp->company_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- SECTION 2 : ADMINISTRATEUR ASSOCIÉ -->
                                                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm mb-5">
                                                    <h6 class="text-[11px] font-black uppercase tracking-widest text-blue-700 mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                                                        <i class="fa-solid fa-user-shield text-sm"></i>
                                                        Administrateur Associé & Responsable
                                                    </h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="input-label-premium">Nom *</label>
                                                            <input type="text" name="admin_name" class="input-field-premium" value="{{ $adminItem->name ?? '' }}" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="input-label-premium">Prénom *</label>
                                                            <input type="text" name="admin_last_name" class="input-field-premium" value="{{ $adminItem->last_name ?? '' }}" required>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="input-label-premium">Email de connexion (Compte Admin) *</label>
                                                            <input type="email" name="admin_email_adresse" class="input-field-premium" value="{{ $adminItem->email_adresse ?? '' }}" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="input-label-premium">Nouveau Mot de Passe (Optionnel)</label>
                                                            <input type="password" name="admin_password" class="input-field-premium" placeholder="Laissez vide pour ne pas modifier">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="input-label-premium">Confirmation du Mot de Passe</label>
                                                            <input type="password" name="admin_password_confirmation" class="input-field-premium" placeholder="Confirmez le mot de passe">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- SECTION 3 : HABILITATIONS ADMIN -->
                                                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                                                    <div class="d-flex align-items-center justify-content-between mb-3 border-b border-slate-100 pb-2">
                                                        <h6 class="text-[11px] font-black uppercase tracking-widest text-blue-700 mb-0 flex items-center gap-2">
                                                            <i class="fa-solid fa-lock text-sm"></i>
                                                            Habilitations de l'Administrateur
                                                        </h6>
                                                        <div class="d-flex gap-2">
                                                            <button type="button" class="btn btn-xs btn-outline-primary" style="font-size: 0.7rem; padding: 2px 8px;" onclick="checkAllHabs({{ $companyItem->id }}, true)">Tout cocher</button>
                                                            <button type="button" class="btn btn-xs btn-outline-secondary" style="font-size: 0.7rem; padding: 2px 8px;" onclick="checkAllHabs({{ $companyItem->id }}, false)">Tout décocher</button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60" id="habsContainer{{ $companyItem->id }}" style="max-height: 280px; overflow-y: auto;">
                                                        @php
                                                            $groupedPermissions = config('accounting_permissions.permissions') ?: [];
                                                            $currentHabs = $adminItem->habilitations ?? [];
                                                        @endphp
                                                        @foreach ($groupedPermissions as $section => $permissions)
                                                            @if(!str_contains($section, 'Super Admin'))
                                                                <div class="mb-3">
                                                                    <div class="text-[10px] font-black uppercase text-slate-500 tracking-wider mb-2 border-bottom pb-1" style="color: #475569;">{{ $section }}</div>
                                                                    <div class="row g-2">
                                                                        @foreach ($permissions as $key => $label)
                                                                            <div class="col-md-6 col-lg-4">
                                                                                <label class="d-flex align-items-center gap-2 p-2 bg-white rounded-lg border border-slate-200/70 hover:border-blue-300 cursor-pointer shadow-2xs">
                                                                                    <input type="checkbox" name="habilitations[{{ $key }}]" value="1" 
                                                                                        class="form-check-input mt-0 hab-check-{{ $companyItem->id }}"
                                                                                        style="width: 1.1rem; height: 1.1rem; cursor: pointer;"
                                                                                        {{ isset($currentHabs[$key]) && $currentHabs[$key] ? 'checked' : '' }}>
                                                                                    <span class="text-xs font-semibold text-slate-700" style="line-height: 1.2;">{{ $label }}</span>
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

                                            <!-- Footer Fixe de la Modal -->
                                            <div class="modal-footer bg-white border-t border-slate-200 px-6 py-3 rounded-b-2xl d-flex justify-content-end gap-3">
                                                <button type="button" class="btn-cancel-premium" data-bs-dismiss="modal">Annuler</button>
                                                <button type="submit" class="btn-save-premium d-flex align-items-center gap-2">
                                                    <i class="fa-solid fa-floppy-disk"></i>
                                                    <span>Enregistrer les modifications</span>
                                                </button>
                                            </div>
                                        </form>

                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    </div>
                    @include('components.footer')
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script>
        function checkAllHabs(companyId, state) {
            document.querySelectorAll('.hab-check-' + companyId).forEach(cb => {
                cb.checked = state;
            });
        }
    </script>
</body>
</html>
