<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Request;
use Illuminate\Auth\Access\HandlesAuthorization;

class RequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Request');
    }

    public function view(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('View:Request');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Request');
    }

    public function update(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('Update:Request');
    }

    public function delete(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('Delete:Request');
    }

    public function restore(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('Restore:Request');
    }

    public function forceDelete(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('ForceDelete:Request');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Request');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Request');
    }

    public function replicate(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('Replicate:Request');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Request');
    }

    public function assignRequest(AuthUser $authUser, Request $request): bool
    {
        return $authUser->can('AssignRequest:Request');
    }

}