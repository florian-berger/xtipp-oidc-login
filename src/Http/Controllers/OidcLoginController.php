<?php
namespace FlorianBerger\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use FlorianBerger\Oidc\Models\OidcIdentity;
use FlorianBerger\Oidc\Models\OidcProvider;
use FlorianBerger\Oidc\Services\DebugLog;
use FlorianBerger\Oidc\Services\IdTokenVerifier;
use FlorianBerger\Oidc\Services\OidcClient;
use FlorianBerger\Oidc\Services\Registration;

/**
 * Login page with the providers and the redirect/callback of the authorization-code flow, for "log in with
 * provider" and for "connect provider to my account" (intent=link)
 */
class OidcLoginController extends Controller {
    /**
     * @param OidcClient      $client
     * @param IdTokenVerifier $verifier
     */
    public function __construct(
        private readonly OidcClient $client,
        private readonly IdTokenVerifier $verifier
    ) {
    }

    /**
     * Page with the login buttons of all active providers
     *
     * @return View|RedirectResponse
     */
    public function index(): View|RedirectResponse {
        if (Auth::check()) {
            return redirect('/home');
        }

        return view('oidc::login', [
            'oidcProviders' => OidcProvider::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Redirect the user to the authorization endpoint of the provider
     *
     * @param Request $request
     * @param string  $provider
     *
     * @return RedirectResponse
     */
    public function redirect(Request $request, string $provider): RedirectResponse {
        $oidcProvider = OidcProvider::where('slug', $provider)->active()->firstOrFail();

        $intent = ($request->query('intent') === 'link' && Auth::check()) ? 'link' : 'login';

        // This address is also the launch URL for the dashboard of a provider (e.g. Authentik). A user who is already
        // logged in is sent to the start page instead: a new login could switch to another account.
        if ($intent === 'login' && Auth::check()) {
            DebugLog::log('flow skipped: the user is already logged in.', $oidcProvider, ['user_id' => Auth::id()]);

            return redirect('/home');
        }

        $state  = Str::random(40);
        $nonce  = Str::random(40);

        try {
            $url = $this->client->getAuthorizationUrl($oidcProvider, $state, $nonce);
        } catch (Throwable $exception) {
            Log::warning('OIDC redirect failed: the discovery document of the provider is not available.', [
                'provider' => $provider, 'exception' => $exception->getMessage(),
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.provider_error'));
        }

        $request->session()->put('plugin-oidc.flow', [
            'state'           => $state,
            'nonce'           => $nonce,
            'intent'          => $intent,
            'provider_id'     => $oidcProvider->id,
            'linking_user_id' => $intent === 'link' ? Auth::id() : null,
        ]);

        DebugLog::log('flow started', $oidcProvider, ['intent' => $intent]);

        return redirect()->away($url);
    }

    /**
     * Handle the redirect back from the provider
     *
     * @param Request $request
     * @param string  $provider
     *
     * @return RedirectResponse
     */
    public function callback(Request $request, string $provider): RedirectResponse {
        $flow = $request->session()->pull('plugin-oidc.flow');

        $oidcProvider = OidcProvider::where('slug', $provider)->active()->first();

        // Only whether the values exist and match, never the state or the code themselves
        DebugLog::log('callback', $provider, [
            'has_flow'          => (bool) $flow,
            'intent'            => $flow['intent'] ?? null,
            'provider_active'   => (bool) $oidcProvider,
            'provider_matches'  => $flow && $oidcProvider && $oidcProvider->id === $flow['provider_id'],
            'has_state'         => $request->filled('state'),
            'state_matches'     => $flow && $request->filled('state') && hash_equals($flow['state'], (string) $request->query('state')),
            'has_code'          => $request->filled('code'),
            'error'             => $request->query('error'),
            'error_description' => $request->query('error_description'),
        ]);

        if (!$flow || !$oidcProvider || $oidcProvider->id !== $flow['provider_id']
            || !$request->filled('state') || !hash_equals($flow['state'], (string) $request->query('state'))) {
            Log::warning('OIDC callback rejected: invalid flow (session, provider or state).', [
                'provider'  => $provider,
                'has_flow'  => (bool) $flow,
                'has_state' => $request->filled('state'),
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.invalid_flow'));
        }

        if (!$request->filled('code')) {
            Log::warning('OIDC callback rejected: no code.', [
                'provider'          => $provider,
                'error'             => $request->query('error'),
                'error_description' => $request->query('error_description'),
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.invalid_flow'));
        }

        try {
            $tokens = $this->client->fetchTokens($oidcProvider, (string) $request->query('code'));
            $claims = $this->verifier->verify($oidcProvider, $tokens['id_token'], $flow['nonce']);
        } catch (Throwable $exception) {
            Log::warning('OIDC callback rejected: the id_token could not be fetched or verified.', [
                'provider' => $provider, 'exception' => $exception->getMessage(),
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.provider_error'));
        }

        // All claims, except the nonce
        DebugLog::log('claims of the id_token', $oidcProvider, ['claims' => array_diff_key($claims, ['nonce' => true])]);

        // Only fills claims that are missing in the id_token, e.g. the email of Discord
        $claims = $this->client->addUserInfoClaims($oidcProvider, $claims, $tokens['access_token']);

        if ($flow['intent'] === 'link') {
            return $this->handleLinkCallback($oidcProvider, $claims, $flow['linking_user_id']);
        }

        return $this->handleLoginCallback($request, $oidcProvider, $claims);
    }

    /**
     * Connect the provider to the account of the logged-in user
     *
     * @param OidcProvider $oidcProvider
     * @param array        $claims
     * @param int|null     $linkingUserId
     *
     * @return RedirectResponse
     */
    private function handleLinkCallback(OidcProvider $oidcProvider, array $claims, ?int $linkingUserId): RedirectResponse {
        // The user could have been logged out or switched in between
        if (!$linkingUserId || Auth::id() !== $linkingUserId) {
            DebugLog::log('link refused: the user who started the flow is no longer logged in.', $oidcProvider, [
                'linking_user_id' => $linkingUserId,
                'current_user_id' => Auth::id(),
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.invalid_flow'));
        }

        $identity = OidcIdentity::where('oidc_provider_id', $oidcProvider->id)
            ->where('subject', $claims['sub'])
            ->first();

        if ($identity && $identity->user_id !== $linkingUserId) {
            DebugLog::log('link refused: the provider account is already linked to another user.', $oidcProvider, [
                'user_id'       => $linkingUserId,
                'other_user_id' => $identity->user_id,
            ]);

            return redirect()->route('plugin-oidc.connections')
                ->with('message-danger', trans('oidc::ui.message.already_linked_elsewhere', ['provider' => $oidcProvider->displayName()]));
        }

        if ($identity) {
            DebugLog::log('link skipped: the provider account is already linked to this user.', $oidcProvider, ['user_id' => $linkingUserId]);

            return redirect()->route('plugin-oidc.connections')
                ->with('message-info', trans('oidc::ui.message.already_connected'));
        }

        // One identity per provider and user
        if (OidcIdentity::where('oidc_provider_id', $oidcProvider->id)->where('user_id', $linkingUserId)->exists()) {
            DebugLog::log('link refused: the user has already linked another account of this provider.', $oidcProvider, ['user_id' => $linkingUserId]);

            return redirect()->route('plugin-oidc.connections')
                ->with('message-info', trans('oidc::ui.message.already_connected'));
        }

        OidcIdentity::create([
            'user_id'          => $linkingUserId,
            'oidc_provider_id' => $oidcProvider->id,
            'subject'          => $claims['sub'],
            'email'            => $claims['email'] ?? null,
            'last_login_at'    => now(),
        ]);

        DebugLog::log('provider linked', $oidcProvider, ['user_id' => $linkingUserId]);

        return redirect()->route('plugin-oidc.connections')
            ->with('message-success', trans('oidc::ui.message.connected', ['provider' => $oidcProvider->displayName()]));
    }

    /**
     * Log in with a connected identity or start the registration, an email of an existing account is rejected
     *
     * @param Request      $request
     * @param OidcProvider $oidcProvider
     * @param array        $claims
     *
     * @return RedirectResponse
     */
    private function handleLoginCallback(Request $request, OidcProvider $oidcProvider, array $claims): RedirectResponse {
        $identity = OidcIdentity::where('oidc_provider_id', $oidcProvider->id)
            ->where('subject', $claims['sub'])
            ->first();

        if ($identity) {
            return $this->finishLogin($request, $identity, $claims);
        }

        $email = $claims['email'] ?? null;

        // An address of an existing account is never taken over, not even if the provider calls it verified: The
        // owner has to log in and connect the provider on the page of the connections.
        if ($email && Registration::emailInUse($email)) {
            DebugLog::log('login refused: no linked account, and the email address belongs to an existing account.', $oidcProvider, ['email' => $email]);

            return $this->redirectWithError(trans('oidc::ui.message.email_exists'));
        }

        if (!Registration::allowed()) {
            DebugLog::log('login refused: no linked account, and the registration is disabled.', $oidcProvider, [
                'xtipp_registration_enabled' => config('xtipp.registration-enable') !== 0,
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.registration_disabled'));
        }

        $request->session()->put('plugin-oidc.pending', [
            'provider_id' => $oidcProvider->id,
            'subject'     => $claims['sub'],
            'email'       => $email,
            'name'        => $claims['name'] ?? null,
            'given_name'  => $claims['given_name'] ?? null,
            'family_name' => $claims['family_name'] ?? null,
            'nickname'    => $this->nicknameFromClaims($claims),
        ]);

        DebugLog::log('registration started: no linked account, the user completes the profile.', $oidcProvider, [
            'email'       => $email,
            'has_email'   => (bool) $email,
            'name'        => $claims['name'] ?? null,
            'given_name'  => $claims['given_name'] ?? null,
            'family_name' => $claims['family_name'] ?? null,
            'nickname'    => $this->nicknameFromClaims($claims),
        ]);

        return redirect()->route('plugin-oidc.complete_profile.show');
    }

    /**
     * Log in the user behind an identity, unless the account is not yet activated by an admin
     *
     * @param Request      $request
     * @param OidcIdentity $identity
     * @param array        $claims
     *
     * @return RedirectResponse
     */
    private function finishLogin(Request $request, OidcIdentity $identity, array $claims): RedirectResponse {
        $user = $identity->user;

        if (!$user || !$user->active) {
            DebugLog::log('login refused: the account does not exist or is not activated.', $identity->provider, [
                'user_id'     => $identity->user_id,
                'user_exists' => (bool) $user,
            ]);

            return $this->redirectWithError(trans('messages.notactive'));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $identity->update([
            'last_login_at' => now(),
            'email'         => $claims['email'] ?? $identity->email,
        ]);

        DebugLog::log('logged in with a linked account', $identity->provider, ['user_id' => $user->id]);

        return redirect('/home');
    }

    /**
     * Default for the nickname of a new account: "nickname", then "preferred_username", then "name"
     *
     * @param array $claims
     *
     * @return string|null
     */
    private function nicknameFromClaims(array $claims): ?string {
        foreach (['nickname', 'preferred_username', 'name'] as $claim) {
            if (isset($claims[$claim]) && is_string($claims[$claim]) && trim($claims[$claim]) !== '') {
                return mb_substr(trim($claims[$claim]), 0, 255);
            }
        }

        return null;
    }

    /**
     * @param string $message
     *
     * @return RedirectResponse
     */
    private function redirectWithError(string $message): RedirectResponse {
        return redirect()->route('login')->with('message-danger', $message);
    }
}
