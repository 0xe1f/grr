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
require("classes/FeedParser.php");
require("classes/JsonController.php");

class FeedController extends JsonController
{
  function initRoutes()
  {
    $this->addGetRoute(array("subscribeTo"), "subscribeRoute");
  }

  function subscribeRoute($feedUrl)
  {
    // Check to see if the system already has the feed

    $storage = Storage::getInstance();
    $feed = $storage->getFeed($feedUrl);

    if (!$feed)
    {
      // Not in the system. Fetch it from www

      // Check URL for validity

      if (!filter_var($feedUrl, FILTER_VALIDATE_URL))
        throw new JsonError(l("Incorrect or unrecognized URL"));

      // Fetch and parse the feed

      try
      {
        $parser = FeedParser::create($feedUrl);
      }
      catch(Exception $e)
      {
        throw new JsonError(l("Could not determine feed format"));
      }

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
    }

    // Subscribe to feed

    if (!$storage->subscribeToFeed($this->user->id, $feed->id))
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

$ctrlr = new FeedController();
$ctrlr->execute();

?>
