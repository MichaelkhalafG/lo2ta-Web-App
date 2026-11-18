<?php

namespace App\Console\Commands;

use App\Support\Uploads\Hardening;
use Illuminate\Console\Command;

/**
 * Writes (or with --check, verifies) the .htaccess files that keep uploads inert. A deploy step after every copy
 * (docs/DEPLOY_NOTES.md): the check must print "OK" for every line.
 */
class HardenUploads extends Command
{
    protected $signature = 'uploads:harden {--check : only verify, change nothing}';

    protected $description = 'Write or verify the server rules that stop uploaded files from executing';

    public function handle(): int
    {
        $bad = 0;
        foreach (Hardening::files() as $path => $content) {
            $current = is_file($path) ? file_get_contents($path) : null;
            if ($current === $content) {
                $this->line("OK      {$path}");

                continue;
            }
            if ($this->option('check')) {
                $this->error(($current === null ? 'MISSING ' : 'CHANGED ').$path);
                $bad++;

                continue;
            }
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $content);
            $this->line("WRITTEN {$path}");
        }

        $link = public_path('storage');
        if (! is_link($link) && ! is_dir($link)) {
            $this->warn("NOTE    {$link} does not exist yet: run php artisan storage:link");
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
