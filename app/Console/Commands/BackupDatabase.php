<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * A nightly mysqldump is enough at this scale — no backup package needed.
 *
 * Schedule it in routes/console.php:
 *   Schedule::command('backup:database')->dailyAt('02:00');
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : How many daily dumps to retain}';

    protected $description = 'Dump the database to storage/backups and prune old dumps';

    public function handle(): int
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        if (($db['driver'] ?? null) !== 'mysql') {
            $this->error("backup:database only supports mysql, not {$db['driver']}.");

            return self::FAILURE;
        }

        $directory = storage_path('backups');
        File::ensureDirectoryExists($directory);

        $file = $directory.'/'.$db['database'].'-'.now()->format('Y-m-d_His').'.sql.gz';

        // The password goes through the environment, never the command line,
        // where it would be visible in `ps`.
        $process = Process::fromShellCommandline(
            sprintf(
                'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick --routines %s | gzip > %s',
                escapeshellarg((string) $db['host']),
                escapeshellarg((string) $db['port']),
                escapeshellarg((string) $db['username']),
                escapeshellarg((string) $db['database']),
                escapeshellarg($file),
            ),
            env: ['MYSQL_PWD' => (string) $db['password']],
            timeout: 600,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Backup failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info(sprintf('Wrote %s (%s KB)', basename($file), number_format(filesize($file) / 1024)));

        $this->prune($directory, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    /** Keeps the most recent N dumps so the disk cannot fill up silently. */
    private function prune(string $directory, int $keep): void
    {
        $dumps = collect(File::files($directory))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $dumps->slice($keep)->each(function ($file) {
            File::delete($file->getPathname());
            $this->line("Pruned {$file->getFilename()}");
        });
    }
}
