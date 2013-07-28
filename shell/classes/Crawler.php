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

require('FeedParser.php');

class Crawler
{
  private $db;
  private $started;
  private $stageId;
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
                                     stage_id)
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
      $this->stageId);

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
                                        stage_id)
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
      $this->stageId);

    foreach ($feed->articles as $article)
    {
      $articleGuid = $article->guid;
      $articleLinkUrl = $article->link_url;
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
                      FROM stages
                     WHERE id = ?
                               ");

    $stmt->bind_param('i', $this->stageId);

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
                WHERE stage_id = ?
                                      ");

    $updateFeeds->bind_param('i', $this->stageId);

    if (!$updateFeeds->execute())
    {
      echo "Staged data writing error (update feeds): {$this->db->error}\n";
    }
    else
    {
      // $this->updated = $updateFeeds->affected_rows;
    }

    $updateFeeds->close();

    // Update links

    $stmt = $this->db->prepare("
          INSERT INTO feed_links (feed_id, url, url_hash)
               SELECT f.id,
                      f.html_url,
                      sha1(f.html_url)
                 FROM feeds f 
           INNER JOIN staged_feeds sf ON sf.feed_hash = f.feed_hash
            LEFT JOIN feed_links fl ON fl.feed_id = f.id AND fl.url = f.html_url
                WHERE stage_id = ? AND fl.id IS NULL
                               ");

    $stmt->bind_param('i', $this->stageId);

    if (!$stmt->execute())
      echo "Error updating links: {$this->db->error}\n";

    $stmt->close();

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
                      AND stage_id = ?
                                          ");

    $updateStatement->bind_param('i', $this->stageId);

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
                         AND stage_id = ?
                                          ");

    $insertStatement->bind_param('i', $this->stageId);

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
          INSERT INTO user_articles (user_id, article_id, feed_id) 
               SELECT u.id, 
                      a.id,
                      a.feed_id
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

    if (!$parser)   
      throw new Exception("Could not determine type of feed from content");
    
    $now = microtime(true);

    echo "  (-) parsing (".get_class($parser).") ... ";

    $feed = $parser->parse();
    $secondsSpentParsingFeed = (microtime(true) - $now);
    $this->secondsSpentParsing += $secondsSpentParsingFeed;

    echo sprintf("done! (%.04fs)\n", $secondsSpentParsingFeed);

    return $feed;
  }

  public function crawl()
  {
    // Initialize crawler

    $this->started = time();
    $this->stageId = false;
    $this->secondsSpentDownloading = 0;
    $this->secondsSpentParsing = 0;
    $this->secondsSpentStaging = 0;
    $this->secondsSpentWriting = 0;

    date_default_timezone_set(TIMEZONE);

    // Initialize DB

    $this->db = new mysqli(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT, MYSQL_SOCKET);
    if ($this->db->connect_error)
      die('Connection error: '.mysqli_connect_error());

    if (!$this->db->set_charset("utf8"))
      die('Connection error: '.mysqli_connect_error());

    if (!$this->db->query("SET time_zone = '+0:00'"))
      die('Connection error: '.mysqli_connect_error());

    // Initialize staging

    $stmt = $this->db->prepare("
        INSERT INTO stages(user_id, started)
             VALUES (NULL, FROM_UNIXTIME(?))
                               ");

    $stmt->bind_param('i', $this->started);

    if ($stmt->execute())
      $this->stageId = $this->db->insert_id;

    $stmt->close();

    if (!$this->stageId)
      die("Unable to initiate staging: $this->db->error");

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

?>
