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

abstract class Storage
{
  private static $instance = null;
  
  public abstract function getUserFeeds($user);
  public abstract function markAllAs($user, $feedFolderId, $filter, $isUnread);
  public abstract function addFeedFolder($userId, $feedId = null, $title = null, $parent = null);
  public abstract function importFeeds($userId, $feeds, $selection);
  public abstract function getUserFeedRoot($userId);
  public abstract function getFeedFolderId($userId, $parentId, $title);
  public abstract function getActiveWelcomeTokens();
  public abstract function getWelcomeToken($tokenHash);
  public abstract function addWelcomeToken($tokenHash, $emailAddress, $createdBy);
  public abstract function getAllUserAccounts();
  public abstract function findUserWithOpenId($openIdIdentity);
  public abstract function getUserCount();
  public abstract function getUserWithSessionHash($sessionHash, $receivedVHash);
  public abstract function createUser($username, $password, $openIdIdentity, $emailAddress, $welcomeTokenId, $roleId);
  public abstract function getRoleId($roleCode);
  public abstract function createSession($userId, $hash, $remoteAddress);
  public abstract function getArticlePage($userId, $feedFolderId, $filter, $pageSize, $continueAfterId = null);
  public abstract function setArticleStatus($userId, $userArticleId, $isUnread, $isStarred, $isLiked);

  public static function getInstance()
  {
    if (self::$instance == null)
      self::$instance = new MySqlStorage();

    return self::$instance;
  }
}

class User
{
  var $id;
  var $username;
  var $role;
  var $sessionId;

  public static function getCurrent()
  {
    if (!isset($_COOKIE[COOKIE_AUTH]) || !isset($_COOKIE[COOKIE_VAUTH]))
      return false;

    $hash = $_COOKIE[COOKIE_AUTH];
    $receivedVHash = $_COOKIE[COOKIE_VAUTH];

    $storage = Storage::getInstance();

    return $storage->getUserWithSessionHash($hash, $receivedVHash);
  }

  public function isAdmin()
  {
    return $this->role == "admin";
  }
}

require("MySqlStorage.php");

?>