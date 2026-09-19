# KitMage Team Explainer

A small WordPress plugin for WooCommerce Memberships for Teams. It adds a **Team seat instructions** field to the parent product and displays the configured message beside the Teams fields for both simple and variable team products.

## Placeholders

- `%max_seats%` — the selected variation's or simple product's maximum member count.
- `%product_name%` — the parent product name.
- `%variation_name%` — the selected variation name (blank for simple products).

Each placeholder accepts a custom fallback after a pipe. For example, `%max_seats|unlimited%` displays “unlimited” when there is no finite seat maximum, and `%variation_name|Standard%` displays “Standard” on a simple product. Without a fallback, a missing value is replaced with an empty string.

The message supports WordPress's safe post HTML. It is loaded whenever the parent product has a configured message, without relying on version-specific Teams product-detection methods.


## Team shortcodes

The plugin also provides shortcodes for the currently logged-in user's WooCommerce Memberships for Teams membership:

- `[teamx_name]` — displays the current user's team name.
- `[teamx_status]` — displays the current user's team membership status.
- `[teamx_restrict]` — conditionally displays enclosed content based on membership plan IDs and/or status.

### Restriction examples

```
[teamx_restrict plan="123"]Visible to active members of plan 123.[/teamx_restrict]

[teamx_restrict plan="123,456"]Visible to active members of plan 123 OR 456.[/teamx_restrict]

[teamx_restrict plan="123+!456"]Visible when plan 123 matches AND plan 456 does not.[/teamx_restrict]

[teamx_restrict status="active,pending"]Visible when status is active OR pending.[/teamx_restrict]

[teamx_restrict plan="123" status="cancelled" mode="hide"]Hidden when the user has a cancelled membership for plan 123.[/teamx_restrict]
```

For `plan` and `status` expressions, commas mean **OR**, plus signs mean **AND**, and `!` negates a token. If `status` is omitted, restriction checks use active memberships only.

## Installation

Copy this directory to `wp-content/plugins/kitmage-team-explainer` and activate **KitMage Team Explainer**. WooCommerce and WooCommerce Memberships for Teams must also be active.

## Author

Mike@KitMage — https://kitmage.com
