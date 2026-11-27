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
            $idToken = $this->client->fetchIdToken($oidcProvider, (string) $request->query('code'));
            $claims  = $this->verifier->verify($oidcProvider, $idToken, $flow['nonce']);
        } catch (Throwable $exception) {
            Log::warning('OIDC callback rejected: the id_token could not be fetched or verified.', [
                'provider' => $provider, 'exception' => $exception->getMessage(),
            ]);

            return $this->redirectWithError(trans('oidc::ui.message.provider_error'));
        }

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
            return $this->redirectWithError(trans('oidc::ui.message.invalid_flow'));
        }

        $identity = OidcIdentity::where('oidc_provider_id', $oidcProvider->id)
            ->where('subject', $claims['sub'])
            ->first();

        if ($identity && $identity->user_id !== $linkingUserId) {
            return redirect()->route('plugin-oidc.connections')
                ->with('message-danger', trans('oidc::ui.message.already_linked_elsewhere', ['provider' => $oidcProvider->displayName()]));
        }

        if ($identity) {
            return redirect()->route('plugin-oidc.connections')
                ->with('message-info', trans('oidc::ui.message.already_connected'));
        }

        // One identity per provider and user
        if (OidcIdentity::where('oidc_provider_id', $oidcProvider->id)->where('user_id', $linkingUserId)->exists()) {
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
            return $this->redirectWithError(trans('oidc::ui.message.email_exists'));
        }

        if (!Registration::allowed()) {
            return $this->redirectWithError(trans('oidc::ui.message.registration_disabled'));
        }

        $request->session()->put('plugin-oidc.pending', [
            'provider_id' => $oidcProvider->id,
            'subject'     => $claims['sub'],
            'email'       => $email,
            'name'        => $claims['name'] ?? null,
            'given_name'  => $claims['given_name'] ?? null,
            'family_name' => $claims['family_name'] ?? null,
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
            return $this->redirectWithError(trans('messages.notactive'));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $identity->update([
            'last_login_at' => now(),
            'email'         => $claims['email'] ?? $identity->email,
        ]);

        return redirect('/home');
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
