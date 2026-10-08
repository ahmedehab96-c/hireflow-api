<?php

namespace App\Policies;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** CVs are private to their owner; anyone else gets a 404 so ids can't be probed. */
class CvPolicy
{
    public function view(User $user, Cv $cv): Response
    {
        return $this->owns($user, $cv);
    }

    public function delete(User $user, Cv $cv): Response
    {
        return $this->owns($user, $cv);
    }

    public function analyze(User $user, Cv $cv): Response
    {
        return $this->owns($user, $cv);
    }

    private function owns(User $user, Cv $cv): Response
    {
        return $cv->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
