<?php

namespace App\Http\Middleware;

use App\Models\SuperAdminPageRule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnforcePageVisibility
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (!$routeName || str_starts_with($routeName, 'superadmin.pages.') || str_starts_with($routeName, 'superadmin.ai-credentials.')) {
            return $next($request);
        }

        if (!Schema::hasTable('superadmin_page_rules')) {
            return $next($request);
        }

        $companyId = session('current_company_id', auth()->user()->company_id);
        $companyRule = SuperAdminPageRule::where('route_name', $routeName)
            ->where('company_id', $companyId)->first();

        // Une règle entreprise précise remplace la règle globale.
        $rule = $companyRule ?: SuperAdminPageRule::where('route_name', $routeName)
            ->whereNull('company_id')->first();

        if ($rule && !$rule->is_visible) {
            abort(404);
        }

        // Les liens des sidebars sont masqués eux aussi (en complément du contrôle serveur).
        $globalHidden = SuperAdminPageRule::whereNull('company_id')->where('is_visible', false)->get()->keyBy('route_name');
        $companyRules = $companyId
            ? SuperAdminPageRule::where('company_id', $companyId)->get()->keyBy('route_name')
            : collect();
        $hiddenNames = $globalHidden->keys()->diff($companyRules->filter(fn ($r) => $r->is_visible)->keys())
            ->merge($companyRules->filter(fn ($r) => !$r->is_visible)->keys())->unique();

        $hiddenUris = $hiddenNames->map(fn ($name) => Route::getRoutes()->getByName($name)?->uri())
            ->filter(fn ($uri) => $uri && !str_contains($uri, '{'))
            ->map(fn ($uri) => '/'.ltrim($uri, '/'))->values()->all();
        View::share('hiddenPageUris', $hiddenUris);

        return $next($request);
    }
}
