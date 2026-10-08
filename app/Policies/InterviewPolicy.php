<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Interviews are private to their owner; anyone else gets a 404 so ids can't be probed. */
class InterviewPolicy
{
    public function view(User $user, Interview $interview): Response
    {
        return $this->owns($user, $interview);
    }

    /** Start, answer and complete. */
    public function update(User $user, Interview $interview): Response
    {
        return $this->owns($user, $interview);
    }

    public function delete(User $user, Interview $interview): Response
    {
        return $this->owns($user, $interview);
    }

    private function owns(User $user, Interview $interview): Response
    {
        return $interview->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
