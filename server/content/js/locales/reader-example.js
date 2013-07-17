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

// Client-side localization example
// See LOCALIZATION.md for help

// File should be named reader-<IETF language tag>.js
// For instance:
//   Portuguese (Brazil): reader-pt-br.js
//   French: reader-fr.js
//   French (Canada): reader-fr-ca.js
// .. and so on...

var dateTimeFormatter = function(date, sameDay)
{
  // Parameters:

  //   date: JavaScript Date object containing date/time information
  //   sameDay: 'true' if the date component represents today's date

  // This function generates a localized date/time string.
  // It returns a string representing:
  //   - date if 'sameDay' is 'false' (e.g. 'Jan 5, 2010'), or
  //   - time if 'sameday' is 'true' (e.g. '10:30 PM')

  // See reader-en-us for a more sophisticated example. 
  // The default implementation uses JavaScript's default date/time 
  // formatters, which aren't ideal.

  return defaultDateTimeFormatter(date, sameDay);
};

$.extend(grrStrings,
{
  'Enter the feed URL': null,
  'Successfully subscribed to "%s"': null,
  'Successfully unsubscribed from "%s"': null,
  'Items marked as read': null,
  'Like': null,
  'Like (%s)': null,
  'New items': null,
  'No new items': null,
  '1 new item': null,
  '%1$s new items': null,
  'Separate multiple tags with commas': null,
  'Share on Google+': null,
  'Share': null,
  'Keep unread': null,
  'Edit tags: %s': null,
  'Add tags': null,
  'Continue': null,
  'New name:': null,
  'Subscription successfully renamed to "%s"': null,
  'Name of folder:': null,
  '"%s" successfully added': null,
  'Unsubscribe from "%s"?': null,
  'Unsubscribe from all feeds under "%s"?': null,
  'An unexpected error has occurred. Please try again later.': null,
  'Subscriptions': null,
  'Navigation': null,
  'Application': null,
  'Articles': null,
  'j/k': null,
  'open next/previous article': null,
  'n/p': null,
  'scan next/previous article': null,
  'Shift+n/p': null,
  'scan next/previous subscription': null,
  'Shift+o': null,
  'open subscription or folder': null,
  'g, then a': null,
  'open subscriptions': null,
  'r': null,
  'refresh': null,
  'u': null,
  'toggle navigation mode': null,
  'a': null,
  'add subscription': null,
  '?': null,
  'help': null,
  'm': null,
  'mark as read/unread': null,
  's': null,
  'star article': null,
  't': null,
  'tag article': null,
  'l': null,
  'like article': null,
  'v': null,
  'open link': null,
  'o': null,
  'open article': null,
  'Shift+a': null,
  'mark all as read': null,
  'All items' => null,
  'Starred' => null,
  'Unsubscribe' => null,
  'New folder…' => null,
  'Subscribe…' => null,
  'Rename subscription…' => null,
  'Unsubscribe from all…' => null,
});
