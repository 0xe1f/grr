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
require("classes/JsonController.php");

class ArticleController extends JsonController
{
  function initRoutes()
  {
    $this->addGetRoute(array("a"), "articleRoute");
    $this->addGetRoute(array("f"), "feedRoute");
    $this->addGetRoute(array("setTag"), "setTagRoute");
  }

  private function flattenFeedTree($feed)
  {
    $feedIds = array($feed["id"] => $feed["unread"]);

    if ($feed["feeds"])
      foreach ($feed["feeds"] as $subfeed)
        foreach ($this->flattenFeedTree($subfeed) as $key => $value)
          $feedIds[$key] = $value;

    return $feedIds;
  }

  function articleRoute()
  {
    $userArticleId = $_GET["a"];
    $isUnread = ($_GET["is_unread"] == "true");
    $isStarred = ($_GET["is_starred"] == "true");
    $isLiked = ($_GET["is_liked"] == "true");

    $storage = Storage::getInstance();
    if ($storage->setArticleStatus($this->user->id, $userArticleId, $isUnread, $isStarred, $isLiked) === false)
      throw new JsonError(l("Article not found"));

    return array(
      "entry" => array(
        "is_starred" => $isStarred,
        "is_unread"  => $isUnread,
        "is_liked"   => $isLiked,
      )
    );
  }

  function setTagRoute($userArticleId)
  {
    $tagsAsString = trim($_GET["tags"]);

    $tags = empty($tagsAsString) ? array() : explode(',', $tagsAsString); // Split the comma-delimited tags
    $tags = array_map('trim', $tags); // Trim each tag
    $tags = array_unique($tags); // Remove all but unique tags 

    $storage = Storage::getInstance();
    if (!$storage->setArticleTags($this->user->id, $userArticleId, $tags))
      throw new JsonError(l("Article not found"));

    return array(
      "entry" => array(
        "tags" => $tags,
      )
    );
  }

  function feedRoute()
  {
    $feedFolderId = $_GET["f"];
    $isUnread = ($_GET["is_unread"] == "true");
    $filter = null;

    $allowedFilters = array("new", "star");
    if (in_array($_GET["filter"], $allowedFilters))
      $filter = $_GET["filter"];

    $storage = Storage::getInstance();
    $result = $storage->markAllAs($this->user, $feedFolderId, $filter, $isUnread);

    if (!$result)
      throw new JsonError(l("Article not found"));

    // Get an updated feed count
    $affectedFeeds = $storage->getUserFeeds($this->user);
    
    // Flatten into an associative array with id => unreadCount
    return array(
      "unreadCounts" => $this->flattenFeedTree($affectedFeeds),
    );
  }
}

$ctrlr = new ArticleController();
$ctrlr->execute();

?>
