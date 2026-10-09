<?php

namespace App\GraphQL\Mutations;

use App\Modules\Master\Models\Studio;
use App\Modules\Master\Models\StudioPhoto;
use App\Modules\Master\Models\Bundle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudioMutations
{
    public function create($_, array $args): Studio
    {
        $this->validateFiles($args);
        $studio = DB::transaction(function () use ($args) {
            $studio = Studio::create($this->studioAttributes($args));
            $this->storeThumbnail($studio, $args['thumbnail'] ?? null);
            $this->storePhotos($studio, $args['photos'] ?? []);

            return $studio;
        });

        return $studio->load('photos');
    }

    public function update($_, array $args): Studio
    {
        $this->validateFiles($args);
        $studio = Studio::findOrFail($args['id']);
        $oldThumbnail = $studio->thumbnail;

        DB::transaction(function () use ($studio, $args, $oldThumbnail) {
            $studio->update($this->studioAttributes($args, false));
            if (array_key_exists('thumbnail', $args) && $args['thumbnail'] instanceof UploadedFile) {
                $this->storeThumbnail($studio, $args['thumbnail']);
                $this->deleteFile($oldThumbnail);
            }
            if (! empty($args['photos'])) {
                $this->storePhotos($studio, $args['photos']);
            }
        });

        return $studio->load('photos');
    }

    public function uploadPhotos($_, array $args): Studio
    {
        $this->validateFiles($args);
        $studio = Studio::findOrFail($args['studio_id']);

        DB::transaction(function () use ($studio, $args) {
            $this->storePhotos($studio, $args['photos']);
        });

        return $studio->load('photos');
    }

    public function deletePhoto($_, array $args): Studio
    {
        $photo = StudioPhoto::findOrFail($args['id']);
        $studio = $photo->studio;
        $path = $photo->path;
        $photo->delete();
        $this->deleteFile($path);

        return $studio->load('photos');
    }

    public function delete($_, array $args): Studio
    {
        $studio = Studio::findOrFail($args['id']);
        $bundle = Bundle::withTrashed()->where('studio_id', $studio->id)->first();
        if ($bundle) {
            throw ValidationException::withMessages([
                'id' => "Studio tidak dapat dihapus karena dipakai bundle {$bundle->name}.",
            ]);
        }

        $paths = $studio->photos()->pluck('path')->push($studio->thumbnail)->filter();
        $studio->delete();
        $paths->each(fn (string $path) => $this->deleteFile($path));

        return $studio;
    }

    private function studioAttributes(array $args, bool $includeDefaults = true): array
    {
        $fields = ['name', 'description', 'capacity', 'size_sqm', 'is_active', 'sort_order'];
        $attributes = array_intersect_key($args, array_flip($fields));

        if (array_key_exists('slug', $args) && $args['slug'] !== null) {
            $attributes['slug'] = Str::slug($args['slug']);
        } elseif ($includeDefaults) {
            $attributes['slug'] = $this->uniqueSlug($args['name']);
        }

        return $attributes;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'studio';
        $slug = $base;
        $suffix = 2;
        while (Studio::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function storeThumbnail(Studio $studio, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        $studio->thumbnail = $file->store('studios/'.$studio->slug.'/thumbnail', 'public');
        $studio->save();
    }

    private function storePhotos(Studio $studio, array $files): void
    {
        $nextOrder = (int) $studio->photos()->max('sort_order') + 1;
        foreach ($files as $file) {
            $path = $file->store('studios/'.$studio->slug.'/showcase', 'public');
            $studio->photos()->create([
                'path' => $path,
                'sort_order' => $nextOrder++,
            ]);
        }
    }

    private function validateFiles(array $args): void
    {
        $files = [];
        if (($args['thumbnail'] ?? null) instanceof UploadedFile) {
            $files[] = $args['thumbnail'];
        }
        foreach ($args['photos'] ?? [] as $file) {
            $files[] = $file;
        }

        foreach ($files as $file) {
            if (! $file->isValid() || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) || $file->getSize() > 10 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'photos' => ['Semua file harus berupa JPG, PNG, atau WEBP dengan ukuran maksimal 10 MB.'],
                ]);
            }
        }
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
