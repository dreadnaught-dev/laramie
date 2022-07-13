<?php

namespace Laramie;

use Arr;
use DB;
use Illuminate\Console\Command;

use App\User;
use Laramie\Globals;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;
use PragmaRX\Google2FA\Google2FA;

class LaramieUser extends LaramieModel
{
    public static function makeWithAuth($username, $password, $enableApi = false, $enableMfa = false)
    {
        $tmp = new static();

        $tmp->user = $username;

        $tmp->api = (object) [
            'enabled' => $enableApi,
            'username' => \Str::random(Globals::API_TOKEN_LENGTH),
            'password' => \Str::random(Globals::API_TOKEN_LENGTH),
        ];

        $google2fa = new Google2FA();

        $tmp->mfa = (object) [
            'enabled' => $enableMfa,
            'registrationCompleted' => false,
            'secret' => $google2fa->generateSecretKey(),
        ];

        $tmp->password = LaramieHelpers::getLaramiePasswordObjectFromPasswordText($password);

        $tmp->status = 'Active';

        $tmp->roles = [];

        return $tmp;
    }

    public static function createWithAuth($username, $password, $enableApi = false, $enableMfa = false)
    {
        return static::makeWithAuth($username, $password, $enableApi = false, $enableMfa = false)->save();
    }

    public function getLaravelUser()
    {
        return User::where(config('laramie.username'), $this->user)->first();
    }

    public function getRoles()
    {
        return data_get($this, 'roles', []);
    }

    public function isSuperAdmin()
    {
        foreach ($this->getRoles() as $role) {
            if ($role->id == Globals::SuperAdminRoleId) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin()
    {
        foreach ($this->getRoles() as $role) {
            if ($role->id == Globals::AdminRoleId) {
                return true;
            }
        }

        return false;
    }

    public function getAbilities()
    {
        $abilities = [];

        foreach ($this->getRoles() as $role) {
            // The `data` attribute contains the abilities the particular role has been granted
            collect(json_decode(data_get($role->toArray(), 'data')))
                ->filter(function($item) { return $item === true; })
                ->each(function($item, $key) use(&$abilities) { $abilities[$key] = true; });
        }

        return $abilities;
    }

    public function hasAbility($ability)
    {
        return array_key_exists($ability, $this->getAbilities());
    }

    public static function loginOnceUsingId($id) {
        $laramieUser = static::filterQuery(false)->find($id);
        $laravelUser = Arr::first(\DB::select('select id from users where '.config('laramie.username').' like ?', [data_get($laramieUser, 'user')]));
        $success = auth()->onceUsingId(data_get($laravelUser, 'id', -1));
        if ($success) {
            if (app()->runningInConsole()) {
                LaramieDataService::$overrideUserId = $laramieUser->id;
                $laravelUser->_laramie = $laramieUser->id;
            }
            else {
                session()->flash('_laramie', $laramieUser->id);
            }
        }

        return $laramieUser;
    }
}
