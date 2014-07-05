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

abstract class FeedParser
{
  const ERROR_DOWNLOAD = 100;
  const ERROR_EMPTY_DOCUMENT = 101;
  const ERROR_TOO_MANY_REDIRECTS = 102;

  protected $url;
  protected $document;
  protected $xml;
  protected $links;

  public function __construct()
  {
    $this->links = array();
  }

  public function getLinks()
  {
    return $this->links;
  }

  public static function create($url, $followHtml = false, $recursions = 0)
  {
    if ($recursions > 2)
      throw new Exception("Download error", FeedParser::ERROR_TOO_MANY_REDIRECTS);

    $originalUrl = $url;
    $document = FeedParser::fetchDocument($url);
    if (!$document)
      throw new Exception("Document is empty", FeedParser::ERROR_EMPTY_DOCUMENT);

    // First pass - parse it as valid XML

    $xmlDocument = null;
    $parser = null;
    $links = array();

    libxml_use_internal_errors(true);

    try
    {
      $xmlDocument = @new SimpleXMLElement($document);
    }
    catch(Exception $e)
    {
    }

    if ($xmlDocument === null)
    {
      // Document didn't parse as valid XML

      $errors = libxml_get_errors();

      foreach ($errors as $error)
      {
        if ($error->code == 9)
        {
          // PCDATA Invalid char value

          $document = preg_replace('/[\x00-\x1f\x80-\xff]/', '', $document);

          // Reparse the document

          try
          {
            $xmlDocument = @new SimpleXMLElement($document);
          }
          catch(Exception $e)
          {
          }

          break;
        }
      }
    }

    if ($xmlDocument === null && $followHtml)
    {
      // Not sure if this is ideal, but let's just blindly assume
      // this is an HTML document and try to parse any rel=alternate
      // links

      $links = FeedParser::extractLinks($url, $document);
      if (count($links) > 0) // Success? Let's hope so.
      {
        if ($parser = FeedParser::create($links[0]->url, $followHtml, $recursions + 1))
        {
          $parser->links[] = $url;
          if ($url != $originalUrl)
            $parser->links[] = $originalUrl;
        }

        return $parser;
      }
    }

    if ($xmlDocument)
    {
      // Valid XML. See if we can determine the type of content

      $rootName = $xmlDocument->getName();

      if ($rootName == 'feed')
        $parser = new AtomParser();
      else if ($rootName == 'rss' || $rootName == 'RDF')
        $parser = new RssParser();
      else if ($followHtml && strcasecmp($rootName, 'html') === 0)
      {
        // HTML document. See if we can find a feed by parsing the HTML
        $links = FeedParser::extractLinks($url, $document);
        if (count($links) > 0)
        {
          if ($parser = FeedParser::create($url, $followHtml, $recursions + 1))
          {
            $parser->links[] = $url;
            if ($url != $originalUrl)
              $parser->links[] = $originalUrl;
          }

          return $parser;
        }
      }
    }

    if ($parser)
    {
      $parser->url = $url;
      $parser->document = $document;
      $parser->xml = $xmlDocument;
    }

    return $parser;
  }

  private static function resolveUrl($baseUrl, $relativeUrl)
  {
    // Source:
    // http://stackoverflow.com/questions/1243418/php-how-to-resolve-a-relative-url

    if (parse_url($relativeUrl, PHP_URL_SCHEME) != '') 
      return $relativeUrl;

    if ($relativeUrl[0] == '#' || $relativeUrl[0] == '?')
      return $baseUrl.$relativeUrl;

    extract(parse_url($baseUrl));

    $path = preg_replace('#/[^/]*$#', '', $path);

    if ($relativeUrl[0] == '/') 
      $path = '';

    $absoluteUrl = "$host$path/$relativeUrl";

    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for ($n = 1; $n > 0; $absoluteUrl = preg_replace($re, '/', $absoluteUrl, -1, $n))
      ; // Noop

    return "$scheme://$absoluteUrl";
  }

  private static function fetchDocument(&$url)
  {
    $curlSession = curl_init();

    curl_setopt($curlSession, CURLOPT_URL, $url);
    curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlSession, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curlSession, CURLOPT_MAXREDIRS, 2);

    $content = @curl_exec($curlSession);
    $effectiveUrl = @curl_getinfo($curlSession, CURLINFO_EFFECTIVE_URL);

    $errorCode = curl_errno($curlSession);
    $errorMessage = curl_error($curlSession);

    curl_close($curlSession);

    if ($errorCode != CURLE_OK)
      throw new Exception("Download error", FeedParser::ERROR_DOWNLOAD);

    if ($effectiveUrl)
      $url = $effectiveUrl; // Use the final redirected URL

    return $content;
  }

  private static function extractLinks($sourceUrl, $html)
  {
    $links = array();

    if (preg_match_all("/<link(?:\\s+\\w+=[\"'][^\"']*[\"'])+\\s*\\/?>/", $html, $matches))
    {
      foreach ($matches[0] as $match)
      {
        if (preg_match_all("/(\\w+)=[\"']([^\"']*)[\"']/", $match, $submatches, PREG_SET_ORDER))
        {
          $attrs = array();
          foreach ($submatches as $submatch)
          {
            list(, $key, $value) = $submatch;
            $attrs[strtolower($key)] = $value;
          }

          if (strcasecmp($attrs["rel"], "alternate") === 0 
            && (strcasecmp($attrs["type"], "application/rss+xml") === 0
              || strcasecmp($attrs["type"], "application/atom+xml") === 0))
          {
            $link = new stdClass();
            
            $link->title = $attrs["title"];
            $link->url = FeedParser::resolveUrl($sourceUrl, $attrs["href"]);

            $links[] = $link;
          }
        }
      }
    }

    return $links;
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
