<!doctype html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

@include('components.head')

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('components.sidebar')

        <div class="layout-page">
            @include('components.header', ['page_title' => 'Affectations'])

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">

                    @if(session('success'))
                        <div class="alert alert-success" style="border-radius:12px;">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger" style="border-radius:12px;">{{ session('error') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger" style="border-radius:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-4">
                        <h1 class="h3 fw-bolder text-slate-900 mb-1">
                            Affecter des <span class="text-primary">personnes</span>
                        </h1>
                        <p class="text-muted mb-0" style="font-size:.85rem;">
                            L'adresse e-mail suffit : si personne ne la porte, le compte est créé.
                        </p>
                    </div>

                    <div class="row g-4">

                        {{-- ─── Comptabilités ─── --}}
                        <div class="col-lg-6">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;height:100%;">
                                <div class="p-3" style="background:#eff6ff;border-bottom:1px solid #dbeafe;">
                                    <h2 class="h6 fw-bolder mb-1" style="color:#1e40af;">
                                        <i class="fa-solid fa-book me-2"></i>Accès à une comptabilité
                                    </h2>
                                    <p class="mb-0" style="font-size:.74rem;color:#1e3a8a;">
                                        Se donne <strong>dossier par dossier</strong>. Un collaborateur ne voit que ce
                                        qu'on lui affecte, jamais tout le portefeuille du cabinet.
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('superadmin.affectations.comptabilite') }}" class="p-3">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Comptabilité</label>
                                        <select name="company_id" class="form-select form-select-sm" required
                                                style="border-radius:10px;" onchange="this.form.dataset.choisie = this.value;">
                                            <option value="">— Choisir la comptabilité —</option>
                                            @foreach($entreprises as $e)
                                                <option value="{{ $e->id }}" @selected($entrepriseChoisie == $e->id)>{{ $e->company_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse e-mail</label>
                                        <input type="email" name="email_adresse" class="form-control form-control-sm" required
                                               style="border-radius:10px;" placeholder="jean@societe.com" value="{{ old('email_adresse') }}">
                                        <small class="text-muted" style="font-size:.7rem;">
                                            Inconnue ? Le compte sera créé, avec un mot de passe provisoire à changer.
                                        </small>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Prénom</label>
                                            <input type="text" name="name" class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="facultatif" value="{{ old('name') }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nom</label>
                                            <input type="text" name="last_name" class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="facultatif" value="{{ old('last_name') }}">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Rôle sur ce dossier</label>
                                        <select name="role" class="form-select form-select-sm" required style="border-radius:10px;">
                                            <option value="comptable">Comptable — saisie et états</option>
                                            <option value="admin">Administrateur — configuration comprise</option>
                                        </select>
                                    </div>

                                    <button class="btn btn-primary w-100" style="border-radius:10px;font-weight:700;">
                                        <i class="fa-solid fa-user-plus me-1"></i>Donner l'accès
                                    </button>
                                </form>

                                <div class="px-3 pb-3">
                                    <div class="text-uppercase mb-2" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                        Rattachements en place
                                    </div>
                                    <div style="max-height:300px;overflow-y:auto;">
                                        @forelse($entreprises as $e)
                                            @php $membres = $parEntreprise[$e->id] ?? collect(); @endphp
                                            @if($membres->count())
                                            <div class="mb-2 p-2" style="background:#f8fafc;border-radius:10px;">
                                                <div class="fw-bold" style="font-size:.78rem;color:#334155;">{{ $e->company_name }}</div>
                                                @foreach($membres as $m)
                                                <div class="d-flex align-items-center justify-content-between mt-1">
                                                    <span style="font-size:.74rem;color:#64748b;">
                                                        {{ trim($m->name . ' ' . $m->last_name) ?: $m->email_adresse }}
                                                        <span class="px-1" style="background:#e2e8f0;border-radius:4px;font-size:.62rem;">{{ $m->role ?: 'comptable' }}</span>
                                                    </span>
                                                    <form method="POST" action="{{ route('superadmin.affectations.comptabilite.retirer') }}">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="company_id" value="{{ $e->id }}">
                                                        <input type="hidden" name="user_id" value="{{ $m->id }}">
                                                        <button class="btn btn-sm p-0 px-1" style="color:#b91c1c;font-size:.7rem;" title="Retirer l'accès">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                @endforeach
                                            </div>
                                            @endif
                                        @empty
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ─── Cabinets ─── --}}
                        <div class="col-lg-6">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;height:100%;">
                                <div class="p-3" style="background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
                                    <h2 class="h6 fw-bolder mb-1" style="color:#166534;">
                                        <i class="fa-solid fa-building-user me-2"></i>Appartenance à un cabinet
                                    </h2>
                                    <p class="mb-0" style="font-size:.74rem;color:#14532d;">
                                        Rattache la personne à la maison. Les dossiers qu'elle ouvrira porteront ce
                                        cabinet et <strong>y resteront même si elle part</strong>. Cela ne donne
                                        accès à aucune comptabilité : celles-ci se donnent à gauche.
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('superadmin.affectations.cabinet') }}" class="p-3">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Cabinet</label>
                                        <select name="cabinet_id" class="form-select form-select-sm" required style="border-radius:10px;">
                                            <option value="">— Choisir le cabinet —</option>
                                            @foreach($cabinets as $c)
                                                <option value="{{ $c->id }}" @selected($cabinetChoisi == $c->id)>
                                                    {{ $c->nom }}@if($c->gerant) — gérant : {{ trim($c->gerant->name . ' ' . $c->gerant->last_name) }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse e-mail</label>
                                        <input type="email" name="email_adresse" class="form-control form-control-sm" required
                                               style="border-radius:10px;" placeholder="jean@cabinet.com">
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Prénom</label>
                                            <input type="text" name="name" class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="facultatif">
                                        </div>
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nom</label>
                                            <input type="text" name="last_name" class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="facultatif">
                                        </div>
                                    </div>

                                    <button class="btn w-100" style="border-radius:10px;font-weight:700;background:#166534;color:#fff;">
                                        <i class="fa-solid fa-user-plus me-1"></i>Rattacher au cabinet
                                    </button>
                                </form>

                                <div class="px-3 pb-3">
                                    <div class="text-uppercase mb-2" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                        Membres des cabinets
                                    </div>
                                    <div style="max-height:300px;overflow-y:auto;">
                                        @foreach($cabinets as $c)
                                            @php $membres = $parCabinet[$c->id] ?? collect(); @endphp
                                            @if($membres->count())
                                            <div class="mb-2 p-2" style="background:#f8fafc;border-radius:10px;">
                                                <div class="fw-bold" style="font-size:.78rem;color:#334155;">{{ $c->nom }}</div>
                                                @foreach($membres as $m)
                                                <div class="d-flex align-items-center justify-content-between mt-1">
                                                    <span style="font-size:.74rem;color:#64748b;">
                                                        {{ trim($m->name . ' ' . $m->last_name) ?: $m->email_adresse }}
                                                    </span>
                                                    <form method="POST" action="{{ route('superadmin.affectations.cabinet.retirer') }}">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="cabinet_id" value="{{ $c->id }}">
                                                        <input type="hidden" name="user_id" value="{{ $m->id }}">
                                                        <button class="btn btn-sm p-0 px-1" style="color:#b91c1c;font-size:.7rem;" title="Retirer du cabinet">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                @endforeach
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
                @include('components.footer')
            </div>
        </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
</div>
</body>
</html>
