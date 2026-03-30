<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    /**
     * Return tenant branding resolved from the request subdomain.
     * Falls back to generic platform defaults when no tenant matches.
     */
    public function show(Request $request): JsonResponse
    {
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        $tenant = Tenant::where('slug', $subdomain)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return response()->json([
                'data' => [
                    'name' => 'Property Management Platform',
                    'logo_url' => null,
                    'primary_color' => '#0B1F38',
                    'accent_color' => '#D89B4B',
                    'credentials' => [],
                    'copyright_name' => 'Property Management Platform',
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'name' => $tenant->name,
                'logo_url' => $tenant->logo_url,
                'primary_color' => $tenant->primary_color,
                'accent_color' => $tenant->accent_color,
                'credentials' => $tenant->credentials ?? [],
                'copyright_name' => $tenant->copyright_name,
            ],
        ]);
    }
}
