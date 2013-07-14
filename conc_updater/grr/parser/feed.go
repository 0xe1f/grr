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
)

type Feed struct {
  Title string
  Description string
  Updated time.Time
  WWWURL string
  Entry []*Entry
  Format string
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
  return nil, errors.New("Unsupported character set encoding: " + charset)
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