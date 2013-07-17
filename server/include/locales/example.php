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

// Server-side localization example.
// See LOCALIZATION.md for help

// File should be named <IETF language tag>.php
// For instance:
//   Portuguese (Brazil): pt-br.php
//   French: fr.php
//   French (Canada): fr-ca.php
// .. and so on...

$grrStrings = array_merge($grrStrings, array(
  "Your session has timed out. Please sign in." => null,
  "An unexpected error has occurred" => null,
  "Enter a valid description" => null,
  "Error generating a new token. Try again" => null,
  "Token successfully created" => null,
  "Article not found" => null,
  "Specify a valid name" => null,
  "Could not add folder" => null,
  "Could not rename feed" => null,
  "Could not unsubscribe" => null,
  "Incorrect or unrecognized URL" => null,
  "Could not read contents of feed" => null,
  "Could not determine type of feed" => null,
  "Could not parse the contents of the feed" => null,
  "An error occurred while adding feed" => null,
  "Could not subscribe to feed" => null,
  "An error occurred while importing feeds" => null,
  "File too large - please try a smaller file" => null,
  "Upload was interrupted - please try again" => null,
  "No file was uploaded" => null,
  "Could not upload file due to a server error" => null,
  "Upload error - please try again later" => null,
  "Reading error - is it a valid OPML-formatted file?" => null,
  "Enter a valid password" => null,
  "Too many unsuccessful attempts. Try again in a short while" => null,
  "Incorrect username or password" => null,
  "User not found" => null,
  "ADMIN_SECRET is incorrect. Try again" => null,
  "Enter a valid username" => null,
  "Enter a valid email address" => null,
  "Passwords should be at least %s characters long" => null,
  "Passwords don't match" => null,
  "Account creation offer has expired" => null,
  "Account creation requires approval" => null,
  "Cannot create account. Try again later" => null,
  "Username or email address already taken. Try again" => null,
  "Manage Accounts" => null,
  "Username" => null,
  "Email Address" => null,
  "Role" => null,
  "Welcome Tokens:" => null,
  "Description" => null,
  "Token" => null,
  "Created By" => null,
  "Date Created" => null,
  "Create new token with description:" => null,
  "Import Successful" => null,
  "No new feeds have been imported" => null,
  "Success! Imported %s new feed(s)" => null,
  "Note that it may be a while before new articles appear in Reader." => null,
  "Return to Reader" => null,
  "Import Subscriptions" => null,
  "Select Feeds" => null,
  "Select the feeds you'd like to import:" => null,
  "Select all" => null,
  "Import" => null,
  "Create New Account" => null,
  "Please check the configuration file ('config.php') on the server. Copy the value of 'ADMIN_SECRET' (without quotes) and paste it in the field below:" => null,
  "Username:" => null,
  "Email Address:" => null,
  "Password:" => null,
  "Confirm Password:" => null,
  "Create account" => null,
  "Log In" => null,
  "Previous Article" => null,
  "Navigate" => null,
  "Refresh" => null,
  "Mark all as read" => null,
  "Subscribe" => null,
  "Select an OPML file to import your existing feeds:" => null,
  "Upload" => null,
  "Signed in as %s" => null,
  "Sign out" => null,
  "Import subscriptions" => null,
  "Administer" => null,
));
?>
