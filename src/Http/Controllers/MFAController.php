<?php

declare(strict_types=1);

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Laramie\LaramieUser;
use Laramie\Services\LaramieDataService;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handle MFA.
 */
class MFAController extends Controller
{
    protected $dataService;

    /**
     * Create a new MFAController.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     *
     * @return TwoFactorController
     */
    public function __construct(LaramieDataService $dataService)
    {
        $this->dataService = $dataService;
        $this->google2fa = new Google2FA();
    }

    public function getLogin()
    {
        return view('laramie::mfa.login');
    }

    public function postLogin(Request $request)
    {
        $user = $this->getLaramieUser();

        $isValid = $this->google2fa->verifyKey(data_get($user, 'mfa.secret'), ($request->get('mfa') ?: ''));

        if ($isValid) {
            $request->session()->put('_mfa', 'allow');

            return redirect()->intended(route('laramie::dashboard'));
        }

        return view('laramie::mfa.login')
            ->withErrors('Invalid OTP Code');
    }

    public function getRecovery()
    {
        return view('laramie::mfa.recovery');
    }

    public function postRecovery(Request $request)
    {
        $user = $this->getLaramieUser();

        $recoveryCodes = collect(preg_split('/[|]/', data_get($user, 'mfa.recoveryCodes')))->filter();
        $recoveryCode = $request->get('recovery-code') ?: 'noop';
        $isValid = $recoveryCodes->contains($recoveryCode);

        if ($isValid) {
            // Remove the recovery code from being used again. The reason for
            // making recovery codes one-time use is to prevent it from becoming a
            // password, which weakens the point of MFA (in addition to keeping it
            // from being used again if cached by some MITM vector; whether the
            // browser a key-logger, networking equipment, etc).
            $mfaRecoveryCodes = $recoveryCodes
                ->filter(function ($item) use ($recoveryCode) {
                    return $item != $recoveryCode;
                });

            for ($i = $mfaRecoveryCodes->count(); $i < 5; $i++) {
                $mfaRecoveryCodes->push(\Str::random(10));
            }

            $user->mfa->recoveryCodes = $mfaRecoveryCodes->join('|');

            $user->save(false, false);

            $request->session()->put('_mfa', 'allow');

            return redirect()->intended(route('laramie::dashboard'));
        }

        return view('laramie::mfa.recovery')
            ->withErrors('Invalid Recovery Code');
    }

    public function getRegister(Request $request)
    {
        $user = $this->getLaramieUser();

        return view('laramie::mfa.register')
            ->with('qrCodeImage', $this->getQRCodeImage($request, $user));
    }

    public function postRegister(Request $request)
    {
        $user = $this->getLaramieUser();

        $isValid = $this->google2fa->verifyKey($this->getUserSecret($request, $user), ($request->get('mfa') ?: ''));

        if ($isValid) {
            $user->mfa->registrationCompleted = true;

            // Give the user some recovery codes:
            $mfaRecoveryCodes = collect([]);
            for ($i = 0; $i < 5; $i++) {
                $mfaRecoveryCodes->push(\Str::random(10));
            }
            $user->mfa->recoveryCodes = $mfaRecoveryCodes->join('|');

            if ($mfaReset = $request->session()->get('_mfa_secret')) {
                $user->mfa->secret = $mfaReset;
            }

            $user->save(false, false);

            $request->session()->put('_mfa', 'allow');

            return redirect()->intended(route('laramie::dashboard'));
        }

        return view('laramie::mfa.register')
            ->with('qrCodeImage', $this->getQRCodeImage($request, $user))
            ->withErrors('Invalid OTP Code');
    }

    private function getQRCodeImage($request, $user)
    {
        return $this->google2fa->getQRCodeInline(
            config('laramie.site_name'),
            data_get($user, 'user'),
            $this->getUserSecret($request, $user)
        );
    }

    private function getUserSecret($request, $user)
    {
        $secret = data_get($user, 'mfa.secret');
        $isResettingMfa = $request->has('mfa-reset');

        if ($isResettingMfa) {
            if ($request->session()->has('_mfa_secret')) {
                $secret = $request->session()->get('_mfa_secret');
            } else {
                $secret = $this->google2fa->generateSecretKey();
                $request->session()->put('_mfa_secret', $secret);
            }
        }

        return $secret;
    }

    private function getLaramieUser()
    {
        $linkedField = config('laramie.username');
        $userRecord = DB::table('laramie_data')
            ->where('type', 'laramieUser')
            ->where(DB::raw('data->>\'user\''), '=', auth()->user()->{$linkedField})
            ->where(DB::raw('data->>\'status\''), '=', 'Active')
            ->first();

        if (!$userRecord) {
            abort(401);
        }

        return LaramieUser::find($userRecord->id);
    }
}
