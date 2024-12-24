=== Simple Image Popup ===
Contributors: mrdigital
Tags: popup, image, lightbox, conditional, accessibility
Author URI: https://www.mrdigital.com.au
Author: Sean Freitas
Requires at least: 5.6
Tested up to: 6.7.1
Requires PHP: 7.4
Stable tag: 2.5.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple way to show a popup image on your website with various enhancements including conditional display and accessibility features.

== Description ==

Use this plugin to display an image popup for promotions, alerts, or messages. Key features include:
- Simple activation/deactivation of the popup.
- Choose an image from your media gallery.
- Set a unique Popup ID to control repeat appearances.
- Optionally link the popup image to any URL.
- Control how frequently the popup reappears using an expiry time.
- Delay popup display by a set number of seconds.
- **NEW:** Conditionally display the popup only on selected posts or pages.
- **NEW:** Enhanced accessibility:
  - Close popup with the "Escape" key.
  - Focus management for keyboard navigation.
  - Aria attributes and roles for screen readers.
- **NEW:** Improved admin UI so that the conditional display options only appear if enabled.

== Installation ==

From your WordPress dashboard:

1. **Visit** Plugins > Add New
2. **Search** for "Simple Image Popup"
3. **Click** "Install Now" and then "Activate"
4. **Go to** Settings > Simple Image Popup to configure your popup settings

== Frequently Asked Questions ==

= How do I make the popup appear only on certain pages or posts? =
Enable "Conditional Display" in the plugin settings, then select the posts or pages you want the popup to appear on.

= Can I close the popup by pressing the ESC key? =
Yes, with the latest version, the popup is accessible and can be closed by pressing the ESC key, ensuring better accessibility and user experience.

== Changelog ==

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
Always back up your site before upgrading. Upgrading to 2.5.x introduces new settings for conditional display and accessibility. After upgrading, review and adjust the plugin settings under Settings > Simple Image Popup as needed.
