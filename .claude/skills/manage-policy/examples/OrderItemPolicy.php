<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy extends BasePolicy
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
     * Determine whether the user can view any order items.
     *
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function viewAny(User $user, Order $order): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the order item.
     *
     * @param User $user
     * @param Order $order
     * @param OrderItem $orderItem
     * @return bool
     */
    public function view(User $user, Order $order, OrderItem $orderItem): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create order items.
     *
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function create(User $user, Order $order): bool
    {
        return $this->authService->hasPermission($user, 'order.manage', $order->workspace_id);
    }

    /**
     * Determine whether the user can update the order item.
     *
     * @param User $user
     * @param Order $order
     * @param OrderItem $orderItem
     * @return bool
     */
    public function update(User $user, Order $order, OrderItem $orderItem): bool
    {
        return $this->authService->hasPermission($user, 'order.manage', $order->workspace_id);
    }

    /**
     * Determine whether the user can delete any order items.
     *
     * @param User $user
     * @param Order $order
     * @return bool
     */
    public function deleteAny(User $user, Order $order): bool
    {
        return $this->authService->hasPermission($user, 'order.manage', $order->workspace_id);
    }

    /**
     * Determine whether the user can delete the order item.
     *
     * @param User $user
     * @param Order $order
     * @param OrderItem $orderItem
     * @return bool
     */
    public function delete(User $user, Order $order, OrderItem $orderItem): bool
    {
        return $this->authService->hasPermission($user, 'order.manage', $order->workspace_id);
    }
}
