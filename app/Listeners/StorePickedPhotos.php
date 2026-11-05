<?php

namespace App\Listeners;

use App\Support\PickedPhotos;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;

class StorePickedPhotos
{
    public function handleMediaSelected(MediaSelected $event): void
    {
        if (! $event->success || $event->cancelled) {
            return;
        }

        $paths = collect($event->files)
            ->map(fn ($file) => is_array($file) ? ($file['path'] ?? null) : $file)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->all();

        PickedPhotos::add(array_values($paths));
    }

    public function handlePhotoTaken(PhotoTaken $event): void
    {
        if ($event->path !== '') {
            PickedPhotos::add([$event->path]);
        }
    }
}
