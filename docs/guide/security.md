# Falcon Security <Badge type="warning" text="Pro" />

The **Falcon Security** plugin adds two-factor sign-in, sign-in alerts, a password policy, a
firewall, a malware scanner and a security audit. It ships
with [FalconCMS Pro](/guide/pro). Activate it under **Plugins**, then open **Security** in the
admin menu.

## Two-factor sign-in

After the password, the site asks for a 6-digit code from an authenticator app on your phone, so
a stolen password is not enough to get in. Any app works: Google Authenticator, Microsoft
Authenticator, Authy, 1Password, Bitwarden.

**Setting it up** — open **Security → My security** (every user can, whatever their role), scan
the QR code with the app, type the code it shows and press **Turn on**. Save the 8 recovery codes
it then shows: if you lose your phone, each one signs you in once.

**Signing in** — after the password, the sign-in page asks for the code from the app, below
your email (a pasted 6-digit code is sent at once). **Not you?** goes back to the password. Tick
**Don't ask again on this device** to skip the code on that browser for 30 days (change the number
in **Security → Sign-in**; 0 always asks).

**Wrong codes lock the account** — whoever reaches the code step already knew the password. After
5 wrong codes (adjustable) the account is locked for 30 minutes: the sign-in page refuses it even
with the right password, and the account's owner gets an email telling them someone knows their
password. Starting over does not reset the count. An administrator can lift the lock early under
**Security → Firewall → Unblock**.

**Requiring it** — in **Security → Sign-in**, tick the roles that must use it. Every role is
listed, including ones plugins add (Wholesale Customer, say), and a user's extra roles count too.
The users list there shows everyone, with a role filter and a search. Someone in such
a role who has not set it up is sent to the setup screen the next time they open the admin. Set it
up for yourself first.

**Lost phone** — sign in with a recovery code, or ask an administrator to **Reset** your
two-factor in **Security → Sign-in**; you then sign in with your password and set it up again.

## Sign-in alerts and sessions

**New sign-in** — when an account signs in from a network it has never used, its owner gets a
"was this you?" email with the address, country and browser. Choose the roles it covers, and an
address that gets a copy, in **Security → Sign-in**. The first sign-in after the plugin is switched
on is only remembered.

**New administrator** — the other administrators get an email when an account is made an
administrator.

**Log out everywhere** — **Security → My security** lists the browsers you are signed in on (when
sessions are kept in the database) and signs you out of every other one. An administrator can do
it for any user from **Security → Sign-in** — use it when an account may be in the wrong hands.
Remember-me cookies and trusted browsers stop working too.

## Password policy

Every form that sets a password — registration, password resets, users added or edited, the
shop's account page — must meet the policy set in **Security → Sign-in**: a minimum length
(10 by default), letters and numbers, optionally a symbol, and not a password known from a data
breach. The breach check uses [Have I Been Pwned](https://haveibeenpwned.com/Passwords) without
sending the password: only the first 5 characters of its hash leave the site. If the service
cannot be reached, the password is allowed.

## Firewall

The firewall checks every request before the site handles it, and refuses:

- addresses and ranges you block (`203.0.113.5`, `203.0.113.0/24`, `2001:db8::/32`);
- visitors from countries you block (known behind Cloudflare, or for visitors the site has looked
  up before);
- **probes** — requests for `/.env`, `/.git`, `/wp-login.php`, `/phpmyadmin`, backup archives and
  the like, which only attackers ask for on a FalconCMS site. Three probes in ten minutes ban the
  address;
- **floods** — more than 40 "not found" pages, or 10 sign-in attempts (the dashboard login, the
  shop's My Account sign-in and magic-link requests), a minute from one address earn a ban;
- **attacks** — SQL injection, cross-site scripting, path traversal, file inclusion, command
  injection and Log4Shell payloads in the address or a form (the admin area is not inspected).
  Repeat offenders are banned like probes; switch it off in **Security → Firewall**;
- optionally, requests naming another site in their Host header.

Bans last 24 hours by default. Everything is adjustable in **Security → Firewall**, which also
lists the bans, what the firewall stopped, and the sign-in form's own lockouts (with **Unblock**).

**Safety valves**

- **Log only** records what would be blocked and blocks nothing — use it to try rules out.
- An administrator's address is never blocked once they have signed in from it.
- Locked out anyway? Add `FALCON_FIREWALL=off` to the site's `.env`.
- Behind Cloudflare, the site must trust Cloudflare's addresses (TrustProxies). Until it does, every
  visitor seems to come from Cloudflare, so the firewall only records, and the audit tells you how
  to fix it.

## Malware scanner

**Security → Scanner → Run the first scan** checks the site's files and database for:

- PHP files in the uploads folder (or PHP hidden inside an "image"), and unexpected PHP in `public/`;
- code that runs hidden (decoded) instructions, passes visitor input to the shell, or matches a
  known web shell;
- server files rewritten to redirect visitors or run a file before every request;
- scripts, miners, invisible iframes and hidden links injected into pages or the Customizer;
- code **changed or added since the baseline** — the first scan records the site as it is as the
  clean state, so run it while you trust the site. After an update, press **Accept the current
  files as the baseline**.

It looks through the site's own code — `app/`, `bootstrap/`, `config/`, `routes/`, `database/`,
`lang/`, the views, themes and plugins, the CMS itself — the files at the top of the project, the
uploads and `public/`. Third-party packages in the rest of `vendor/` are left to Composer.

Tick findings (or **Select all** in a tab) for a **bulk action**: remove (each finding gets its
recommended, low-risk removal; risky ones are skipped for you to decide one by one), quarantine,
mark safe, restore or undo. All findings about one file move together.

For each finding:

- **Remove the code** — when malicious code was injected into a file or post the site needs, only
  that code is cut out; the rest stays. You see exactly what will go before you confirm. If it
  cannot be cut out without breaking the file, the option is not offered.
- **Delete file** — for a file that should not exist at all (PHP in the uploads, a web shell, a
  file that is nothing but malicious code). Deleting a file the site had when it was clean is
  marked **high risk**: you must type its name to confirm. Files the site cannot start without
  (`public/index.php`, `bootstrap/app.php`) are never deleted.
- **Quarantine** moves a file out of reach (restore it from the Quarantined tab), **It's safe**
  hides it until the file changes.

Before anything is removed, a dialog explains what goes and that data may be lost, and asks you to
confirm. A backup is kept for 30 days: **Removed → Undo** puts it back. The scanner never removes
anything on its own. It also runs every night (with the site's scheduler) and
emails new findings; both can be turned off on the screen. From the command line:
`php artisan falcon:security-scan` (`--baseline` to accept the current files).

## Security audit

**Security → Audit → Run the audit** checks the site for the setup mistakes attackers try first,
and says how to fix each one: debug mode left on, the `.env` file, `.git` folder or logs reachable
from the web, no HTTPS, an insecure session cookie, a wrong APP_URL, Cloudflare not trusted, an
outdated FalconCMS or PHP, administrators without two-factor, a default `admin@admin.com` account,
the default sign-in address, the firewall switched off, no bot check on the sign-in form, storage
folders the site cannot write to, no password policy, and malware findings or a scan long overdue.

**Security → Overview** shows the firewall, two-factor use among administrators, the malware
scanner, the audit score and the latest firewall events at a glance.
