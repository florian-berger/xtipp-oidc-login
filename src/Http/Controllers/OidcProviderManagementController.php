<?php
namespace FlorianBerger\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use FlorianBerger\Oidc\Models\OidcProvider;
use FlorianBerger\Oidc\Services\OidcClient;
use FlorianBerger\Oidc\Services\OidcDiscoveryService;
use FlorianBerger\Oidc\Services\ProviderTemplates;

/**
 * Administration of the providers
 *
 * The routes are protected by the middleware "auth" and "hasRole:Admin". The check in the constructor is a second
 * barrier, in case the routes are ever registered without them.
 */
class OidcProviderManagementController extends Controller {
    /**
     * Scopes of a new provider, also used when a template has none
     */
    private const DEFAULT_SCOPES = 'openid email profile';

    /**
     * @param OidcClient $client
     */
    public function __construct(private readonly OidcClient $client) {
        $this->middleware(function (Request $request, Closure $next) {
            $user = Auth::user();
            abort_unless($user !== null && $user->hasRole('Admin'), 403);

            return $next($request);
        });
    }

    /**
     * Overview
     *
     * @return View
     */
    public function index(): View {
        $allOidcProviders = OidcProvider::orderBy('name')->get();

        return view('oidc::admin.index', [
            'allOidcProviders' => $allOidcProviders,
            'redirectUrls'     => $allOidcProviders->mapWithKeys(fn (OidcProvider $provider) => [$provider->id => $this->client->redirectUri($provider)]),
        ]);
    }

    /**
     * Form for a new provider
     *
     * @param ProviderTemplates $templates
     *
     * @return View
     */
    public function newView(ProviderTemplates $templates): View {
        return view('oidc::admin.form', [
            'oidcProvider'      => new OidcProvider(['scopes' => self::DEFAULT_SCOPES, 'active' => true]),
            'providerTemplates' => $templates->all(),
            'defaultScopes'     => self::DEFAULT_SCOPES,
        ]);
    }

    /**
     * Create the provider
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function create(Request $request): RedirectResponse {
        $request->validate($this->validationRules($request));

        $provider = new OidcProvider();
        $provider->fill($request->except(['client_secret', 'extra_authorize_params', 'active']));
        $provider->client_secret          = $request->input('client_secret');
        $provider->extra_authorize_params = $this->decodeExtraAuthorizeParams($request);
        $provider->active                 = $request->boolean('active');
        $provider->save();

        session()->flash('message-success', trans('oidc::ui.admin.success.create'));

        return redirect()->route('plugin-oidc.admin.index');
    }

    /**
     * Form for an existing provider
     *
     * @param int $id
     *
     * @return View
     */
    public function editView(int $id): View {
        return view('oidc::admin.form', ['oidcProvider' => OidcProvider::findOrFail($id)]);
    }

    /**
     * Update the provider
     *
     * @param Request              $request
     * @param OidcDiscoveryService $discovery
     *
     * @return RedirectResponse
     */
    public function update(Request $request, OidcDiscoveryService $discovery): RedirectResponse {
        $provider = OidcProvider::findOrFail($request->input('oidc_provider_id'));

        $request->validate($this->validationRules($request, $provider->id));

        $provider->fill($request->except(['client_secret', 'extra_authorize_params', 'active']));
        $provider->extra_authorize_params = $this->decodeExtraAuthorizeParams($request);

        // Keep the saved secret if the field is blank
        if ($request->filled('client_secret')) {
            $provider->client_secret = $request->input('client_secret');
        }

        $provider->active = $request->boolean('active');
        $provider->save();

        // The endpoints may have changed
        $discovery->forget($provider);

        session()->flash('message-success', trans('oidc::ui.admin.success.save'));

        return back();
    }

    /**
     * Delete the provider with its connections
     *
     * @param Request              $request
     * @param OidcDiscoveryService $discovery
     * @param int                  $id
     *
     * @return RedirectResponse
     */
    public function delete(Request $request, OidcDiscoveryService $discovery, int $id): RedirectResponse {
        $provider = OidcProvider::findOrFail($id);

        $provider->identities()->delete();
        $discovery->forget($provider);
        $provider->delete();

        session()->flash('message-success', trans('oidc::ui.admin.success.delete'));

        return back();
    }

    /**
     * Validation rules for create/update
     *
     * @param Request  $request
     * @param int|null $ignoreId
     *
     * @return array
     */
    private function validationRules(Request $request, ?int $ignoreId = null): array {
        return [
            'name'                   => 'required|max:255',
            'slug'                   => ['required', 'max:255', 'alpha_dash', Rule::unique('plugin_oidc_providers', 'slug')->ignore($ignoreId)],
            'button_label'           => 'nullable|max:255',
            'button_color'           => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon'                   => ['nullable', 'max:100', 'regex:/^[a-zA-Z0-9\-\s]+$/'],
            'issuer'                 => 'required|max:255|url|starts_with:https://',
            'client_id'              => 'required|max:255',
            'client_secret'          => $ignoreId === null ? 'required|max:2000' : 'nullable|max:2000',
            'scopes'                 => 'required|max:255',
            'extra_authorize_params' => [
                'nullable',
                'json',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value !== null && $value !== '' && !is_array(json_decode($value, true))) {
                        $fail(trans('oidc::ui.admin.error.extra_authorize_params_not_object'));
                    }
                },
            ],
        ];
    }

    /**
     * Decode the JSON object of extra authorization parameters (e.g. "claims" of Twitch)
     *
     * @param Request $request
     *
     * @return array|null
     */
    private function decodeExtraAuthorizeParams(Request $request): ?array {
        if (!$request->filled('extra_authorize_params')) {
            return null;
        }

        return json_decode($request->input('extra_authorize_params'), true);
    }
}
