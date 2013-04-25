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

require("include/common.php");
require("classes/Core.php");

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

  function defaultRoute()
  {
    $this->renderAdminView();
  }

  function createTokenRoute($dummy, $emailAddress)
  {
    // FIXME: validate email address format
    $emailAddress = trim($emailAddress);

    if (strlen($emailAddress) < 1)
    {
      $this->renderAdminView(null, l("Enter a valid email address"));
      return;
    }

    $tokenHash = sha1(sha1(sprintf("%d,%s,%d", time(), $emailAddress, rand())));

    $storage = Storage::getInstance();
    if (!$storage->addWelcomeToken($tokenHash, $emailAddress, $this->user))
    {
      $this->renderAdminView(null, l("Error generating a new token. Try again"));
      return;
    }

    $this->renderAdminView(l("Token successfully created"));
  }

  function renderAdminView($message = null, $errorMessage = null)
  {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <link href="content/grr.css" type="text/css" rel="stylesheet"/>
    <title>Manage Accounts</title>
  </head>
  <body>
    <div id="header">
      <h1>grr <span class="grr">&gt;:(</span></h1>
    </div>
    <div id="content" class="login">
<?
    if ($message)
    {
?>
      <div class="message"><?= h($message) ?></div>
<?
    }

    if ($errorMessage)
    {
?>
      <div class="error"><?= h($errorMessage) ?></div>
<?
    }

    $storage = Storage::getInstance();
?>
      <span class="directions">Accounts:</span>
      <table>
        <tr>
          <th><?= l("Username") ?></th>
          <th><?= l("Email Address") ?></th>
          <th><?= l("Role") ?></th>
        </tr>
<?
    $users = $storage->getAllUserAccounts();

    if ($users !== false)
    {
      foreach ($users as $user)
      {
?>
        <tr>
          <td><?= h($user["username"]) ?></td>
          <td><?= h($user["emailAddress"]) ?></td>
          <td><?= h($user["roleName"]) ?></td>
        </tr>
<?
      }
    }
?>
      </table>
      <span class="directions">Welcome Tokens:</span>
      <table>
        <tr>
          <th><?= l("Email Address") ?></th>
          <th><?= l("Token") ?></th>
          <th><?= l("Created By") ?></th>
          <th><?= l("Date Created") ?></th>
        </tr>
<?
    $tokens = $storage->getActiveWelcomeTokens();

    if ($tokens !== false)
    {
      foreach ($tokens as $token)
      {
        // FIXME: the date format should be localized
?>
        <tr>
          <td><?= h($token["emailAddress"]) ?></td>
          <td><a href="login.php?createToken=<?= h($token["token"]) ?>"><?= h($token["token"]) ?></a></td>
          <td><?= h($token["createdBy"]) ?></td>
          <td><?= h(date("F j, Y, g:i a", $token["createdOn"])) ?></td>
        </tr>
<?
      }
    }
?>
      </table>
      <span class="directions">Create new token with email address:</span>
      <form action="admin.php" method="post">
        <input type="hidden" name="newToken" value="true" />
        <input type="text" name="emailAddress" value="<?= h($_POST["emailAddress"]) ?>" />
        <input type="submit" value="Create" />
      </form>
    </div>
    <div id="footer">
      &copy; 2013 Akop Karapetyan | <a href="https://github.com/melllvar/grr">grr</a> is Open and Free Software licensed under GPL
    </div>
  </body>
</html>
<?
  }
}

$ctrlr = new AdminController();
$ctrlr->execute();

?>
