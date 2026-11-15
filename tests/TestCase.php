<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private mixed $lastActor = null;

    /**
     * Switching to a different user starts a fresh session, as signing out and in again would.
     * Without this, AuthenticateSession sees the previous user's password hash and signs the new one out.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->lastActor !== null && $this->lastActor !== $user->getAuthIdentifier()) {
            $this->flushSession();
        }
        $this->lastActor = $user->getAuthIdentifier();

        return parent::actingAs($user, $guard);
    }
}
