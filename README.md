# Cargus for WooCommerce 1.6.0: community fixes

This repository holds the **Cargus** WooCommerce shipping plugin, version 1.6.0, with a set of fixes on top. The plugin is GPL-2.0+ software published by Cargus at <https://wordpress.org/plugins/cargus/>.

This is an unofficial community patch. It is not affiliated with, endorsed by or supported by Cargus. All credit for the plugin belongs to Cargus. The fixes are offered back under the same licence, in the hope they are merged upstream.

The first commit is the untouched 1.6.0 release, byte for byte as downloaded from `https://downloads.wordpress.org/plugin/cargus.1.6.0.zip`. Each later commit fixes one problem, so it can be reviewed or cherry-picked on its own. The whole change is also published as a single unified diff, [`cargus-1.6.0-fixes.patch`](cargus-1.6.0-fixes.patch).

A short summary in Romanian is in [PATCH-SUMMARY.ro.md](PATCH-SUMMARY.ro.md).

## The main problem: customers cannot enter their street at checkout

With **"Selectare stradă"** (`street-select`) enabled, a customer who picks a locality whose streets have their own postcodes (Oradea, Cluj-Napoca, Bucureşti and other cities) sees an **empty "Strada" dropdown**. No streets are listed and nothing can be typed, so the customer cannot complete the address and cannot order.

The "Numar" field shows `1` in it, so it looks filled in. That `1` is only the placeholder, and the field is flagged red as empty.

### Cause

`public/js/cargus.js` declares only `jquery` as a dependency, but it calls `.select2()` directly. Many themes and optimisation plugins dequeue select2/selectWoo on the checkout so that the browser's native pickers are used. When select2 is missing, the call throws:

```
TypeError: $(...).select2 is not a function      (cargus.js, filterCities)
```

By the time it throws, the street field has already been turned into an empty `<select>`. The same uncaught call also breaks the locality select2 setup and the restore of a saved address on page load.

The server side is fine. `admin-ajax.php?action=cargus_get_streets` returns the streets for Oradea correctly. The browser just never asks for them.

### How to reproduce

1. Install Cargus 1.6.0 and enable "Selectare localitate" and "Selectare stradă".
2. Remove select2 from the checkout, as many themes do. For example:
   ```php
   add_action( 'wp_enqueue_scripts', function () {
       if ( is_checkout() ) { wp_dequeue_script( 'selectWoo' ); wp_dequeue_style( 'select2' ); }
   }, 1000 );
   ```
3. On the checkout, choose Țara = România, Județ = Bihor, Localitate = ORADEA.
4. "Strada" is now an empty dropdown and the console shows `$(...).select2 is not a function`.

A second problem exists even when select2 is loaded: a street that is missing from the Cargus nomenclature (a new street, a village, a different spelling) cannot be entered at all.

## What each commit fixes

