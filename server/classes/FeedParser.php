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

abstract class FeedParser
{
  protected $url;
  protected $document;
  protected $xml;
  protected $enableLogging;

  public function enableLogging($enableLogging)
  {
    $this->enableLogging = $enableLogging;
  }

  public static function create($url)
  {
    $curlSession = curl_init();

    curl_setopt($curlSession, CURLOPT_URL, $url);
    curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

    $content = curl_exec($curlSession);

    $errorCode = curl_errno($curlSession);
    $errorMessage = curl_error($curlSession);

    curl_close($curlSession);

    if ($errorCode != CURLE_OK)
      throw new Exception("Error reading document ({$errorCode}: '{$errorMessage}')");

    if (!$content)
      throw new Exception('Document is empty');

    try
    {
      $xml = @new SimpleXMLElement($content);
    }
    catch(Exception $e)
    {
      // In case there are invalid control characters...
      if ($this->enableLogging)
        echo "  (i) Attempting to strip out control characters\n";

      $content = preg_replace('/[\x00-\x1f\x80-\xff]/', '', $content);
      $xml = @new SimpleXMLElement($content);
    }

    $namespaces = $xml->getDocNamespaces();

    $parser = null;
    if ($namespaces[""] == "http://www.w3.org/2005/Atom")
      $parser = new AtomParser();
    else
      $parser = new RssParser();

    if ($parser != null)
    {
      $parser->url = $url;
      $parser->document = $content;
      $parser->xml = $xml;
    }

    return $parser;
  }

  public abstract function parse();
}

class Feed
{
  private $lastBuildDate;
  private $updatePeriod;
  private $updateFrequency;

  public $id;
  public $url;
  public $articles;
  public $title;
  public $summary;
  public $type;
  public $link;

  public function __construct()
  {
    $this->articles = array();
  }

  public function setUpdateInformation($updatePeriod, $updateFrequency)
  {
    $this->updatePeriod = $updatePeriod;
    $this->updateFrequency = $updateFrequency;
  }

  public function getLastBuildDate()
  {
    return $this->lastBuildDate;
  }

  public function setLastBuildDate($string)
  {
    $this->lastBuildDate = null;

    if (!empty($string))
      $this->lastBuildDate = strtotime($string);
  }

  public function getNextUpdate($lastUpdate)
  {
    $periodInSeconds = 60 * 60 * 24; // default: day
    $frequency = 1; // default: once

    if (!$this->updatePeriod && !$this->updateFrequency)
    {
      // No syndication information has been explicitly specified

      $minPeriod = 60 * 30;      // 30 minutes
      $maxPeriod = 60 * 60 * 24; // 1 day
      
      if (count($this->articles) > 1)
      {
        // Try to figure out next update time based on the publishing
        // dates in the current feed
        
        // Collect the dates and sort them in ascending order

        $publishingDates = array();
        foreach ($this->articles as $article)
          $publishingDates[] = $article->published;
        sort($publishingDates, SORT_NUMERIC);

        // Compute the average difference between publishing dates

        $deltaSum = 0;
        for ($i = 1, $n = count($publishingDates); $i < $n; $i++)
          $deltaSum += $publishingDates[$i] - $publishingDates[$i - 1];

        $periodInSeconds = $deltaSum / ($n - 1);

        // Clamp the frequency

        if ($periodInSeconds > $maxPeriod)
          $periodInSeconds = $maxPeriod;
        else if ($periodInSeconds < $minPeriod)
          $periodInSeconds = $minPeriod;

        echo sprintf("  (i) Estimated update frequency: every %ss (%.02fh)\n",
          $periodInSeconds, (float)$periodInSeconds / 3600.0);
      }
    }
    else
    {
      if ($this->updatePeriod == "hourly")
        $periodInSeconds = 60 * 60;
      else if ($this->updatePeriod == "daily")
        $periodInSeconds = 60 * 60 * 24;
      else if ($this->updatePeriod == "weekly")
        $periodInSeconds = 60 * 60 * 24 * 7;
      else if ($this->updatePeriod == "monthly")
        $periodInSeconds = 60 * 60 * 24 * 30;
      else if ($this->updatePeriod == "yearly")
        $periodInSeconds = 60 * 60 * 24 * 365;

      if (is_int($this->updateFrequency))
      {
        $frequency = (int)$this->updateFrequency;
        if ($frequency < 1)
          $frequency = 1;
      }
    }

    if (!$lastUpdate)
      $lastUpdate = time();

    return $lastUpdate + ($periodInSeconds / $frequency);
  }

  public function getTitle()
  {
    return Article::dehtmlize($this->title);
  }

  public function getSummary()
  {
    $summary = Article::dehtmlize($this->summary);
    return mb_substr($summary, 0, 512, 'UTF-8');
  }
}

class Article
{
  public $guid;
  public $published = 0;
  public $link_url;
  public $author;
  public $title;
  public $text;

  public static function dehtmlize($str)
  {
    // Strip the HTML tags
    $summary = strip_tags($str);

    // Decode HTML entities
    $summary = html_entity_decode($summary, ENT_NOQUOTES, 'UTF-8');

    // Remove extraneous whitespace
    return preg_replace('/\\s+/u', ' ', $summary);
  }

  public function getAuthor()
  {
    return Article::dehtmlize($this->author);
  }

  public function getTitle()
  {
    return Article::dehtmlize($this->title);
  }

  public function getSummary()
  {
    $summary = Article::dehtmlize($this->text);
    return mb_substr($summary, 0, 512, 'UTF-8');
  }
}

require('AtomParser.php');
require('RssParser.php');

?>
