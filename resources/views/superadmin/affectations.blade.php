<!doctype html>
<html lang="fr" class="layout-compact" data-assets-path="../assets/" data-template="vertical-menu-template-free" data-bs-theme="light">

@include('components.head')

<style>
    /* Une adresse se choisit dans la liste plutôt que de se retaper : une
       faute de frappe crée un doublon qu'on ne rattrape plus. */
    .champ-adresse { position: relative; }
    .champ-adresse .propositions {
        position: absolute; z-index: 1200; top: calc(100% + 3px); left: 0; right: 0;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
        box-shadow: 0 12px 28px rgba(15,23,42,.16); max-height: 220px; overflow-y: auto;
    }
    .champ-adresse .propositions[hidden] { display: none; }
    .champ-adresse .proposition {
        display: block; width: 100%; text-align: left; border: 0; background: transparent;
        padding: .4rem .6rem; font-size: .8rem; color: #334155;
    }
    .champ-adresse .proposition:hover, .champ-adresse .proposition.survol { background: #eff6ff; }
    .champ-adresse .proposition small { display: block; color: #94a3b8; font-size: .68rem; }
    .verdict { font-size: .74rem; font-weight: 700; min-height: 1.1rem; margin-top: .25rem; }
    .verdict.possible { color: #047857; }
    .verdict.impossible { color: #b91c1c; }
    .dossiers-cabinet { max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: .5rem; }
    .dossiers-cabinet label { display: flex; align-items: center; gap: .4rem; font-size: .8rem; padding: .2rem 0; }
</style>

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
                            Choisissez l'adresse dans la liste. Si personne ne la porte, créez d'abord le compte —
                            le créer ne donne accès à aucune comptabilité.
                        </p>
                    </div>

                    <div class="row g-4">

                        {{-- ─── Créer une personne ─── --}}
                        <div class="col-12">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;">
                                <div class="p-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                    <h2 class="h6 fw-bolder mb-1" style="color:#334155;">
                                        <i class="fa-solid fa-user-plus me-2"></i>Créer un compte
                                    </h2>
                                    <p class="mb-0" style="font-size:.74rem;color:#64748b;">
                                        Crée la personne, et <strong>rien d'autre</strong> : aucun accès à aucune
                                        comptabilité. Les dossiers se donnent ensuite, un par un.
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('superadmin.affectations.personne') }}" class="p-3">
                                    @csrf
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Prénom</label>
                                            <input type="text" name="name" class="form-control form-control-sm" required style="border-radius:10px;">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nom</label>
                                            <input type="text" name="last_name" class="form-control form-control-sm" required style="border-radius:10px;">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse e-mail</label>
                                            <input type="email" name="email_adresse" class="form-control form-control-sm" required style="border-radius:10px;">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Mot de passe</label>
                                            <div class="input-group input-group-sm">
                                                <input type="password" name="password" id="motDePasseCreation"
                                                       class="form-control form-control-sm" required minlength="8"
                                                       style="border-radius:10px 0 0 10px;">
                                                <button type="button" class="btn btn-light" id="voirMotDePasse"
                                                        style="border:1px solid #e2e8f0;border-radius:0 10px 10px 0;" title="Voir">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-dark btn-sm w-100" style="border-radius:10px;font-weight:700;padding:.45rem;">
                                                Créer
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- ─── Comptabilités ─── --}}
                        <div class="col-lg-6">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;height:100%;">
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
                                        <select name="company_id" class="form-select form-select-sm" required style="border-radius:10px;">
                                            <option value="">— Choisir la comptabilité —</option>
                                            @foreach($entreprises as $e)
                                                <option value="{{ $e->id }}" @selected($entrepriseChoisie == $e->id)>{{ $e->company_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3 champ-adresse" data-adresse>
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse e-mail</label>
                                        <input type="email" name="email_adresse" autocomplete="off" required
                                               class="form-control form-control-sm" style="border-radius:10px;"
                                               placeholder="Chercher une adresse…" data-champ>
                                        <div class="propositions" hidden data-liste></div>
                                        <div class="verdict" data-verdict></div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Prénom</label>
                                            <input type="text" name="name" class="form-control form-control-sm" readonly
                                                   style="border-radius:10px;background:#f8fafc;" data-prenom>
                                        </div>
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nom</label>
                                            <input type="text" name="last_name" class="form-control form-control-sm" readonly
                                                   style="border-radius:10px;background:#f8fafc;" data-famille>
                                        </div>
                                    </div>

                                    {{-- Confier un dossier, c'est en confier la tenue : le role est
                                         toujours administrateur. Un acces partiel se regle ensuite par
                                         les habilitations, dossier ouvert. --}}
                                    <input type="hidden" name="role" value="admin">

                                    <button class="btn btn-primary w-100" style="border-radius:10px;font-weight:700;">
                                        <i class="fa-solid fa-key me-1"></i>Donner l'accès
                                    </button>
                                </form>

                                <div class="px-3 pb-3">
                                    <div class="text-uppercase mb-2" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                        Rattachements en place
                                    </div>
                                    <div style="max-height:260px;overflow-y:auto;">
                                        @foreach($entreprises as $e)
                                            @php $membres = $parEntreprise[$e->id] ?? collect(); @endphp
                                            @if($membres->count())
                                            <div class="mb-2 p-2" style="background:#f8fafc;border-radius:10px;">
                                                <div class="fw-bold" style="font-size:.78rem;color:#334155;">{{ $e->company_name }}</div>
                                                @foreach($membres as $m)
                                                @php
                                                    $estCreateur = ($m->role ?? null) === 'createur';
                                                    $titre = $estCreateur
                                                        ? 'créateur — administrateur'
                                                        : (($m->role ?? null) === 'admin' ? 'administrateur' : 'accès limité');
                                                @endphp
                                                <div class="d-flex align-items-center justify-content-between mt-1">
                                                    <span style="font-size:.74rem;color:#64748b;">
                                                        {{ trim($m->name . ' ' . $m->last_name) ?: $m->email_adresse }}
                                                        <span class="px-1" style="background:{{ $estCreateur ? '#dbeafe' : '#e2e8f0' }};border-radius:4px;font-size:.62rem;">{{ $titre }}</span>
                                                    </span>
                                                    @if($estCreateur)
                                                        {{-- On ne retire pas quelqu'un du dossier qu'il a ouvert : il
                                                             n'y a aucune ligne de liaison à défaire. --}}
                                                        <span style="color:#94a3b8;font-size:.62rem;" title="Le créateur du dossier ne s'en retire pas">
                                                            <i class="fa-solid fa-lock"></i>
                                                        </span>
                                                    @else
                                                    <form method="POST" action="{{ route('superadmin.affectations.comptabilite.retirer') }}">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="company_id" value="{{ $e->id }}">
                                                        <input type="hidden" name="user_id" value="{{ $m->id }}">
                                                        <button class="btn btn-sm p-0 px-1" style="color:#b91c1c;font-size:.7rem;" title="Retirer l'accès">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                    @endif
                                                </div>
                                                @endforeach
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ─── Cabinets ─── --}}
                        <div class="col-lg-6">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;height:100%;">
                                <div class="p-3" style="background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
                                    <h2 class="h6 fw-bolder mb-1" style="color:#166534;">
                                        <i class="fa-solid fa-building-user me-2"></i>Appartenance à un cabinet
                                    </h2>
                                    <p class="mb-0" style="font-size:.74rem;color:#14532d;">
                                        Rattache la personne à la maison. Les dossiers qu'elle ouvrira porteront ce
                                        cabinet et <strong>y resteront même si elle part</strong>.
                                        <strong>Cela ne donne accès à aucune comptabilité</strong> : dans son espace,
                                        elle verra qu'elle appartient au cabinet, sans pouvoir ouvrir les dossiers
                                        tant qu'on ne les lui a pas confiés.
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('superadmin.affectations.cabinet') }}" class="p-3" id="formCabinet">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Cabinet</label>
                                        <select name="cabinet_id" id="choixCabinet" class="form-select form-select-sm" required style="border-radius:10px;">
                                            <option value="">— Choisir le cabinet —</option>
                                            @foreach($cabinets as $c)
                                                <option value="{{ $c->id }}" @selected($cabinetChoisi == $c->id)>
                                                    {{ $c->nom }}@if($c->gerant) — gérant : {{ trim($c->gerant->name . ' ' . $c->gerant->last_name) }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3 champ-adresse" data-adresse>
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse e-mail</label>
                                        <input type="email" name="email_adresse" autocomplete="off" required
                                               class="form-control form-control-sm" style="border-radius:10px;"
                                               placeholder="Chercher une adresse…" data-champ>
                                        <div class="propositions" hidden data-liste></div>
                                        <div class="verdict" data-verdict></div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Prénom</label>
                                            <input type="text" name="name" class="form-control form-control-sm" readonly
                                                   style="border-radius:10px;background:#f8fafc;" data-prenom>
                                        </div>
                                        <div class="col-6">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nom</label>
                                            <input type="text" name="last_name" class="form-control form-control-sm" readonly
                                                   style="border-radius:10px;background:#f8fafc;" data-famille>
                                        </div>
                                    </div>

                                    <button class="btn w-100" style="border-radius:10px;font-weight:700;background:#166534;color:#fff;">
                                        <i class="fa-solid fa-user-plus me-1"></i>Rattacher au cabinet
                                    </button>
                                </form>

                                {{-- Les dossiers du cabinet choisi, à confier d'un clic. --}}
                                <form method="POST" action="{{ route('superadmin.affectations.cabinet.dossiers') }}" class="px-3 pb-3" id="formDossiers">
                                    @csrf
                                    <input type="hidden" name="cabinet_id" id="dossiersCabinetId">
                                    <div class="text-uppercase mb-2" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                        Dossiers de ce cabinet
                                    </div>
                                    <p class="mb-2" style="font-size:.72rem;color:#64748b;" id="dossiersAide">
                                        Choisissez un cabinet pour voir ses dossiers.
                                    </p>
                                    <div class="dossiers-cabinet mb-2" id="listeDossiers" hidden></div>

                                    <div class="mb-2 champ-adresse" data-adresse id="adresseDossiers" hidden
                                         data-message-trouve="Compte trouvé"
                                         data-message-absent="Aucun compte ne porte cette adresse : créez-le d'abord.">
                                        <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">À qui les confier</label>
                                        <input type="email" autocomplete="off" class="form-control form-control-sm"
                                               style="border-radius:10px;" placeholder="Chercher une adresse…" data-champ>
                                        <div class="propositions" hidden data-liste></div>
                                        <div class="verdict" data-verdict></div>
                                        <input type="hidden" name="user_id" data-user-id>
                                    </div>

                                    <button class="btn btn-sm w-100" id="btnDossiers" hidden
                                            style="border-radius:10px;font-weight:700;background:#0f766e;color:#fff;">
                                        Confier ces dossiers en administrateur
                                    </button>
                                </form>

                                <div class="px-3 pb-3">
                                    <div class="text-uppercase mb-2" style="font-size:.62rem;font-weight:800;color:#64748b;">
                                        Membres des cabinets
                                    </div>
                                    <div style="max-height:220px;overflow-y:auto;">
                                        @foreach($cabinets as $c)
                                            @php $membres = $parCabinet[$c->id] ?? collect(); @endphp
                                            @if($membres->count())
                                            <div class="mb-2 p-2" style="background:#f8fafc;border-radius:10px;">
                                                <div class="fw-bold" style="font-size:.78rem;color:#334155;">{{ $c->nom }}</div>
                                                @foreach($membres as $m)
                                                @php
                                                    $estGerant = ($m->role ?? null) === 'gerant';
                                                    $titreCabinet = match ($m->role ?? null) {
                                                        'gerant' => 'gérant',
                                                        'admin' => 'admin du cabinet',
                                                        default => 'collaborateur',
                                                    };
                                                @endphp
                                                <div class="d-flex align-items-center justify-content-between mt-1">
                                                    <span style="font-size:.74rem;color:#64748b;">
                                                        {{ trim($m->name . ' ' . $m->last_name) ?: $m->email_adresse }}
                                                        <span class="px-1" style="background:{{ $estGerant ? '#dcfce7' : '#e2e8f0' }};border-radius:4px;font-size:.62rem;">{{ $titreCabinet }}</span>
                                                        <span class="d-block" style="font-size:.66rem;color:#94a3b8;">{{ $m->email_adresse }}</span>
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

                        {{-- ─── Changer une adresse ─── --}}
                        <div class="col-12">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;">
                                <div class="p-3" style="background:#fffbeb;border-bottom:1px solid #fde68a;">
                                    <h2 class="h6 fw-bolder mb-1" style="color:#854d0e;">
                                        <i class="fa-solid fa-at me-2"></i>Changer l'adresse d'une personne
                                    </h2>
                                    <p class="mb-0" style="font-size:.74rem;color:#713f12;">
                                        Remplace l'adresse sans toucher aux accès : la personne garde ses dossiers.
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('superadmin.affectations.adresse') }}" class="p-3">
                                    @csrf
                                    <div class="row g-2 align-items-start">
                                        <div class="col-md-5 champ-adresse" data-adresse
                                             data-message-trouve="Compte trouvé"
                                             data-message-absent="Aucun compte ne porte cette adresse : rien à remplacer.">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse actuelle</label>
                                            <input type="email" autocomplete="off" required class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="Chercher l'adresse à remplacer…" data-champ>
                                            <div class="propositions" hidden data-liste></div>
                                            <div class="verdict" data-verdict></div>
                                            <input type="hidden" name="user_id" data-user-id>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nouvelle adresse</label>
                                            <input type="email" name="nouvelle_adresse" required class="form-control form-control-sm" style="border-radius:10px;">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">&nbsp;</label>
                                            <button class="btn btn-warning btn-sm w-100" style="border-radius:10px;font-weight:700;">
                                                Remplacer
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>


                        {{-- ─── Donner un mot de passe ─── --}}
                        <div class="col-12">
                            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;">
                                <div class="p-3" style="background:#fef2f2;border-bottom:1px solid #fecaca;">
                                    <h2 class="h6 fw-bolder mb-1" style="color:#991b1b;">
                                        <i class="fa-solid fa-key me-2"></i>Donner un mot de passe
                                    </h2>
                                    <p class="mb-0" style="font-size:.74rem;color:#7f1d1d;">
                                        Un compte créé par simple adresse reçoit le mot de passe provisoire
                                        <strong>{{ \App\Http\Controllers\Super\SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE }}</strong>.
                                        Les comptes créés <em>avant</em> cette page en avaient un que personne ne
                                        connaissait : la connexion leur était refusée, et l'écran répondait seulement
                                        « identifiants incorrects ». C'est ici qu'on leur en donne un.
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('superadmin.affectations.mot_de_passe') }}" class="p-3">
                                    @csrf
                                    <div class="row g-2 align-items-start">
                                        <div class="col-md-5 champ-adresse" data-adresse
                                             data-message-trouve="Compte trouvé"
                                             data-message-absent="Aucun compte ne porte cette adresse.">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Adresse de la personne</label>
                                            <input type="email" autocomplete="off" required class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="Chercher l'adresse…" data-champ>
                                            <div class="propositions" hidden data-liste></div>
                                            <div class="verdict" data-verdict></div>
                                            <input type="hidden" name="user_id" data-user-id>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">Nouveau mot de passe</label>
                                            <input type="text" name="password" required minlength="8" class="form-control form-control-sm"
                                                   style="border-radius:10px;" placeholder="8 caractères au minimum">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="d-block text-uppercase mb-1" style="font-size:.62rem;font-weight:800;color:#64748b;">&nbsp;</label>
                                            <button class="btn btn-danger btn-sm w-100" style="border-radius:10px;font-weight:700;">
                                                Définir
                                            </button>
                                        </div>
                                    </div>
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

