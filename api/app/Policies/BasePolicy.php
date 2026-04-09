<?php

namespace App\Policies;

use App\Services\AuthService;

abstract class BasePolicy
{
    /**
     * @var AuthService
     */
    protected AuthService $authService;

    /**
     * BasePolicy constructor.
     */
    public function __construct()
    {
        $this->authService = new AuthService();
    }
}
