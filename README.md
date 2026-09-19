# KitMage Team Explainer

A small WordPress plugin for WooCommerce Memberships for Teams. It adds a **Team seat instructions** field to the parent product and displays the configured message beside the Teams fields for both simple and variable team products.

## Placeholders

- `%max_seats%` — the selected variation's or simple product's maximum member count.
- `%product_name%` — the parent product name.
- `%variation_name%` — the selected variation name (blank for simple products).

Each placeholder accepts a custom fallback after a pipe. For example, `%max_seats|unlimited%` displays “unlimited” when there is no finite seat maximum, and `%variation_name|Standard%` displays “Standard” on a simple product. Without a fallback, a missing value is replaced with an empty string.

The message supports WordPress's safe post HTML. It is loaded whenever the parent product has a configured message, without relying on version-specific Teams product-detection methods.

## Installation

Copy this directory to `wp-content/plugins/kitmage-team-explainer` and activate **KitMage Team Explainer**. WooCommerce and WooCommerce Memberships for Teams must also be active.

## Author

Mike@KitMage — https://kitmage.com