<script>
(function () {
    const PERSONNES = @json($personnes);
    const DOSSIERS = @json($dossiersParCabinet);

    const sansAccent = v => (v || '').toString()
        .normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

    // ── Un champ d'adresse : liste cherchable, et verdict immédiat ──
    document.querySelectorAll('[data-adresse]').forEach(function (bloc) {
        const champ = bloc.querySelector('[data-champ]');
        const liste = bloc.querySelector('[data-liste]');
        const verdict = bloc.querySelector('[data-verdict]');
        const prenom = bloc.querySelector('[data-prenom]');
        const famille = bloc.querySelector('[data-famille]');
        const identifiant = bloc.querySelector('[data-user-id]');

        function connue(adresse) {
            const cherche = sansAccent(adresse);
            return PERSONNES.find(p => sansAccent(p.email) === cherche) || null;
        }

        function juger() {
            const saisie = champ.value.trim();
            const p = connue(saisie);

            if (identifiant) identifiant.value = p ? p.id : '';
            if (prenom) prenom.value = p ? p.prenom : '';
            if (famille) famille.value = p ? p.famille : '';

            if (!saisie) {
                verdict.textContent = '';
                verdict.className = 'verdict';
                return;
            }

            if (p) {
                verdict.textContent = 'Liaison possible — ' + (p.prenom + ' ' + p.famille).trim();
                verdict.className = 'verdict possible';
            } else {
                verdict.textContent = "Liaison impossible : aucun compte ne porte cette adresse. Créez-le d'abord.";
                verdict.className = 'verdict impossible';
            }
        }

        function proposer() {
            const cherche = sansAccent(champ.value);
            const trouvees = PERSONNES
                .filter(p => !cherche || sansAccent(p.email).includes(cherche)
                          || sansAccent(p.prenom + ' ' + p.famille).includes(cherche))
                .slice(0, 40);

            liste.innerHTML = '';

            if (!trouvees.length) { liste.hidden = true; return; }

            trouvees.forEach(function (p) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'proposition';
                b.innerHTML = p.email + '<small>' + ((p.prenom + ' ' + p.famille).trim() || '—') + '</small>';
                b.addEventListener('click', function () {
                    champ.value = p.email;
                    liste.hidden = true;
                    juger();
                });
                liste.appendChild(b);
            });

            liste.hidden = false;
        }

        // Sur le dernier bloc de la page, il n'y a pas la place en dessous :
        // le panneau s'ouvrait hors de l'ecran et restait invisible.
        function placer() {
            const r = champ.getBoundingClientRect();
            const enBas = window.innerHeight - r.bottom < 240;
            liste.style.top = enBas ? 'auto' : 'calc(100% + 3px)';
            liste.style.bottom = enBas ? 'calc(100% + 3px)' : 'auto';
        }

        champ.addEventListener('focus', function () { placer(); proposer(); });
        champ.addEventListener('input', function () { placer(); proposer(); juger(); });
        champ.addEventListener('blur', function () { setTimeout(() => { liste.hidden = true; }, 150); });
        juger();
    });

    // ── Voir le mot de passe à la création ──
    const oeil = document.getElementById('voirMotDePasse');
    if (oeil) {
        oeil.addEventListener('click', function () {
            const champ = document.getElementById('motDePasseCreation');
            const cache = champ.type === 'password';
            champ.type = cache ? 'text' : 'password';
            oeil.innerHTML = cache ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        });
    }

    // ── Les dossiers du cabinet choisi ──
    const choixCabinet = document.getElementById('choixCabinet');
    const listeDossiers = document.getElementById('listeDossiers');
    const aideDossiers = document.getElementById('dossiersAide');
    const champCabinetId = document.getElementById('dossiersCabinetId');
    const adresseDossiers = document.getElementById('adresseDossiers');
    const btnDossiers = document.getElementById('btnDossiers');

    function montrerLesDossiers() {
        const id = choixCabinet.value;
        champCabinetId.value = id;

        const dossiers = (DOSSIERS && DOSSIERS[id]) ? DOSSIERS[id] : [];

        if (!id) {
            aideDossiers.textContent = 'Choisissez un cabinet pour voir ses dossiers.';
            listeDossiers.hidden = true;
            adresseDossiers.hidden = true;
            btnDossiers.hidden = true;
            return;
        }

        if (!dossiers.length) {
            aideDossiers.textContent = "Ce cabinet ne porte encore aucun dossier.";
            listeDossiers.hidden = true;
            adresseDossiers.hidden = true;
            btnDossiers.hidden = true;
            return;
        }

        aideDossiers.textContent = dossiers.length + ' dossier(s). Cochez ceux à confier : le rôle sera administrateur.';
        listeDossiers.innerHTML = dossiers.map(function (d) {
            return '<label><input type="checkbox" name="dossiers[]" value="' + d.id + '"> '
                 + d.company_name + '</label>';
        }).join('');
        listeDossiers.hidden = false;
        adresseDossiers.hidden = false;
        btnDossiers.hidden = false;
    }

    if (choixCabinet) {
        choixCabinet.addEventListener('change', montrerLesDossiers);
        montrerLesDossiers();
    }
})();
</script>
</body>
</html>
