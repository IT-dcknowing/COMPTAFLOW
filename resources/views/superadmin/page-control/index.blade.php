<!DOCTYPE html>
<html lang="fr" class="layout-menu-fixed layout-compact">
@include('components.head')
<style>
.page-control-hero{background:linear-gradient(110deg,#edf4ff,#fff);border:1px solid #dce8fb;border-radius:18px;padding:24px}
.page-section{border:1px solid #e5ebf4;border-radius:14px;overflow:hidden;background:#fff}
.page-section summary{cursor:pointer;list-style:none;padding:16px 18px;font-weight:800;color:#193b75;display:flex;justify-content:space-between;align-items:center}
.page-section summary::-webkit-details-marker{display:none}
.page-rule-row{padding:14px 18px;border-top:1px solid #eef2f7;display:grid;grid-template-columns:minmax(160px,1fr) minmax(260px,1.5fr);gap:18px;align-items:center}
.page-rule-row:nth-child(even){background:#fbfdff}
.page-route{font-size:.78rem;color:#718096}
.page-actions{display:flex;flex-wrap:wrap;gap:7px;align-items:center}
.page-actions input,.page-actions select{min-width:120px}
.key-card{border:1px solid #e2eaf5;border-radius:14px;padding:16px;background:#fff}
@media(max-width:800px){.page-rule-row{grid-template-columns:1fr}.page-actions{align-items:stretch}.page-actions>*{flex:1 1 130px}}
</style>
<body>
<div class="layout-wrapper layout-content-navbar"><div class="layout-container">
@include('components.superadmin_sidebar')
<div class="layout-page">
@include('components.header',['page_title'=>'Pilotage des fonctionnalités'])
<div class="content-wrapper"><div class="container-xxl flex-grow-1 container-p-y">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<div class="page-control-hero mb-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
<div><span class="badge bg-primary-subtle text-primary mb-2">SUPER ADMIN · CONFIGURATION</span><h3 class="mb-1 fw-bold">Visibilité des pages & clés IA</h3><p class="text-muted mb-0">Contrôlez les fonctionnalités disponibles, globalement ou entreprise par entreprise.</p></div>
<div class="d-flex gap-2"><span class="badge bg-white text-primary border p-2">{{ collect($sections)->sum(fn($items)=>count($items)) }} pages détectées</span><span class="badge bg-white text-success border p-2">{{ $credentials->count() }} modèles IA</span></div>
</div></div>
<ul class="nav nav-pills mb-3" role="tablist">
<li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pages-tab" type="button">Pages de l'application</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#ia-tab" type="button">Fournisseurs & clés API IA</button></li>
<li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#quotas-tab" type="button">Quotas SCAN</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="pages-tab">
<div class="alert alert-info border-0"><strong>Règle de priorité :</strong> une règle définie pour une entreprise remplace la règle générale. Sans règle spécifique, la règle générale s'applique. Une page masquée est aussi bloquée côté serveur.</div>
@foreach($sections as $section=>$pages)
@if(count($pages))
<details class="page-section mb-3" open><summary><span>{{ $section }} <small class="text-muted fw-normal">({{ count($pages) }} pages)</small></span><i class="fa-solid fa-chevron-down"></i></summary>
@foreach($pages as $routeName=>$page)
@php($globalRule=$rules->get('global',collect())->firstWhere('route_name',$routeName))
<div class="page-rule-row">
<div><div class="fw-semibold">{{ $page['label'] }}</div><div class="page-route">{{ $page['uri'] }} · <code>{{ $routeName }}</code></div>
@if($globalRule)<small class="text-{{ $globalRule->is_visible?'success':'danger' }}">Règle générale : {{ $globalRule->is_visible?'Visible':'Masquée' }}</small>@else<small class="text-muted">Règle générale : visibilité par défaut</small>@endif
</div>
<form class="page-actions" method="POST" action="{{ route('superadmin.pages.rules.store') }}">@csrf
<input type="hidden" name="route_name" value="{{ $routeName }}">
<select name="scope" class="form-select form-select-sm" onchange="this.form.querySelector('[name=company_id]').required=this.value==='company';this.form.querySelector('[name=company_id]').disabled=this.value!=='company'">
<option value="global">Toute l'application</option><option value="company">Entreprise ciblée</option></select>
<select name="company_id" class="form-select form-select-sm" disabled>
<option value="">Choisir l'entreprise</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->company_name }}</option>@endforeach</select>
<select name="is_visible" class="form-select form-select-sm"><option value="0">Masquer</option><option value="1">Afficher</option></select>
<input name="reason" class="form-control form-control-sm" placeholder="Motif / précision (facultatif)">
<button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Appliquer</button>
</form>
</div>
@endforeach</details>
@endif
@endforeach
</div>
<div class="tab-pane fade" id="ia-tab">
<div class="alert alert-info border-0 mb-3"><strong>API gratuite conseillée pour les essais :</strong> Google AI Studio · Gemini 2.5 Flash-Lite. Créez une clé sur <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>, puis enregistrez-la ici. Modèle : <code>gemini-2.5-flash-lite</code>. N’utilisez que des factures fictives ou anonymisées sur le niveau gratuit : Google indique que les contenus soumis au service gratuit peuvent être utilisés pour améliorer ses produits et examinés par des personnes.</div>
<div class="row g-4">
<div class="col-xl-5"><div class="card border-0 shadow-sm"><div class="card-header bg-white"><h5 class="mb-0">Ajouter un fournisseur / modèle</h5></div><div class="card-body">
<form method="POST" action="{{ route('superadmin.ai-credentials.store') }}">@csrf
<div class="mb-3"><label class="form-label">Maison / fournisseur IA</label><input name="provider" class="form-control" list="ai-providers" placeholder="Ex. Google, OpenAI, Anthropic, Mistral" required><datalist id="ai-providers">@foreach($credentials->pluck('provider')->unique() as $provider)<option value="{{ $provider }}">@endforeach</datalist></div>
<div class="mb-3"><label class="form-label">Nom du modèle</label><input name="model" class="form-control" placeholder="Ex. gemini-2.5-flash" required></div>
<div class="mb-3"><label class="form-label">Clé API</label><input name="api_key" type="password" class="form-control" autocomplete="new-password" required></div>
<div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Usage, environnement, remarques"></textarea></div>
<button class="btn btn-primary w-100"><i class="fa-solid fa-shield-halved me-1"></i>Enregistrer la configuration</button>
</form><small class="text-muted d-block mt-3">Les clés sont chiffrées en base et ne sont jamais réaffichées en clair. La connexion effective du modèle devra être raccordée au service IA concerné.</small>
</div></div></div>
<div class="col-xl-7"><h5 class="fw-bold mb-3">Modèles configurés</h5>
@if($credentials->isEmpty())<div class="card border-0 bg-light p-4 text-center text-muted">Aucun fournisseur IA configuré pour le moment.</div>@endif
@foreach($credentials as $credential)<div class="key-card mb-3"><div class="d-flex justify-content-between gap-3 align-items-start"><div><div class="fw-bold">{{ $credential->provider }} <span class="text-primary">/</span> {{ $credential->model }}</div><div class="small text-muted">Clé enregistrée · {{ $credential->updated_at?->format('d/m/Y H:i') }}</div>@if($credential->notes)<p class="small mb-0 mt-2">{{ $credential->notes }}</p>@endif</div><span class="badge bg-{{ $credential->is_active?'success':'secondary' }}">{{ $credential->is_active?'Active':'Inactive' }}</span></div>
<div class="d-flex gap-2 mt-3"><form method="POST" action="{{ route('superadmin.ai-credentials.toggle',$credential) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ $credential->is_active?'Désactiver':'Activer' }}</button></form><form method="POST" action="{{ route('superadmin.ai-credentials.delete',$credential) }}" onsubmit="return confirm('Supprimer cette configuration IA ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Supprimer</button></form></div></div>@endforeach
</div></div>
<div class="tab-pane fade" id="quotas-tab">
<div class="alert alert-info border-0"><strong>Contrôle désactivé par défaut.</strong> Aucun plafond n'est appliqué tant que cette option reste désactivée. Les valeurs ci-dessous sont préparées pour une configuration ultérieure.</div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><h5 class="mb-0">Paramétrage des quotas de factures</h5></div><div class="card-body">
<form method="POST" action="{{ route('superadmin.scan-quotas.store') }}">@csrf
<div class="form-check form-switch mb-4">
<input class="form-check-input" type="checkbox" role="switch" id="quotas_enabled" name="quotas_enabled" value="1" {{ $scanSettings->quotas_enabled ? 'checked' : '' }}>
<label class="form-check-label fw-bold" for="quotas_enabled">Activer le contrôle des quotas de scan</label>
</div>
<div class="table-responsive"><table class="table table-bordered align-middle">
<thead class="table-light"><tr><th>Forfait</th><th>Factures / mois</th><th>Factures / traitement groupé</th></tr></thead><tbody>
@foreach(['Starter','Pro','Enterprise'] as $pack)
<tr><th>{{ $pack }}</th>
<td><input type="number" min="1" class="form-control" name="limits[{{ $pack }}][monthly]" value="{{ data_get($scanSettings->limits, $pack.'.monthly') }}" placeholder="Sans limite"></td>
<td><input type="number" min="1" class="form-control" name="limits[{{ $pack }}][batch]" value="{{ data_get($scanSettings->limits, $pack.'.batch') }}" placeholder="Sans limite"></td>
</tr>
@endforeach
</tbody></table></div>
<p class="small text-muted">Laissez une valeur vide pour ne pas définir de plafond sur cette dimension. L'application des limites et du compteur de consommation sera activée dans une étape ultérieure.</p>
<button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Enregistrer les paramètres</button>
</form></div></div></div>
</div>
</div>
</div></div></div></div>
</body></html>