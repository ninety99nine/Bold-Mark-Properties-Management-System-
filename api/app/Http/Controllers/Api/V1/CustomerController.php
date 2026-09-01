<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CustomerGroup;
use App\Models\Owner;
use App\Services\CustomerGroupService;
use App\Services\CustomerService;
use App\Http\Resources\CustomerGroupResources;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\OwnerResources;
use App\Http\Requests\Customer\ShowCustomersRequest;
use App\Http\Requests\Customer\CreateCustomerRequest;
use App\Http\Requests\Customer\ShowCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Requests\Customer\DisableCustomerRequest;
use App\Http\Requests\Customer\EnableCustomerRequest;
use App\Http\Requests\Customer\DeleteCustomerRequest;
use App\Http\Requests\Customer\ImportCustomerNotesRequest;
use App\Http\Requests\Customer\ImportDebitOrderMandatesRequest;
use App\Http\Requests\CustomerGroup\ShowCustomerGroupsRequest;
use App\Http\Requests\CustomerGroup\CreateCustomerGroupRequest;
use App\Http\Requests\CustomerGroup\UpdateCustomerGroupRequest;
use App\Http\Requests\CustomerGroup\DeleteCustomerGroupRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * @var CustomerService
     */
    protected $service;

    /**
     * CustomerController constructor.
     *
     * @param CustomerService $service
     */
    public function __construct(CustomerService $service)
    {
        $this->service = $service;
    }

    /**
     * Show customers for a community.
     *
     * @param ShowCustomersRequest $request
     * @param Community $community
     * @return OwnerResources|array
     */
    public function showCustomers(ShowCustomersRequest $request, Community $community): OwnerResources|array
    {
        return $this->service->showCustomers($community, $request->validated());
    }

    /**
     * Create a customer.
     *
     * @param CreateCustomerRequest $request
     * @param Community $community
     * @return array
     */
    public function createCustomer(CreateCustomerRequest $request, Community $community): array
    {
        return $this->service->createCustomer($community, $request->validated());
    }

    /**
     * Download the customer notes upload template.
     *
     * @param ShowCustomersRequest $request
     * @param Community $community
     * @return StreamedResponse
     */
    public function downloadNotesTemplate(ShowCustomersRequest $request, Community $community): StreamedResponse
    {
        return $this->service->downloadNotesTemplate($community);
    }

    /**
     * Import customer notes from an uploaded file.
     *
     * @param ImportCustomerNotesRequest $request
     * @param Community $community
     * @return array
     */
    public function importNotes(ImportCustomerNotesRequest $request, Community $community): array
    {
        return $this->service->importNotes($community, $request->file('file'));
    }

    /**
     * Download the debit-order mandates upload template.
     *
     * @param ShowCustomersRequest $request
     * @param Community $community
     * @return StreamedResponse
     */
    public function downloadMandatesTemplate(ShowCustomersRequest $request, Community $community): StreamedResponse
    {
        return $this->service->downloadMandatesTemplate($community);
    }

    /**
     * Import debit-order mandates from an uploaded file.
     *
     * @param ImportDebitOrderMandatesRequest $request
     * @param Community $community
     * @return array
     */
    public function importMandates(ImportDebitOrderMandatesRequest $request, Community $community): array
    {
        return $this->service->importMandates($community, $request->file('file'));
    }

    /**
     * Show a single customer.
     *
     * @param ShowCustomerRequest $request
     * @param Community $community
     * @param Owner $owner
     * @return OwnerResource
     */
    public function showCustomer(ShowCustomerRequest $request, Community $community, Owner $owner): OwnerResource
    {
        return $this->service->showCustomer($owner);
    }

    /**
     * Update a customer.
     *
     * @param UpdateCustomerRequest $request
     * @param Community $community
     * @param Owner $owner
     * @return array
     */
    public function updateCustomer(UpdateCustomerRequest $request, Community $community, Owner $owner): array
    {
        return $this->service->updateCustomer($community, $owner, $request->validated());
    }

    /**
     * Disable a customer.
     *
     * @param DisableCustomerRequest $request
     * @param Community $community
     * @param Owner $owner
     * @return array
     */
    public function disableCustomer(DisableCustomerRequest $request, Community $community, Owner $owner): array
    {
        return $this->service->disableCustomer($owner);
    }

    /**
     * Enable a customer.
     *
     * @param EnableCustomerRequest $request
     * @param Community $community
     * @param Owner $owner
     * @return array
     */
    public function enableCustomer(EnableCustomerRequest $request, Community $community, Owner $owner): array
    {
        return $this->service->enableCustomer($owner);
    }

    /**
     * Delete a customer.
     *
     * @param DeleteCustomerRequest $request
     * @param Community $community
     * @param Owner $owner
     * @return array
     */
    public function deleteCustomer(DeleteCustomerRequest $request, Community $community, Owner $owner): array
    {
        return $this->service->deleteCustomer($owner);
    }

    /**
     * Show customer groups for a community.
     *
     * @param ShowCustomerGroupsRequest $request
     * @param Community $community
     * @return CustomerGroupResources|array
     */
    public function showCustomerGroups(ShowCustomerGroupsRequest $request, Community $community): CustomerGroupResources|array
    {
        return (new CustomerGroupService())->showCustomerGroups($community, $request->validated());
    }

    /**
     * Create a customer group.
     *
     * @param CreateCustomerGroupRequest $request
     * @param Community $community
     * @return array
     */
    public function createCustomerGroup(CreateCustomerGroupRequest $request, Community $community): array
    {
        return (new CustomerGroupService())->createCustomerGroup($community, $request->validated());
    }

    /**
     * Update a customer group.
     *
     * @param UpdateCustomerGroupRequest $request
     * @param Community $community
     * @param CustomerGroup $customerGroup
     * @return array
     */
    public function updateCustomerGroup(UpdateCustomerGroupRequest $request, Community $community, CustomerGroup $customerGroup): array
    {
        return (new CustomerGroupService())->updateCustomerGroup($customerGroup, $request->validated());
    }

    /**
     * Delete a customer group.
     *
     * @param DeleteCustomerGroupRequest $request
     * @param Community $community
     * @param CustomerGroup $customerGroup
     * @return array
     */
    public function deleteCustomerGroup(DeleteCustomerGroupRequest $request, Community $community, CustomerGroup $customerGroup): array
    {
        return (new CustomerGroupService())->deleteCustomerGroup($customerGroup);
    }
}
