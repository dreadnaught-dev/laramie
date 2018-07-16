<?php

namespace Laramie\Console\Commands;

use Illuminate\Console\Command;

class ClearModelCache extends Command
{
    protected $signature = 'laramie:clear';

    protected $description = 'Clear cached Laramie models';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Clear cached Laramie models. This _should_ happen when a change happens to Laramie model file, but if an
     * `referenced` field is updated, the cache needs to be manually cleared.
     *
     * @return mixed
     */
    public function handle()
    {
        $cachedConfigPath = storage_path('framework/cache/laramie-models.php');
        if (file_exists($cachedConfigPath)) {
            unlink($cachedConfigPath);
        }
    }
}
