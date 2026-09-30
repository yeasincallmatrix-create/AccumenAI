<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateBackupMasterKey extends Command
{
    protected $signature = 'backup:generate-master-key';
    protected $description = 'Generate BACKUP_MASTER_KEY for .env';

    public function handle()
    {
        $key = base64_encode(random_bytes(32));

        $this->info('BACKUP_MASTER_KEY generated:');
        $this->newLine();
        $this->line('BACKUP_MASTER_KEY=' . $key);
        $this->newLine();
        $this->warn('Add this line to .env and NEVER lose it.');
        $this->warn('Losing this key = all backups unrecoverable.');

        return 0;
    }
}
