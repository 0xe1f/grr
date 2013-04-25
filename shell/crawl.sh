#! /usr/bin/php
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

  // WARNING

  // This is the crawler script, meant to be invoked from the shell/cron.
  // This script and its accompanying 'config.php' should reside somewhere not 
  // www-reachable!

  require('config.php');

  abstract class FeedParser
  {
    protected $url;
    protected $document;
    protected $xml;

    public static function create($url)
    {
      $content = file_get_contents($url);

      if (!$content)
        throw new Exception('Document is empty');

      $xml = @new SimpleXMLElement($content);
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

  class RssParser extends FeedParser
  {
    public function parse()
    {
      $feed = new Feed();

      $this->xml->channel->registerXPathNamespace('sy', 
        'http://purl.org/rss/1.0/modules/syndication/');

      $feed->type = "rss";
      $feed->url = $this->url;
      $feed->setLastBuildDate((string)$this->xml->channel->lastBuildDate);
      $feed->title = (string)$this->xml->channel->title;
      $feed->summary = (string)$this->xml->channel->description;
      $feed->link = (string)$this->xml->channel->link;

      $sy = $this->xml->channel->children('http://purl.org/rss/1.0/modules/syndication/');
      $feed->setUpdateInformation((string)$sy->updatePeriod, (string)$sy->updateFrequency);

      foreach($this->xml->channel->item as $item)
      {
        $item->registerXPathNamespace('dc', 
          'http://purl.org/dc/elements/1.1/');

        $article = new Article();
        $article->guid = (string)$item->guid;
        
        $article->published = time();

        $pubDate = (string)$item->pubDate;
        if ($pubDate)
        {
          if (($timestamp = strtotime($pubDate)) !== false)
            $article->published = $timestamp;
        }

        $article->link_url = (string)$item->link;
        $article->title = (string)$item->title;
        $article->text = (string)$item->description;

        $creator = $item->xpath('dc:creator');
        if ($creator)
          $article->author = (string)$creator[0];

        $feed->articles[] = $article;
      }

      return $feed;
    }
  }

  class AtomParser extends FeedParser
  {
    public function parse()
    {
      $this->xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
      $this->xml->feed->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');

      $feed = new Feed();

      $feed->type = "atom";
      $feed->url = $this->url;
      $feed->setLastBuildDate((string)$this->xml->updated);
      $feed->title = (string)$this->xml->title;
      $feed->summary = (string)$this->xml->subtitle;

      $links = $this->xml->xpath('atom:link[@rel="alternate"]');
      if (!empty($links))
      {
        $link = $links[0]->attributes();
        $feed->link = (string)$link->href;
      }

      foreach ($this->xml->xpath('/atom:feed/atom:entry') as $entry) 
      {
        $entry->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');

        $article = new Article();
        $article->guid = (string)$entry->id;
        $article->published = time();

        $pubDate = (string)$entry->published;
        if (!$pubDate)
          $pubDate = (string)$entry->updated;

        if ($pubDate)
        {
          if (($timestamp = strtotime($pubDate)) !== false)
            $article->published = $timestamp;
        }

        $links = $entry->xpath('atom:link[@rel="alternate"]');
        if ($links)
        {
          $link = $links[0]->attributes();
          $article->link_url = (string)$link->href;
        }

        $article->text = (string)$entry->summary;

        if (isset($entry->author) && isset($entry->author->name))
          $article->author = (string)$entry->author->name;

        $article->title = (string)$entry->title;

        $feed->articles[] = $article;
      }

      return $feed;
    }
  }

  class Feed
  {
    private $lastBuildDate;
    private $updatePeriod;
    private $updateFrequency;

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

      if ($this->updatePeriod === null && $this->updateFrequency === null)
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
      return substr($summary, 0, 512);
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
      return substr($summary, 0, 512);
    }
  }

  class Crawler
  {
    private $db;
    private $started;
    private $inserted;
    private $updated;
    private $personalized;
    private $secondsSpentDownloading;
    private $secondsSpentParsing;
    private $secondsSpentStaging;
    private $secondsSpentWriting;

    private function stage($feed, $feedId, $lastUpdated)
    {
      $now = microtime(true);

      $stageFeedStatement = $this->db->prepare("
             INSERT INTO staged_feeds (feed_url,
                                       feed_hash,
                                       html_url,
                                       title,
                                       summary,
                                       last_built,
                                       last_updated,
                                       next_update,
                                       staged)
                  VALUES (?,?,?,?,?,FROM_UNIXTIME(?),UTC_TIMESTAMP(),FROM_UNIXTIME(?),?)
                                       ");

      $stageFeedStatement->bind_param('sssssiii',
        $feed->url,
        sha1($feed->url),
        $feed->link, 
        $feed->getTitle(),
        $feed->getSummary(),
        $feed->getLastBuildDate(),
        $feed->getNextUpdate($lastUpdated),
        $this->started);

      if (!$stageFeedStatement->execute())
      {
        echo "  Error staging feed: {$this->db->error}\n";

        // Continue with article staging anyway
      }

      $stageFeedStatement->close();

      $stageArticleStatement = $this->db->prepare("
             INSERT INTO staged_articles (feed_id,
                                          guid,
                                          link_url,
                                          title,
                                          author,
                                          summary,
                                          content,
                                          published,
                                          crawled,
                                          staged)
                  VALUES (?,?,?,?,?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),?)
                                       ");

      $stageArticleStatement->bind_param('issssssiii',
        $feedId,
        $articleGuid,
        $articleLinkUrl, 
        $articleTitle,
        $articleAuthor,
        $articleSummary,
        $articleText,
        $articlePublished,
        $this->started,
        $this->started);

      foreach ($feed->articles as $article)
      {
        $articleGuid = $article->guid;
        $articleLinkUrl = $article->link_url;

        // If a post has no GUID, use its link instead
        if (!$articleGuid)
          $articleGuid = $articleLinkUrl;

        if (!$articleGuid)
        {
          // Don't add posts without GUID's
          echo "  Error: Post titled '{$article->getTitle()}' has no GUID; skipping\n";
          continue;
        }
        
        $articleTitle = $article->getTitle();
        $articleAuthor = $article->getAuthor();
        $articleSummary = $article->getSummary();
        $articleText = $article->text;
        $articlePublished = $article->published;

        if (!$stageArticleStatement->execute())
        {
          echo "Staging error: {$this->db->error}\n";
          continue;
        }
      }

      $stageArticleStatement->close();

      $this->secondsSpentStaging += (microtime(true) - $now);
    }

    private function cleanUp()
    {
      $now = microtime(true);

      $stmt = $this->db->prepare("
                      DELETE 
                        FROM staged_articles
                       WHERE staged = ?
                                 ");

      $stmt->bind_param('i', $this->started);

      if (!$stmt->execute())
      {
        echo "Error cleaning up staged data: {$this->db->error}\n";
      }

      $stmt->close();

      $this->secondsSpentCleaningUp += (microtime(true) - $now);
    }

    private function write()
    {
      $now = microtime(true);

      // Update feeds

      $updateFeeds = $this->db->prepare("
                 UPDATE feeds f
             INNER JOIN staged_feeds sf ON sf.feed_hash = f.feed_hash
                    SET f.html_url = sf.html_url,
                        f.title = sf.title,
                        f.summary = sf.summary,
                        f.last_built = sf.last_built,
                        f.last_updated = sf.last_updated,
                        f.next_update = sf.next_update
                  WHERE staged = ?
                                        ");

      $updateFeeds->bind_param('i', $this->started);

      if (!$updateFeeds->execute())
      {
        echo "Staged data writing error (update feeds): {$this->db->error}\n";
      }
      else
      {
        // $this->updated = $updateFeeds->affected_rows;
      }

      $updateFeeds->close();

      // Update modified articles

      $updateStatement = $this->db->prepare("
                 UPDATE articles a
             INNER JOIN staged_articles sa ON sa.feed_id = a.feed_id AND sa.guid = a.guid
                    SET a.link_url = sa.link_url,
                        a.title = sa.title,
                        a.author = sa.author,
                        a.summary = sa.summary,
                        a.content = sa.content,
                        a.published = sa.published,
                        a.crawled = sa.crawled
                  WHERE sa.published != a.published
                        AND staged = ?
                                            ");

      $updateStatement->bind_param('i', $this->started);

      if (!$updateStatement->execute())
      {
        echo "Staged data writing error (update articles): {$this->db->error}\n";
      }
      else
      {
        $this->updated = $updateStatement->affected_rows;
      }

      $updateStatement->close();

      // Insert new articles

      $insertStatement = $this->db->prepare("
               INSERT INTO articles (feed_id,
                                     guid,
                                     link_url,
                                     title,
                                     author,
                                     summary,
                                     content,
                                     published,
                                     crawled)
                    SELECT sa.feed_id,
                           sa.guid,
                           sa.link_url,
                           sa.title,
                           sa.author,
                           sa.summary,
                           sa.content,
                           sa.published,
                           sa.crawled
                      FROM staged_articles sa
                 LEFT JOIN articles a ON a.feed_id = sa.feed_id AND a.guid = sa.guid
                     WHERE a.id IS NULL
                           AND staged = ?
                                            ");

      $insertStatement->bind_param('i', $this->started);

      if (!$insertStatement->execute())
      {
        echo "Staged data writing error (insert): {$this->db->error}\n";
      }
      else
      {
        $this->inserted = $insertStatement->affected_rows;
      }

      $insertStatement->close();

      // Personalize the articles

      $personalizeStatement = $this->db->prepare("
            INSERT INTO user_articles (user_id, article_id) 
                 SELECT u.id user_id, 
                        a.id article_id
                   FROM articles a 
             INNER JOIN users u 
             INNER JOIN feed_folders ff ON ff.feed_id = a.feed_id AND ff.user_id = u.id 
              LEFT JOIN user_articles ua ON ua.article_id = a.id AND ua.user_id = u.id 
                  WHERE ua.id IS NULL
                                            ");

      if (!$personalizeStatement->execute())
      {
        echo "Staged data writing error (personalize): {$this->db->error}\n";
      }
      else
      {
        $this->personalized = $personalizeStatement->affected_rows;
      }

      $personalizeStatement->close();

      // Update statistics

      $this->secondsSpentWriting = (microtime(true) - $now);
    }

    private function parseFeed($feedUrl)
    {
      $now = microtime(true);
      $parser = FeedParser::create($feedUrl);
      $this->secondsSpentDownloading += (microtime(true) - $now);

      $now = microtime(true);
      $articles = $parser->parse();
      $this->secondsSpentParsing += (microtime(true) - $now);

      return $articles;
    }

    public function crawl()
    {
      $this->started = time();
      $this->secondsSpentDownloading = 0;
      $this->secondsSpentParsing = 0;
      $this->secondsSpentStaging = 0;
      $this->secondsSpentWriting = 0;

      date_default_timezone_set(TIMEZONE);

      $this->db = new mysqli(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE);
      if ($this->db->connect_error)
        die('Connection error: '.mysqli_connect_error());

      if (!$this->db->set_charset("utf8"))
        die('Connection error: '.mysqli_connect_error());

      if (!$this->db->query("SET time_zone = '+0:00'"))
        die('Connection error: '.mysqli_connect_error());

      $query = "
       SELECT id,
              feed_url,
              UNIX_TIMESTAMP(last_built) last_built,
              UNIX_TIMESTAMP(last_updated) last_updated,
              UNIX_TIMESTAMP(next_update) next_update
         FROM feeds
               ";

      echo "Starting at ".date("m/d/Y H:i:s", $this->started)."\n";
      echo "Crawling and staging ...\n\n";

      if ($result = $this->db->query($query))
      {
        while ($feedRow = $result->fetch_object())
        {
          echo "- {$feedRow->feed_url}\n";

          if ($this->started < $feedRow->next_update)
          {
            echo "  (i) Skipping download until at least ".date("m/d/Y H:i:s", $feedRow->next_update)."\n";
            continue;
          }

          try
          {
            $feed = $this->parseFeed($feedRow->feed_url);
          }
          catch(Exception $e)
          {
            echo "  (e) error: {$e->getMessage()}\n";
            continue;
          }

          $feedLastBuilt = $feed->getLastBuildDate();
          if ($feedLastBuilt && $feedLastBuilt <= $feedRow->last_built)
          {
            echo "  (i) Skipping update - new feed older or unchanged\n";
            continue;
          }

          $this->stage($feed, $feedRow->id, $feedRow->last_updated);
        }

        $result->close();

        echo "Writing ...\n";

        $this->write();

        echo "Cleaning up ...\n";

        $this->cleanUp();
      }

      $this->db->close();

      echo "Done\n";
    }

    public function printStatistics()
    {
      echo sprintf("Inserted: %d\nUpdated: %d\nPersonalized: %d\n\nDownload: %.04fs\nParse: %.04fs\nStage: %.04fs\nWrite: %.04fs\nCleanup: %.04fs\n",
        $this->inserted, $this->updated, $this->personalized,
        $this->secondsSpentDownloading, $this->secondsSpentParsing, 
        $this->secondsSpentStaging, $this->secondsSpentWriting,
        $this->secondsSpentCleaningUp);
    }
  }

  $crawler = new Crawler();

  $crawler->crawl();
  $crawler->printStatistics();
?>
