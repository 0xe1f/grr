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
    
  // User's timezone. Crucial for correct date/time information
  // For a list of possible values, see:
  //   http://php.net/manual/en/timezones.php
  define('TIMEZONE', null); // example: 'America/Los_Angeles'

  // MySQL database information
  define('MYSQL_HOSTNAME', null); // example: 'localhost'
  define('MYSQL_USERNAME', null); // example: 'user'
  define('MYSQL_PASSWORD', null); // example: 'password'
  define('MYSQL_DATABASE', null); // example: 'grr'

  define('MYSQL_PORT',   null); // 'null' to use default port
  define('MYSQL_SOCKET', null); // 'null' to use default socket
?>
