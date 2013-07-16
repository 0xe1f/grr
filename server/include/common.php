<?
/*****************************************************************************
 **
 ** grr >:(
 ** https://github.com/melllvar/grr
 ** Copyright (C) 2013 Akop Karapetyan
 **
 ** This program is free software; you can redistribute it and/or modify
 ** it under the terms of the GNU General Public License as published by
 ** the Free Software Foundation; either version 2 of the License, or
 ** (at your option) any later version.
 **
 ** This program is distributed in the hope that it will be useful,
 ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 ** GNU General Public License for more details.
 **
 ** You should have received a copy of the GNU General Public License
 ** along with this program; if not, write to the Free Software
 ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 **
 ******************************************************************************
 */

define('ERROR_UNAUTHENTICATED', 100);
define('ERROR_UNAUTHORIZED',    101);

define('ERROR_REAUTHENTICATE',  102);

// Magic quotes should be turned off
// To turn them off via .htaccess, add the following line to the .htaccess file:
//   php_flag magic_quotes_gpc Off

if (get_magic_quotes_gpc())
  die("Turn off magic quotes");

if (TIMEZONE === null ||
    MYSQL_HOSTNAME === null ||
    MYSQL_USERNAME === null ||
    MYSQL_PASSWORD === null ||
    MYSQL_DATABASE === null ||
    HOSTNAME === null ||
    SALT_VHASH === null ||
    SALT_VOPENID === null ||
    SALT_SESSION === null)
{
  die("Configuration is incomplete");
}

// Locale information
// Order here is unimportant - client controls order of preference
$GRR_SUPPORTED_LOCALES = array(
  "en",
  "en-us",
);
// This should be 'en'. 
// It reflects the language in the 'default' string maps
$GRR_DEFAULT_LOCALE = "en";

function l()
{
  // Localization stub
  $args = func_get_args();
  if (count($args) < 1)
    return "";

  global $grrStrings;
  if (isset($grrStrings[$args[0]]))
    $args[0] = $grrStrings[$args[0]];

  if (count($args) == 1)
    return $args[0];

  return call_user_func_array('sprintf', $args);
}

function h($string)
{
  return htmlspecialchars($string, ENT_QUOTES, "UTF-8");
}

function compareLocales($a, $b)
{
  $q1 = (int)($a["q"] * 100);
  $q2 = (int)($b["q"] * 100);

  if ($q1 == $q2)
  {
    $index1 = $a["index"];
    $index2 = $b["index"];

    // Q-values are the same - compare by index
    if ($index1 == $index2)
      return 0;

    return $index1 > $index2 ? -1 : 1;
  }

  // Compare by Q-value
  return $q1 > $q2 ? -1 : 1;
} 

function getAcceptedLocales()
{
  $acceptedLocales = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  if (!$acceptedLocales)
    return array();

  $localeSpecs = explode(",", $acceptedLocales);
  $locales = array();
  $index = 0;

  foreach ($localeSpecs as $localeSpec)
  {
    $specParts = explode(";", $localeSpec);

    $locale = array("name" => strtolower($specParts[0]), "index" => $index++, "q" => 1.0);

    if (count($specParts) == 2 && strncmp($specParts[1], "q=", 2) == 0)
      $locale["q"] = substr($specParts[1], 2); // Explicit Q-value

    $locales[] = $locale;
  }

  // Sort the locales
  uasort($locales, 'compareLocales');
  
  // Collect the names, keep only uniques
  $localeNames = array_unique(array_map('current', $locales));
  $localeNames = array_values($localeNames); // Reset indices

  return $localeNames;
}

// Get a sorted list of accepted locales
$acceptedLocales = getAcceptedLocales();
$grrCurrentLocale = null; // Default locale

foreach ($acceptedLocales as $acceptedLocale)
{
  if (in_array($acceptedLocale, $GRR_SUPPORTED_LOCALES))
  {
    if ($acceptedLocale != $GRR_DEFAULT_LOCALE)
      $grrCurrentLocale = $acceptedLocale; // Explicit locale

    break;
  }
}

require('include/locales/default.php');

if ($grrCurrentLocale != null)
{
  $localeFile = "include/locales/{$grrCurrentLocale}.php";
  if (file_exists($localeFile))
    include($localeFile);
}

?>
