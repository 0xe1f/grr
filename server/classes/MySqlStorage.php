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

require("models/Subscription.php");
require("models/WelcomeToken.php");

class MySqlStorage extends Storage
{
  // FIXME: there are no unique constraints on users(open_id_identity)
  // This would make it possible to add two accounts with the same openId
  
  private $db;

  public function __construct()
  {
    $this->db = new mysqli(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT, MYSQL_SOCKET);
    if ($this->db->connect_error)
      die('Connection error: '.mysqli_connect_error());

    if (!$this->db->set_charset("utf8"))
      die('Connection error: '.mysqli_connect_error());

    if (!$this->db->query("SET time_zone = '+0:00'"))
      die('Connection error: '.mysqli_connect_error());
  }

  private function pruneSubscriptions(&$subscriptions)
  {
    $unread = 0;

    foreach ($subscriptions->subs as &$subfeed)
    {
      $unread += $subfeed->unread;

      unset($subfeed->parentId);

      if (count($subfeed->subs) < 1)
      {
        unset($subfeed->subs);
        continue;
      }

      $unread += $this->pruneSubscriptions($subfeed);
    }

    $subscriptions->unread = $unread;

    return $unread;
  }

  private function stageFeedImportLevel($userId, $feeds, $selection, $stageId)
  {
    $stmt = $this->db->prepare("
        INSERT INTO staged_feeds(user_id, feed_hash, feed_url, html_url, title, last_updated, stage_id)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)
                         ");

    $stmt->bind_param('issssi', $userId,
                                $feedUrlHash,
                                $feedUrl,
                                $htmlUrl,
                                $feedTitle,
                                $stageId);

    // TODO: should there be an upper bound on the number of feeds in a group?
    //       probably.

    $success = true;

    foreach ($feeds as $feed)
    {
      if (!in_array($feed["id"], $selection))
        continue; // If a group is not selected, skip along with any children

      if (isset($feed["xmlUrl"]) && isset($feed["htmlUrl"]))
      {
        $feedUrl = $feed["xmlUrl"];
        $feedUrlHash = sha1($feedUrl);
        $htmlUrl = $feed["htmlUrl"];
        $feedTitle = $feed["title"];

        if (!$stmt->execute())
        {
          $success = false;
          break;
        } 
      }
      
      if (isset($feed["children"]))
      {
        if (!$this->stageFeedImportLevel($userId, $feed["children"], $selection, $stageId))
        {
          $success = false;
          break;
        }
      }
    }

    $stmt->close();

    return $success;
  }

