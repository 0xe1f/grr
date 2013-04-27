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

require('Core.php');

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

    echo "  (-) parsing (".get_class($parser).") ... ";

    $articles = $parser->parse();
    $secondsSpentParsingFeed = (microtime(true) - $now);
    $this->secondsSpentParsing += $secondsSpentParsingFeed;

    echo sprintf("done! (%.04fs)\n", $secondsSpentParsingFeed);

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

?>
