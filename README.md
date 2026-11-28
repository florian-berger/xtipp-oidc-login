# OIDC login for xTIPP

![GitHub License](https://img.shields.io/github/license/florian-berger/xtipp-oidc-login?style=for-the-badge)
[![Issues](https://img.shields.io/github/issues/florian-berger/xtipp-oidc-login?style=for-the-badge)](https://github.com/florian-berger/xtipp-oidc-login/issues)
[![Discord](https://img.shields.io/discord/302523634075828226?label=Discord&logo=discord&style=for-the-badge)](https://ultgmng.de/discord)

A plugin for [xTIPP](https://xcript.de/), the tippgame software, that adds sign-in and registration through any OpenID Connect provider (for example Google or Twitch). It is built on the plugin system of xTIPP and its hook points (since xTIPP 1.0.36), so **no file of xTIPP is changed**, and the plugin survives updates of xTIPP.

The interface of the plugin is in German, like xTIPP itself.

> [!IMPORTANT]
> - **This is security-relevant code!** It handles logins and creates accounts. The verification of the ID token is a hand-written implementation on top of OpenSSL and has not been audited by a third party. It was checked against real RSA and EC keys, but not with a full test suite, and automated tests are not part of this plugin.
> - **Test before you rely on it.** Try the whole flow with your own providers, with registration on and off, and with an email address that already exists, before you use it on a live site.
> - **No warranty.** The plugin is provided as it is, without any guarantee of fitness for a purpose, and you use it at your own risk. Review the code before you install it, as with any plugin: it runs with full access to xTIPP and its database.

## Features

- Any number of OIDC providers, managed by administrators in an own admin page: issuer, client ID, client secret, scopes, optional extra authorization parameters, button label, colour and icon (with a live preview).
- Login and registration with the authorization code flow, with `state` and `nonce`.
- New accounts are created inactive, exactly like the normal registration of xTIPP. The welcome mail and the notification of the admins are sent as usual, admins must approve new users.
- The login buttons appear on the normal login page of xTIPP, below the username/password form.
- Menu entries in the admin center (**Konfiguration → OIDC-Anbieter**) and in the profile menu (**Verknüpfte Konten**).
- Users can connect and disconnect providers on their own page.
- An email address that already belongs to an xTIPP account is never taken over (see [Security](#security)).

## Requirements

- xTIPP 1.0.36 (the hook points for menus and pages are required, older versions do not have them; use plugin version 0.0.1 for xTIPP 1.0.35)
- PHP 8.4 and the extensions xTIPP needs; additionally `openssl`, which is used to verify the signature of the ID tokens

## Installation

1. Install the plugin, either way:
   - **ZIP upload:** upload the ZIP of a [release](https://github.com/florian-berger/xtipp-oidc-login/releases) in the admin area under **Konfiguration → Erweiterungen**. The upload must be enabled on the server with `XTIPP_PLUGIN_UPLOAD=1` in the `.env` of xTIPP.
   - **FTP:** upload the folder to `plugins/oidc/` of your xTIPP installation. Check that **all files** arrived.
2. Activate it in the admin area under **Konfiguration → Erweiterungen**. This runs the migration, which creates the database tables.
3. Open **Konfiguration → OIDC-Anbieter** (`/admin/oidc-anbieter`) and create a provider (pick a **template** ("Vorlage") if there is one for your provider). Use **Kopieren … → Redirect-URL** in the list to copy its redirect URL (`https://your-site/oidc/<slug>/callback`) and register it at the provider.
4. Optional: allow new accounts via OIDC (see [Settings](#settings)).

The buttons of all active providers then appear on the login page of xTIPP.

To update the plugin, upload the ZIP of the new version the same way (the old version is kept in `storage/app/plugin-backups/`), or deactivate it, replace the files via FTP and activate it again.

## Pages

| Address | Who | Purpose |
|---|---|---|
| `/oidc/anmelden` | logged-out users | Login buttons of all active providers (the same buttons are also shown on `/login`) |
| `/oidc/{slug}/redirect` | everyone | Starts the flow (`?intent=link` to connect, when logged in); also the launch URL for the dashboard of a provider, see [Launch URL](#launch-url-authentik) |
| `/oidc/{slug}/callback` | everyone | Redirect URL, to be registered at the provider |
| `/oidc/profil-vervollstaendigen` | everyone | Last step of a new registration |
| `/oidc/verknuepfte-konten` | logged-in users | Link and unlink providers, shows since when each provider is linked ("Verknüpfte Konten") |
| `/admin/oidc-anbieter` | **administrators only** | Manage the providers |

The admin routes use the same middleware as the admin area of xTIPP (`auth`, `hasRole:Admin`, `last_activity`). The
controller checks the role again and answers with 403 otherwise.

## Settings

Shown by xTIPP under **Konfiguration → Erweiterungen → OIDC-Anmeldung**:

| Key | Default | Meaning |
|---|---|---|
| `oidc_allow_registration` | off | Allow new accounts via OIDC. The general registration setting of xTIPP (`registration-enable`) must be on, too. Users who already connected a provider can always log in. |
| `oidc_debug` | off | Debug mode: writes every step of the flow to the log of Laravel, see [Debug mode](#debug-mode). |

The providers themselves are not plugin settings, because a setting is limited to 400 characters. They are managed on `/admin/oidc-anbieter`.

## Launch URL (Authentik)

Some providers show the applications of a user on a dashboard, for example **My applications** in Authentik. A click on the tile opens the **launch URL** of the application. OpenID Connect has no login that is started by the provider, so the tile has to open the start of the normal flow of the plugin:

```
https://your-site/oidc/<slug>/redirect
```

The list on `/admin/oidc-anbieter` copies it with **Kopieren … → Login-URL**. In Authentik, enter it under **Applications → your application → Launch URL**. When the user clicks the tile, the plugin sends them to Authentik, which already knows them and sends them straight back, and they are logged in without any further click. Users who are already logged in to xTIPP land on the start page, no new login is started.

This only works without a click if the authorization flow of the provider in Authentik does not ask for consent (for example `default-provider-authorization-implicit-consent`); with explicit consent, Authentik asks once. Users without a linked account are handled like on the login page: registration if it is allowed, otherwise an error.

## How a login works

1. The user clicks a button, the plugin loads the discovery document of the provider (cached for 24 hours; can be cleared via **Konfiguration → System → Cache leeren**) and sends the user to the authorization endpoint with a random `state` and `nonce` kept in the session.
2. The provider redirects to the callback. The plugin checks the session, the provider and the `state`, then exchanges the code for an ID token (`client_secret_post`).
3. The ID token is verified (see [Security](#security)). If it has no `email`, `name`, `given_name` or `family_name` and the provider has a `userinfo_endpoint`, the plugin asks that endpoint with the access token and fills in the missing claims, including `nickname` and `preferred_username` (Discord, for example, sends the email only there).
4. Then one of the following happens:
   - The provider account is already connected: the user is logged in. Inactive accounts are refused.
   - The email address belongs to an existing account: the login is refused with a hint to log in and link the provider under **Verknüpfte Konten** in the profile menu (`/oidc/verknuepfte-konten`).
   - Otherwise, if registration is allowed: the user completes nickname and name (as far as xTIPP asks for them, prefilled from the claims and changeable). The default nickname is the `nickname` claim, then `preferred_username`, then `name`, then the part of the email address before the `@`. An inactive account is created together with the connection. A notification to the admins is sent that there is a new registration.

## Security

> [!NOTE]
> Found a security problem? Please do not open a public issue. How to report it privately, which versions are supported and what is in scope is described in the [security policy](SECURITY.md).

- **ID token:** `firebase/php-jwt` and `league/oauth2-client` are not available for plugins, so the token is verified in `src/Services/IdTokenVerifier.php` with OpenSSL. Only RS256/384/512 and ES256/384/512 are accepted, never `none` or HS*. The keys always come from the JWKS of the provider (reloaded once on an unknown key). Checked claims are `iss`, `aud`, `nonce`, `sub`, `exp` and `nbf`, with 60 seconds of tolerance.
- **No account takeover:** an email address that exists in xTIPP is refused, whether or not the provider reports it as verified. Deleted accounts count too, and the comparison ignores the case. A provider can only be added to an account by its owner while logged in.
- **Client secret:** stored with the `encrypted` cast of Laravel (AES-256-CBC with the `APP_KEY`). A database dump alone does not reveal it; a dump together with the `.env` does. If a secret leaks, create a new one at the provider.
- **Userinfo endpoint:** its answer is only used if its `sub` equals the one of the verified ID token, and it only fills claims the ID token does not have; values of the signed ID token always win. If the endpoint fails, the login goes on with the claims of the ID token. The access token is only kept in memory for this request.
- **Stored data:** per linked provider, `plugin_oidc_identities` keeps only the user, the provider, the subject (`sub`), the email address of the last login and the time of the last login. The other claims of the ID token (name, avatar, locale, ...) are used once to prefill the registration form and are not stored. No ID token, access token or refresh token is stored.
- **Logging:** a rejected callback logs the provider, the reason and flags, but never the `state` or any token. The [debug mode](#debug-mode) logs more, including personal data, but never secrets or tokens either.
- The session id is regenerated on login, and all state-changing routes are protected by CSRF.
- Routes of the flow are rate limited (20 requests per minute).

## Debug mode

For setting up a provider or finding out why a login fails, turn on **Debug-Modus** (`oidc_debug`) under **Konfiguration → Erweiterungen → OIDC-Anmeldung**. While it is on, the list on `/admin/oidc-anbieter` shows a warning, and every step of a login writes an entry with the level `info` to the log of Laravel (`storage/logs/`). All entries start with `OIDC debug:` and contain the slug of the provider:

| Step | Logged |
|---|---|
| Start | `intent` (`login` or `link`), the authorization endpoint, the `redirect_uri`, the scopes and the extra authorize parameters |
| Discovery / JWKS | URL, whether the result came from the cache, `issuer`, the endpoints, `scopes_supported`, `claims_supported`, `id_token_signing_alg_values_supported` and the keys of the JWKS (`kid`, `kty`, `alg`, `use`); on an error, the HTTP status and the beginning of the answer |
| Callback | whether the flow of the session exists and matches (provider, `state`), whether a `code` was sent, and `error` / `error_description` of the provider |
| Token endpoint | HTTP status; on success the names of the fields and the granted `scope`, on an error `error` / `error_description` |
| ID token | `alg`, `kid` and `typ` of the header; if a check fails, the expected and the received value (`iss`, `aud`, `sub`), or the server time next to `iat`, `exp` and `nbf` |
| Claims | all claims of the verified ID token, except the `nonce` |
| Userinfo | the missing claims and the whole answer of the userinfo endpoint; on an error the HTTP status and `error` / `error_description`; or why the endpoint was not asked |
| Decision | logged in, linked, registration started or completed, or the reason of a refusal (email address in use, registration disabled, account not activated, provider account already linked, ...) |

The client secret, the `code`, the `state`, the `nonce` and the tokens (ID, access and refresh token) are never logged.

The claims contain **personal data** of the users (name, email address, avatar URL, ...). Turn the debug mode on only while you need it, and off afterwards. Entries already written stay in the log until the log rotation removes them, or until you delete them. The entries need `LOG_LEVEL` `info` or `debug` in the `.env` of xTIPP.

## Limitations

The README of xTIPP's plugin system forbids changing xTIPP files and overriding its views, and this plugin follows that on purpose. It only uses the official hook points of xTIPP 1.0.36, which leaves these limitations:

- xTIPP has no hook point inside the login form. The buttons are added through `layout.content.bottom` on the login route, so they appear **below** the form.
- The linked accounts are a page of their own (`/oidc/verknuepfte-konten`, linked in the profile menu as **Verknüpfte Konten**), not a section of the profile settings page.
- xTIPP sends a Content-Security-Policy. The scripts of the admin pages are inline scripts with `csp_nonce()`, and event attributes like `onclick` are not used.
- No Composer packages, tests or compiled CSS (the hover effect of the buttons uses a small inline style).

## Provider templates

The dropdown **Vorlage** on the create page (not on the edit page) is filled from `resources/provider-templates.json` and lists the templates sorted by name. Picking a template fills the fields directly, without a reload, moves the cursor to the Client-ID and shows where to get Client-ID and Client-Secret. Every value can still be changed afterwards, and Client-ID and Client-Secret are never part of a template.

```json
{
    "$schema": "./provider-templates.schema.json",
    "templates": [
        {
            "key": "twitch",
            "name": "Twitch",
            "slug": "twitch",
            "issuer": "https://id.twitch.tv/oauth2",
            "scopes": "openid user:read:email",
            "extra_authorize_params": "{\"claims\":\"{\\\"id_token\\\":{\\\"email\\\":null,\\\"email_verified\\\":null}}\"}",
            "button_color": "#9146FF",
            "icon": "fa-brands fa-twitch",
            "console_url": "https://dev.twitch.tv/console/apps"
        }
    ]
}
```

- `key` (letters, digits, `-` and `_`, unique) and `name` are required, all other fields are optional. A missing field is emptied when the template is applied; `scopes` falls back to `openid email profile`, and a missing or invalid `button_color` (not `#RRGGBB`) to the neutral gray `#6B7280`.
- The JSON Schema `resources/provider-templates.schema.json` describes all fields. The file links it with `$schema`, so editors like VS Code or PhpStorm check the file while editing it.
- `extra_authorize_params` can be a JSON string as above or a JSON object.
- `console_url` must start with `https://`. It is shown as a link to the developer console of the provider.
- An invalid entry is skipped. If the file is missing or is not a JSON object with an array `templates`, the dropdown is hidden. Both are logged in the log of Laravel, and the create page keeps working.

Changes in the file are overwritten by an update of the plugin, so send new templates as a pull request. Make sure that the configuration you suggest as a template is tested!

## Removing the plugin

Deactivating the plugin does **not** run `down()` of the migration, and tables and settings stay in the database.
To remove everything:

1. Deactivate the plugin.
2. Optional: `php artisan migrate:reset --path=plugins/oidc/database/migrations --realpath --force` (rolls back all migrations of the plugin, which drops both tables and all connections; run it before deleting the folder).
3. Delete `plugins/oidc/`.
4. If you kept the tables and want a clean state, remove the migration rows of the plugin from the `migrations` table and the setting `plugin.oidc.oidc_allow_registration` from `xtipp_config_general`.

Users who registered only through a provider have no password; they can set their own with "Passwort vergessen?" or in their profile area.

## Structure

```
plugins/oidc/
├── plugin.json
├── database/migrations/         tables of the plugin
├── routes/web.php
├── resources/
│   ├── lang/de/ui.php
│   └── views/                   login, connections, complete-profile, admin/, partials/ (buttons, login slot)
└── src/                         namespace FlorianBerger\Oidc\
    ├── OidcServiceProvider.php
    ├── Http/Controllers/
    ├── Models/
    └── Services/                OidcClient, OidcDiscoveryService, IdTokenVerifier, Registration, ProviderTemplates, DebugLog
```
