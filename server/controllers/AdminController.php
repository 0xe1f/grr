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

require("classes/Controller.php");

class AdminController extends Controller
{
  function initRoutes()
  {
    $this->addPostRoute(array("newToken", "emailAddress"), "createTokenRoute");
  }

  function mustBeAuthorized()
  {
    return ROLE_ADMIN;
  }

  function onPreRender()
  {
    $storage = Storage::getInstance();

    $this->tokens = $storage->getActiveWelcomeTokens();
    $this->users = $storage->getAllUserAccounts();
  }

  function getTemplate()
  {
    return "default"; // No other templates
  }

  function createTokenRoute($dummy, $emailAddress)
  {
    $emailAddress = trim($emailAddress);

    $this->message = null;
    $this->errorMessage = null;

    if (!$this->isValidEmailAddress($emailAddress))
    {
      $this->errorMessage = l("Enter a valid email address");
    }
    else
    {
      $tokenHash = sha1(sha1(sprintf("%d,%s,%d", time(), $emailAddress, rand())));

      $storage = Storage::getInstance();
      if (!$storage->addWelcomeToken($tokenHash, $emailAddress, $this->user))
        $this->errorMessage = l("Error generating a new token. Try again");
      else
        $this->message = l("Token successfully created");
    }
  }
}

?>
