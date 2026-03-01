<?php

namespace App\Services;

use Illuminate\Support\Str;

class SpatieMediaService
{
    public static function upload($model, $media)
    {
        // Try to get the uploaded file from the current request to derive a slugged filename
        $file = request()->file($media);
        if ($file) {
            $original = $file->getClientOriginalName();
            $basename = pathinfo($original, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $slug = Str::slug($basename);
            $sluggedName = $extension ? ($slug . '.' . $extension) : $slug;

            return $model
                ->addMediaFromRequest($media)
                ->usingFileName($sluggedName)
                ->toMediaCollection($media);
        }

        // Fallback if file is not available for any reason
        return $model->addMediaFromRequest($media)->toMediaCollection($media);
    }

    public static function uploadFromRequest($model, $file, $collectionName)
    {
        $original = $file->getClientOriginalName();
        $basename = pathinfo($original, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $slug = Str::slug($basename);
        $sluggedName = $extension ? ($slug . '.' . $extension) : $slug;

        return $model
            ->addMedia($file)
            ->usingFileName($sluggedName)
            ->toMediaCollection($collectionName);
    }

    public static function update($request, $model, $media)
    {
        if ($request->hasFile($media)) {
            $newImageFile = $request->file($media);
            $existingImage = $model->getFirstMedia($media);

            $original = $newImageFile->getClientOriginalName();
            $basename = pathinfo($original, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $slug = Str::slug($basename);
            $newImageName = $extension ? ($slug . '.' . $extension) : $slug;

            if (!$existingImage || $existingImage->file_name !== $newImageName) {
                return $model
                    ->addMedia($newImageFile)
                    ->usingFileName($newImageName)
                    ->toMediaCollection($media);
            }
        }
        return null;
    }
}
