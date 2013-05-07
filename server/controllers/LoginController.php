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

require("include/openid.php");
require("include/PasswordHash.php");

class LoginController extends Controller
{
  private $openId;

  function mustBeAuthorized()
  {
    return false;
  }

  function initRoutes()
  {
    $this->addGetRoute(array("logInWith"), "logInRoute");
    $this->addGetRoute(array("logout"), "logOutRoute");
    $this->addGetRoute(array("newAdminAccount"), "newAdminRoute");
    $this->addGetRoute(array("logIn"), "localLoginRoute", "default");
  }

  function defaultRoute()
  {
    if (isset($_GET["createToken"]))
      $this->createToken = $_GET["createToken"];

    if ($this->isAuthenticated())
      $this->redirectTo("reader");
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
    $username = $_POST["username"];
    $password = $_POST["password"];

    $this->errorMessage = null;

    if (strlen($username) < 1)
    {
      $this->errorMessage = l("Enter a valid username");
      return;
    }
    else if (!preg_match('/^\\w+$/', $password))
    {
      $this->errorMessage = l("Enter a valid username");
      return;
    }

    if ($this->shouldThrottleLogin())
    {
      $this->errorMessage = l("Enter a valid username");
      return;
    }

    if (!$this->errorMessage)
    {
      $storage = Storage::getInstance();
      $user = $storage->findUserWithUsername($username, $hash);

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

    $this->unsetUser();
    $this->redirectTo("login");
  }

  function newAdminRoute()
  {
    $this->openIdIdentity = $_POST["oid"];
    $this->computedOpenIdHash = $this->saltAndHashOpenId($this->openIdIdentity);
    $this->errorMessage = null;

    $tokenHash = $_POST["createToken"];
    $username = $_POST["username"];
    $emailAddress = $_POST["emailAddress"];

    $receivedOpenIdHash = $_POST["v"];

    if ($this->computedOpenIdHash != $receivedOpenIdHash)
    {
      $this->errorMessage = l("Could not log in - try again");
      return;
    }
    else if (!preg_match('/^\\w+$/', $username))
    {
      $this->errorMessage = l("Enter a valid username");
      return;
    }
    else if (strlen($this->openIdIdentity) >= 512)
    {
      $this->errorMessage = l("Cannot create account with this OpenId");
      return;
    }
    else if (!$this->isValidEmailAddress($emailAddress))
    {
      $this->errorMessage = l("Enter a valid email address");
      return;
    }
    else if (ADMIN_SECRET === null || trim($tokenHash) !== ADMIN_SECRET)
    {
      $this->errorMessage = l("ADMIN_SECRET is incorrect. Try again");
      return;
    }

    $storage = Storage::getInstance();

    $userCount = $storage->getUserCount();
    if ($userCount === false)
    {
      $this->errorMessage = l("Cannot verify number of users");
      return;
    }
    else if ($userCount > 0)
    {
      $this->redirectTo("login");
      return;
    }

    $roleId = $storage->getRoleId(ROLE_ADMIN);
    if ($roleId === false)
    {
      $this->errorMessage = l("Cannot create account. Try again later");
      return;
    }

    if (($user = $storage->createUser($username, null, $this->openIdIdentity, $emailAddress, null, $roleId)) === false)
    {
      $this->errorMessage = l("Cannot create account at the moment");
      return;
    }

    $this->authorizeUser($user);
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
      $welcomeToken = $_GET["createToken"];

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

  private function saltAndHashOpenId($openId)
  {
    return hash('sha256', hash('sha256', 
      sprintf("%s,%s", SALT_VOPENID, $openId)));
  }

  private function authorizeUser($user)
  {
    $hash = hash('sha256', hash('sha256', sprintf("%s,%s,%s,%s", 
      SALT_SESSION, $user->id, mt_rand(), time())));

    $storage = Storage::getInstance();
    $sessionId = $storage->createSession($user->id, $hash, $_SERVER["REMOTE_ADDR"]);

    if ($sessionId !== false)
    {
      // Verification hash
      $vhash = hash('md5', hash('md5', sprintf("%s,%s,%s",
        SALT_VHASH, $user->username, $sessionId)));

      setcookie(COOKIE_AUTH, $hash, time() + SESSION_DURATION);
      setcookie(COOKIE_VAUTH, $vhash, time() + SESSION_DURATION);

      $this->redirectTo("reader");
    }

    return $sessionId;
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
