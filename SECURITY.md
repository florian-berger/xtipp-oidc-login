# Security policy

This plugin logs users in to xTIPP and creates accounts, so a security problem in it can give an attacker access to other accounts. Thank you for taking the time to report one responsibly.

## Supported versions

Only the latest release gets security fixes. A fix is released as a new version, older versions are not patched.

| Plugin version | xTIPP | Supported |
|---|---|---|
| latest 0.1.x | 1.0.36 | yes |
| 0.0.1 | 1.0.35 | no |
| unreleased (`main`, feature branches) | | no, but reports are welcome |

If you run an older version, update to the latest [release](https://github.com/florian-berger/xtipp-oidc-login/releases) before you report, and check whether the problem still happens.

## Reporting a vulnerability

**Do not open a public issue, pull request or discussion, and do not post in the xTIPP forum or on Discord.**

Report it privately through a [GitHub security advisory](https://github.com/florian-berger/xtipp-oidc-login/security/advisories/new) ("Report a vulnerability" on the **Security** tab of the repository).

Please include:

- the version of the plugin, xTIPP and PHP,
- the provider (Google, Twitch, Keycloak, ...) and whether registration via OIDC is enabled,
- what an attacker can do, and what they need for it (an account, a provider account, admin rights, network position, ...),
- the steps to reproduce it, ideally with a proof of concept against a test installation,
- if you have one, an idea how to fix it.

Remove real secrets and personal data from your report: client secrets, tokens, the `code` and `state` of a callback URL, the `APP_KEY` and data of real users. Test only against installations you own or are allowed to test.

## What happens next

This is a hobby project maintained by one person, so the times below are goals, not guarantees:

1. You get an answer that the report arrived within **14 days**.
2. The report is confirmed or rejected, with a reason, as soon as it is reproduced.
3. A confirmed problem is fixed in a private fork of the advisory and released as a new version. You are asked to check the fix before it is released, if you like.
4. The advisory is published after the release, with a CVE if it is warranted, and you are credited in it unless you do not want that.

Please do not disclose the problem publicly before the fixed version is released, or before **90 days** have passed since your report, whichever comes first. If a fix needs longer, we will agree on a date together.

## Scope

In scope is everything in this repository, especially:

- the verification of the ID token (`src/Services/IdTokenVerifier.php`): algorithms, keys, `iss`, `aud`, `nonce`, `exp`, `nbf`,
- the flow itself: `state`, `nonce`, session handling, CSRF, the redirect and callback routes,
- account takeover: logging in as another user, linking a provider to someone else's account, taking over an existing email address,
- the registration: creating active accounts, bypassing `oidc_allow_registration` or the registration setting of xTIPP,
- the admin pages (`/admin/oidc-anbieter`): access without the admin role, XSS, leaking the client secret,
- the data the plugin stores or logs: tokens, secrets or the `state` that end up in the database or the log, also with the debug mode on.

Not in scope:

- problems of **xTIPP itself** that also happen without this plugin. Report them to the [xTIPP developers](https://xcript.de/).
- problems of an **OIDC provider** (Google, Twitch, ...). Report them to the provider.
- anything that needs administrator rights in xTIPP, write access to the server, the files of the plugin, the `.env` or the database, because these already allow full control of xTIPP. This includes an admin who enters a malicious issuer on purpose.
- personal data in the log while the [debug mode](README.md#debug-mode) is on. This is documented and expected; secrets and tokens in the log are in scope, though.
- missing hardening without a concrete attack (for example headers that xTIPP sets), denial of service by flooding the server with requests, and reports from automated scanners without a proof that the problem can be exploited.

If you are unsure whether something is in scope, report it anyway.

## Security design

How the plugin protects logins, tokens and stored data is described in the [Security](README.md#security) section of the README. Note that the verification of the ID token is a hand-written implementation on top of OpenSSL and has not been audited by a third party; reviews of it are very welcome.
