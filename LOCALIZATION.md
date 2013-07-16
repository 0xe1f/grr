Localization
============

As of July 7 2013, **grr** supports localization. String localization in *grr* is twofold:

* Client-side, via [js/locales/reader-&lt;ietf-tag&gt;.js](server/content/js/locales/reader-example.js), and
* Server-side, via [include/locales/&lt;ietf-tag&gt;.php](server/include/locales/example.php)

How It Works
------------

**grr** uses the preferred localization based on the `Accept-Language` HTTP header. It selects the best match based on availability, and the order of tags in the header.

Adding a New Localization
-------------------------

To add a new localization:

* Determine the IETF language tag for the locale - e.g. _pt-br_, _fr-ca_, _en-gb_. For more information, see http://www.i18nguy.com/unicode/language-identifiers.html
* Copy [js/locales/reader-example.js](server/content/js/locales/reader-example.js) by replacing "example" in the filename with the IETF language tag. For instance, the French translation would be named "reader-fr.js"
* Update function `dateTimeFormatter` to return a properly formatted date/time string -- see `dateTimeFormatter` in [js/locales/reader-en-us.js](server/content/js/locales/reader-en-us.js) for an example
* For lines following `$.extend(grrStrings,`, replace the `null` placeholder on each line with the translated string, in quotes
* Copy [include/locales/example.php](server/include/locales/example.php) by replacing "example" in the filename with the IETF language tag. Again, the French translation would be named "fr.php"
* On each line, replace `null` with the translated string, in quotes
* Add the IETF language tag to the `$GRR_SUPPORTED_LOCALES` array in [include/common.php](server/include/common.php): `$GRR_SUPPORTED_LOCALES = array("en", "fr");`

When translating, please be mindful of string placeholders such as `%s` and `%1$s`.
