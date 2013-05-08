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

require("include/openid.php");
require("include/PasswordHash.php");

class LoginController extends GatewayController
{
  private $openId;

  function initRoutes()
  {
    $this->addGetRoute(array("logInWith"), "logInRoute");
    $this->addGetRoute(array("logout"), "logOutRoute");
    $this->addGetRoute(array("logIn"), "localLoginRoute", "default");
  }

  function defaultRoute()
  {
    if (isset($_GET["createToken"]))
      $this->welcomeToken = $_GET["createToken"];

    if ($this->isAuthenticated())
      $this->redirectTo("reader");

    $this->canCreateAccounts = CREATE_UNKNOWN_ACCOUNTS || $this->missingAdminAccounts() || $this->welcomeToken;
  }

  function logInRoute($provider)
  {
    $authUrl = null;

    if ($provider == "google")
      $authUrl = "https://www.google.com/accounts/o8/id";
    else if ($provider == "yahoo")
      $authUrl = "https://me.yahoo.com";

    if ($authUrl)
    {
      $this->openId->required = array('contact/email');
      $this->openId->identity = $authUrl;

      $this->redirectToUrl($this->openId->authUrl());
    }
  }

  function localLoginRoute()
  {
    $this->username = $_POST["username"];
    $password = $_POST["password"];

    $this->errorMessage = null;

    if (strlen(trim($this->username)) < 1)
    {
      $this->errorMessage = l("Enter a valid username");
      return;
    }
    else if (strlen($password) < 1)
    {
      $this->errorMessage = l("Enter a valid password");
      return;
    }

    if ($this->shouldThrottleLogin())
    {
      $this->errorMessage = l("Too many unsuccessful attempts. Try again in a short while");
      return;
    }

    if (!$this->errorMessage)
    {
      $storage = Storage::getInstance();
      $user = $storage->findUserWithUsername($this->username, $hash);

      if ($user === false || !$this->getHasher()->CheckPassword($password, $hash))
      {
        $storage->reportFailedLogin($user ? $user->id : null, $_SERVER["REMOTE_ADDR"]);

        $this->errorMessage = l("Incorrect username or password");
        return;
      }
    }

    $this->authorizeUser($user);
  }

  function logOutRoute()
  {
    setcookie(COOKIE_AUTH, "", time() - 3600);
    setcookie(COOKIE_VAUTH, "", time() - 3600);

    $storage = Storage::getInstance();
    $storage->voidSession($this->user->id, $this->user->sessionId);

    $this->user = null;
    $this->redirectTo("login");
  }

  function openIdModeRoute($mode)
  {
    if ($mode == "cancel" || !$this->openId->validate())
    {
      $this->redirectTo("login");
      return;
    }

    $openIdIdentity = $this->openId->identity;
    $this->errorMessage = null;

    // User has signed in via openID

    $storage = Storage::getInstance();
    if (($user = $storage->findUserWithOpenId($openIdIdentity)) === false)
    {
      // Unknown user. Redirect to account creation controller

      $openIdAttrs = $this->openId->getAttributes();
      $emailAddress = $openIdAttrs["contact/email"];
      $welcomeToken = $_GET["welcomeToken"];

      $openIdHash = $this->saltAndHashOpenId($openIdIdentity);

      $this->redirectTo("newUser", array(
        "welcomeToken" => $welcomeToken,
        "emailAddress" => $emailAddress,
        "openId"       => $openIdIdentity,
        "v"            => $openIdHash));

      return;
    }

    // Already a member
    $this->authorizeUser($user);
  }

  protected function route()
  {
    $this->openId = new LightOpenID(HOSTNAME);

    if ($this->openId->mode)
      $this->openIdModeRoute($this->openId->mode);
    else
      parent::route();
  }

  private function shouldThrottleLogin()
  {
    if (LOGIN_THROTTLING_WINDOW === false)
      return false;

    // Get number of recent failed logins

    $storage = Storage::getInstance();
    $failedLogin = $storage->getFailedLoginStatistics($_SERVER["REMOTE_ADDR"], 
      LOGIN_THROTTLING_WINDOW);

    if ($failedLogin == null)
      return false;

    if ($failedLogin->failedLoginCount < 2)
      return false;

    $delay = pow(2, $failedLogin->failedLoginCount - 2);
    
    return time() < $failedLogin->lastFailedAttempt + $delay;
  }
}

?>
