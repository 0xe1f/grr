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

  // config.php defaults

  //
  // Information in this section should be modified for each installation
  //

  // User's timezone. Crucial for correct date/time information
  // For a list of possible values, see http://php.net/manual/en/timezones.php
  define('TIMEZONE', null); // example: 'America/Los_Angeles'

  // MySQL database information
  define('MYSQL_HOSTNAME', null); // example: 'localhost'
  define('MYSQL_USERNAME', null); // example: 'user'
  define('MYSQL_PASSWORD', null); // example: 'password'
  define('MYSQL_DATABASE', null); // example: 'grr'

  define('MYSQL_PORT',   null); // 'null' to use default port
  define('MYSQL_SOCKET', null); // 'null' to use default socket

  // Hostname of the server where the script resides.
  // Required for OpenID; should NOT be $_SERVER['HTTP_HOST']
  // See openid.php for more information
  define('HOSTNAME', null); // example: 'localhost'

  // Various salts
  define('SALT_VHASH',   null); // For these three values, use any three distinct
  define('SALT_VOPENID', null); // strings (20 characters or longer)
  define('SALT_SESSION', null);

  // This is the secret key that's used to create the first administrative account.
  // It's only relevant in instances where no users are present in the system, but
  // it's recommended that it be a string 20 characters or longer.
  define('ADMIN_SECRET', null);

  //
  // This section includes default values which can be customized
  //

  // Window of time (in seconds) within which unsuccessful login attempts will be
  // throttled, or 'false' to disable failed login throttling.
  define('LOGIN_THROTTLING_WINDOW', 60);

  // Shortest allowed password length
  define('SHORTEST_PASSWORD_LENGTH', 8);

  // Maximum number of articles to load each time
  define('PAGE_SIZE', 40);

  // Authentication cookie names
  define('COOKIE_AUTH', 'grr_auth');
  define('COOKIE_VAUTH', 'grr_v');

  // The duration period of each login session. After a timeout,
  // users will be asked to reauthenticate with OID provider
  define('SESSION_DURATION', 60 * 60 * 24 * 30);

  // Maximum size of import files, in kilobytes. 
  // Anything larger than this will be refused
  define('MAX_IMPORT_FILE_SIZE_KB', '50');

  // 'true' to allow any openID-authenticated user to create a new account in the system.
  // 'false' (default) to reject users not already in the system 
  //    New accounts can be created by generating 'welcome token' links 
  //    in the Admin section
  define('CREATE_UNKNOWN_ACCOUNTS', false);

  // Various role codes
  //   Modifying these requires changing values of 'code' column in the 'roles' table
  define('ROLE_USER', 'user');
  define('ROLE_ADMIN', 'admin');
?>
