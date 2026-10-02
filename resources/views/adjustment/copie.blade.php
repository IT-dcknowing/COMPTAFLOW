<!DOCTYPE html>
<html lang="fr" class="layout-menu-fixed layout-compact">
@include('components.head')

<style>
    .copie-header-card {
        background: linear-gradient(135deg, #0f766e 0%, #14b8a6 60%, #5eead4 100%);
        border-radius: 20px;
        padding: 2rem;
        color: white;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    .copie-header-card::before {
        content: '';
        position: absolute;
        top: -50px; right: -50px;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
    }
    .copie-carte {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
    }
    .copie-entete {
        padding: .9rem 1.1rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .copie-titre {
        font-size: .62rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
    }
    .copie-piece {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: .75rem;
        overflow: hidden;
    }
    .copie-piece-entete {
        background: #f1f5f9;
        padding: .5rem .85rem;
        display: flex;
        align-items: center;
        gap: .6rem;
        font-size: .76rem;
        font-weight: 700;
        color: #334155;
    }
    .copie-piece.desequilibree .copie-piece-entete {
        background: #fef3c7;
        color: #92400e;
    }
    .copie-table td, .copie-table th {
        font-size: .78rem;
        padding: .45rem .85rem;
        vertical-align: middle;
    }
    .copie-table tr.cochee { background: #ecfdf5; }
    .copie-mois {
        display: flex;
        align-items: center;
        gap: .45rem;
        padding: .4rem .6rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: .78rem;
        cursor: pointer;
        background: #fff;
    }
    .copie-mois.clos {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }
    .copie-mois input { cursor: inherit; }
    .copie-barre {
        position: sticky;
        bottom: 0;
        background: #fff;
        border-top: 2px solid #0f766e;
        padding: .9rem 1.1rem;
        z-index: 5;
    }
</style>

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('components.sidebar')
        <div class="layout-page">
            @include('components.header', ['page_title' => "Copie d'écritures"])
            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">

                    <div class="copie-header-card">
                        <div class="position-relative" style="z-index:1;">
                            <h1 class="h3 fw-bold mb-1" style="color:white;">
                                <i class="fa-solid fa-copy me-2"></i>Copie d'écritures
                            </h1>
                            <p class="mb-0" style="opacity:.92;font-size:.88rem;max-width:62ch;">
                                Ce qui revient à l'identique d'un mois sur l'autre ne se ressaisit plus.
                                Choisissez le journal et le mois d'origine, cochez les lignes, puis dites
                                où elles doivent arriver. <strong>Tout est recopié tel quel</strong> —
                                comptes, tiers, libellés, montants. Seuls le journal, le mois et les
                                numéros de saisie changent. Le reste se corrige ensuite à la main.
                            </p>
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success" style="border-radius:12px;">
                            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger" style="border-radius:12px;">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger" style="border-radius:12px;">
                            @foreach($errors->all() as $erreur)
                                <div>{{ $erreur }}</div>
                            @endforeach
                        </div>
                    @endif

                    {{-- ─── D'où l'on copie ─── --}}
                    <div class="copie-carte mb-4">
                        <div class="copie-entete">
                            <span class="copie-titre">1 · D'où l'on copie</span>
                        </div>
                        <form method="GET" class="p-3 d-flex flex-wrap gap-2 align-items-end">
                            <div>
                                <label class="d-block copie-titre mb-1">Journal d'origine</label>
                                <select name="journal_source" class="form-select form-select-sm"
                                        style="min-width:260px;border-radius:10px;" required>
                                    <option value="">Choisir un journal…</option>
                                    @foreach($journaux as $j)
                                        <option value="{{ $j->id }}" @selected((int) $journalSource === (int) $j->id)>
                                            {{ $j->code_journal }} — {{ $j->intitule }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="d-block copie-titre mb-1">Mois d'origine</label>
                                <select name="mois_source" class="form-select form-select-sm"
                                        style="min-width:200px;border-radius:10px;" required>
                                    <option value="">Choisir un mois…</option>
                                    @foreach($mois as $m)
                                        <option value="{{ $m['valeur'] }}" @selected($moisSource === $m['valeur'])>
                                            {{ ucfirst($m['libelle']) }}{{ $m['clos'] ? ' (clos)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-sm text-white" style="background:#0f766e;border-radius:10px;font-weight:700;padding:.4rem 1.2rem;">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Afficher les écritures
                            </button>
                            @if($journalSource || $moisSource)
                                <a href="{{ route('adjustment.copie') }}" class="btn btn-light btn-sm" style="border-radius:10px;">Effacer</a>
                            @endif
                        </form>

                        @if(empty($mois))
                            <div class="px-3 pb-3" style="font-size:.8rem;color:#b45309;">
                                Aucun exercice comptable n'est ouvert sur cette comptabilité : il n'y a
                                donc aucun mois où copier. Ouvrez un exercice depuis
                                <strong>Traitement &gt; Exercice comptable</strong>.
                            </div>
                        @endif
                    </div>

                    @if($journalSource && $moisSource)
                    <form method="POST" action="{{ route('adjustment.copie.appliquer') }}" id="formulaireCopie">
                        @csrf

                        {{-- ─── Ce que l'on copie ─── --}}
                        <div class="copie-carte mb-4">
                            <div class="copie-entete d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <span class="copie-titre">
                                    2 · Ce que l'on copie — {{ $pieces->flatten()->count() }} ligne(s)
                                    en {{ $pieces->count() }} pièce(s)
                                </span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            style="border-radius:10px;font-weight:700;" id="toutCocher">
                                        <i class="fa-solid fa-check-double me-1"></i>Tout le journal
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            style="border-radius:10px;" id="toutDecocher">
                                        Tout décocher
                                    </button>
                                </div>
                            </div>

                            <div class="p-3" style="max-height:52vh;overflow-y:auto;">
                                @forelse($pieces as $numero => $lignes)
                                    @php
                                        $ecart = round($lignes->sum('debit') - $lignes->sum('credit'), 2);
                                    @endphp
                                    <div class="copie-piece {{ abs($ecart) >= 0.01 ? 'desequilibree' : '' }}">
                                        <div class="copie-piece-entete">
                                            <input type="checkbox" class="form-check-input mt-0 piece-entiere"
                                                   data-piece="{{ $loop->index }}">
                                            <span>Pièce {{ $numero }}</span>
                                            <span style="font-weight:500;color:#64748b;">
                                                {{ \Carbon\Carbon::parse($lignes->first()->date)->format('d/m/Y') }}
                                                · {{ $lignes->first()->description_operation }}
                                            </span>
                                            <span class="ms-auto">
                                                {{ number_format($lignes->sum('debit'), 0, ',', ' ') }}
                                                /
                                                {{ number_format($lignes->sum('credit'), 0, ',', ' ') }}
                                                @if(abs($ecart) >= 0.01)
                                                    <i class="fa-solid fa-triangle-exclamation ms-1"
                                                       title="Cette pièce ne s'équilibre pas : sa copie non plus."></i>
                                                @endif
                                            </span>
                                        </div>
                                        <table class="table table-sm mb-0 copie-table">
                                            <thead style="background:#fafafa;">
                                                <tr class="copie-titre">
                                                    <th style="width:38px;"></th>
                                                    <th>Date</th>
                                                    <th>Compte</th>
                                                    <th>Tiers</th>
                                                    <th>Libellé</th>
                                                    <th>Référence</th>
                                                    <th class="text-end">Débit</th>
                                                    <th class="text-end">Crédit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($lignes as $ligne)
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input ligne-a-copier"
                                                               name="ids[]" value="{{ $ligne->id }}"
                                                               data-piece="{{ $loop->parent->index }}">
                                                    </td>
                                                    <td>{{ \Carbon\Carbon::parse($ligne->date)->format('d/m/Y') }}</td>
                                                    <td>
                                                        <span class="fw-bold">{{ optional($ligne->planComptable)->numero_de_compte }}</span>
                                                        <span class="d-block" style="font-size:.7rem;color:#94a3b8;">
                                                            {{ optional($ligne->planComptable)->intitule }}
                                                        </span>
                                                    </td>
                                                    <td style="font-size:.72rem;color:#64748b;">
                                                        {{ optional($ligne->planTiers)->numero_de_tiers ?: '—' }}
                                                    </td>
                                                    <td>{{ $ligne->description_operation }}</td>
                                                    <td style="font-size:.72rem;color:#64748b;">{{ $ligne->reference_piece ?: '—' }}</td>
                                                    <td class="text-end">{{ $ligne->debit ? number_format($ligne->debit, 0, ',', ' ') : '' }}</td>
                                                    <td class="text-end">{{ $ligne->credit ? number_format($ligne->credit, 0, ',', ' ') : '' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @empty
                                    <div class="text-center py-5" style="font-size:.85rem;color:#64748b;">
                                        Aucune écriture dans ce journal pour ce mois.
                                        <div class="mt-1" style="font-size:.76rem;color:#94a3b8;">
                                            Les reports à nouveau n'y figurent jamais : ils naissent de la
                                            clôture, et ne se recopient pas.
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        {{-- ─── Où cela arrive ─── --}}
                        <div class="copie-carte mb-4">
                            <div class="copie-entete">
                                <span class="copie-titre">3 · Où cela arrive</span>
                            </div>
                            <div class="p-3">
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <label class="d-block copie-titre mb-1">Journal de destination</label>
                                        <select name="journal_cible" id="journalCible" class="form-select form-select-sm"
                                                style="border-radius:10px;" required>
                                            <option value="">Choisir un journal…</option>
                                            @foreach($journaux as $j)
                                                <option value="{{ $j->id }}" @selected((int) $journalSource === (int) $j->id)>
                                                    {{ $j->code_journal }} — {{ $j->intitule }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="mt-2" style="font-size:.72rem;color:#94a3b8;">
                                            Le même journal est permis : c'est le cas courant, on recopie
                                            d'un mois sur l'autre dans le même journal.
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <label class="d-block copie-titre mb-2">Mois de destination (un ou plusieurs)</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($mois as $m)
                                                <label class="copie-mois {{ $m['clos'] ? 'clos' : '' }}"
                                                       title="{{ $m['exercice'] }}{{ $m['clos'] ? ' — exercice clos' : '' }}">
                                                    <input type="checkbox" class="form-check-input mt-0 mois-cible"
                                                           name="mois_cibles[]" value="{{ $m['valeur'] }}"
                                                           @disabled($m['clos'])>
                                                    {{ ucfirst($m['libelle']) }}
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="mt-2" style="font-size:.72rem;color:#94a3b8;">
                                            Seuls les mois d'un exercice ouvert sont proposés : ailleurs,
                                            l'écriture n'aurait pas d'exercice où se ranger. Le jour du mois
                                            est conservé ; un 31 recopié en février se pose au 28 ou au 29.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="copie-barre d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div id="resumeCopie" style="font-size:.82rem;color:#334155;">
                                    Cochez des lignes et un mois de destination.
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            style="border-radius:10px;font-weight:700;" id="boutonApercu">
                                        <i class="fa-solid fa-eye me-1"></i>Vérifier d'abord
                                    </button>
                                    <button type="submit" class="btn btn-sm text-white"
                                            style="background:#0f766e;border-radius:10px;font-weight:700;padding:.45rem 1.4rem;"
                                            id="boutonCopier" disabled>
                                        <i class="fa-solid fa-copy me-1"></i>Recopier
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endif

                </div><!-- /container -->
                @include('components.footer')
            </div>
        </div>
    </div>
</div>

@if($journalSource && $moisSource)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulaire = document.getElementById('formulaireCopie');
    if (!formulaire) return;

    const lignes = Array.from(document.querySelectorAll('.ligne-a-copier'));
    const pieces = Array.from(document.querySelectorAll('.piece-entiere'));
    const moisCibles = Array.from(document.querySelectorAll('.mois-cible'));
    const journalCible = document.getElementById('journalCible');
    const resume = document.getElementById('resumeCopie');
    const bouton = document.getElementById('boutonCopier');
    const apercu = document.getElementById('boutonApercu');

    function cochees() {
        return lignes.filter(function (l) { return l.checked; });
    }

    function moisChoisis() {
        return moisCibles.filter(function (m) { return m.checked; });
    }

    function rafraichir() {
        const n = cochees().length;
        const m = moisChoisis().length;

        cochees().forEach(function (l) {
            const tr = l.closest("tr");
            if (tr) tr.classList.add("cochee");
        });
        lignes.filter(function (l) { return !l.checked; }).forEach(function (l) {
            const tr = l.closest("tr");
            if (tr) tr.classList.remove("cochee");
        });

        // La case de pièce suit ses lignes : cochée seulement si toutes le sont.
        pieces.forEach(function (p) {
            const siennes = lignes.filter(function (l) { return l.dataset.piece === p.dataset.piece; });
            const prises = siennes.filter(function (l) { return l.checked; }).length;
            p.checked = prises > 0 && prises === siennes.length;
            p.indeterminate = prises > 0 && prises < siennes.length;
        });

        bouton.disabled = (n === 0 || m === 0 || !journalCible.value);

        if (n === 0) {
            resume.textContent = "Cochez les lignes à recopier.";
        } else if (m === 0) {
            resume.textContent = n + " ligne(s) cochée(s) — choisissez maintenant un mois de destination.";
        } else {
            resume.textContent = n + " ligne(s) × " + m + " mois = " + (n * m)
                + " ligne(s) à créer.";
        }
    }

    lignes.forEach(function (l) { l.addEventListener("change", rafraichir); });
    moisCibles.forEach(function (m) { m.addEventListener("change", rafraichir); });
    journalCible.addEventListener("change", rafraichir);

    pieces.forEach(function (p) {
        p.addEventListener("change", function () {
            lignes.filter(function (l) { return l.dataset.piece === p.dataset.piece; })
                .forEach(function (l) { l.checked = p.checked; });
            rafraichir();
        });
    });

    document.getElementById("toutCocher").addEventListener("click", function () {
        lignes.forEach(function (l) { l.checked = true; });
        rafraichir();
    });

    document.getElementById("toutDecocher").addEventListener("click", function () {
        lignes.forEach(function (l) { l.checked = false; });
        rafraichir();
    });

    // Vérifier avant d'écrire : ce qui serait créé, ce qui serait passé, et
    // pourquoi un mois est refusé. Mieux vaut le savoir que le découvrir.
    apercu.addEventListener("click", function () {
        const donnees = new FormData();
        donnees.append("_token", formulaire.querySelector("[name=_token]").value);
        donnees.append("journal_cible", journalCible.value);
        cochees().forEach(function (l) { donnees.append("ids[]", l.value); });
        moisChoisis().forEach(function (m) { donnees.append("mois_cibles[]", m.value); });

        resume.textContent = "Vérification…";

        fetch("{{ route('adjustment.copie.apercu') }}", {
            method: "POST",
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" },
            body: donnees,
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) { resume.textContent = "Vérification impossible : réessayez."; return; }

                let texte = d.creations + " ligne(s) seraient créées";
                if (d.deja > 0) {
                    texte += ", " + d.deja + " déjà présente(s) et donc passée(s)";
                }
                texte += ".";

                if (d.desequilibrees && d.desequilibrees.length) {
                    texte += " Pièce(s) qui ne s'équilibrent pas : "
                        + d.desequilibrees.join(", ") + ".";
                }

                if (d.refus && d.refus.length) {
                    texte += " " + d.refus.join(" ");
                }

                resume.textContent = texte;
            })
            .catch(function () { resume.textContent = "Vérification impossible : réessayez."; });
    });

    // Un double clic ne doit pas doubler les livres : le bouton se ferme dès
    // l'envoi, et le serveur passe de toute façon les lignes déjà présentes.
    formulaire.addEventListener("submit", function () {
        bouton.disabled = true;
        bouton.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i>Copie en cours…";
    });

    rafraichir();
});
</script>
@endif

</body>
</html>
