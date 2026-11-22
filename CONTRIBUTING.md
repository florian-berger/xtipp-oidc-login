# Contributing

Thank you for helping to improve the OIDC login for xTIPP! This file explains how to report problems, how to suggest changes, and what a pull request should look like.

## Before you start

- **Security problems are never reported as public issues.** Use a [private security advisory](https://github.com/florian-berger/xtipp-oidc-login/security/advisories/new) instead, for example for a way to log in as another user, to bypass the token verification or to read data of other users.
- **Questions about installation or usage** are answered fastest in the plugin thread in the xTIPP forum or on [Discord](https://ultgmng.de/discord).
- **Bugs of xTIPP itself** (they also happen without this plugin) belong to the [xTIPP developers](https://xcript.de/).
- Search the [existing issues](https://github.com/florian-berger/xtipp-oidc-login/issues) first. A comment with more details on an existing issue helps more than a duplicate.

## Issues

Pick the matching template (bug report, feature request, xTIPP compatibility, documentation) and fill it out completely. Good bug reports contain:

- the versions of the plugin, xTIPP and PHP,
- the provider (Google, Twitch, Keycloak, …) and whether registration via OIDC is enabled,
- the steps to reproduce the problem, what you expected and what happened instead,
- the matching lines from the log of Laravel (`storage/logs/`).

> [!WARNING]
> Remove all secrets before you post logs or screenshots: client secrets, ID tokens, access tokens, the `code` and `state` parameters of a callback URL, the `APP_KEY` and personal data of your users.

For larger changes, please open an issue (or comment on one) **before** you start coding, so we can agree on the approach first. That saves you work on a pull request that cannot be merged as it is.

## Ground rules of the plugin

These rules come from the plugin system of xTIPP and from the design of this plugin. A pull request that breaks one of them cannot be merged.

- **No xTIPP file is changed and no view of xTIPP is overridden.** Only the official hook points of xTIPP are used, so the plugin survives updates of xTIPP.
- **No Composer packages and no build step.** Plugins cannot bring their own dependencies; use what xTIPP and Laravel already provide, and PHP extensions that xTIPP requires (plus `openssl`).
- **Respect the Content-Security-Policy.** Inline scripts get `nonce="{{ csp_nonce() }}"`, event attributes like `onclick` or `onchange` are not used, and no scripts or styles are loaded from external hosts.
- **The interface is in German**, like xTIPP. All texts go into `resources/lang/de/ui.php`, not directly into views or controllers. Code, comments, commit messages and documentation are in English.
- **Database changes** are made with a new migration in `database/migrations/`, never by editing a migration that was already released. Tables keep the prefix `plugin_oidc_`, and `down()` must undo `up()`.

## Development setup

1. Set up a local xTIPP installation (the version listed under `requires.xtipp` in `plugin.json`).
2. Clone your fork and link or copy it to `plugins/oidc/` of that installation.
3. Activate the plugin under **Konfiguration → Erweiterungen**, which runs the migration.
4. Create a test application at one or more providers with the redirect URL `https://<your-local-site>/oidc/<slug>/callback`. A local [Keycloak](https://www.keycloak.org/) in Docker is handy, because you can create as many test users as you need.

Never use the client secrets of a live site for development, and never commit them.

## Code style

Follow the style of the existing code:

- PHP 8.4, 4 spaces for indentation, opening braces on the same line, lines up to about 120 characters.
- Namespace `FlorianBerger\Oidc\`, one class per file, PSR-4 under `src/`.
- Type declarations for parameters and return values.
- A docblock for every class and every method that explains **why** it exists, not only what it does, with `@param` and `@return`.
- Controllers stay thin; logic that can be reused or tested goes into `src/Services/`.
- Validate every input of a request with the validator of Laravel, and use Eloquent or the query builder with bindings, never raw SQL with concatenated values.
- Blade escapes with `{{ }}`. Use `{!! !!}` only for content that cannot contain user input, and explain in a comment why it is safe.

## Security-relevant changes

This plugin logs users in and creates accounts, so changes in these areas need extra care:

- `src/Services/IdTokenVerifier.php`: the accepted algorithms, the checked claims and the handling of keys.
- `src/Http/Controllers/OidcLoginController.php`: `state`, `nonce`, the session and the decision between login, linking and registration.
- `src/Services/Registration.php`: the rule that an existing email address is never taken over.

For changes there, please describe in the pull request which attack you considered and how you tested it, for example with a manipulated `state`, an expired token, a token of another client (`aud`), a wrong `nonce` or an email address that already exists. Never log tokens, the `state` or the client secret, and never weaken a check to make a provider work. If a provider does not follow the specification, handle that explicitly and only for that case.

## Provider templates

New templates in `resources/provider-templates.json` are very welcome. Please:

- only add a configuration you have **tested yourself** with a real login and a real registration,
- keep the file valid against `resources/provider-templates.schema.json` (VS Code and PhpStorm check this automatically),
- choose a unique `key`, sort nothing by hand (the dropdown sorts by name) and keep the formatting with 4 spaces,
- use the official brand colour and a Font Awesome icon that is available in xTIPP,
- set `console_url` to the page where the client ID and secret are created (it must start with `https://`),
- never put a client ID or a client secret into a template.

Mention in the pull request which scopes and claims the provider returns, and whether it reports `email_verified`.

## Pull requests

1. Fork the repository and create a branch from `main`, named after the issue, for example `feature/12-keycloak-template` or `fix/15-callback-state`.
2. Keep a pull request focused on one topic. Unrelated refactorings or formatting changes belong in a separate pull request.
3. Write commit messages in the imperative mood ("Add Keycloak template", not "Added …"), with a short subject and, if needed, a body that explains the reason for the change.
4. Update the `README.md` if you change behaviour, settings, routes or requirements.
5. Do not change the version in `plugin.json`; it is set by the release workflow.
6. Fill out the description of the pull request: what changed, why, which issue it closes (`Closes #12`) and how you tested it.

Pull requests are merged with **squash and merge**, so the history of `main` keeps one commit per change.

### Checklist

Before you open the pull request, check that:

- [ ] the whole flow was tested manually: login, linking and unlinking under **Verknüpfte Konten**, and registration with the setting on and off,
- [ ] a login with an email address that already exists is still refused,
- [ ] the admin pages work and show no CSP errors in the browser console,
- [ ] there are no PHP errors or warnings in the log of Laravel,
- [ ] all new texts are in `resources/lang/de/ui.php`,
- [ ] no secrets, tokens or personal data are part of the commits,
- [ ] the README is up to date.

There are no automated tests yet. A pull request that adds some (for example for `IdTokenVerifier` or `ProviderTemplates`) without new dependencies is very welcome.

## Compatibility with new xTIPP versions

The plugin supports exactly the xTIPP versions between `requires.xtipp` and `requires.xtipp_max` in `plugin.json`. When a new version of xTIPP is released, compare the hook points, the login flow, the CSP and the settings with the previous version, and use the template **xTIPP compatibility** to report what has to change.

## Releases

Releases are made by the maintainer: a tag `vX.Y.Z` on `main` starts the workflow, which writes the version to `plugin.json`, builds the ZIP and creates a draft release. Versions follow [Semantic Versioning](https://semver.org/).

## AI-assisted contributions

AI tools may be used, as they were for this plugin. You are still responsible for every line you submit: understand it, test it, and say in the pull request that AI assisted. Especially for the security-relevant parts, generated code must be reviewed as carefully as code written by hand.

## License

By contributing, you agree that your contribution is licensed under the [MIT License](LICENSE) of this project.
