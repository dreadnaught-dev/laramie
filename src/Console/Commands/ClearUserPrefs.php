<?php

namespace Laramie\Console\Commands;

use DB;

use Illuminate\Console\Command;

use Laramie\LaramieUser;

class ClearUserPrefs extends Command
{
    protected $signature = 'laramie:clear-user-prefs {user} {keys?*}';

    protected $description = 'Unset a Laramie user\'s preferences data';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * The keys passed into this command are automatically scoped to the 'prefs' json object on the laramieUser record's data field.
     * You can target nested keys by using a comma. For example, this command:
     * `php artisan laramie:clear-user-prefs bobby@dreadnaught.io pageTemplates,listFields,owner closedBannerIds`
     * will unset just the `owner` key in pageTemplates->listFields and the entire closedBannerIds key:value pair.
     *
     * @return mixed
     */
    public function handle()
    {
        $userArg = $this->argument('user');
        $keys = collect($this->argument('keys'));

        $userLookupField = Str::isUuid($userArg)
            ? 'id'
            : 'user';

        $user = LaramieUser::where($userLookupField, $userArg)->first();

        if ($user) {
            $updateString = $keys->reduce(function($carry, $item) {
                return $carry . ' ' . sprintf('#- \'{prefs,%s}\'', $item);
            });

            $query = sprintf('update laramie_data set data = data %s where id = \'%s\'', ($updateString ?? '- \'prefs\''), data_get($user, 'id'));

            DB::statement($query);

            $this->info('Prefs updated for ' . $this->argument('user'));
        }
        else {
            $this->error('Could not find user ' . $this->argument('user'));
        }
    }
}
