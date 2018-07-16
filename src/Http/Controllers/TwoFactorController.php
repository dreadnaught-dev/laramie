<?php

namespace Laramie\Http\Controllers;

use Laramie\Services\DuoService;
use Laramie\Services\LaramieDataService;
use DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Handle MFA.
 */
class TwoFactorController extends Controller
{
    protected $duo;
    protected $dataService;

    /**
     * Create a new TwoFactorController.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     *
     * @return TwoFactorController
     */
    public function __construct(LaramieDataService $dataService, DuoService $duo)
    {
        $this->dataService = $dataService;
        $this->duo = $duo;
    }

    /**
     * Register the app with DUO.
     *
     * @return \Illuminate\Http\Request
     */
    public function duoRegistration(Request $request)
    {
        $laramieUser = $this->getLaramieUser();

        // If the user is already logged in via two-factor, send them to the dashboard
        if ($request->session()->has('_laramie')) {
            return redirect()->intended(route('laramie::dashboard'));
        }

        $registrationInfo = $this->duo->register($laramieUser->id);
        $laramieUser->twoFactorAuthentication->id = sprintf('%s|%s', object_get($registrationInfo, 'response.user_id'), object_get($registrationInfo, 'response.username'));
        $this->dataService->save($this->dataService->getModelByKey('LaramieUser'), $laramieUser);

        return view('laramie::two-factor.duo.register', ['registrationInfo' => $registrationInfo]);
    }

    /**
     * Show the user a view to select how they'd like to authenticate with DUO.
     *
     * Check on the user's enrollment status. If the user hasn't completed
     * registration, send them back to the registration scrren to do so. If they
     * have, show them a screen where they can select _how_ they want to
     * authenticate.
     *
     * @return \Illuminate\Http\Request
     */
    public function duoLogin(Request $request)
    {
        $laramieUser = $this->getLaramieUser();
        list($duoUserId, $duoUsername) = explode('|', object_get($laramieUser, 'twoFactorAuthentication.id'));

        // If the user is already logged in via two-factor, send them to the dashboard
        if ($request->session()->has('_laramie')) {
            return redirect()->intended(route('laramie::dashboard'));
        }

        // First, check on the user's enrollment status:
        $preAuth = $this->duo->preAuthenitcate($duoUserId);

        $result = strtolower(object_get($preAuth, 'response.result', ''));

        switch ($result) {
            case 'auth':
                /* Don't do anything, handling will pick up after switch */
                break;
            case 'allow':
                $request->session()->put('_two_factor', 'allow');

                return redirect()->intended(route('laramie::dashboard'));
            case 'deny':
                throw new AuthorizationException('You are not authorized for access.');
            case 'enroll':
                return redirect()->to(route('laramie::duo-register'));
            default:
                throw new AuthorizationException('There was an error connecting to DUO\'s API');
        }

        return view('laramie::two-factor.duo.login', ['preAuth' => $preAuth]);
    }

    /**
     * Login with DUO.
     *
     * @return \Illuminate\Http\Request
     */
    public function postDuoLogin(Request $request)
    {
        $laramieUser = $this->getLaramieUser();
        list($duoUserId, $duoUsername) = explode('|', object_get($laramieUser, 'twoFactorAuthentication.id'));

        $response = $this->duo->authenticate($duoUserId, $request->get('passcode'));

        // Redirect to intended location
        if (object_get($response, 'stat') == 'OK' && object_get($response, 'response.status') == 'allow') {
            $request->session()->put('_two_factor', 'allow');

            return redirect()->intended(route('laramie::dashboard'));
        }

        throw new AuthorizationException('There was an error completing second-factor authentication');
    }

    private function getLaramieUser()
    {
        $linkedField = config('laramie.username');

        // Get the LaramieUser that's linked to the Laravel one (via `$linkedField`)
        $tmp = DB::table('laramie_data')
            ->where('type', 'LaramieUser')
            ->where(DB::raw('data->>\'user\''), '=', request()->user()->{$linkedField})
            ->first();

        return $this->dataService->findById($this->dataService->getModelByKey('LaramieUser'), $tmp->id);
    }
}
