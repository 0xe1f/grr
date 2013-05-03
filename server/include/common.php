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
  die(l("Turn off magic quotes"));

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
  die(l("Configuration is incomplete"));
}

function l()
{
  // Localization stub
  $args = func_get_args();
  if (count($args) < 1)
    return "";
  if (count($args) == 1)
    return $args[0];

  return call_user_func_array('sprintf', $args);
}

function h($string)
{
  return htmlspecialchars($string, ENT_QUOTES, "UTF-8");
}

?>
