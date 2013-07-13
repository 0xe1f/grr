package main

import (
  "os"
  "fmt"
  "time"
  "log"
  "net/http"
  // "encoding/json"
  "path"
  "./grr/parser"
  "bufio"
  "strings"
  "errors"
)

// FIXME: syndication info
// HTML parsing: pull out rss link

type DataFeed struct {
  Title string;
}

func main() {
  if len(os.Args) < 2 {
    fmt.Printf("Usage: %s <rss-file.xml>\n", path.Base(os.Args[0]))
    return
  }

  fi, err := os.Open(os.Args[1])
  if err != nil {
    log.Fatal(err)
    return
  }

  defer fi.Close()

  startTime := time.Now()

  n := 0
  scanner := bufio.NewScanner(fi)

  c := make(chan *parser.Feed)

  for scanner.Scan() {
    URL := strings.TrimSpace(scanner.Text())

    if URL == "" {
      break // Break on empty string
    }

    if strings.HasPrefix(URL, "#") {
      continue // Skip "comments"
    }

    n++
    go DumpURLInfo(URL, c)
  }

  for i := 0; i < n; i++ {
    feed := <-c;
    if false {
      fmt.Println(feed)
    }
  }

  fmt.Println("done in ", time.Since(startTime))

  if err := scanner.Err(); err != nil {
    log.Fatal(err)
    return
  }
}

func redirectPolicyFunc(req *http.Request, via []*http.Request) error {
  if len(via) > 2 {
    return errors.New("Too many redirects")
  }

  return nil
}

func DumpURLInfo(URL string, c chan<- *parser.Feed) {
  startTime := time.Now()

  feed, format, err := Parse(URL)

  // bf, _ := json.MarshalIndent(feed, "", "  ")
  // fmt.Println(string(bf))

  if err == nil {
    fmt.Printf("OK:  %5s  %s (%s)\n", format, URL, time.Since(startTime))
    c<- feed
  } else {
    fmt.Printf("ERR: %5s  %s (%s): %s\n", format, URL, time.Since(startTime), err)
    c<- nil
  }
}

func Parse(URL string) (*parser.Feed, string, error) {
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
