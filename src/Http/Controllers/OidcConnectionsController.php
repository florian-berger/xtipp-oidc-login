<?php
namespace FlorianBerger\Oidc\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use FlorianBerger\Oidc\Models\OidcIdentity;
use FlorianBerger\Oidc\Models\OidcProvider;

/**
 * The connected providers of the logged-in user
 */
class OidcConnectionsController extends Controller {
    /**
     * Show the connected and the connectable providers
     *
     * @return View
     */
    public function index(): View {
        $linkedIdentities = OidcIdentity::where('user_id', Auth::id())->with('provider')->get()
            ->filter(fn (OidcIdentity $identity) => $identity->provider !== null);

        $connectableProviders = OidcProvider::active()
            ->whereNotIn('id', $linkedIdentities->pluck('oidc_provider_id'))
            ->orderBy('name')
            ->get();

        return view('oidc::connections', [
            'linkedIdentities'     => $linkedIdentities,
            'connectableProviders' => $connectableProviders,
        ]);
    }

    /**
     * Disconnect an identity from the account of the user
     *
     * A user can always set a password with "forgot password", so the last connection is not blocked.
     *
     * @param int $identity
     *
     * @return RedirectResponse
     */
    public function disconnect(int $identity): RedirectResponse {
        OidcIdentity::where('user_id', Auth::id())->findOrFail($identity)->delete();

        return redirect()->route('plugin-oidc.connections')
            ->with('message-success', trans('oidc::ui.message.disconnected'));
    }
}
