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

class ImportController extends Controller
{
  function initRoutes()
  {
    $this->addFileRoute(array("feed"), "uploadFileRoute");
    $this->addPostRoute(array("feeds"), "importFeedsRoute");
  }

  function importFeedsRoute($feedsJson)
  {
    $feeds = json_decode($feedsJson, true);
    if ($feeds === false)
    {
      $this->redirectTo("import.php");
      return;
    }

    $selection = $_POST["selection"];
    if (!$selection || !is_array($selection))
      $selection = array();

    if (($result = $this->isSelectionValid($feeds, $selection)) !== true)
      $this->renderSelectFeeds($feeds, $selection, $result);
    else
    {
      $storage = Storage::getInstance();

      $feedsAdded = $storage->importFeeds($this->user->id, $feeds, $selection);
      if ($feedsAdded === false)
      {
        $this->renderSelectFeeds($feeds, $selection, l("An error occurred while importing feeds"));
        return;
      }

      $this->renderImportSuccessful($feedsAdded);
    }
  }

  function uploadFileRoute($file)
  {
    $errorCode = $file['error'];

    if (($errorCode == UPLOAD_ERR_INI_SIZE) || 
      ($errorCode == UPLOAD_ERR_FORM_SIZE) ||
      ($file['size'] > MAX_IMPORT_FILE_SIZE_KB * 1024))
    {
      $this->renderSelectFile(l("File too large - please try a smaller file"));
    }
    else if ($errorCode == UPLOAD_ERR_PARTIAL)
    {
      $this->renderSelectFile(l("Upload was interrupted - please try again"));
    }
    else if ($errorCode == UPLOAD_ERR_NO_FILE)
    {
      $this->renderSelectFile(l("No file was uploaded"));
    }
    else if ($errorCode == UPLOAD_ERR_NO_TMP_DIR 
      || $errorCode == UPLOAD_ERR_CANT_WRITE 
      || $errorCode == UPLOAD_ERR_EXTENSION)
    {
      $this->renderSelectFile(l("Could not upload file due to a server error"));
    }
    else if (($contents = @file_get_contents($file['tmp_name'])) === false)
    {
      $this->renderSelectFile(l("Upload error - please try again later"));
    } 
    else if (($feeds = $this->getFeeds($contents)) === false)
    {
      $this->renderSelectFile(l("Reading error - is it a valid OPML-formatted file?"));
    }
    else
    {
      $this->renderSelectFeeds($feeds);
    }
  }

  function defaultRoute()
  {
    $this->renderSelectFile();
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

  function getFeedLevel($feedXmlNode, &$idCounter)
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

  function getFeeds($document)
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

  function renderSelectFile($errorMessage = null)
  {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <link href="content/grr.css" type="text/css" rel="stylesheet"/>
    <title>Import Subscriptions</title>
  </head>
  <body>
    <div id="header">
      <h1>grr <span class="grr">&gt;:(</span></h1>
    </div>
    <div id="content">
<?
    if ($errorMessage)
    {
?>
      <div class="error"><?= h($errorMessage) ?></div>
<?
    }
?>
      <span class="directions">Select an OPML file to import your existing feeds:</span>
      <form enctype="multipart/form-data" action="import.php" method="POST">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMPORT_FILE_SIZE_KB * 1024 ?>" />
        <input name="feed" type="file" />
        <input type="submit" value="Upload" />
      </form>
      <span class="directions-small">Importing Google Reader subscriptions? 
        Use <code>subscriptions.xml</code> generated by 
        <a onclick="this.target='_blank' ;return true;" href="https://www.google.com/takeout/#custom:reader">Google Takeout</a>
      </span>
    </div>
    <div id="footer">
      &copy; 2013 Akop Karapetyan | <a href="https://github.com/melllvar/grr">grr</a> is Open and Free Software licensed under GPL
    </div>
  </body>
</html>
<?
  }

  function renderImportSuccessful($rowsImported)
  {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <link href="content/grr.css" type="text/css" rel="stylesheet"/>
    <title>Import Completed Successfully</title>
  </head>
  <body>
    <div id="header">
      <h1>grr <span class="grr">&gt;:(</span></h1>
    </div>
    <div id="content">
      <p>
<?
    if ($rowsImported < 1)
    {
?>
        <span class="directions">No new feeds have been imported</span>
<?
    }
    else
    {
?>
        <span class="directions">Success! <?= $rowsImported ?> new feeds have been imported</span>
<?
    }
?>
      </p>
      <p>
        Note that it may be a while before new articles appear in Reader.
      </p>
      <p>
        <a href="index.php">Return to Reader</a>
      </p>
    </div>
    <div id="footer">
      &copy; 2013 Akop Karapetyan | <a href="https://github.com/melllvar/grr">grr</a> is Open and Free Software licensed under GPL
    </div>
  </body>
</html>
<?
  }

  function renderSelectFeedsLevel($feeds, $selection)
  {
?>
      <div class="feed-group">
<?
    foreach ($feeds as $feed)
    {
      $id = $feed["id"];
      $isSelected = ($selection === null) || in_array($id, $selection);
?>
        <div class="feed">
          <input type="checkbox" name="selection[]" value="<?= $id ?>" class="feed-option" id="cb<?= $id ?>" <?= $isSelected ? ' checked="checked"' : '' ?> />
          <label for="cb<?= $id ?>"><?= h($feed["title"]) ?></label>
<?
      $children = &$feed["children"];
      if (!empty($children))
        $this->renderSelectFeedsLevel($children, $selection);
?>
        </div>
<?
    }
?>
      </div>
<?
  }

  function renderSelectFeeds($feeds, $selection = null, $errorMessage = null)
  {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <link href="content/grr.css" type="text/css" rel="stylesheet"/>
    <script src="content/jquery-1.9.1.min.js" type="text/javascript"></script>
    <script src="content/import.js" type="text/javascript"></script>
    <title>Import Subscriptions</title>
  </head>
  <body>
    <div id="header">
      <h1>grr <span class="grr">&gt;:(</span></h1>
    </div>
    <div id="content">
<?
    if ($errorMessage)
    {
?>
      <div class="error"><?= h($errorMessage) ?></div>
<?
    }
?>
      <span class="directions">Select the feeds you'd like to import:</span>
      <div class="options">
        <a class="select-all">Select all</a> | <a class="select-none">Select none</a>
      </div>
      <form action="import.php" method="post">
        <input type="hidden" name="feeds" value="<?= h(json_encode($feeds)) ?>" />
<?
      $this->renderSelectFeedsLevel($feeds, $selection);
?>
        <input type="submit" value="Submit" />
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

$ctrlr = new ImportController();
$ctrlr->execute();

?>