| Commit | Problem | `error_log` entries it removes |
|---|---|---|
| **Cargus 1.6.0 as distributed** | The baseline. | |
| **Checkout: street and number fields work without select2, and accept any street** | Empty street dropdown and no way to order (above). Every select2 call now goes through a guard. Without select2, the street is a text input with a native `<datalist>` of matching Cargus streets. With select2, the typed text is offered after the known streets. Picking a known street still fills the postcode and the AWB `StreetId`. The street and locality lookups always answer JSON. Street search ignores diacritics. The placeholders `Unirii` / `1` become `ex: Unirii` / `ex: 12`. `address_1` is also built on the server, so the order still goes through if the script did not run. `cargus_before_checkout_create_order()` now loads the settings it tests, so the street, street id and number are saved on the order. | (JavaScript errors in the browser) |
| **Sessions: stop starting a PHP session on every request** | `session_start()` was hooked on `init` for every request without a `headers_sent()` check. It also sent a `PHPSESSID` cookie with every page, which defeats full-page caching. `Cargus_Api` already opens the session itself when it needs one. Without a session it now uses its own values instead of returning `null`. | `session_start(): Session cannot be started after headers have already been sent ... class-cargus-admin.php on line 157` (6,134 entries in one week) |
| **Shipping price: normalise the API answer (array vs object)** | `ShippingCalculation` errors arrive as a JSON list, so `$result->Subtotal` is read on an array. The `null` price then went through the discount arithmetic as `0`, and the **Cargus rate was offered free ("- Gratuit")** whenever the API returned an error. The answer is now normalised: a price object, or no rate. The "dynamic price" order action writes the API error into the order note instead of an empty price. Also fixes a payment gateway read on `false`. | `Attempt to read property "Subtotal" on array ... class-cargus-shipping-method.php on line 746`; `"BaseCost" / "ExtraKmCost" / "WeightCost" / "InsuranceCost" / "SpecialCost" / "RepaymentCost" / "Subtotal" / "Tax" / "GrandTotal" on array ... class-cargus-admin.php on lines 2217-2225`; `Attempt to read property "id" on bool ... class-cargus-shipping-method.php on line 607` |
| **AWB: fix fatal "Unsupported operand types: int + string" on order save** | With the per-parcel metabox, an empty parcel weight (`""`) is a PHP 8 `TypeError`. It is raised inside `save_post`, so the order cannot be saved at all. A decimal comma (`2,5`) was read as `2`. Weights are now parsed as numbers. | `PHP Fatal error: Uncaught TypeError: Unsupported operand types: int + string in .../class-cargus-admin.php:1158` |
| **Settings: fix fatal "get_pickup_locations() on null" when saving the method** | `process_admin_options()` refreshed the pickup points through `$this->api`, which is only created when the webservice URL and API key are both in the form. | `PHP Fatal error: Uncaught Error: Call to a member function get_pickup_locations() on null in .../class-cargus-shipping-method.php:191` |
| **Pickup points: stop reading the obsolete cargus_pickup_points option** | Nothing in 1.6.0 writes `cargus_pickup_points`, so these loops ran over `false`. As a result, the pre10/pre12 check never recognised a Bucharest sender, and the "dynamic price" action called the API with `FromLocalityId = null`. The plugin now uses the transient-cached `PickupLocations`, and still reads the old option first when a site has it. | `foreach() argument must be of type array\|object, bool given ... class-cargus-public.php on line 1168` and `... class-cargus-admin.php on line 2136`; `Undefined variable $location` and `Trying to access array offset on value of type null ... class-cargus-admin.php on line 2190` |

The error-log line numbers refer to the unmodified 1.6.0 files.

## Compatibility

- PHP 7.4 to 8.5. Every changed PHP file passes `php -l` on 7.4, 8.1, 8.2 and 8.5.
- The JavaScript uses only what 1.6.0 already uses (ES2015, jQuery). It works with select2, with selectWoo, or with neither.
- No database changes, no new options and no new settings. The happy path (a valid API answer, select2 present, numeric weights) sends the same API requests as 1.6.0.

## How it was tested

- On a WooCommerce 11 checkout that dequeues selectWoo, with headless Chrome. 1.6.0 reproduces the empty "Strada" dropdown and four uncaught `select2 is not a function` errors. With the patch:
  - Oradea offers street suggestions, and *Republicii, Calea* + 12 fills postcode 410025 and street id 14110.
  - A street not in the list is accepted as typed.
  - Salonta, which has its own postcode, gets a plain street field and postcode 415500.
- The same flow with selectWoo loaded, using the select2 path.
- Server-side checkout validation (`WC_Checkout::validate_checkout`) with `address_1` left empty: the order is accepted when a street is given, and still refused when street and number are missing.
- `cargus_create_awb()` with parcel weights `["", "2,5"]`. 1.6.0 fails with the logged `TypeError` at line 1158. With the patch, the AWB request is built with `TotalWeight = 2.5`.
- `process_admin_options()` with an empty webservice URL and API key: no fatal error.
- The pre10/pre12 AJAX handler with no `cargus_pickup_points` option: no warnings.

## Installing

Copy the plugin files (everything except `README.md`, `PATCH-SUMMARY.ro.md` and the `.patch` file) over `wp-content/plugins/cargus/`. You can also apply the patch to a clean 1.6.0:

```sh
cd wp-content/plugins/cargus && patch -p1 < cargus-1.6.0-fixes.patch
```

## Licence

GPL-2.0-or-later, the same as the original plugin. See the plugin header in `cargus.php`.
