<?php
namespace FlorianBerger\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\NewUserMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use FlorianBerger\Oidc\Models\OidcIdentity;
use FlorianBerger\Oidc\Models\OidcProvider;
use FlorianBerger\Oidc\Services\Registration;

/**
 * Finishes a new OIDC signup: the callback keeps the verified claims in the session, here only the fields are
 * collected which xTIPP still requires (like the registration of xTIPP) before the account is created.
 */
class OidcCompleteProfileController extends Controller {
    /**
     * Show the "complete your profile" form
     *
     * @param Request $request
     *
     * @return View|RedirectResponse
     */
    public function show(Request $request): View|RedirectResponse {
        $pending = $request->session()->get('plugin-oidc.pending');

        if (!$pending) {
            return redirect()->route('login');
        }

        if (!Registration::allowed()) {
            return $this->registrationDisabled($request);
        }

        return view('oidc::complete-profile', [
            'pending' => $pending,
        ]);
    }

    /**
     * Create the account from the pending claims and the submitted fields
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse {
        $pending = $request->session()->get('plugin-oidc.pending');

        if (!$pending) {
            return redirect()->route('login');
        }

        if (!Registration::allowed()) {
            return $this->registrationDisabled($request);
        }

        $oidcProvider = OidcProvider::active()->find($pending['provider_id']);

        if (!$oidcProvider) {
            $request->session()->forget('plugin-oidc.pending');

            return redirect()->route('login')->with('message-danger', trans('oidc::ui.message.invalid_flow'));
        }

        $email = $pending['email'];

        $rules = [];

        if (!$email) {
            $rules['email'] = 'required|email|max:255|unique:users,email';
        }

        if (config('xtipp.user-nickname') === 1) {
            $rules['nickname'] = 'required|max:255|unique:users,nickname';
        }

        if (config('xtipp.user-first-last-name') === 1) {
            $rules['firstname'] = 'required|max:255';
            $rules['name']      = 'required|max:255';
        }

        $request->validate($rules);

        $finalEmail = $email ?: $request->input('email');

        // A verified email of the provider could belong to an account created in the meantime
        if ($email && Registration::emailInUse($email)) {
            $request->session()->forget('plugin-oidc.pending');

            return redirect()->route('login')->with('message-danger', trans('oidc::ui.message.email_exists'));
        }

        // The subject must not be linked in the meantime (second tab)
        if (OidcIdentity::where('oidc_provider_id', $oidcProvider->id)->where('subject', $pending['subject'])->exists()) {
            $request->session()->forget('plugin-oidc.pending');

            return redirect()->route('login')->with('message-danger', trans('oidc::ui.message.invalid_flow'));
        }

        $userDataArray = [
            'active'   => 0,
            'email'    => $finalEmail,
            // Random password: The user can set a real one with "forgot password"
            'password' => bcrypt(Str::random(40)),
            // nickname/name are not nullable - fall back to something, when the field is not collected
            'nickname' => $pending['name'] ?: explode('@', $finalEmail)[0],
            'name'     => '',
        ];

        if (config('xtipp.user-nickname') === 1) {
            $userDataArray['nickname'] = $request->input('nickname');
        }

        if (config('xtipp.user-first-last-name') === 1) {
            $userDataArray['firstname'] = $request->input('firstname');
            $userDataArray['name']      = $request->input('name');
        }

        if (xtipp_config('email-reminder.enable-by-default', '0') === '1') {
            $userDataArray['email_reminder'] = 1;
        }

        if (xtipp_config('email-tip-confirmation.enable-by-default', '0') === '1') {
            $userDataArray['email_tip_confirmation'] = 1;
        }

        if (xtipp_config('email-message-notification.enable-by-default', '0') === '1') {
            $userDataArray['email_message_notification'] = 1;
        }

        $user = DB::transaction(function () use ($userDataArray, $oidcProvider, $pending) {
            $user = new User($userDataArray);
            $user->save();

            $availableLeagues = config('xtipp.available-leagues');
            if (!is_null($availableLeagues)) {
                $leagueParts = explode(',', $availableLeagues);
                if (count($leagueParts) === 1) {
                    $user->current_league_id = $leagueParts[0];
                    $user->save();
                }
            }

            OidcIdentity::create([
                'user_id'          => $user->id,
                'oidc_provider_id' => $oidcProvider->id,
                'subject'          => $pending['subject'],
                'email'            => $user->email,
                'raw_claims'       => $pending['raw_claims'],
                'last_login_at'    => now(),
            ]);

            return $user;
        });

        Mail::to($user->email)->send(new WelcomeMail($user));

        $allAdmins = User::where('email_no_admin_notification', 0)
            ->whereHas('roles.role', function ($query) {
                $query->where('name', 'Admin');
            })
            ->where('active', '1')
            ->get();
        foreach ($allAdmins as $adminUser) {
            Mail::to($adminUser->email)->send(new NewUserMail($user));
        }

        $request->session()->forget('plugin-oidc.pending');
        $request->session()->flash('message-success', trans('messages.register.success'));

        return redirect()->route('login');
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    private function registrationDisabled(Request $request): RedirectResponse {
        $request->session()->forget('plugin-oidc.pending');

        return redirect()->route('login')->with('message-danger', trans('oidc::ui.message.registration_disabled'));
    }
}
