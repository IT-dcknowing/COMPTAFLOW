<!doctype html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

@include('components.head')

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('components.sidebar')

        <div class="layout-page">
            @include('components.header', ['page_title' => 'Mot de passe'])

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">

                    <div class="row justify-content-center">
                        <div class="col-lg-6">

                            @if(session('success'))
                                <div class="alert alert-success" style="border-radius:12px;">{{ session('success') }}</div>
                            @endif

                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                                <div class="p-4" style="border-bottom:1px solid #e2e8f0;">
                                    <h1 class="h5 fw-bolder text-slate-900 mb-1">
                                        Changer mon <span class="text-primary">mot de passe</span>
                                    </h1>
                                    <p class="text-muted mb-0" style="font-size:.8rem;">
                                        Connecté en tant que <strong>{{ auth()->user()->email_adresse }}</strong>.
                                        L'ancien mot de passe est demandé : sans lui, une session laissée ouverte
                                        suffirait à prendre le compte.
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('superadmin.mot_de_passe.enregistrer') }}" class="p-4">
                                    @csrf

                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                            Mot de passe actuel
                                        </label>
                                        <input type="password" name="mot_de_passe_actuel" required autocomplete="current-password"
                                               class="form-control @error('mot_de_passe_actuel') is-invalid @enderror"
                                               style="border-radius:10px;">
                                        @error('mot_de_passe_actuel')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <hr style="border-color:#e2e8f0;">

                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                            Nouveau mot de passe
                                        </label>
                                        <input type="password" name="nouveau" required autocomplete="new-password"
                                               class="form-control @error('nouveau') is-invalid @enderror"
                                               style="border-radius:10px;">
                                        @error('nouveau')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted" style="font-size:.72rem;">
                                            Dix caractères au moins, avec des lettres et des chiffres.
                                        </small>
                                    </div>

                                    <div class="mb-4">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                            Répéter le nouveau mot de passe
                                        </label>
                                        <input type="password" name="nouveau_confirmation" required autocomplete="new-password"
                                               class="form-control" style="border-radius:10px;">
                                    </div>

                                    <div class="p-3 mb-4" style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;">
                                        <p class="mb-0" style="font-size:.76rem;color:#854d0e;">
                                            Après le changement, les autres sessions ouvertes sur ce compte sont fermées.
                                            Vous resterez connecté ici.
                                        </p>
                                    </div>

                                    <button class="btn btn-primary w-100" style="border-radius:10px;font-weight:700;padding:.7rem;">
                                        <i class="fa-solid fa-key me-1"></i>Changer le mot de passe
                                    </button>
                                </form>
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
