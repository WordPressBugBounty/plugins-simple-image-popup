=== Simple Image Popup ===
Contributors: mrdigital
Tags: popup, image, lightbox, conditional, accessibility
Author URI: https://www.mrdigital.com.au
Author: Sean Freitas
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern popup manager for WordPress with multiple popups, targeting rules, legacy migration, and popup view tracking.

== Description ==

Use this plugin to manage image-based popups for promotions, alerts, announcements, and featured campaigns. Version 3 introduces a rebuilt architecture and admin experience:

- Create and manage multiple popups from a dedicated admin app.
- Store popup definitions in a custom post type instead of one Options API array.
- Automatically migrate your existing legacy popup settings into the new system.
- Choose images from the media library or provide an image URL.
- Set popup status, delay, repeat frequency, click-to-close behaviour, and maximum width.
- Target popups sitewide or to selected posts/pages.
- Track popup views when a popup is actually shown to visitors.
- Improved accessibility with dialog semantics, keyboard close support, and focus management.

== Installation ==

From your WordPress dashboard:

1. **Visit** Plugins > Add New
2. **Search** for "Simple Image Popup"
3. **Click** "Install Now" and then "Activate"
4. **Go to** Settings > Simple Image Popup to configure your popups

== Frequently Asked Questions ==

= How do I make a popup appear only on certain pages or posts? =
Edit the popup, change the targeting mode to "Selected posts/pages", then search for and add the content where the popup should appear.

= Will my old popup settings be lost when I upgrade? =
No. Version 3 automatically migrates the old `sip_plugin_options` settings into a popup record and keeps the legacy option data in place for safety.

= Does the plugin track popup views? =
Yes. The browser reports a view when a popup opens, and the site stores the view in its own database. The count measures popup displays.

== CSS Overrides ==

Go to Settings > Simple Image Popup and open Help > CSS Overrides in the top-right corner. The tab includes the complete frontend stylesheet, including the mobile rules and close-button states, plus copyable examples.

Copy just the rules you want to change into Additional CSS. For a block theme, open Appearance > Editor > Styles and choose Additional CSS from the three-dot Styles actions menu. For a classic theme, open Appearance > Customize > Additional CSS. Save your changes and preview a page that displays the popup.

Use `.simple-image-popup-plugin` for the full-screen container. Use `.simple-image-popup-plugin__backdrop` for the overlay, `.simple-image-popup-plugin__inner` for the dialog, `.simple-image-popup-plugin__close` for the close button, `.simple-image-popup-plugin__image` for the image, and `.simple-image-popup-plugin__link` for linked images. To style a single popup, prepend its ID to each selector, for example `#simple-image-popup-123 .simple-image-popup-plugin__backdrop`. Inspect the popup on the site to find its ID.

Use the popup editor’s maximum width setting to resize the dialog. Keep the `[hidden]` and `.simple-image-popup-plugin--open` rules so dismissal and scroll locking work correctly. The default rules are in `css/simple-image-popup.css`.

== Privacy ==

The plugin stores a total view count for each popup. Earlier versions stored individual display times. Repeat limits use a timestamp saved in the visitor's browser. If a popup uses an externally hosted image, the visitor's browser requests the image from that host.

== Development ==

The frontend and admin source files are included in `resources/`. To rebuild the assets, run `npm ci` and `npm run build` from the plugin directory. The admin bundle uses [Vue](https://github.com/vuejs/core) under the MIT license. The locked dependency versions are listed in `package-lock.json`.

== Changelog ==

= 3.0.2 =
* Redesigned the popup editor and added translation support for its interface.
* Added a CSS Overrides help tab with the complete frontend stylesheet and copyable examples.
* Fixed popup loading on CDNs that serve JavaScript without cross-origin headers.
* Tightened popup permissions and view tracking. Existing view totals are preserved.
* Updated JavaScript dependencies.

= 3.0.1 =
* Moved the popup editor to Settings > Simple Image Popup.

= 3.0.0 =
* Verified compatibility with WordPress 7.1, including sites that use plain permalinks.
* Rebuilt the plugin around a `sip_popup` custom post type.
* Added a Vue-powered admin interface built with Vite.
* Added automatic migration from the legacy `sip_plugin_options` settings.
* Added popup view tracking with server-side storage.
* Added support for managing multiple popups.
* Reworked frontend rendering and targeting into dedicated services.

= 2.5.6 =
* Updated to include new dispatch date message.
* Minor bug fixes and performance improvements.

= 2.5.3 =
* Added JavaScript and styling to only show the "Select Posts/Pages" field if "Conditional Display" is enabled.
* Improved accessibility and UI adjustments.

= 2.5.2 =
* Introduced conditional display options to limit popup to certain posts or pages.
* Added accessibility enhancements: close with ESC key, focus management, and ARIA attributes.

= 2.4.0 =
* Initial public release with basic popup functionality.
* Choose image, set popup ID, set expiry, and delay time.

== Upgrade Notice ==

= 3.0.2 =
Refresh your page cache after updating so visitors receive the new popup script. Existing popup settings and view totals are preserved.

= 3.0.1 =
Find the popup editor under Settings > Simple Image Popup.

= 3.0.0 =
Always back up your site before upgrading. Version 3.0.0 migrates the plugin from a single settings page into a popup manager with automatic legacy data migration. After upgrading, review the migrated popup under the Simple Image Popup admin screen.
