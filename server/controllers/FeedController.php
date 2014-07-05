<?
/*****************************************************************************
 **
 ** grr >:(
 ** https://github.com/pokebyte/grr
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
require("classes/FeedParser.php");

class FeedController extends JsonController
{
  function initRoutes()
  {
    $this->addPostRoute(array("subscribeTo", "createUnder"), "subscribeRoute");
    $this->addPostRoute(array("renameSubscription", "newName"), "renameSubscriptionRoute");
    $this->addPostRoute(array("unsubscribeFrom"), "unsubscribeRoute");
    $this->addPostRoute(array("createFolderUnder", "folderName"), "createSubfolderRoute");
  }

  function createSubfolderRoute($parentFolderId, $folderName)
  {
    $folderName = trim($folderName);
    if (strlen($folderName) < 1)
      throw new JsonError(l("Specify a valid name"));

    $storage = Storage::getInstance();
    $newFolderId = $storage->addFeedFolder($this->user->id, null, $folderName, $parentFolderId);

    if ($newFolderId === false)
      throw new JsonError(l("Could not add folder"));

    return array(
      "folder" => array(
        "id"    => $newFolderId,
        "title" => $folderName,
      ),
      "allItems" => $storage->getUserFeeds($this->user),
    );
  }

  function renameSubscriptionRoute($feedFolderId, $newName)
  {
    $storage = Storage::getInstance();
    if (!$storage->renameSubscription($this->user->id, $feedFolderId, $newName))
      throw new JsonError(l("Could not rename feed"));

    return array(
      "feed" => array(
        "title" => $newName,
      ),
      "allItems" => $storage->getUserFeeds($this->user),
    );
  }

  function unsubscribeRoute($feedFolderId)
  {
    $storage = Storage::getInstance();
    if (!$storage->unsubscribe($this->user->id, $feedFolderId))
      throw new JsonError(l("Could not unsubscribe"));

    return array(
      "allItems" => $storage->getUserFeeds($this->user),
    );
  }

  function subscribeRoute($feedUrl, $parentFolderId)
  {
    // Check to see if the system already has the feed

    $storage = Storage::getInstance();
    $feed = $storage->getFeed($feedUrl);

    if (!$feed) // Try the list of links
    {
      $matches = $storage->findFeedFromLinks($feedUrl);
      if (count($matches) > 0)
        $feed = $matches[0]; // TODO: allow selection if > 1
    }

    if (!$feed)
    {
      // Not in the system. Fetch it from www

      // Check URL for validity

      if (!filter_var($feedUrl, FILTER_VALIDATE_URL))
        throw new JsonError(l("Incorrect or unrecognized URL"));

      // Fetch and parse the feed

      try
      {
        $parser = FeedParser::create($feedUrl, true);
      }
      catch(Exception $e)
      {
        throw new JsonError(l("Could not read contents of feed"));
      }

      if (!$parser)
        throw new JsonError(l("Could not determine type of feed"));

      try
      {
        $feed = $parser->parse();
      }
      catch(Exception $e)
      {
        throw new JsonError(l("Could not parse the contents of the feed"));
      }

      // Import the feed contents

      $feed->id = $storage->importFeed($this->user->id, $feed);
      if ($feed->id === false)
        throw new JsonError(l("An error occurred while adding feed"));

      // Import any links

      $links = $parser->getLinks();
      if (!in_array($feed->link, $links))
        $links[] = $feed->link;

      $storage->addLinks($feed->id, $links);
    }

    // Subscribe to feed

    if (!$storage->subscribeToFeed($this->user->id, $feed->id, $parentFolderId))
      throw new JsonError(l("Could not subscribe to feed"));

    return array(
      "feed" => array(
        "title" => $feed->title,
      ),
      "allItems" => $storage->getUserFeeds($this->user),
    );
  }

  function defaultRoute()
  {
    $storage = Storage::getInstance();
    return array(
      "allItems" => $storage->getUserFeeds($this->user),
    );
  }
}

?>
