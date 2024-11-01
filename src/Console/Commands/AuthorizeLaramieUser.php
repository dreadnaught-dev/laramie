<?php

namespace Laramie\Console\Commands;

use Arr;
use DB;
use Str;

use Illuminate\Console\Command;
use Laramie\Globals;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;
use PragmaRX\Google2FA\Google2FA;

class AuthorizeLaramieUser extends Command
{
    protected $signature = 'laramie:authorize-user {user} {--password=} {--role=}';

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
        $model = $dataService->getModelByKey('laramieUser');
        $user = $this->argument('user');
        $password = $this->option('password');

        // Because Laravel can use username or email, etc, Laramie tries to be flexible as well.
        $linkedField = config('laramie.username');

        $laramiePassword = LaramieHelpers::getLaramiePasswordObjectFromPasswordText($password);

        // First, find the Laravel user. If they don't exist, create them if a password was provided
        $dbUser = DB::table('users')->where($linkedField, 'like', $user)->first();
        if (!$dbUser) {
            if ($password) {
                \DB::table('users')->insert([
                    'name' => $user,
                    'email' => $user,
                    'password' => $laramiePassword->encryptedValue,
                    'created_at' => 'now()',
                    'updated_at' => 'now()',
                ]);
            } else {
                $this->error(sprintf('Could not find user with %s of \'%s\'. If you would like to create them, you may do so by passing an additional `password` option to this command', $linkedField, $user));

                return;
            }
        } else {
            $laramiePassword = (object) ['encryptedValue' => $dbUser->password];
        }

        // Find the role to assign the user to.
        $role = $this->option('role');
        if ($role == 'super') {
            $role = Globals::SuperAdminRoleId;
        } else {
            $role = Globals::AdminRoleId;
        }

        // Determine if this is the first user. If yes, make them a super admin. Everyone else gets the `admin` role
        // unless the role option is passed as 'super'.
        $existingLaramieUsers = $dataService->findByType($model, ['filterQuery' => false]);
        if (count($existingLaramieUsers) == 0) {
            $role = Globals::SuperAdminRoleId;
        }

        // Find all Laramie users that correspond to the Laravel one
        $existingUsers = $dataService->findByType($model, ['filterQuery' => false], function ($query) use ($user) {
            $query->where(DB::raw('data->>\'user\''), 'ilike', $user);
        });

        if (count($existingUsers) == 0) {
            // No existing Laramie users exist. Set some default info:
            $laramieModel = new LaramieModel();
            $laramieModel->api = (object) [
                'enabled' => false,
                'username' => Str::random(Globals::API_TOKEN_LENGTH),
                'password' => Str::random(Globals::API_TOKEN_LENGTH),
            ];

            $google2fa = new Google2FA();

            $laramieModel->mfa = (object) [
                'enabled' => true,
                'registrationCompleted' => false,
                'secret' => $google2fa->generateSecretKey(),
            ];

            $laramieModel->password = $laramiePassword;
        } else {
            // The user already exists
            $laramieModel = Arr::first($existingUsers);
        }

        $laramieModel->status = 'Active';

        // Get the laramieRole
        $role = $dataService->findById($dataService->getModelByKey('laramieRole'), $role);

        // Set the user's role:
        $laramieModel->user = $user;
        $laramieModel->roles = [$role];

        // Save the user, but prevent events -- we've created everything by hand, don't want LaramieUser's pre/post save events to try to double that work:
        config(['laramie.suppress_events' => true]);
        $dataService->save($model, $laramieModel);

        $this->info('Done');
    }
}
