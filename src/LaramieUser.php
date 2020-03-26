<?php

namespace Laramie;

use DB;
use Illuminate\Console\Command;

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
            'username' => str_random(Globals::API_TOKEN_LENGTH),
            'password' => str_random(Globals::API_TOKEN_LENGTH),
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
}


