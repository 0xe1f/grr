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

package grr

import (
  "fmt"
  "time"
  "net/http"
  "./parser"
  "errors"
)

import "database/sql"
import _ "github.com/go-sql-driver/mysql"

type parserResult struct {
  Feed *parser.Feed
  Format string
  Error error
}

type crawl struct {
  Connection *sql.DB
  Started time.Time
  StagingId int64
  ResultChannel chan<- parserResult
}

// FIXME: syndication info
// HTML parsing: pull out rss link

func Crawl() {
  var con *sql.DB
  var err error

  if con, err = sql.Open(DatabaseDriver, DatabaseDataSource); err != nil {
    fmt.Println("sql.Open error: ", err)
    return
  }

  defer con.Close()

  ch := make(chan parserResult)
  crawl := crawl {
    Connection: con,
    Started: time.Now(),
    ResultChannel: ch,
  }

  fmt.Println("Initializing...")

  if err := crawl.initializeStaging(); err != nil {
    fmt.Println("Error initializing stage: ", err)
    return
  }

  feedsParsed := 0

  fmt.Println("Parsing...")

  if rows, err := con.Query("SELECT id, feed_url, UNIX_TIMESTAMP(last_built), UNIX_TIMESTAMP(last_updated), UNIX_TIMESTAMP(next_update) FROM feeds"); err == nil {
    var id int64
    var feedUrl sql.NullString
    var lastBuilt sql.NullInt64
    var lastUpdated sql.NullInt64
    var nextUpdate sql.NullInt64

    for rows.Next() {
      if rowErr := rows.Scan(&id, &feedUrl, &lastBuilt, &lastUpdated, &nextUpdate); rowErr == nil {
        feedsParsed++
        go dumpURLInfo(feedUrl.String, crawl.ResultChannel)
      } else {
        fmt.Println("rows.Scan error: ", rowErr)
      }
    }

    // Wait for the parsers to complete
    for i := 0; i < feedsParsed; i++ {
      chanResult := <-ch
      if false {
        fmt.Println(chanResult.Feed)
      }
    }
  } else {
    fmt.Println("con.Query error: ", err)
  }

  fmt.Println("Completed in ", time.Since(crawl.Started))
}

func redirectPolicyFunc(req *http.Request, via []*http.Request) error {
  if len(via) > 2 {
    return errors.New("Too many redirects")
  }

  return nil
}

func (crawl *crawl) initializeStaging() error {
  var result sql.Result 
  var err error = nil

  if result, err = crawl.Connection.Exec("INSERT INTO stages(user_id, started) VALUES (NULL, FROM_UNIXTIME(?))", crawl.Started.Unix()); err == nil {
    var id int64
    if id, err = result.RowsAffected(); err == nil {
      crawl.StagingId = id
    }
  }

  return err
}

func (crawl *crawl) stageFeed(feed parser.Feed) {
  /*
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
  */
}

func (crawl *crawl) parseFeed(URL string) {
  startTime := time.Now()

  chanResult := parserResult {}
  chanResult.Feed, chanResult.Format, chanResult.Error = parse(URL)

  if chanResult.Error == nil {
    fmt.Printf("OK:  %5s  %s (%s)\n", chanResult.Format, URL, time.Since(startTime))
  } else {
    fmt.Printf("ERR: %5s  %s (%s): %s\n", chanResult.Format, URL, time.Since(startTime), chanResult.Error)
  }

  crawl.ResultChannel<- chanResult
}

func dumpURLInfo(URL string, c chan<- parserResult) {
  startTime := time.Now()

  chanResult := parserResult {}
  chanResult.Feed, chanResult.Format, chanResult.Error = parse(URL)

  if chanResult.Error == nil {
    fmt.Printf("OK:  %5s  %s (%s)\n", chanResult.Format, URL, time.Since(startTime))
  } else {
    fmt.Printf("ERR: %5s  %s (%s): %s\n", chanResult.Format, URL, time.Since(startTime), chanResult.Error)
  }

  c<- chanResult
}

func parse(URL string) (*parser.Feed, string, error) {
  client := &http.Client {
    CheckRedirect: redirectPolicyFunc,
  }
  
  resp, err := client.Get(URL)
  
  if err != nil {
    return nil, "", err
  }

  defer resp.Body.Close()

  return parser.UnmarshalStream(resp.Body)
}