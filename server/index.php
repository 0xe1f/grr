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

class ReaderController extends Controller
{
  function defaultRoute()
  {
    $user = $this->getCurrentUser();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <link href="content/reader.css" type="text/css" rel="stylesheet"/>
    <script src="content/sprintf.min.js" type="text/javascript"></script>
    <script src="content/jquery-1.9.1.min.js" type="text/javascript"></script>
    <script src="content/jquery.scrollintoview.min.js" type="text/javascript"></script>
    <script src="content/jquery.hotkeys.js" type="text/javascript"></script>
    <script src="content/spin.min.js" type="text/javascript"></script>
    <script src="content/reader.js" type="text/javascript"></script>
    <title>&gt;:(</title>
  </head>
  <body>
    <div id="toast"><span></span></div>
    <div id="header">
      <h1>grr <span class="grr">&gt;:(</span></h1>
      <div class="navbar">
        <span class="logout">Signed in as <span class="username"><?= h($user->username) ?></span> <a href="login.php?logout=true">Sign out</a></span>
        <a class="import-subs" href="import.php">Import subscriptions</a>
<?
    if ($user->isAdmin())
    {
?>
        <a class="admin" href="admin.php">Admin</a>
<?
    }
?>
      </div>
    </div>
    <div id="navbar">
      <div class="right-aligned">
        <button class="select-article up"><?= l("Previous") ?></button>
        <button class="select-article down"><?= l("Next") ?></button>
      </div>
      <button class="refresh"><?= l("Refresh") ?></button>
      <span class="spacer"></span>
      <button class="mark-all-as-read"><?= l("Mark all as read") ?></button>
      <span class="spacer"></span>
      <select class="article-filter">
        <option value="all" class="filter-all"><?= l("All Items") ?></option>
        <option value="new" class="filter-new"><?= l("New Items") ?></option>
        <option value="star" class="filter-star"><?= l("Starred") ?></option>
      </select>
    </div>
    <div id="reader">
      <div class="feeds-container">
        <ul id="feeds"></ul>
      </div>
      <div class="entries-container">
        <div id="entries"></div>
      </div>
    </div>
  </body>
</html>
<?
  }  
}

$ctrlr = new ReaderController();
$ctrlr->execute();

?>
