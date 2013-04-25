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

class PagingController extends JsonController
{
  private $pageSize;
  private $feed;
  private $filter;

  function initRoutes()
  {
    $this->addGetRoute(array("continue"), "continueRoute");
  }

  function onInitialized()
  {
    parent::onInitialized();

    $this->pageSize = PAGE_SIZE;
    $this->feed = is_numeric($_GET["feed"]) ? $_GET["feed"] : null;
    $this->filter = null; // Default (all)

    $allowedFilters = array("new", "star");
    if (in_array($_GET["filter"], $allowedFilters))
      $this->filter = $_GET["filter"];
  }

  function continueRoute($afterRecord)
  {
    $storage = Storage::getInstance();

    $articles = $storage->getArticlePage($this->user->id, 
      $this->feed, $this->filter, $this->pageSize, $afterRecord);

    $response = array("entries"  => $articles);
    if (count($articles) >= $this->pageSize)
    {
      $last = end($articles);
      $response["continue"] = $last["id"];
    }

    return $response;
  }

  function defaultRoute()
  {
    $storage = Storage::getInstance();

    $articles = $storage->getArticlePage($this->user->id, 
      $this->feed, $this->filter, $this->pageSize);

    $response = array("entries"  => $articles);
    if (count($articles) >= $this->pageSize)
    {
      $last = end($articles);
      $response["continue"] = $last["id"];
    }

    return $response;
  }
}

$ctrlr = new PagingController();
$ctrlr->execute();

?>
