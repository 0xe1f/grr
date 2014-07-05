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

require("classes/Controller.php");

class ImportController extends Controller
{
  function initRoutes()
  {
    $this->addFileRoute(array("feed"), "selectFeedsRoute");
    $this->addPostRoute(array("feeds"), "completedRoute");
  }

  function completedRoute($feedsJson)
  {
    $this->feeds = json_decode($feedsJson, true);
    if ($this->feeds === false)
    {
      $this->redirectTo("import");
      return;
    }

    $this->selection = $_POST["selection"];
    if (!$this->selection || !is_array($this->selection))
      $this->selection = array();

    if (($this->errorMessage = $this->isSelectionValid($this->feeds, $this->selection)) !== true)
      $this->setTemplate("selectFeeds");
    else
    {
      $storage = Storage::getInstance();

      $this->rowsImported = $storage->importFeeds($this->user->id, $this->feeds, $this->selection);
      if ($this->rowsImported === false)
      {
        $this->errorMessage = l("An error occurred while importing feeds");
        $this->setTemplate("selectFeeds");

        return;
      }
    }
  }

  function selectFeedsRoute($file)
  {
    $errorCode = $file['error'];
    $this->errorMessage = null;

    if (($errorCode == UPLOAD_ERR_INI_SIZE) || 
      ($errorCode == UPLOAD_ERR_FORM_SIZE) ||
      ($file['size'] > MAX_IMPORT_FILE_SIZE_KB * 1024))
    {
      $this->errorMessage = l("File too large - please try a smaller file");
    }
    else if ($errorCode == UPLOAD_ERR_PARTIAL)
    {
      $this->errorMessage = l("Upload was interrupted - please try again");
    }
    else if ($errorCode == UPLOAD_ERR_NO_FILE)
    {
      $this->errorMessage = l("No file was uploaded");
    }
    else if ($errorCode == UPLOAD_ERR_NO_TMP_DIR 
      || $errorCode == UPLOAD_ERR_CANT_WRITE 
      || $errorCode == UPLOAD_ERR_EXTENSION)
    {
      $this->errorMessage = l("Could not upload file due to a server error");
    }
    else if (($contents = @file_get_contents($file['tmp_name'])) === false)
    {
      $this->errorMessage = l("Upload error - please try again later");
    } 
    else if (($feeds = $this->getFeeds($contents)) === false)
    {
      $this->errorMessage = l("Reading error - is it a valid OPML-formatted file?");
    }

    if ($this->errorMessage)
      $this->setTemplate("default");

    $this->selection = null;
    $this->feeds = $feeds;
  }

  private function isIdInGroup($feeds, $id)
  {
    foreach ($feeds as $feed)
    {
      if ($feed["id"] == $id)
        return true;

      if (isset($feed["children"]) && $this->isIdInGroup($feed["children"], $id))
        return true;
    }

    return false;
  }

  private function isSelectionValid($feeds, $selection)
  {
    if (count($feeds) < 1)
      return "No feeds were found to import";

    if (count($selection) < 1)
      return "Please select one or more feeds";

    foreach ($selection as $selectedId)
      if (!$this->isIdInGroup($feeds, $selectedId))
        return "One or more selections are invalid";

    // FIXME: validate all URL's and ensure titles are always available
    return true;
  }

  private function getFeedLevel($feedXmlNode, &$idCounter)
  {
    $feeds = array();

    foreach($feedXmlNode->outline as $outline)
    {
      $idCounter++;
      $attrs = $outline->attributes();

      $feed = array(
        "id"    => $idCounter,
        "title" => (string)$attrs->text,
      );

      if (isset($attrs->xmlUrl))
      {
        // Actual feed
        $feed["xmlUrl"]  = (string)$attrs->xmlUrl;
        $feed["htmlUrl"] = (string)$attrs->htmlUrl;
      }
      else
      {
        // Group of feeds
        $feed["children"] = $this->getFeedLevel($outline, $idCounter);
      }

      $feeds[] = $feed;
    }

    return $feeds;
  }

  private function getFeeds($document)
  {
    try 
    {
      $xml = @new SimpleXMLElement($document);
    }
    catch (Exception $e) 
    {
      return false;
    }

    $idCounter = 0;
    return $this->getFeedLevel($xml->body, $idCounter);
  }
}

?>
