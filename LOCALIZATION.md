Localization
============

As of July 7 2013, **grr** supports string localization. String localization in *grr* is two-fold:

* Client-side, via [js/locales/reader-<ietf-tag>.js](server/content/js/locales/reader-example.js), and
* Server-side, via [include/locales/<ietf-tag>.php](server/include/locales/example.php)

How It Works
------------

**grr** uses the preferred localization based on the `Accept-Language` HTTP header. It selects the best match based on availability, and the order of tags in the header.

Adding a New Localization
-------------------------

To add a new localization:

* Determine the IETF language tag for the locale - e.g. pt-br, fr-ca, en-gb. For more information, see http://www.i18nguy.com/unicode/language-identifiers.html
* Copy [js/locales/reader-example.js](server/content/js/locales/reader-example.js) by replacing "example" in the filename to the IETF language tag. For instance, the French translation would be named "reader-fr.js". On each line, replace `null` with the translated string, in quotes.
* Copy [include/locales/example.php](server/include/locales/example.php) by replacing "example" in the filename to the IETF language tag. For instance, the French translation would be named "fr.php". On each line, replace `null` with the translated string, in quotes.
* Add the IETF language tag to the `$GRR_SUPPORTED_LOCALES` array in [include/common.php](server/include/common.php)

When translating, please be mindful of string placeholders such as `%s` and `%1$s`.