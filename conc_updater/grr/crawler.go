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

  ch := make(chan *feedInfo)

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
        // go dumpURLInfo(feedUrl.String, crawl.ResultChannel)
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
    if id, err = result.LastInsertId(); err == nil {
      crawl.StagingId = id
    }
  }

  return err
}

func (crawl *crawl) stageFeed(info *feedInfo) {
  var result sql.Result 
  var err error = nil

  feed := info.Feed

  // FIXME: nextUpdate is NIL!
  // FIXME: I'm still trying to figure out how to deal with MB strings in Go
  //        For some reason, mysql isn't happy with the length produced by
  //        substr(feed.go). Remove SUBSTRING() from the SQL queries; do all 
  //        string truncation in code

  // Stage the feeds
  if _, err = crawl.Connection.Exec(`
      INSERT INTO staged_feeds (feed_url,feed_hash,html_url,title,summary,last_built,last_updated,next_update,stage_id)
      VALUES (?,SHA1(?),?,?,?,FROM_UNIXTIME(?),UTC_TIMESTAMP(),FROM_UNIXTIME(?),?)`, 
      info.URL, info.URL, feed.WWWURL, feed.Title, feed.Description, feed.Updated.Unix(), nil, crawl.StagingId); err != nil {
    // Not a fatal error
    fmt.Println("stageFeed: Error staging feed ", info.URL, ": ", err)
  }

  // Stage the articles
  for _, entry := range feed.Entry {
    if result, err = crawl.Connection.Exec(`
        INSERT INTO staged_articles (feed_id,guid,link_url,title,author,summary,content,published,crawled,stage_id)
        VALUES (?,?,?,SUBSTRING(?,1,256),?,SUBSTRING(?,1,512),?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),?)`,
        info.FeedId, entry.GUID, entry.WWWURL, 
        entry.PlainTextTitle(), entry.PlainTextAuthor(), entry.PlainTextSummary(), 
        entry.Content, entry.Published.Unix(), crawl.Started.Unix(), crawl.StagingId); err != nil {
      fmt.Println("stageFeed: Error staging articles ", info.URL, ": ", err)
      if false { // FIXME
        fmt.Println(result)
      }
    }
  }
}

func (crawl *crawl) parseFeed(info *feedInfo) {
  startTime := time.Now()

  info.Feed, info.Format, info.Error = parse(info.URL)

  if info.Error == nil {
    fmt.Printf("OK:  %5s  %s (%s)\n", info.Format, info.URL, time.Since(startTime))
  } else {
    fmt.Printf("ERR: %5s  %s (%s): %s\n", info.Format, info.URL, time.Since(startTime), info.Error)
  }

  if info.Feed != nil {
    crawl.stageFeed(info)
  }

  crawl.ResultChannel<- info
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