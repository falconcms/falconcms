# Security Policy

## Supported versions

Security fixes are released for the latest minor version of FalconCMS. Please update to it
before reporting, in case the problem is already fixed.

| Version | Supported |
| ------- | --------- |
| 2.8.x   | ✅        |
| < 2.8   | ❌ — update with `composer require falconcms/falconcms:^2.8` |

FalconCMS Pro and its bundled plugins follow the same policy for their latest release.

## Reporting a vulnerability

**Please do not open a public issue for a security problem.** Report it privately instead:

1. **GitHub** — open a private report from this repository's
   [Security tab → Report a vulnerability](https://github.com/falconcms/falconcms/security/advisories/new).
2. **Or** write to us through the [contact page](https://falconcms.com/contact) and ask for a
   secure channel; do not include exploit details in the first message.

Please include:

- the FalconCMS version (and Pro version, if it is involved), PHP and Laravel versions;
- what an attacker can do, and what access they need (visitor, customer, editor, admin…);
- steps to reproduce, or a proof of concept;
- any idea you have for a fix.

## What happens next

- We confirm that we received the report and keep you informed while we work on it.
- Once a fix is released, we publish an advisory and credit you, unless you prefer not to be
  named.
- Please give us reasonable time to release a fix before telling anyone else about it.

Testing is welcome on your own installation. Please do not test against sites you do not own,
including the public demo, and do not access or change other people's data.
