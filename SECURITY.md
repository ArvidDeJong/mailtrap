# Security policy

This package sits in the mail path of your application and exposes a public webhook, so security reports are taken seriously.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## What counts as a vulnerability

For example:

- a webhook call that is accepted without a valid `Mailtrap-Signature`;
- a way to change mail logs or address verdicts from outside the application;
- the inbox UI showing data or running an action for a visitor the `viewMailtrap` gate or the middleware refuses;
- secrets such as `MAILTRAP_API_TOKEN` or `MAILTRAP_WEBHOOK_SECRET` ending up in logs or output.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/mailtrap/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, the Laravel version and the steps or request that reproduce it.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

Problems in Mailtrap itself belong with Mailtrap; this is an independent package.
