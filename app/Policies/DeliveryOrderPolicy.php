<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DeliveryOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeliveryOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DeliveryOrder');
    }

    public function view(AuthUser $authUser, DeliveryOrder $deliveryOrder): bool
    {
        return $authUser->can('View:DeliveryOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DeliveryOrder');
    }

    public function update(AuthUser $authUser, DeliveryOrder $deliveryOrder): bool
    {
        return $authUser->can('Update:DeliveryOrder');
    }

    public function delete(AuthUser $authUser, DeliveryOrder $deliveryOrder): bool
    {
        return $authUser->can('Delete:DeliveryOrder');
    }

    public function restore(AuthUser $authUser, DeliveryOrder $deliveryOrder): bool
    {
        return $authUser->can('Restore:DeliveryOrder');
    }

    public function forceDelete(AuthUser $authUser, DeliveryOrder $deliveryOrder): bool
    {
        return $authUser->can('ForceDelete:DeliveryOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DeliveryOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DeliveryOrder');
    }

    public function replicate(AuthUser $authUser, DeliveryOrder $deliveryOrder): bool
    {
        return $authUser->can('Replicate:DeliveryOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DeliveryOrder');
    }

}