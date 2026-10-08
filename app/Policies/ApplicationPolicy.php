<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Applications are private to their owner; others get a 404 so ids can't be probed. */
class ApplicationPolicy
{
    public function view(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    public function update(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    public function delete(User $user, Application $application): Response
    {
        return $this->owns($user, $application);
    }

    private function owns(User $user, Application $application): Response
    {
        return $application->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
