<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

trait DeletesFiles
{
    protected static function bootDeletesFiles()
    {
        static::deleting(function ($model) {
            // Only hard delete (forceDelete) deletes files
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }
            $model->deleteAssociatedFiles();
        });
    }

    protected function deleteAssociatedFiles()
    {
        $columns = $this->fileColumns ?? ['photo', 'document', 'logo_path', 'banner', 'attachment', 'file_path', 'profile_photo'];

        foreach ($columns as $column) {
            if (empty($this->{$column})) {
                continue;
            }

            $path = $this->{$column};
            $disk = $this->disk ?? 'public';

            // Try public disk
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info("Deleted file: {$path} from record ID {$this->id}");
                continue;
            }

            // Try local disk
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
                Log::info("Deleted file: {$path} from record ID {$this->id}");
                continue;
            }

            // Legacy absolute path fallback
            if (file_exists($path)) {
                @unlink($path);
                Log::info("Deleted legacy file: {$path} from record ID {$this->id}");
            }
        }
    }
}
