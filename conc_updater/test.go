package main

import (
  "os"
  "fmt"
  "log"
  "net/http"
  "encoding/json"
  "path"
  "./grr/parser"
  "bufio"
  "strings"
  "errors"
)

// FIXME: sort out timezone information (esp. RSS)
// FIXME: syndication info
// HTML parsing: pull out rss link

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

  async := false
  i := 0
  scanner := bufio.NewScanner(fi)
  for scanner.Scan() {
    URL := strings.TrimSpace(scanner.Text())

    if URL == "" {
      break // Break on empty string
    }

    if strings.HasPrefix(URL, "#") {
      continue // Skip "comments"
    }

    if async {
      go DumpURLInfo(URL)
    } else {
      DumpURLInfo(URL)
    }

    i++
  }

  if async {
    var input string
    fmt.Scanln(&input)
  }

  fmt.Println("done")

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

func DumpURLInfo(URL string) {
  feed, err := Parse(URL)

  if err != nil {
    fmt.Println("ERR ", URL, " : ", err)
    return
  }

  fmt.Println("OK!   ", URL)

  return
  bf, _ := json.MarshalIndent(feed, "", "  ")
  fmt.Println(string(bf))
}

func Parse(URL string) (*parser.Feed, error) {
  client := &http.Client {
    CheckRedirect: redirectPolicyFunc,
  }
  
  resp, err := client.Get(URL)
  
  if err != nil {
    return nil, err
  }

  defer resp.Body.Close()

  return parser.UnmarshalStream(resp.Body)
}
