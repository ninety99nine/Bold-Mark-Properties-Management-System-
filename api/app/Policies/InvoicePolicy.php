<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends BasePolicy
{
    /**
     * Super-admins bypass all policy checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($this->authService->isSuperAdmin($user)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->authService->hasPermission($user, 'invoice.update');
    }

    /**
     * Determine whether the user can bulk-delete invoices.
     * Iterates the incoming `invoice_ids` array and checks permission.
     */
    public function deleteAny(User $user): bool
    {
        $invoiceIds = request()->input('invoice_ids', []);

        if (empty($invoiceIds)) {
            return false;
        }

        return $this->authService->hasPermission($user, 'invoice.delete');
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->authService->hasPermission($user, 'invoice.delete');
    }

    /**
     * Determine whether the user can resend the invoice email.
     */
    public function resend(User $user, Invoice $invoice): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore a soft-deleted invoice.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $this->authService->hasPermission($user, 'invoice.delete');
    }

    /**
     * Determine whether the user can permanently delete an invoice.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $this->authService->hasPermission($user, 'invoice.delete');
    }
}
