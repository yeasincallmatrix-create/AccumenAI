<?php

namespace App\Console\Commands;

use App\Models\NotificationTemplate;
use App\Services\Notification\DefaultNotificationTemplates;
use Illuminate\Console\Command;

/**
 * Seeds (idempotently) the industry-neutral default notification templates.
 * Global templates carry institute_id = NULL and are used when an institute has
 * no override for the event/channel/language combination.
 */
class SeedNotificationTemplates extends Command
{
    protected $signature = 'notifications:seed-templates {--force : Re-seed even if global templates exist}';

    protected $description = 'Seed default notification templates for every registered event and channel';

    public function handle(): int
    {
        $existing = NotificationTemplate::query()->whereNull('institute_id')->count();
        if ($existing > 0 && ! $this->option('force')) {
            $this->info('Default templates already exist ('.$existing.'). Use --force to overwrite.');

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            NotificationTemplate::query()->whereNull('institute_id')->delete();
        }

        $created = 0;
        foreach (DefaultNotificationTemplates::all() as $event => $channels) {
            foreach ($channels as $channel => $languages) {
                foreach ($languages as $language => $template) {
                    $events = config('notifications.events', []);
                    $eventMeta = is_array($events) ? ($events[$event] ?? []) : [];
                    $variables = $eventMeta['variables'] ?? [];

                    NotificationTemplate::updateOrCreate(
                        ['institute_id' => null, 'event' => $event, 'channel' => $channel, 'language' => $language],
                        [
                            'name' => $event.' — '.$channel.' ('.$language.')',
                            'subject' => $template['subject'],
                            'body' => $template['body'],
                            'variables' => $variables,
                            'is_active' => true,
                        ]
                    );
                    $created++;
                }
            }
        }

        $this->info("Seeded {$created} default notification templates.");

        return self::SUCCESS;
    }
}
