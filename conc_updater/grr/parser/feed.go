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
 
package parser

import (
  "time"
  "encoding/xml"
  "io"
  "io/ioutil"
  "bytes"
  "errors"
  "strings"
  "../html/template"
  "sort"
)

type Feed struct {
  Title string
  Description string
  Updated time.Time
  WWWURL string
  Entry []*Entry
  Format string
  HourlyUpdateFrequency float32
}

func (feed *Feed)LatestEntryModification() time.Time {
  mostRecent := time.Time {}
  for _, entry := range feed.Entry {
    latestModification := entry.LatestModification()
    if latestModification.After(mostRecent) {
      mostRecent = latestModification
    }
  }

  return mostRecent
}

type SortableTimes []time.Time 

func (s SortableTimes) Len() int {
  return len(s)
}

func (s SortableTimes) Swap(i int, j int) {
  s[i], s[j] = s[j], s[i]
}

func (s SortableTimes) Less(i int, j int) bool {
  return s[i].Before(s[j])
}

func (feed *Feed)DurationBetweenUpdates() time.Duration {
  if feed.HourlyUpdateFrequency != 0 {
    // Set explicitly
    return time.Duration(feed.HourlyUpdateFrequency) * time.Hour
  }

  // Compute frequency by analyzing entries in the feed
  pubDates := make(SortableTimes, len(feed.Entry))
  for i, entry := range feed.Entry {
    pubDates[i] = entry.LatestModification()
  }

  // Sort dates in ascending order
  sort.Sort(pubDates)

  // Compute the average difference between them
  durationBetweenUpdates := time.Duration(0)
  if len(pubDates) > 1 {
    deltaSum := 0.0
    for i, n := 1, len(pubDates); i < n; i++ {
      deltaSum += float64(pubDates[i].Sub(pubDates[i - 1]).Hours())
    }

    durationBetweenUpdates = time.Duration(deltaSum / float64(len(pubDates) - 1)) * time.Hour
  }

  // Clamp the frequency
  minFrequency := time.Duration(30) * time.Minute // 30 minutes
  maxFrequency := time.Duration(24) * time.Hour   // 1 day

  if durationBetweenUpdates > maxFrequency {
    return maxFrequency
  } else if durationBetweenUpdates < minFrequency {
    return minFrequency
  }

  return durationBetweenUpdates
}

type Entry struct {
  GUID string
  Author string
  Title string
  Content string
  Published time.Time
  Updated time.Time
  WWWURL string
}

func (entry *Entry)PlainTextTitle() string {
  return template.StripTags(entry.Title)
}

func (entry *Entry)PlainTextAuthor() string {
  return template.StripTags(entry.Author)
}

func (entry *Entry)PlainTextSummary() string {
  plainText := strings.TrimSpace(template.StripTags(entry.Content))
  return substr(plainText, 0, 512)
}

func (entry *Entry)LatestModification() time.Time {
  if entry.Updated.After(entry.Published) {
    return entry.Updated
  }

  return entry.Published
}

type FeedMarshaler interface {
  Marshal() (Feed, error)
}

type GenericFeed struct {
  XMLName xml.Name
}

func charsetReader(charset string, r io.Reader) (io.Reader, error) {
  // FIXME: This hardly does anything useful at the moment
  if strings.ToLower(charset) == "iso-8859-1" {
    return r, nil
  }
  return nil, errors.New("Unsupported encoding: " + charset)
}

func UnmarshalStream(reader io.Reader) (*Feed, string, error) {
  format := ""

  // Read the stream into memory (we'll need to parse it twice)
  var contentReader *bytes.Reader
  if buffer, err := ioutil.ReadAll(reader); err == nil {
    contentReader = bytes.NewReader(buffer)
  } else {
    return nil, format, err
  }

  genericFeed := GenericFeed{}

  decoder := xml.NewDecoder(contentReader)
  decoder.CharsetReader = charsetReader

  if err := decoder.Decode(&genericFeed); err != nil {
     return nil, format, err
  }

  var xmlFeed FeedMarshaler

  if genericFeed.XMLName.Space == "http://www.w3.org/1999/02/22-rdf-syntax-ns#" && genericFeed.XMLName.Local == "RDF" {
    xmlFeed = &rss1Feed{}
    format = "RSS1"
  } else if genericFeed.XMLName.Local == "rss" {
    xmlFeed = &rss2Feed{}
    format = "RSS2"
  } else if genericFeed.XMLName.Space == "http://www.w3.org/2005/Atom" && genericFeed.XMLName.Local == "feed" {
    xmlFeed = &atomFeed{}
    format = "Atom"
  } else {
    return nil, format, errors.New("Unsupported type of feed (" +
      genericFeed.XMLName.Space + ":" + genericFeed.XMLName.Local + ")")
  }

  contentReader.Seek(0, 0)

  decoder = xml.NewDecoder(contentReader)
  decoder.CharsetReader = charsetReader

  if err := decoder.Decode(xmlFeed); err != nil {
    return nil, format, err
  }
  
  feed, err := xmlFeed.Marshal()
  if err != nil {
    return nil, format, err
  }

  return &feed, format, nil
}

func parseTime(supportedFormats []string, timeSpec string) (time.Time, error) {
  if timeSpec != "" {
    for _, format := range supportedFormats {
      if parsedTime, err := time.Parse(format, timeSpec); err == nil {
        return parsedTime.UTC(), nil
      }
    }

    return time.Time {}, errors.New("Unrecognized time format: " + timeSpec)
  }

  return time.Time {}, nil
}

func substr(s string, pos int, length int) string {
  runes := []rune(s)
  l := pos + length
  if l > len(runes) {
    l = len(runes)
  }

  return string(runes[pos:l])
}