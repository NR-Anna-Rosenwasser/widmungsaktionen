<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AddressSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suggestions for the address step of the order form (street + number,
 * postal code + town). The browser never talks to the geocoder directly.
 */
class AddressController extends Controller
{
    public function __construct(private AddressSearch $search) {}

    public function streets(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($tenant->is_active, 404);

        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        return response()->json(['data' => $this->search->streets($data['q'])]);
    }

    public function postcodes(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($tenant->is_active, 404);

        $data = $request->validate([
            'q' => ['required', 'string', 'regex:/^\d{1,4}$/'],
        ]);

        return response()->json(['data' => $this->search->postcodes($data['q'])]);
    }
}
