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

require("GatewayController.php");

require("include/PasswordHash.php");

class NewUserController extends GatewayController
{
  function initRoutes()
  {
    $this->addPostRoute(array("newUserAuthMode"), "createUserRoute", "default");
  }

  function defaultRoute()
  {
    if ($this->isAuthenticated())
      $this->redirectTo("reader");

    $this->emailAddress = $_GET["emailAddress"];
    $this->welcomeToken = $_GET["welcomeToken"];
    $this->newAdminToken = null;

    $openIdIdentity = $_GET["openId"];
    $receivedOpenIdHash = $_GET["v"];

    if ($openIdIdentity && $receivedOpenIdHash)
    {
      $computedOpenIdHash = $this->saltAndHashOpenId($openIdIdentity);
      if ($receivedOpenIdHash === $computedOpenIdHash)
      {
        $this->openIdIdentity = $openIdIdentity;
        $this->openIdHash = $receivedOpenIdHash;
      }
      else
      {
        // Invalid openId/hash

        $this->redirectTo("login");
        return;
      }
    }

    if ($this->welcomeToken)
    {
      $storage = Storage::getInstance();
      if (($token = $storage->getWelcomeToken($this->welcomeToken)) === false)
      {
        $this->errorMessage = l("Account creation offer has expired");
        return;
      }

      if (!$this->emailAddress)
        $this->emailAddress = $token->emailAddress;
    }
    
    $this->authMode = ($this->openIdIdentity) ? "openId" : "local";
    $this->newAdminMode = $this->missingAdminAccounts();
    $this->canCreateAccounts = CREATE_UNKNOWN_ACCOUNTS || $this->newAdminMode || $this->welcomeToken;

    if (!$this->canCreateAccounts)
    {
      $this->errorMessage = l("User not found");
      return;
    }
  }

  function createUserRoute($authMode)
  {
    $this->authMode = $authMode;
    $this->newAdminMode = $this->missingAdminAccounts();

    $this->username = $_POST["username"];
    $this->emailAddress = $_POST["emailAddress"];
    $this->welcomeToken = $_POST["welcomeToken"];
    
    $this->openIdIdentity = null;
    $this->openIdHash = null;

    $this->canCreateAccounts = CREATE_UNKNOWN_ACCOUNTS || $this->newAdminMode || $this->welcomeToken;

    $hashedPassword = null;
    $tokenId = null;

    // Initialize openID information
    
    if ($authMode == "openId")
    {
      $openIdIdentity = $_POST["openId"];
      $receivedOpenIdHash = $_POST["v"];

      if ($openIdIdentity && $receivedOpenIdHash)
      {
        $computedOpenIdHash = $this->saltAndHashOpenId($openIdIdentity);
        if ($receivedOpenIdHash === $computedOpenIdHash)
        {
          $this->openIdIdentity = $openIdIdentity;
          $this->openIdHash = $receivedOpenIdHash;
        }
      }
    }

    // Validate admin token

    if ($this->newAdminMode)
    {
      $adminToken = $_POST["newAdminToken"];
      if (ADMIN_SECRET === null || $adminToken !== ADMIN_SECRET)
      {
        $this->errorMessage = l("ADMIN_SECRET is incorrect. Try again");
        return;
      }
    }

    // Validate input

    if (!preg_match('/^\\w+$/', $this->username))
    {
      $this->errorMessage = l("Enter a valid username");
      return;
    }

    if (!$this->isValidEmailAddress($this->emailAddress))
    {
      $this->errorMessage = l("Enter a valid email address");
      return;
    }

    if ($authMode == "openId")
    {
      if (!$this->openIdIdentity)
      {
        // OpenID information is not valid
        
        $this->redirectTo("login");
        return;
      }
    }
    else if ($authMode == "local")
    {
      $password = $_POST["password"];
      $confirmPassword = $_POST["confirmPassword"];

      if (!$password || strlen(trim($password)) < SHORTEST_PASSWORD_LENGTH)
      {
        $this->errorMessage = l("Passwords should be at least %s characters long", SHORTEST_PASSWORD_LENGTH);
        return;
      }

      if ($password != $confirmPassword)
      {
        $this->errorMessage = l("Passwords don't match");
        return;
      }

      // Generate a password

      $hashedPassword = $this->getHasher()->HashPassword($password);
      if (strlen($hashedPassword) < 20)
      {
        $this->errorMessage = l("Cannot create account. Try again later");
        return;
      }
    }
    else
    {
      // Authentication mode is set incorrectly

      $this->redirectTo("login");
      return;
    }

    $storage = Storage::getInstance();

    if ($this->newAdminMode)
    {
      $roleId = $storage->getRoleId(ROLE_ADMIN);
    }
    else
    {
      if ($this->welcomeToken)
      {
        if (($token = $storage->getWelcomeToken($this->welcomeToken)) === false)
        {
          $this->errorMessage = l("Account creation offer has expired");
          return;
        }

        $tokenId = $token->id;
      }

      if (!CREATE_UNKNOWN_ACCOUNTS && $tokenId === null)
      {
        $this->errorMessage = l("Account creation requires approval");
        return;
      }

      $roleId = $storage->getRoleId(ROLE_USER);
    }

    if ($roleId === false)
    {
      $this->errorMessage = l("Cannot create account. Try again later");
      return;
    }

    if (($user = $storage->createUser($this->username, $hashedPassword, $this->openIdIdentity, $this->emailAddress, $tokenId, $roleId)) === false)
    {
      $this->errorMessage = l("Username or email address already taken. Try again");
      return;
    }

    $this->authorizeUser($user);
  }
}

?>
