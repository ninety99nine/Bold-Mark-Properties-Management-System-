<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BankAccountService;
use App\Http\Resources\BankAccountResources;
use App\Http\Requests\BankAccount\ShowBankAccountsRequest;

class BankAccountController extends Controller
{
    protected BankAccountService $service;

    public function __construct(BankAccountService $service)
    {
        $this->service = $service;
    }

    /**
     * Return a paginated list of bank accounts for the authenticated user's organization.
     *
     * @param ShowBankAccountsRequest $request
     * @return BankAccountResources
     */
    public function showBankAccounts(ShowBankAccountsRequest $request): BankAccountResources
    {
        return $this->service->showBankAccounts($request->validated());
    }
}
