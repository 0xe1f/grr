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

abstract class GatewayController extends Controller
{
  function mustBeAuthorized()
  {
    return false;
  }

  protected function saltAndHashOpenId($openId)
  {
    return hash('sha256', hash('sha256', 
      sprintf("%s,%s", SALT_VOPENID, $openId)));
  }

  protected function authorizeUser($user)
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

  protected function missingAdminAccounts()
  {
    $storage = Storage::getInstance();

    $adminCount = $storage->getAdminCount();
    return ($adminCount !== false && $adminCount < 1);
  }
}

?>
