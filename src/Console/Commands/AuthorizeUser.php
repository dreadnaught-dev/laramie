<?php

namespace Laramie\Console\Commands;

use Arr;
use Carbon\Carbon;
use DB;
use Hash;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;
use Str;

use Laramie\Globals;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;
use Laramie\AdminModels\LaramieRole;

class AuthorizeUser extends Command
{
    protected $signature = 'laramie:authorize-user {email} {--password=} {--role=}';

    protected $description = 'Authorize a user for access to the admin platform';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Authorize access to the Laramie backend.
     *
     * @return mixed
     */
    public function handle(LaramieDataService $dataService)
    {
        $email = $this->argument('email');
        $password = $this->option('password');
        $roleName = $this->option('role');

        // Because Laravel can use username or email, etc, Laramie tries to be flexible as well.
        $linkedField = config('laramie.username');

        $roleId = $roleName
            ? data_get(LaramieRole::where('name', 'ilike', $roleName)->first(), 'id')
            : Globals::AdminRoleId;

        if (!$roleId) {
            $this->error(sprintf('Could not find the role with the name of \'%s\'', $roleName));
            return;
        }

        // Find the Laravel user. If they don't exist, create them if a password was provided
        $user = DB::table('users')->where($linkedField, 'ilike', $email)->first();

        if (!$user && $password) {
            DB::table('users')->insert([
                'name' => $email,
                'email' => $email,
                'password' => Hash::make($password),
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);

            $user = DB::table('users')->where($linkedField, 'ilike', $email)->first();
        } elseif (!$user) {
            $this->error(sprintf('Could not find user with %s of \'%s\'. If you would like to create them, you may do so by passing an additional `password` option to this command', $linkedField, $email));

            return;
        }

        // User found. Grant them access to Laramie

        $laramieData = json_decode(data_get($this, 'laramie', '{}'));

        if (data_get($laramieData, 'roles')) {
            $this->error('This user has already been granted access.');
            return;
        }

        $laramieData->api = (object) [
            'enabled' => false,
            'username' => Str::random(Globals::API_TOKEN_LENGTH),
            'password' => Str::random(Globals::API_TOKEN_LENGTH),
        ];

        $laramieData->roles = [$roleId];

        DB::table('users')
            ->where('id', $user->id)
            ->update(['data' => json_encode($laramieData)]);
    }
}
