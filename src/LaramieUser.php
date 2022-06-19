<?php

declare(strict_types=1);

namespace Laramie;

use DB;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use PragmaRX\Google2FA\Google2FA;
use Str;

class LaramieUser extends LaramieModel
{
    public static function makeWithAuth($username, $password, $enableApi = false, $enableMfa = false)
    {
        $tmp = new static();

        $tmp->user = $username;

        $tmp->api = (object) [
            'enabled' => $enableApi,
            'username' => Str::random(Globals::API_TOKEN_LENGTH),
            'password' => Str::random(Globals::API_TOKEN_LENGTH),
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

    public function getRoles()
    {
        return data_get($this, 'roles', []);
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
                ->filter(function ($item) { return $item === true; })
                ->each(function ($item, $key) use (&$abilities) { $abilities[$key] = true; });
        }

        return $abilities;
    }

    public function hasAbility($ability)
    {
        return array_key_exists($ability, $this->getAbilities());
    }

    public static function onceUsingId($id)
    {
        if (!is_int($id) && LaramieHelpers::isValidUuid($id)) {
            $id = data_get(DB::table('users')
                ->where(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text)::text'), $id)
                ->first(), 'id', -1);
        }

        return auth()->onceUsingId($id);
    }
}