  private function addToMyFeedsLevel($userId, $feeds, $selection, $parentFolderId)
  {
    $feedsAdded = 0;

    $insertFeedStatement = $this->db->prepare("
      INSERT INTO feed_folders (user_id, feed_id, title) 
                        SELECT ?,
                               f.id,
                               ?
                          FROM feeds f
                     LEFT JOIN feed_folders ff ON ff.feed_id = f.id AND ff.user_id = ?
                         WHERE f.feed_hash = ?
                               AND ff.feed_id IS NULL
                                        ");

    $insertFeedStatement->bind_param('isis', $userId, $feedTitleParam, $userId, $feedHashParam);

    $insertFeedFolderStatement = $this->db->prepare("
              INSERT INTO feed_folders (user_id, title)
                                SELECT ?, ?
                                              ");

    $insertFeedFolderStatement->bind_param('is', $userId, $feedTitleParam);

    $insertFeedFolderTreeStatement = $this->db->prepare("
      INSERT INTO feed_folder_trees (ancestor_id, descendant_id, distance)
           SELECT ancestor_id,
                  ?,
                  distance + 1
             FROM feed_folder_trees
            WHERE descendant_id = ?
            UNION ALL
           SELECT ?, ?, 0
                         ");

    $insertFeedFolderTreeStatement->bind_param('iiii', $feedFolderIdParam, $parentFolderIdParam, 
      $feedFolderIdParam, $feedFolderIdParam);

    $success = true;

    foreach ($feeds as $feed)
    {
      if (!in_array($feed["id"], $selection))
        continue; // If a group is not selected, skip along with any children

      if (empty($feed["title"]))
        continue; // Missing a title

      $feedTitleParam = $feed["title"];

      // Add to list of user's feeds

      if (isset($feed["xmlUrl"]))
      {
        // An RSS/Atom feed

        $feedHashParam = sha1($feed["xmlUrl"]);

        if (!$insertFeedStatement->execute())
        {
          $success = false;
          break;
        }

        $affected = $this->db->affected_rows;
        if ($affected < 1)
          continue;

        $feedFolderId = $this->db->insert_id;
        $feedsAdded += $affected;
      }
      else
      {
        // A group. See if there's an existing one

        $feedFolderId = $this->getFeedFolderId($userId, $parentFolderId, $feed["title"]);
        if ($feedFolderId === false)
        {
          if (!$insertFeedFolderStatement->execute())
          {
            $success = false;
            break;
          }
 
          $feedFolderId = $this->db->insert_id;
        }
      }

      // Set up the tree relationship

      $feedFolderIdParam = $feedFolderId;
      $parentFolderIdParam = $parentFolderId;

      if (!$insertFeedFolderTreeStatement->execute())
      {
        // For now, assume the error is due to tree information already being
        // present
      }

      // Look into any child nodes

      if (isset($feed["children"]))
      {
        $result = $this->addToMyFeedsLevel($userId, $feed["children"], $selection, $feedFolderId);
        if ($result === false)
        {
          $success = false;
          break;
        }

        $feedsAdded += $result;
      }
    }

    $insertFeedStatement->close();
    $insertFeedFolderStatement->close();
    $insertFeedFolderTreeStatement->close();

    return $success ? $feedsAdded : $success;
  }

  // Feeds, etc.

  public function getUserFeeds($user, $restrictToFolderId = null)
  {
    if (!$user)
      return false;

    $stmt = $this->db->prepare("
             SELECT ff.id, 
                    ff.feed_id,
                    fft.ancestor_id,
                    ff.title source,
                    f.html_url link,
                    SUM(ua.is_unread) unread_count
               FROM feed_folders ff
         INNER JOIN feed_folder_trees fft ON fft.descendant_id = ff.id
          LEFT JOIN articles a ON a.feed_id = ff.feed_id
          LEFT JOIN user_articles ua ON ua.article_id = a.id AND ua.user_id = ff.user_id
          LEFT JOIN feeds f ON f.id = ff.feed_id
              WHERE ff.user_id = ? AND fft.distance = 1
           GROUP BY ff.id, fft.ancestor_id, ff.title
           ORDER BY ff.title
                         ");

    $stmt->bind_param('i', $user->id);

    $subscriptionMap = array();

    if ($stmt->execute())
    {
      $stmt->bind_result($userFeedId, $feedId, $ancestorId, 
        $feedTitle, $feedLink, $unreadCount);

      while ($stmt->fetch())
      {
        $subscription = new Subscription();
        $subscription->id = (int)$userFeedId;
        $subscription->title = $feedTitle;
        $subscription->type = $feedId ? "feed" : "folder";
        $subscription->unread = (int)$unreadCount;
        $subscription->parentId = (int)$ancestorId;

        if ($feedLink)
          $subscription->link = $feedLink;

        $subscriptionMap[$userFeedId] = $subscription;
      }
    }

    $stmt->close();

    $matchingFeed = null;
    if ($restrictToFolderId !== null)
      $matchingFeed = &$subscriptionMap[$restrictToFolderId];

    $movedItems = array();
    foreach ($subscriptionMap as $subscriptionId => &$subscription)
    {
      $parentId = $subscription->parentId;
      $subfeeds = &$subscriptionMap[$parentId]->subs;

      $subfeeds[] = &$subscription;
      $movedItems[] = $subscription->id;
    }

    foreach ($movedItems as $movedItem)
      unset($subscriptionMap[$movedItem]);

    if (empty($subscriptionMap))
    {
      $subscriptions = new Subscription();
      $rootId = $this->getUserFeedRoot($user->id);
    }
    else
    {
      $subscriptions = reset($subscriptionMap);
      $rootId = key($subscriptionMap);
      
      $this->pruneSubscriptions($subscriptions);
    }

    $subscriptions->id = $rootId;
    $subscriptions->type = "root";

    if ($matchingFeed != null && $restrictToFolderId != $rootId)
      $subscriptions = $matchingFeed;

    return $subscriptions;
  }

  public function markAllAs($user, $feedFolderId, $filter, $isUnread)
  {
    if (!$user)
      return false;

    $isUnread = $isUnread ? 1 : 0;
    
    switch ($filter)
    {
    case "new":
      $filterClause = " AND ua.is_unread = 1";
      break;
    case "star":
      $filterClause = " AND ua.is_starred = 1";
      break;
    default:
      $filterClause = "";
    }

    $stmt = $this->db->prepare("
                UPDATE user_articles ua
            INNER JOIN articles a ON a.id = ua.article_id
            INNER JOIN feeds f ON f.id = a.feed_id
            INNER JOIN feed_folders ff ON ff.feed_id = f.id
            INNER JOIN feed_folder_trees fft ON fft.descendant_id = ff.id
                   SET ua.is_unread = ?
                 WHERE fft.ancestor_id = ? 
                       AND ua.user_id = ?
                       {$filterClause}
                         ");

    $stmt->bind_param('iii', $isUnread, $feedFolderId, $user->id);

    $affectedRows = false;
    if ($stmt->execute())
      $affectedRows = $this->db->affected_rows;

    $stmt->close();

    return ($affectedRows !== false);
  }

  public function addFeedFolder($userId, $feedId = null, $title = null, $parent = null)
  {
    // TODO: No check is currently performed to make sure a folder isn't added under
    // a feed. May want to perform such a check in the future

    $this->db->autocommit(false);

    // Add the folder itself

    $stmt = $this->db->prepare("
      INSERT INTO feed_folders (user_id, feed_id, title) 
                        VALUES (?, ?, ?)");

    $stmt->bind_param('iis', $userId, $feedId, $title);

    $feedFolderId = false;
    if ($stmt->execute())
      $feedFolderId = $this->db->insert_id;

    $stmt->close();

    if ($feedFolderId === false)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Add tree relationships

    $stmt = $this->db->prepare("
      INSERT INTO feed_folder_trees (ancestor_id, descendant_id, distance)
           SELECT ancestor_id,
                  ?,
                  distance + 1
             FROM feed_folder_trees
            WHERE descendant_id = ?
            UNION ALL 
           SELECT ?, ?, 0
                         ");

    $stmt->bind_param('iiii', $feedFolderId, $parent, $feedFolderId, $feedFolderId);

    $success = $stmt->execute();
    $stmt->close();

    if (!$success)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Commit the transaction

    $this->db->commit();
    $this->db->autocommit(true);

    // Return the identifier of the new folder

    return $feedFolderId;
  }

  public function renameSubscription($userId, $feedFolderId, $newName)
  {
    $rootFolderId = $this->getUserFeedRoot($userId);
    if ($rootFolderId === false || $rootFolderId == $feedFolderId)
      return false; // Don't remove the root folder

    $stmt = $this->db->prepare("
          UPDATE feed_folders
             SET title = ?
           WHERE id = ? AND user_id = ?
                               ");

    $stmt->bind_param('sii', $newName, $feedFolderId, $userId);

    $success = $stmt->execute() && $this->db->affected_rows > 0;

    $stmt->close();

    return $success;
  }

  public function unsubscribe($userId, $feedFolderId)
  {
    $rootFolderId = $this->getUserFeedRoot($userId);
    if ($rootFolderId === false || $rootFolderId == $feedFolderId)
      return false; // Don't remove the root folder

    $stmt = $this->db->prepare("
          DELETE 
            FROM feed_folders 
           WHERE user_id = ?
                 AND id IN (SELECT descendant_id 
                              FROM feed_folder_trees 
                             WHERE ancestor_id = ?)
                               ");

    $stmt->bind_param('ii', $userId, $feedFolderId);

    $success = $stmt->execute() && $this->db->affected_rows > 0;

    $stmt->close();

    return $success;
  }

  public function importFeed($userId, $feed)
  {
    // Initiate staging

    $stageId = $this->beginStaging($userId);
    if (!$stageId)
      return false;

    // Stage the feed

    $stmt = $this->db->prepare("
        INSERT INTO staged_feeds(user_id, feed_hash, feed_url, html_url, title, last_updated, stage_id)
             VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)
                         ");

    $stmt->bind_param('issssii', $userId,
                                 $feedUrlHash,
                                 $feedUrl,
                                 $htmlUrl,
                                 $feedTitle,
                                 $crawled,
                                 $stageId);

    $feedId = false;
    $success = true;

    $feedUrl = $feed->url;
    $feedUrlHash = sha1($feedUrl);
    $htmlUrl = $feed->link;
    $feedTitle = $feed->title;
    $crawled = time();

    $success = $stmt->execute();

    $stmt->close();

    if ($success)
    {
      // Import the feed

      $stmt = $this->db->prepare("
          INSERT INTO feeds(feed_url, html_url, feed_hash, title, added, last_updated)
               SELECT sf.feed_url,
                      sf.html_url,
                      sf.feed_hash,
                      sf.title,
                      UTC_TIMESTAMP(),
                      sf.last_updated
                 FROM staged_feeds sf
            LEFT JOIN feeds f ON f.feed_hash = sf.feed_hash
                WHERE f.id IS NULL 
                      AND sf.user_id = ? 
                      AND sf.stage_id = ?
                                 ");

      $stmt->bind_param('ii', $userId, $stageId);

      if ($stmt->execute())
      {
        $feedId = $this->db->insert_id;
        if (!$feedId) // Recover from possible race condition
          $feedId = $this->getFeedId($feed->url);
      }

      $stmt->close();

      if ($feedId !== false)
      {
        // Stage the articles

        $stmt = $this->db->prepare("
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

        $stmt->bind_param('issssssiii',
          $feedId,
          $articleGuid,
          $articleLinkUrl, 
          $articleTitle,
          $articleAuthor,
          $articleSummary,
          $articleText,
          $articlePublished,
          $crawled,
          $stageId);

        foreach ($feed->articles as $article)
        {
          $articleGuid = $article->guid;
          $articleLinkUrl = $article->link_url;
          $articleTitle = $article->getTitle();
          $articleAuthor = $article->getAuthor();
          $articleSummary = $article->getSummary();
          $articleText = $article->text;
          $articlePublished = $article->published;

          if (!$stmt->execute())
            continue;
        }

        $stmt->close();

        // Write the articles

        $stmt = $this->db->prepare("
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
                   LEFT JOIN articles a ON a.feed_id = sa.feed_id 
                             AND a.guid = sa.guid
                       WHERE a.id IS NULL
                             AND stage_id = ?
                                   ");

        // It's fine if unstaging fails - the crawler should catch it later

        $stmt->bind_param('i', $stageId);
        $stmt->execute();

        $stmt->close();

        // Personalize feed contents

        $stmt = $this->db->prepare("
              INSERT INTO user_articles (user_id, article_id) 
                   SELECT u.id user_id, 
                          a.id article_id
                     FROM articles a 
               INNER JOIN users u ON u.id = ?
                LEFT JOIN user_articles ua ON ua.article_id = a.id AND ua.user_id = u.id
                    WHERE a.feed_id = ?
                          AND ua.id IS NULL
                                   ");

        $stmt->bind_param('ii', $userId, $feedId);

        if (!$stmt->execute())
          $feedId = false;

        $stmt->close();
      }
    }

    return $feedId;
  }

  public function importFeeds($userId, $feeds, $selection)
  {
    $stageId = $this->beginStaging($userId);
    if (!$stageId)
      return false;

    $userFeedRoot = $this->getUserFeedRoot($userId);
    if ($userFeedRoot === false)
      return false;

    $this->db->autocommit(false);

    // Add to staging table

    if (!$this->stageFeedImportLevel($userId, $feeds, $selection, $stageId))
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Import from the staging table into the master feed table

    $stmt = $this->db->prepare("
        INSERT INTO feeds(feed_url, html_url, feed_hash, title, added, last_updated)
             SELECT sf.feed_url,
                    sf.html_url,
                    sf.feed_hash,
                    sf.title,
                    UTC_TIMESTAMP(),
                    sf.last_updated
               FROM staged_feeds sf
          LEFT JOIN feeds f ON f.feed_hash = sf.feed_hash
              WHERE f.id IS NULL 
                    AND sf.user_id = ? 
                    AND sf.stage_id = ?
                         ");

    $stmt->bind_param('ii', $userId, $stageId);

    $success = $stmt->execute();

    $stmt->close();

    if (!$success)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Add to user's list of feeds

    $added = $this->addToMyFeedsLevel($userId, $feeds, $selection, $userFeedRoot);

    if ($added === false)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    $this->db->commit();
    $this->db->autocommit(true);

    return $added;
  }
  
  public function getUserFeedRoot($userId)
  {
    // FIXME: Figure out a more sure-fire way to get the root folder
    //        e.g. by looking at the actual tree

    $stmt = $this->db->prepare("
             SELECT ff.id
               FROM feed_folders ff
              WHERE ff.user_id = ? AND title IS NULL
                         ");

    $stmt->bind_param('i', $userId);

    $rootFolderId = false;

    if ($stmt->execute())
    {
      $stmt->bind_result($rootFolderId);

      if (!$stmt->fetch())
        $rootFolderId = false;
    }

    $stmt->close();

    return $rootFolderId;
  }

  public function getFeedFolderId($userId, $parentId, $title)
  {
    $stmt = $this->db->prepare("
             SELECT ff.id
               FROM feed_folders ff
         INNER JOIN feed_folder_trees fft ON fft.descendant_id = ff.id AND fft.distance = 1
              WHERE ff.user_id = ? AND fft.ancestor_id = ? AND title = ?
                         ");

    $stmt->bind_param('iis', $userId, $parentId, $title);

    $folderId = false;

    if ($stmt->execute())
    {
      $stmt->bind_result($folderId);

      if (!$stmt->fetch())
        $folderId = false;
    }

    return $folderId;
  }

  public function getFeed($feedOrHomeUrl)
  {
    $stmt = $this->db->prepare("
             SELECT id,
                    title,
                    feed_url,
                    html_url
               FROM feeds 
              WHERE feed_url = ?
                    OR html_url = ?
                         ");

    $stmt->bind_param('ss', $feedOrHomeUrl, $feedOrHomeUrl);

    $feed = null;

    if ($stmt->execute())
    {
      $stmt->bind_result($feedId, $feedTitle, $feedUrl, $feedLink);
      if ($stmt->fetch())
      {
        $feed = new Feed();
        $feed->url = $feedUrl;
        $feed->link = $feedLink;
        $feed->title = $feedTitle;
        $feed->id = $feedId;
      }
    }

    return $feed;
  }

  public function findFeedFromLinks($url)
  {
    $stmt = $this->db->prepare("
             SELECT f.id,
                    f.title,
                    f.feed_url,
                    f.html_url
               FROM feed_links fl
         INNER JOIN feeds f ON f.id = fl.feed_id
               WHERE url_hash = ? AND url = ?
                         ");

    $stmt->bind_param('ss', $urlHash, $url);

    $url = trim($url);
    $urlHash = sha1($url);

    $matches = array();

    if ($stmt->execute())
    {
      $stmt->bind_result($feedId, $feedTitle, $feedUrl, $feedLink);

      while ($stmt->fetch())
      {
        $feed = new Feed();

        $feed->url = $feedUrl;
        $feed->link = $feedLink;
        $feed->title = $feedTitle;
        $feed->id = $feedId;

        $matches[] = $feed;
      }
    }

    return $matches;
  }

  public function getFeedId($feedOrHomeUrl)
  {
    $feed = $this->getFeed($feedOrHomeUrl);
    if (!$feed)
      return false;

    return $feed->id;
  }

  public function subscribeToFeed($userId, $feedId, $parentFeedFolderId = null)
  {
    // FIXME: verify that the parent folder belongs to the same user
    
    if ($parentFeedFolderId == null) // 'All items' by default
      $parentFeedFolderId = $this->getUserFeedRoot($userId);

    if (!$parentFeedFolderId)
      return false;

    $stmt = $this->db->prepare("
      INSERT INTO feed_folders (user_id, feed_id, title) 
                        SELECT ?,
                               f.id,
                               f.title
                          FROM feeds f
                     LEFT JOIN feed_folders ff ON ff.feed_id = f.id AND ff.user_id = ?
                         WHERE f.id = ?
                               AND ff.feed_id IS NULL
                               ");

    $stmt->bind_param('iis', $userId, $userId, $feedId);

    $success = $stmt->execute();
    $feedFolderId = $this->db->insert_id;

    $stmt->close();

    if (!$success)
      return false;

    if (!$feedFolderId)
      return true; // Nothing was added - likely already subscribed

    // Set up the tree relationship

    $stmt = $this->db->prepare("
      INSERT INTO feed_folder_trees (ancestor_id, descendant_id, distance)
           SELECT ancestor_id,
                  ?,
                  distance + 1
             FROM feed_folder_trees
            WHERE descendant_id = ?
            UNION ALL
           SELECT ?, ?, 0
                               ");

    $stmt->bind_param('iiii', $feedFolderId, $parentFeedFolderId, 
      $feedFolderId, $feedFolderId);

    $success = $stmt->execute();

    $stmt->close();

    if ($success)
    {
      // Personalize any existing articles

      $stmt = $this->db->prepare("
            INSERT INTO user_articles (user_id, article_id) 
                 SELECT ?, 
                        a.id article_id
                   FROM articles a 
             INNER JOIN feeds f ON f.id = a.feed_id
              LEFT JOIN user_articles ua ON ua.article_id = a.id AND ua.user_id = ?
                  WHERE ua.id IS NULL
                                 ");

      $stmt->bind_param('ii', $userId, $userId);

      $success = $stmt->execute();
    }

    return $success;
  }

  public function addLinks($feedId, $links)
  {
    $success = true;

    $stmt = $this->db->prepare("
        INSERT INTO feed_links (feed_id, url, url_hash)
             SELECT f.id,
                    ?,
                    ?
               FROM feeds f
          LEFT JOIN feed_links fl ON fl.feed_id = f.id AND fl.url_hash = ?
              WHERE f.id = ? AND fl.id IS NULL
                               ");

    $stmt->bind_param('sssi', $url, $urlHash, $urlHash, $feedId);

    foreach ($links as $link)
    {
      $url = $link;
      $urlHash = sha1($url);

      if (!$stmt->execute())
        $success = false;
    }

    $stmt->close();

    return $success;
  }

  // Welcome Tokens

  public function getActiveWelcomeTokens()
  {
    $stmt = $this->db->prepare("
                SELECT wt.id,
                       token_hash token,
                       wt.description,
                       u.id creator_id,
                       u.username creator_username,
                       UNIX_TIMESTAMP(created) created
                  FROM welcome_tokens wt
            INNER JOIN users u ON u.id = wt.created_by_user_id
                 WHERE wt.created BETWEEN UTC_TIMESTAMP() - INTERVAL 14 DAY AND UTC_TIMESTAMP()
                       AND wt.claimed IS NULL
                       AND wt.revoked IS NULL
              ORDER BY wt.created DESC 
                               ");

    $tokens = array();

    if ($stmt->execute())
    {
      $stmt->bind_result($tokenId, $tokenHash, 
        $description, $createdById, $createdByUsername, $createdOn);

      while ($stmt->fetch())
      {
        $token = new WelcomeToken();
        $token->id = $tokenId;
        $token->hash = $tokenHash;
        $token->description = $description;
        $token->createdById = $createdById;
        $token->createdByUsername = $createdByUsername;
        $token->createdOnStamp = $createdOn;

        $tokens[] = $token;
      }
    }

    $stmt->close();

    return $tokens;
  }

  public function findActiveWelcomeToken($tokenHash)
  {
    $stmt = $this->db->prepare("
                SELECT wt.id,
                       token_hash token,
                       wt.description,
                       u.id creator_id,
                       u.username creator_username,
                       UNIX_TIMESTAMP(created) created
                  FROM welcome_tokens wt
            INNER JOIN users u ON u.id = wt.created_by_user_id
                 WHERE wt.token_hash = ?
                       AND wt.created BETWEEN UTC_TIMESTAMP() - INTERVAL 14 DAY AND UTC_TIMESTAMP()
                       AND wt.claimed IS NULL
                       AND wt.revoked IS NULL
                               ");

    $stmt->bind_param('s', $tokenHash);

    $token = false;

    if ($stmt->execute())
    {
      $stmt->bind_result($tokenId, $tokenHash, 
        $description, $createdById, $createdByUsername, $createdOn);

      if ($stmt->fetch())
      {
        $token = new WelcomeToken();
        $token->id = $tokenId;
        $token->hash = $tokenHash;
        $token->description = $description;
        $token->createdById = $createdById;
        $token->createdByUsername = $createdByUsername;
        $token->createdOnStamp = $createdOn;
      }
    }

    $stmt->close();

    return $token;
  }

  public function addWelcomeToken($tokenHash, $description, $createdBy)
  {
    if (!$createdBy)
      return false;

    $stmt = $this->db->prepare("
       INSERT INTO welcome_tokens (token_hash,
                                   description,
                                   created_by_user_id,
                                   created)
                           VALUES (?, ?, ?, UTC_TIMESTAMP())
                               ");

    $stmt->bind_param('ssi', $tokenHash, $description, $createdBy->id);

    $affectedRows = false;
    if ($stmt->execute())
      $affectedRows = $this->db->affected_rows;

    $stmt->close();

    $this->db->commit();

    return $affectedRows > 0;
  }

  // User Accounts

  public function getAllUserAccounts()
  {
    $stmt = $this->db->prepare("
                SELECT u.id user_id,
                       username,
                       email_address,
                       open_id_identity,
                       r.name role_name
                  FROM users u
            INNER JOIN roles r ON r.id = u.role_id
                         ");

    $users = array();

    if ($stmt->execute())
    {
      $stmt->bind_result($userId, $username, $emailAddress, $openIdIdentity, $roleName);

      while ($stmt->fetch())
      {
        $users[] = array(
          "id"           => $userId,
          "username"     => $username,
          "emailAddress" => $emailAddress,
          "roleName"     => $roleName,
        );
      }
    }

    $stmt->close();

    return $users;
  }

  public function findUserWithOpenId($openIdIdentity)
  {
    if (strlen($openIdIdentity) < 1)
      return false;

    $user = false; // Error by default
    
    $stmt = $this->db->prepare("
             SELECT u.id,
                    u.username,
                    r.id,
                    r.code
               FROM users u
         INNER JOIN roles r ON r.id = u.role_id
              WHERE open_id_identity = ?
                         ");

    $stmt->bind_param('s', $openIdIdentity);

    if ($stmt->execute())
    {
      $stmt->bind_result($userId, $username, $roleId, $roleCode);

      if ($stmt->fetch())
      {
        $user = new User();

        $user->id = $userId;
        $user->username = $username;
        $user->role = $roleCode;
        $user->roleId = $roleId;
      }
    }
    
    $stmt->close();

    return $user;
  }

  public function findUserWithUsername($username, &$password = null)
  {
    if (strlen($username) < 1)
      return false;

    $user = false; // Error by default
    
    $stmt = $this->db->prepare("
             SELECT u.id,
                    u.username,
                    u.password,
                    r.id,
                    r.code
               FROM users u
         INNER JOIN roles r ON r.id = u.role_id
              WHERE username = ?
                         ");

    $stmt->bind_param('s', $username);

    if ($stmt->execute())
    {
      $stmt->bind_result($userId, $username, $password, $roleId, $roleCode);

      if ($stmt->fetch())
      {
        $user = new User();

        $user->id = $userId;
        $user->username = $username;
        $user->role = $roleCode;
        $user->roleId = $roleId;
      }
    }
    
    $stmt->close();

    return $user;
  }

  public function getAdminCount()
  {
    $stmt = $this->db->prepare("
             SELECT COUNT(*) user_count
               FROM users u
         INNER JOIN roles r ON r.id = u.role_id
              WHERE r.code = ?
                         ");

    $userCount = false;
    $role = ROLE_ADMIN;

    $stmt->bind_param('s', $role);

    if ($stmt->execute())
    {
      $stmt->bind_result($userCount);

      if (!$stmt->fetch())
        $userCount = false;
    }
    
    $stmt->close();

    return $userCount;
  }

  public function getUserWithSessionHash($sessionHash, $receivedVHash)
  {
    if (!$sessionHash)
      return false;

    $stmt = $this->db->prepare("
             SELECT s.id session_id,
                    u.id user_id,
                    r.code role,
                    username
               FROM sessions s
         INNER JOIN users u ON s.user_id = u.id
         INNER JOIN roles r ON r.id = u.role_id
              WHERE hash = ? AND voided IS NULL
                               ");

    $stmt->bind_param('s', $sessionHash);

    $user = false; // Error by default

    if ($stmt->execute())
    {
      $stmt->bind_result($sessionId, $userId, $role, $username);

      while ($stmt->fetch())
      {
        $computedVHash = hash('md5', hash("md5", sprintf("%s,%s,%s",
          SALT_VHASH, $username, $sessionId)));

        if ($computedVHash == $receivedVHash)
        {
          $user = new User();
          $user->id = $userId;
          $user->role = $role;
          $user->username = $username;
          $user->sessionId = $sessionId;

          break;
        }
      }
    }
    
    $stmt->close();

    return $user;
  }

  public function createUser($username, $password, $openIdIdentity, $emailAddress, $welcomeTokenId, $roleId)
  {
    if (strlen($username) < 1)
      return false;

    if ($password)
    {
      if (strlen(trim($password)) < 1)
        return false;
    }

    if ($openIdIdentity)
    {
      // DB column size restriction
      if (strlen($openIdIdentity) < 1 || strlen($openIdIdentity) >= 512)
        return false;
    }

    // One or the other is required
    if (!$password && !$openIdIdentity)
      return false;

    $this->db->autocommit(false);

    // Create user

    $stmt = $this->db->prepare("
         INSERT INTO users (username, password, email_address, open_id_identity, welcome_token_id, role_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                         ");

    $stmt->bind_param('ssssii', $username, $password, $emailAddress, $openIdIdentity, $welcomeTokenId, $roleId);

    $userId = false;
    if ($stmt->execute())
      $userId = $this->db->insert_id;

    $stmt->close();

    if ($userId === false)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Add the 'All Items' root

    $stmt = $this->db->prepare("
      INSERT INTO feed_folders (user_id) 
                        VALUES (?)
                               ");

    $stmt->bind_param('i', $userId);

    $feedFolderId = false;
    if ($stmt->execute())
      $feedFolderId = $this->db->insert_id;

    $stmt->close();

    if ($feedFolderId === false)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Add tree relationship

    $stmt = $this->db->prepare("
      INSERT INTO feed_folder_trees (ancestor_id, descendant_id, distance)
                             VALUES (?, ?, 0)
                         ");

    $stmt->bind_param('ii', $feedFolderId, $feedFolderId);

    $success = $stmt->execute();
    $stmt->close();

    if (!$success)
    {
      $this->db->rollback();
      $this->db->autocommit(true);

      return false;
    }

    // Disable the token

    if ($welcomeTokenId)
    {
      $stmt = $this->db->prepare("
                          UPDATE welcome_tokens 
                             SET claimed = UTC_TIMESTAMP()
                           WHERE id = ?
                                 ");

      $stmt->bind_param('i', $welcomeTokenId);

      $success = $stmt->execute();
      $stmt->close();

      if (!$success)
      {
        $this->db->rollback();
        $this->db->autocommit(true);

        return false;
      }
    }

    // Commit the transaction

    $this->db->commit();
    $this->db->autocommit(true);

    // Create a new user

    $user = new User();

    $user->id = $userId;
    $user->username = $username;
    $user->role = $roleCode;
    $user->roleId = $roleId;

    return $user;
  }

  public function getRoleId($roleCode)
  {
    if (strlen($roleCode) < 1)
      return false;

    $roleId = false;
    
    $stmt = $this->db->prepare("
             SELECT id
               FROM roles
              WHERE code = ?
                         ");

    $stmt->bind_param('s', $roleCode);

    if ($stmt->execute())
    {
      $stmt->bind_result($roleId);
      if (!$stmt->fetch())
        $roleId = false;
    }
    
    $stmt->close();

    return $roleId;
  }

  public function createSession($userId, $hash, $remoteAddress)
  {
    // Create session

    $sessionId = false;
    $now = time();
    
    $stmt = $this->db->prepare("
        INSERT INTO sessions (user_id, hash, source_ip, created)
             VALUES (?, ?, ?, FROM_UNIXTIME(?))
                               ");

    $stmt->bind_param('issi', $userId, $hash, $remoteAddress, $now);

    if ($stmt->execute())
      $sessionId = $this->db->insert_id;

    $stmt->close();

    return $sessionId;
  }

  public function voidSession($userId, $sessionId)
  {
    $now = time();

    $stmt = $this->db->prepare("
        UPDATE sessions
           SET voided = FROM_UNIXTIME(?)
         WHERE id = ? AND user_id = ?
                               ");

    $stmt->bind_param('iii', $now, $sessionId, $userId);

    $success = $stmt->execute();

    $stmt->close();

    return $success;
  }

  public function reportFailedLogin($userId, $remoteAddress)
  {
    $now = time();

    $stmt = $this->db->prepare("
                INSERT INTO failed_logins (source_ip, attempt_time, user_id) 
                     VALUES (?, FROM_UNIXTIME(?), ?) 
                               ");

    $stmt->bind_param('sii', $remoteAddress, $now, $userId);

    $success = $stmt->execute();

    $stmt->close();

    return $success;
  }

  public function getFailedLoginStatistics($remoteAddress, $timeWindowInSeconds)
  {
    $failedLogin = null;
    $now = time();

    $stmt = $this->db->prepare("
             SELECT COUNT(*) failed_login_count,
                    UNIX_TIMESTAMP(MAX(attempt_time)) last_failed_attempt
               FROM failed_logins 
              WHERE source_ip = ? 
                    AND attempt_time > DATE_SUB(FROM_UNIXTIME(?), INTERVAL ? SECOND)
                         ");

    $stmt->bind_param('sii', $remoteAddress, $now, $timeWindowInSeconds);

    if ($stmt->execute())
    {
      $stmt->bind_result($failedLoginCount, $lastFailedAttempt);

      if ($stmt->fetch())
      {
        $failedLogin = new stdClass();

        $failedLogin->failedLoginCount = $failedLoginCount;
        $failedLogin->lastFailedAttempt = $lastFailedAttempt;
      }
    }
    
    $stmt->close();

    return $failedLogin;
  }

  // Articles

  public function getArticlePage($userId, $feedFolderId, $filter, $pageSize, $continueAfterId = null)
  {
    if (!$userId)
      return false;

    switch ($filter)
    {
    case "new":
      $filterClause = " AND ua.is_unread = 1";
      break;
    case "star":
      $filterClause = " AND ua.is_starred = 1";
      break;
    default:
      $filterClause = "";
    }

    if ($continueAfterId === null)
    {
      $stmt = $this->db->prepare("
               SELECT ua.id,
                      a.title,
                      a.link_url link,
                      a.author,
                      a.summary,
                      a.content,
                      UNIX_TIMESTAMP(a.crawled) published,
                      ff.id source_id,
                      ff.title source,
                      f.html_url source_www,
                      ua.is_unread,
                      ua.is_starred,
                      ua.is_liked,
                      ua.tags,
                      (SELECT SUM(is_liked)
                         FROM user_articles ua_liked
                        WHERE ua_liked.article_id = ua.article_id) liked_count
                 FROM articles a
           INNER JOIN user_articles ua FORCE INDEX FOR JOIN (fk_user_articles_article_id) ON a.id = ua.article_id
           INNER JOIN feeds f ON f.id = ua.feed_id
           INNER JOIN feed_folders ff ON ff.feed_id = f.id AND ff.user_id = ua.user_id
           INNER JOIN feed_folder_trees fft ON fft.descendant_id = ff.id 
                WHERE fft.ancestor_id = ? 
                      AND ua.user_id = ?
                      {$filterClause}
             ORDER BY a.crawled DESC, a.id DESC
                LIMIT ?
                                 ");
      $stmt->bind_param('iii', 
        $feedFolderId,
        $userId,
        $pageSize);
    }
    else
    {
      $stmt = $this->db->prepare("
               SELECT ua.id id,
                      a.title,
                      a.link_url link,
                      a.author,
                      a.summary,
                      a.content,
                      UNIX_TIMESTAMP(a.crawled) published,
                      ff.id source_id,
                      ff.title source,
                      f.html_url source_www,
                      ua.is_unread,
                      ua.is_starred,
                      ua.is_liked,
                      ua.tags,
                      (SELECT SUM(is_liked)
                         FROM user_articles ua_liked
                        WHERE ua_liked.article_id = ua.article_id) liked_count
                 FROM articles a
           INNER JOIN user_articles ua FORCE INDEX FOR JOIN (fk_user_articles_article_id) ON a.id = ua.article_id
           INNER JOIN feeds f ON f.id = ua.feed_id
           INNER JOIN feed_folders ff ON ff.feed_id = f.id AND ff.user_id = ua.user_id
           INNER JOIN feed_folder_trees fft ON fft.descendant_id = ff.id 

           INNER JOIN (SELECT a2.crawled 
                         FROM user_articles ua2 
                   INNER JOIN articles a2 ON a2.id = ua2.article_id 
                        WHERE ua2.id = ? AND ua2.user_id = ?) o ON a.crawled = o.crawled AND ua.id < ? OR a.crawled < o.crawled

                WHERE fft.ancestor_id = ?
                      AND ua.user_id = ?
                      {$filterClause}
             ORDER BY a.crawled DESC, a.id DESC
                LIMIT ?
                                 ");

      $stmt->bind_param('iiiiii', 
        $continueAfterId,
        $userId,
        $continueAfterId,
        $feedFolderId,
        $userId,
        $pageSize);
    }

    $articles = false;

    if ($stmt->execute())
    {
      $stmt->bind_result($userArticleId, 
                         $articleTitle,
                         $articleLink,
                         $articleAuthor,
                         $articleSummary,
                         $articleContent,
                         $articlePublished,
                         $feedId,
                         $feedTitle,
                         $feedWwwUrl,
                         $isArticleUnread,
                         $isArticleStarred,
                         $isArticleLiked,
                         $articleTags,
                         $likeCount);

      $articles = array();

      while ($stmt->fetch())
      {
        $articles[] = array(
          "id"          => $userArticleId,
          "title"       => $articleTitle,
          "link"        => $articleLink,
          "author"      => $articleAuthor,
          "summary"     => $articleSummary,
          "content"     => $articleContent,
          "published"   => $articlePublished,
          "source_id"   => $feedId,
          "source"      => $feedTitle,
          "source_www"  => $feedWwwUrl,
          "is_unread"   => ($isArticleUnread > 0),
          "is_starred"  => ($isArticleStarred > 0),
          "is_liked"    => ($isArticleLiked > 0),
          "tags"        => empty($articleTags) ? array() : explode(',', $articleTags),
          "like_count"  => (int)$likeCount,
        );
      }
    }

    $stmt->close();

    return $articles;
  }

  public function setArticleStatus($userId, $userArticleId, $isUnread, $isStarred, $isLiked)
  {
    $isArticleUnread = $isUnread ? 1 : 0;
    $isArticleStarred = $isStarred ? 1 : 0;
    $isArticleLiked = $isLiked ? 1 : 0;
    $now = time();

    $stmt = $this->db->prepare("
                UPDATE user_articles
                   SET star_time = CASE WHEN is_starred = ? 
                                             THEN star_time 
                                        WHEN is_starred = 0 AND ? = 1
                                             THEN FROM_UNIXTIME(?)
                                        ELSE 
                                             NULL
                                        END,
                       read_time = CASE WHEN is_unread = ? 
                                             THEN read_time 
                                        WHEN is_unread = 1 AND ? = 0
                                             THEN FROM_UNIXTIME(?)
                                        ELSE 
                                             NULL
                                        END,
                       like_time = CASE WHEN is_liked = ? 
                                             THEN like_time 
                                        WHEN is_liked = 0 AND ? = 1
                                             THEN FROM_UNIXTIME(?)
                                        ELSE 
                                             NULL
                                        END,
                       is_starred = ?,
                       is_unread = ?,
                       is_liked = ?
                 WHERE id = ? 
                       AND user_id = ?
                         ");

    $stmt->bind_param('iiiiiiiiiiiiii', 
      $isArticleStarred, $isArticleStarred, $now,
      $isArticleUnread, $isArticleUnread, $now,
      $isArticleLiked, $isArticleLiked, $now,
      $isArticleStarred, $isArticleUnread, $isArticleLiked, 
      $userArticleId, $userId);

    $result = $stmt->execute();
    $stmt->close();

    return $result;
  }  

  public function setArticleTags($userId, $userArticleId, $tags)
  {
    // Delete existing tags

    $stmt = $this->db->prepare("
                DELETE uat
                  FROM user_article_tags uat
            INNER JOIN user_articles ua ON ua.id = uat.user_article_id 
                 WHERE uat.user_article_id = ? 
                       AND ua.user_id = ?
                         ");

    $stmt->bind_param('ii', $userArticleId, $userId);

    $result = $stmt->execute();
    $stmt->close();

    if ($result)
    {
      // Add new tags

      $stmt = $this->db->prepare("
              INSERT INTO user_article_tags (id, user_article_id, tag)
                   SELECT NULL,
                          ua.id,
                          ?
                     FROM user_articles ua
                    WHERE ua.id = ?
                          AND ua.user_id = ?
                           ");

      $stmt->bind_param('sii', $userArticleTag, $userArticleId, $userId);

      foreach ($tags as $tag)
      {
        $userArticleTag = $tag;
        if (!$stmt->execute())
          $result = false;
      }

      $stmt->close();

      // Update the UA table

      $stmt = $this->db->prepare("
              UPDATE user_articles 
                 SET tags = ?
               WHERE id = ?
                     AND user_id = ?
                     ");

      $stmt->bind_param('sii', $delimitedTags, $userArticleId, $userId);
      $delimitedTags = implode(",", $tags);

      $result = $stmt->execute();

      $stmt->close();
    }

    return $result;
  }  

  // Etc

  public function beginStaging($userId)
  {
    $stageId = false;

    $stmt = $this->db->prepare("
        INSERT INTO stages(user_id, started)
             VALUES (?, FROM_UNIXTIME(?))
                               ");

    $stmt->bind_param('ii', $userId, $started);

    $started = time();

    if ($stmt->execute())
      $stageId = $this->db->insert_id;

    $stmt->close();

    return $stageId;
  }
}
?>