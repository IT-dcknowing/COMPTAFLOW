<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\AiProviderCredential;
use App\Models\Company;
use App\Models\SuperAdminPageRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class SuperAdminPageControlController extends Controller
{
    public function index()
    {
        $sections = [
            'Pilotage' => [], 'Traitement' => [], 'Analytique' => [],
            'Gouvernance' => [], 'Administration' => [], 'Import / Export' => [],
            'Autres' => [],
        ];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name || str_starts_with($name, 'superadmin.pages.') || str_starts_with($name, 'superadmin.ai-credentials.')) continue;
            if (!in_array('GET', $route->methods(), true)) continue;
            if (str_contains($route->uri(), 'api/') || str_contains($name, '.api.') || str_contains($name, 'csrf')) continue;
            $uri = $route->uri();
            if (str_starts_with($uri, 'superadmin/')) continue;

            $section = match (true) {
                str_contains($uri, 'dashboard') || str_starts_with($uri, 'admin/performance') => 'Pilotage',
                str_contains($uri, 'scan') || str_contains($uri, 'ecriture') || str_contains($uri, 'tiers') => 'Traitement',
                str_starts_with($uri, 'analytique') => 'Analytique',
                str_contains($uri, 'habilitation') || str_contains($uri, 'notification') || str_contains($uri, 'audit') => 'Gouvernance',
                str_starts_with($uri, 'admin') || str_contains($uri, 'company') => 'Administration',
                str_contains($uri, 'import') || str_contains($uri, 'export') || str_contains($uri, 'reporting') => 'Import / Export',
                default => 'Autres',
            };
            $sections[$section][$name] = [
                'uri' => '/'.$uri,
                'label' => ucfirst(str_replace(['.', '-', '_'], ' ', preg_replace('/^(admin|compta|superadmin)\./', '', $name))),
            ];
        }

        $rules = SuperAdminPageRule::all()->groupBy(fn ($r) => $r->company_id === null ? 'global' : (string) $r->company_id);
        $companies = Company::orderBy('company_name')->get(['id', 'company_name']);
        $credentials = AiProviderCredential::orderBy('provider')->orderBy('model')->get();

        return view('superadmin.page-control.index', compact('sections', 'rules', 'companies', 'credentials'));
    }

    public function savePageRules(Request $request)
    {
        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:190'],
            'scope' => ['required', Rule::in(['global', 'company'])],
            'company_id' => ['nullable', 'required_if:scope,company', 'exists:companies,id'],
            'is_visible' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        SuperAdminPageRule::updateOrCreate([
            'route_name' => $data['route_name'],
            'company_id' => $data['scope'] === 'global' ? null : $data['company_id'],
        ], [
            'is_visible' => $data['is_visible'],
            'reason' => $data['reason'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'La règle de visibilité a été enregistrée.');
    }

    public function deletePageRule(SuperAdminPageRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'La règle de visibilité a été supprimée.');
    }

    public function storeCredential(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:120'],
            'model' => ['required', 'string', 'max:160'],
            'api_key' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        AiProviderCredential::updateOrCreate(
            ['provider' => trim($data['provider']), 'model' => trim($data['model'])],
            ['api_key' => $data['api_key'], 'notes' => $data['notes'] ?? null, 'is_active' => true, 'updated_by' => $request->user()->id]
        );

        return back()->with('success', 'La configuration du fournisseur IA a été enregistrée. La clé est chiffrée en base.');
    }

    public function toggleCredential(AiProviderCredential $credential)
    {
        $credential->update(['is_active' => !$credential->is_active, 'updated_by' => request()->user()->id]);
        return back()->with('success', 'Statut de la configuration IA mis à jour.');
    }

    public function deleteCredential(AiProviderCredential $credential)
    {
        $credential->delete();
        return back()->with('success', 'Configuration IA supprimée.');
    }
}
