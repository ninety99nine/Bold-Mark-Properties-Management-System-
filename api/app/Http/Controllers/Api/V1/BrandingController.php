<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    /**
     * Return organization branding resolved from the request subdomain.
     * Falls back to generic platform defaults when no organization matches.
     */
    public function show(Request $request): JsonResponse
    {
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        $organization = Organization::where('slug', $subdomain)
            ->where('is_active', true)
            ->first();

        if (! $organization) {
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
                'name' => $organization->name,
                'logo_url' => $organization->logo_url,
                'primary_color' => $organization->primary_color,
                'accent_color' => $organization->accent_color,
                'credentials' => $organization->credentials ?? [],
                'copyright_name' => $organization->copyright_name,
            ],
        ]);
    }
}
