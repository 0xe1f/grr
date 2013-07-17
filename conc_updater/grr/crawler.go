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

type feedInfo struct {
  FeedId int64
  URL string
  Feed *parser.Feed
  Format string
  Error error
} 

type crawl struct {
  Connection *sql.DB
  Started time.Time
  StagingId int64
  ResultChannel chan<- *feedInfo

  StageFeed *sql.Stmt
  StageArticle *sql.Stmt
}

// HTML parsing: pull out rss link

func Crawl() {
  var con *sql.DB
  var err error

  if con, err = sql.Open(DatabaseDriver, DatabaseDataSource); err != nil {
    fmt.Println("sql.Open error: ", err)
    return
  }

  defer con.Close()

  ch := make(chan *feedInfo)

  crawl := crawl {
    Connection: con,
    Started: time.Now(),
    ResultChannel: ch,
  }

  // Initialize prepared statements
  if stmt, err := con.Prepare(stageFeedQuery); err == nil {
    crawl.StageFeed  = stmt
  } else {
    fmt.Println("Error initializing preparing staged_feeds statement: ", err)
    return
  }

  if stmt, err := con.Prepare(stageArticleQuery); err == nil {
    crawl.StageArticle  = stmt
  } else {
    fmt.Println("Error initializing preparing staged_articles statement: ", err)
    return
  }

  fmt.Println("Initializing...")

  if err := crawl.initializeStaging(); err != nil {
    fmt.Println("Error initializing stage: ", err)
    return
  }

  feedsParsed := 0

  fmt.Println("Parsing...")

  if rows, err := con.Query(retrieveFeedsQuery); err == nil {
    var id int64
    var feedUrl sql.NullString
    var lastBuilt sql.NullInt64
    var lastUpdated sql.NullInt64
    var nextUpdate sql.NullInt64

    for rows.Next() {
      if rowErr := rows.Scan(&id, &feedUrl, &lastBuilt, &lastUpdated, &nextUpdate); rowErr == nil {
        feedsParsed++
        info := &feedInfo {
          FeedId: id,
          URL: feedUrl.String,
        }
        go crawl.parseFeed(info)
      } else {
        fmt.Println("rows.Scan error: ", rowErr)
      }
    }

    // Wait for the parsers to complete
    for i := 0; i < feedsParsed; i++ {
      info := <-ch
      if false {
        fmt.Println(info.Feed)
      }
    }
  } else {
    fmt.Println("Error retrieving list of feeds: ", err)
  }

  fmt.Println("Unstaging data...")

  crawl.unstage()

  fmt.Println("Cleaning up...")

  crawl.cleanUp()

  fmt.Println("Completed in ", time.Since(crawl.Started))
}

func redirectPolicyFunc(req *http.Request, via []*http.Request) error {
  if len(via) > 2 {
    return errors.New("Too many redirects")
  }

  return nil
}

func (crawl *crawl) initializeStaging() error {
  if result, err := crawl.Connection.Exec(initializeStagingQuery, crawl.Started.Unix()); err == nil {
    if id, err := result.LastInsertId(); err == nil {
      crawl.StagingId = id
    } else {
      return err
    }
  } else {
    return err
  }

  return nil
}

func (crawl *crawl) stageFeed(info *feedInfo) {
  feed := info.Feed

  // Compute time until next update
  nextUpdate := crawl.Started.Add(feed.DurationBetweenUpdates()).UTC()

  // Stage the feeds
  if _, err := crawl.StageFeed.Exec(info.URL, info.URL, feed.WWWURL, feed.Title, 
      feed.Description, feed.Updated.Unix(), nextUpdate.Unix(), crawl.StagingId); err != nil {
    // Not a fatal error
    fmt.Println("stageFeed: Error staging feed ", info.URL, ": ", err)
  }

  // Stage the articles
  for _, entry := range feed.Entry {
    if _, err := crawl.StageArticle.Exec(info.FeedId, entry.GUID, entry.WWWURL, 
        entry.PlainTextTitle(), entry.PlainTextAuthor(), entry.PlainTextSummary(), 
        entry.Content, entry.Published.Unix(), crawl.Started.Unix(), crawl.StagingId); err != nil {
      // Not fatal
      fmt.Println("stageFeed: Error staging articles ", info.URL, ": ", err)
    }
  }
}

func (crawl *crawl) parseFeed(info *feedInfo) {
  startTime := time.Now()

  info.Feed, info.Format, info.Error = parse(info.URL)

  if info.Error == nil {
    fmt.Printf("OK:  %5s %10s  %s (%s)\n", info.Format, info.Feed.DurationBetweenUpdates(), info.URL, time.Since(startTime))
  } else {
    fmt.Printf("ERR: %5s %10s  %s (%s): %s\n", info.Format, "", info.URL, time.Since(startTime), info.Error)
  }

  if info.Feed != nil {
    crawl.stageFeed(info)
  }

  crawl.ResultChannel<- info
}

func (crawl *crawl) unstage() {
  if _, err := crawl.Connection.Exec(writeUpdatedFeedsQuery, crawl.StagingId); err != nil {
    fmt.Println("Error updating feeds: ", err)
  }

  if _, err := crawl.Connection.Exec(writeUpdatedLinksQuery, crawl.StagingId); err != nil {
    fmt.Println("Error updating links: ", err)
  }

  if _, err := crawl.Connection.Exec(writeUpdatedArticlesQuery, crawl.StagingId); err != nil {
    fmt.Println("Error writing updated articles: ", err)
  }

  if _, err := crawl.Connection.Exec(writeNewArticlesQuery, crawl.StagingId); err != nil {
    fmt.Println("Error writing new articles: ", err)
  }

  if _, err := crawl.Connection.Exec(personalizeArticlesQuery); err != nil {
    fmt.Println("Error personalizing articles: ", err)
  }
}

func (crawl *crawl) cleanUp() error {
  if _, err := crawl.Connection.Exec(deleteStagedDataQuery, crawl.StagingId); err != nil {
    fmt.Println("Error cleaning up staged data: ", err)
    return err
  }

  return nil
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