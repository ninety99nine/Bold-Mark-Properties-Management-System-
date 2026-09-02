<?php

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Models\User;

class SupplierInvoicePolicy extends BasePolicy
{
    /**
     * Grant all permissions to super admins.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        return $this->authService->isSuperAdmin($user) ? true : null;
    }

    /**
     * Determine whether the user can view any supplier invoices.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the supplier invoice.
     *
     * @param User $user
     * @param SupplierInvoice $supplierInvoice
     * @return bool
     */
    public function view(User $user, SupplierInvoice $supplierInvoice): bool
    {
        if ($supplierInvoice->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can create supplier invoices.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the supplier invoice.
     *
     * @param User $user
     * @param SupplierInvoice $supplierInvoice
     * @return bool
     */
    public function update(User $user, SupplierInvoice $supplierInvoice): bool
    {
        if ($supplierInvoice->organization_id !== $user->organization_id) {
            abort(404);
        }
        return true;
    }

    /**
     * Determine whether the user can delete the supplier invoice.
     *
     * @param User $user
     * @param SupplierInvoice $supplierInvoice
     * @return bool
     */
    public function delete(User $user, SupplierInvoice $supplierInvoice): bool
    {
        if ($supplierInvoice->organization_id !== $user->organization_id) {
            abort(404);
        }
        return $this->authService->hasPermission($user, 'supplier.delete');
    }
}
