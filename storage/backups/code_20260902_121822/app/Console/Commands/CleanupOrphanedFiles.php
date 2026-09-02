<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Institute;
use App\Models\Student;
use App\Models\Document;
use App\Models\Course;
use App\Models\HrEmployee;

class CleanupOrphanedFiles extends Command
{
    protected $signature = 'files:cleanup-orphans {--dry-run : Show files that would be deleted without actually deleting}';
    protected $description = 'Delete orphaned files (files without database records)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $deletedCount = 0;

        $directories = [
            ['disk' => 'public', 'dir' => 'institutes', 'paths' => Institute::withTrashed()->whereNotNull('logo_path')->pluck('logo_path')->toArray()],
            ['disk' => 'public', 'dir' => 'profile-images/students', 'paths' => Student::withTrashed()->whereNotNull('photo')->pluck('photo')->toArray()],
            ['disk' => 'public', 'dir' => 'course-banners', 'paths' => Course::whereNotNull('banner')->pluck('banner')->toArray()],
            ['disk' => 'public', 'dir' => 'documents', 'paths' => Document::withTrashed()->whereNotNull('file_path')->pluck('file_path')->toArray()],
        ];

        foreach ($directories as $item) {
            $deletedCount += $this->cleanDirectory($item['disk'], $item['dir'], $item['paths'], $dryRun);
        }

        $this->info("Cleanup complete. {$deletedCount} orphaned files deleted.");
    }

    protected function cleanDirectory($disk, $directory, $existingPaths, $dryRun)
    {
        if (!Storage::disk($disk)->exists($directory)) {
            return 0;
        }

        $allFiles = Storage::disk($disk)->allFiles($directory);
        $orphaned = array_diff($allFiles, $existingPaths);
        $count = count($orphaned);

        if ($dryRun) {
            $this->line("DRY RUN: Would delete " . $count . " files from {$directory}");
            foreach ($orphaned as $file) {
                $this->line("  - {$file}");
            }
        } else {
            foreach ($orphaned as $file) {
                Storage::disk($disk)->delete($file);
                $this->line("Deleted: {$file}");
            }
        }
        return $count;
    }
}
