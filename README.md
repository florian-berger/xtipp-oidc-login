# OIDC login for xTIPP

![GitHub License](https://img.shields.io/github/license/florian-berger/xtipp-oidc-login?style=for-the-badge)
[![Issues](https://img.shields.io/github/issues/florian-berger/xtipp-oidc-login?style=for-the-badge)](https://github.com/florian-berger/xtipp-oidc-login/issues)
[![Discord](https://img.shields.io/discord/302523634075828226?label=Discord&logo=discord&style=for-the-badge)](https://ultgmng.de/discord)

A plugin for [xTIPP](https://xcript.de/), the tippgame software, that adds sign-in and registration through any OpenID Connect provider (for example Google or Twitch). It is built on the plugin system of xTIPP and its hook points (since xTIPP 1.0.36), so **no file of xTIPP is changed**, and the plugin survives updates of xTIPP.

The interface of the plugin is in German, like xTIPP itself.

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
3. Open **Konfiguration → OIDC-Anbieter** (`/admin/oidc-anbieter`) and create a provider (pick a **template** ("Vorlage") if there is one for your provider). Use the button in the list to copy its redirect URL (`https://your-site/oidc/<slug>/callback`) and register it at the provider.
4. Optional: allow new accounts via OIDC (see [Settings](#settings)).

The buttons of all active providers then appear on the login page of xTIPP.

To update the plugin, upload the ZIP of the new version the same way (the old version is kept in `storage/app/plugin-backups/`), or deactivate it, replace the files via FTP and activate it again.

## Pages

| Address | Who | Purpose |
|---|---|---|
| `/oidc/anmelden` | logged-out users | Login buttons of all active providers (the same buttons are also shown on `/login`) |
| `/oidc/{slug}/redirect` | everyone | Starts the flow (`?intent=link` to connect, when logged in) |
| `/oidc/{slug}/callback` | everyone | Redirect URL, to be registered at the provider |
| `/oidc/profil-vervollstaendigen` | everyone | Last step of a new registration |
| `/oidc/verknuepfte-konten` | logged-in users | Link and unlink providers ("Verknüpfte Konten") |
| `/admin/oidc-anbieter` | **administrators only** | Manage the providers |

The admin routes use the same middleware as the admin area of xTIPP (`auth`, `hasRole:Admin`, `last_activity`). The
controller checks the role again and answers with 403 otherwise.

## Settings

Shown by xTIPP under **Konfiguration → Erweiterungen → OIDC-Anmeldung**:

| Key | Default | Meaning |
|---|---|---|
| `oidc_allow_registration` | off | Allow new accounts via OIDC. The general registration setting of xTIPP (`registration-enable`) must be on, too. Users who already connected a provider can always log in. |

The providers themselves are not plugin settings, because a setting is limited to 400 characters. They are managed on `/admin/oidc-anbieter`.

## How a login works

1. The user clicks a button, the plugin loads the discovery document of the provider (cached for 24 hours; can be cleared via **Konfiguration → System → Cache leeren**) and sends the user to the authorization endpoint with a random `state` and `nonce` kept in the session.
2. The provider redirects to the callback. The plugin checks the session, the provider and the `state`, then exchanges the code for an ID token (`client_secret_post`).
3. The ID token is verified (see [Security](#security)).
4. Then one of the following happens:
   - The provider account is already connected: the user is logged in. Inactive accounts are refused.
   - The email address belongs to an existing account: the login is refused with a hint to log in and link the provider under **Verknüpfte Konten** in the profile menu (`/oidc/verknuepfte-konten`).
   - Otherwise, if registration is allowed: the user completes nickname and name (as far as xTIPP asks for them) and an inactive account is created together with the connection. A notification to the admins is sent that there is a new registration.

## Security

- **ID token:** `firebase/php-jwt` and `league/oauth2-client` are not available for plugins, so the token is verified in `src/Services/IdTokenVerifier.php` with OpenSSL. Only RS256/384/512 and ES256/384/512 are accepted, never `none` or HS*. The keys always come from the JWKS of the provider (reloaded once on an unknown key). Checked claims are `iss`, `aud`, `nonce`, `sub`, `exp` and `nbf`, with 60 seconds of tolerance.
- **No account takeover:** an email address that exists in xTIPP is refused, whether or not the provider reports it as verified. Deleted accounts count too, and the comparison ignores the case. A provider can only be added to an account by its owner while logged in.
- **Client secret:** stored with the `encrypted` cast of Laravel (AES-256-CBC with the `APP_KEY`). A database dump alone does not reveal it; a dump together with the `.env` does. If a secret leaks, create a new one at the provider.
- **Stored data:** the claims of each login are stored in `plugin_oidc_identities.raw_claims` as plain JSON (name, email, subject). No ID token and no access token is stored.
- **Logging:** a rejected callback logs the provider, the reason and flags, but never the `state` or any token.
- The session id is regenerated on login, and all state-changing routes are protected by CSRF.
- Routes of the flow are rate limited (20 requests per minute).

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
2. Optional: `php artisan migrate:rollback --path=plugins/oidc/database/migrations --realpath --force` (drops both tables and all connections; run it before deleting the folder).
3. Delete `plugins/oidc/`.
4. If you kept the tables and want a clean state, remove the migration row from the `migrations` table and the setting `plugin.oidc.oidc_allow_registration` from `xtipp_config_general`.

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
    └── Services/                OidcClient, OidcDiscoveryService, IdTokenVerifier, Registration, ProviderTemplates
```

## AI disclaimer

While writing this, I am not forced to write this AI disclaimer based on the rules of the European Union. But to be fully transparent, I write the following paragraph.

Developing this plugin was assisted by AI, specific **Claude Code** by **Anthropic**. The used model was **Claude Sonnet 5.5**. AI assisted means that a human designed the approach and AI helped building it.

> [!IMPORTANT]
> - **This is security-relevant code!** It handles logins and creates accounts. The verification of the ID token is a hand-written implementation on top of OpenSSL and has not been audited by a third party. It was checked against real RSA and EC keys, but not with a full test suite, and automated tests are not part of this plugin.
> - **Test before you rely on it.** Try the whole flow with your own providers, with registration on and off, and with an email address that already exists, before you use it on a live site.
> - **No warranty.** The plugin is provided as it is, without any guarantee of fitness for a purpose, and you use it at your own risk. Review the code before you install it, as with any plugin: it runs with full access to xTIPP and its database.
