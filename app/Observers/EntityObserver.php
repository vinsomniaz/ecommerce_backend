<?php

namespace App\Observers;

use App\Models\Entity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class EntityObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(Entity $entity): void
    {
        $this->flush($entity);
    }

    public function updated(Entity $entity): void
    {
        // Invalidate cache when relevant fields change
        if ($entity->wasChanged([
            'type',
            'is_active',
            'tipo_documento',
        ])) {
            $this->flush($entity);
        }
    }

    public function deleted(Entity $entity): void
    {
        $this->flush($entity);
    }

    public function restored(Entity $entity): void
    {
        $this->flush($entity);
    }

    private function flush(Entity $entity): void
    {
        // Increment version to invalidate all cached entity statistics
        Cache::increment('entities_version');
    }
}
