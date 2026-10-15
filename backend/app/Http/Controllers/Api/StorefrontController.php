<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AktionResource;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    /**
     * Map a storefront host to a tenant (custom domains) or tell the
     * platform domain what to show on its bare root.
     */
    public function resolve(Request $request): JsonResponse
    {
        $host = strtolower((string) $request->query('host'));

        $tenant = TenantDomain::query()
            ->where('domain', $host)
            ->whereHas('tenant', fn ($q) => $q->active())
            ->first()?->tenant;

        if ($tenant) {
            return response()->json(['mode' => 'custom_domain', 'tenant' => $tenant->slug]);
        }

        $active = Tenant::query()->active()->orderBy('name')->get(['id', 'slug']);

        return response()->json($active->count() === 1
            ? ['mode' => 'redirect', 'tenant' => $active->first()->slug]
            : ['mode' => 'picker', 'tenant' => null]);
    }

    public function index(): JsonResponse
    {
        $tenants = Tenant::query()->active()->orderBy('name')->get();

        return response()->json($tenants->map(fn (Tenant $tenant) => [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'authors' => $tenant->books()->where('is_active', true)->distinct()->pluck('author_name'),
            'open_aktion' => ($aktion = $tenant->openAktion()) ? [
                'name' => $aktion->name,
                'order_deadline' => $aktion->order_deadline->toIso8601String(),
            ] : null,
        ]));
    }

    public function show(Tenant $tenant): JsonResponse
    {
        abort_unless($tenant->is_active, 404);

        $open = $tenant->openAktion()?->load('books');
        $next = $tenant->nextAktion()?->load('books');

        return response()->json([
            'tenant' => new TenantResource($tenant),
            'aktion' => $open ? new AktionResource($open) : null,
            'next_aktion' => $next ? new AktionResource($next) : null,
        ]);
    }
}
