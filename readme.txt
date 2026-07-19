=== AutoPoly Menu Translator ===
Contributors: abdullahwp
Tags: polylang, menu translation, translator, multilingual
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: polylang
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Duplicate and translate classic Polylang navigation menus with Chrome's on-device Translator API.

== Description ==

AutoPoly Menu Translator translates menu titles locally in desktop Chrome, duplicates the selected menu, preserves its hierarchy, and uses translated Polylang links when available.

No translation API key is required. Browser and language-pair availability depend on Chrome and the device. Always review machine-generated translations before publishing them.

== Installation ==

1. Activate Polylang and configure languages.
2. Create a classic WordPress navigation menu.
3. Activate AutoPoly Menu Translator.
4. Open Appearance > AutoPoly Menu Translator in desktop Chrome.

== Changelog ==

= 1.1.0 =
* Updated to Chrome's current Translator API.
* Added capability checks and strict request validation.
* Removed duplicate save requests and added rollback handling.
* Prevented existing menus from being silently deleted.
* Added taxonomy translation support and public documentation.

= 1.0.0 =
* Initial version.
