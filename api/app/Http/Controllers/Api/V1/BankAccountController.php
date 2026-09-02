<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use App\Http\Resources\BankAccountResource;
use App\Http\Resources\BankAccountResources;
use App\Http\Requests\BankAccount\ShowBankAccountsRequest;
use App\Http\Requests\BankAccount\ShowBankAccountRequest;
use App\Http\Requests\BankAccount\CreateBankAccountRequest;
use App\Http\Requests\BankAccount\UpdateBankAccountRequest;
use App\Http\Requests\BankAccount\DeleteBankAccountRequest;

class BankAccountController extends Controller
{
    /**
     * @var BankAccountService
     */
    protected $service;

    /**
     * BankAccountController constructor.
     *
     * @param BankAccountService $service
     */
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

    /**
     * Create a bank account.
     *
     * @param CreateBankAccountRequest $request
     * @return array
     */
    public function createBankAccount(CreateBankAccountRequest $request): array
    {
        return $this->service->createBankAccount($request->validated());
    }

    /**
     * Return a single bank account.
     *
     * @param ShowBankAccountRequest $request
     * @param BankAccount $bankAccount
     * @return BankAccountResource
     */
    public function showBankAccount(ShowBankAccountRequest $request, BankAccount $bankAccount): BankAccountResource
    {
        return $this->service->showBankAccount($bankAccount);
    }

    /**
     * Update a bank account's details.
     *
     * @param UpdateBankAccountRequest $request
     * @param BankAccount $bankAccount
     * @return array
     */
    public function updateBankAccount(UpdateBankAccountRequest $request, BankAccount $bankAccount): array
    {
        return $this->service->updateBankAccount($bankAccount, $request->validated());
    }

    /**
     * Delete a single bank account.
     *
     * @param DeleteBankAccountRequest $request
     * @param BankAccount $bankAccount
     * @return array
     */
    public function deleteBankAccount(DeleteBankAccountRequest $request, BankAccount $bankAccount): array
    {
        return $this->service->deleteBankAccount($bankAccount);
    }
}
