<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\CountryHelper;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Organization;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    /**
     * Return all summary data needed to render the dashboard.
     *
     * @param Request $request
     * @return array
     */
    public function getDashboardSummary(Request $request): array
    {
        $year = $request->filled('year') ? (int) $request->input('year') : null;

        return $this->service->getDashboardSummary($request->input('country'), $year);
    }

    /**
     * Return the list of countries that have communities, plus supported options.
     *
     * @param Request $request
     * @return array
     */
    public function getCountries(Request $request): array
    {
        $organizationId = Auth::user()->organization_id;

        $countsByCcode = Community::where('organization_id', $organizationId)
            ->whereNotNull('country')
            ->selectRaw('country, count(*) as community_count')
            ->groupBy('country')
            ->pluck('community_count', 'country')
            ->toArray();

        $countries = [];
        foreach ($countsByCcode as $code => $count) {
            $info = CountryHelper::get($code);
            if ($info) {
                $countries[] = array_merge(['code' => $code, 'community_count' => $count], $info);
            }
        }

        // Also include the organization's default country
        $organization = Organization::find($organizationId);
        $defaultCountry = $organization?->country;

        return [
            'countries'       => $countries,
            'default_country' => $defaultCountry,
            'supported'       => CountryHelper::options(),
        ];
    }
}
