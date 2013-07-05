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

require("classes/JsonController.php");

class ArticleController extends JsonController
{
  function initRoutes()
  {
    // Paging
    $this->addGetRoute(array("fetch", "filter", "continue"), "fetchPageRoute");
    $this->addGetRoute(array("fetch", "filter"), "fetchPageRoute");

    // Modification
    $this->addPostRoute(array("toggleStatusOf", "isUnread", "isStarred", "isLiked"), "toggleArticleStatusRoute");
    $this->addPostRoute(array("toggleUnreadUnder", "filter"), "toggleAllUnreadRoute");
    $this->addPostRoute(array("setTagsFor", "tags"), "setTagRoute");
  }

  private function flattenFeedTree($feed)
  {
    $feedIds = array($feed->id => $feed->unread);

    if ($feed->subs)
      foreach ($feed->subs as $subfeed)
        foreach ($this->flattenFeedTree($subfeed) as $key => $value)
          $feedIds[$key] = $value;

    return $feedIds;
  }

  function toggleArticleStatusRoute($userArticleId, $isUnread, $isStarred, $isLiked)
  {
    $isUnread = ($isUnread == "true");
    $isStarred = ($isStarred == "true");
    $isLiked = ($isLiked == "true");

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

  function setTagRoute($userArticleId, $tagsAsString)
  {
    $tagsAsString = trim($tagsAsString);

    $tags = empty($tagsAsString) ? array() : explode(',', $tagsAsString); // Split the comma-delimited tags
    $tags = array_map('trim', $tags); // Trim each tag
    $tags = array_unique($tags); // Remove all but unique tags

    // Remove empty tag (if any)
    foreach ($tags as $index => $tag)
      if ($tag === "")
      {
        unset($tags[$index]);
        $tags = array_values($tags); // Reorder array

        // Unique prevents presence of more than one empty tag
        break;
      }

    $storage = Storage::getInstance();
    if (!$storage->setArticleTags($this->user->id, $userArticleId, $tags))
      throw new JsonError(l("Article not found"));

    return array(
      "entry" => array(
        "tags" => $tags,
      )
    );
  }

  function toggleAllUnreadRoute($feedFolderId, $requestedFilter)
  {
    $isUnread = false;
    $filter = null;

    $allowedFilters = array("new", "star");
    if (in_array($requestedFilter, $allowedFilters))
      $filter = $requestedFilter;

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

  function fetchPageRoute($subscriptionId, $filter, $continueAfterId = null)
  {
    $storage = Storage::getInstance();

    $articles = $storage->getArticlePage($this->user->id, 
      $subscriptionId, $filter, PAGE_SIZE, $continueAfterId);

    $response = array("entries"  => $articles);
    if (count($articles) >= PAGE_SIZE)
    {
      $last = end($articles);
      $response["continue"] = $last["id"];
    }

    return $response;
  }
}

?>
