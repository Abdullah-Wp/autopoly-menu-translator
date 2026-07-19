# AutoPoly Menu Translator

AutoPoly Menu Translator duplicates a classic WordPress navigation menu for a Polylang language, translates its item titles with Chrome's on-device Translator API, and preserves menu hierarchy and translated post or taxonomy links.

Translation happens in the administrator's browser. The plugin does not send menu titles to a translation service or require an API key.

## Features

- Uses Chrome's on-device Translator API.
- Preserves parent and child menu relationships.
- Uses Polylang translations for linked posts, pages, and taxonomy terms when available.
- Keeps custom links and common menu-item metadata.
- Validates permissions, requests, languages, and translated menu data.
- Rolls back a newly created menu if an item cannot be saved.
- Never silently overwrites an existing translated menu.

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- Polylang.
- A classic WordPress navigation menu.
- A supported desktop version of Google Chrome with the Translator API available.

The Translator API is device- and language-pair-dependent. Chrome may download an on-device language model the first time a language pair is used. It is not available in every browser or on every device.

## Installation

1. Download the latest release ZIP.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload and activate the plugin.
4. Confirm Polylang languages and a source navigation menu already exist.
5. Open **Appearance > AutoPoly Menu Translator** in desktop Chrome.

## Usage

1. Select the source menu.
2. Select the target Polylang language.
3. Enter the source language code, such as `en` or `fr`.
4. Choose **Translate and duplicate menu**.
5. Review the generated menu under **Appearance > Menus** before assigning it to a location.

The generated menu is named using the source menu and target language, for example `Primary Menu - FR (AI)`. If that name already exists, the plugin stops instead of deleting or replacing it.

## Privacy

Menu titles are translated locally by Chrome. Chrome itself may download language models required by the requested language pair. The plugin sends normal authenticated WordPress AJAX requests only to the same WordPress site to read and save the menu.

## Limitations

- Designed for classic WordPress navigation menus, not the Navigation block used by block themes.
- Machine translations should always be reviewed by a fluent speaker.
- Browser support and available language pairs are controlled by Chrome.

## Development

Run syntax checks with:

```text
php -l autopoly-menu-translator.php
node --check assets/admin.js
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
