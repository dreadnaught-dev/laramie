<?php

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Laramie\Services\LaramieDataService;

use Laramie\LaramieUser;

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

    public function getRegister()
    {
        $user = $this->getLaramieUser();

        $qrCodeImage = $this->google2fa->getQRCodeInline(
            config('laramie.site_name'),
            data_get($user, 'user'),
            data_get($user, 'mfa.secret')
        );

        return view('laramie::mfa.register')
            ->with('qrCodeImage', $qrCodeImage);
    }

    public function postRegister(Request $request)
    {
        $user = $this->getLaramieUser();

        $isValid = $this->google2fa->verifyKey(data_get($user, 'mfa.secret'), ($request->get('mfa') ?: ''));

        if ($isValid) {
            $user->mfa->registrationCompleted = true;
            $user->save(false, false);
            $request->session()->put('_mfa', 'allow');

            return redirect()->intended(route('laramie::dashboard'));
        }

        return view('laramie::mfa.register')
            ->withErrors('Invalid OTP Code');
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
