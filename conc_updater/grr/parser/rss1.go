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
)

type RSS1Feed struct {
  XMLName xml.Name `xml:"http://www.w3.org/1999/02/22-rdf-syntax-ns# RDF"`
  Title string `xml:"channel>title"`
  Description string `xml:"channel>description"`
  Updated string `xml:"channel>date"`
  Link string `xml:"channel>link"`
  Entry []*RSS1Entry `xml:"item"`
}

type RSS1Entry struct {
  Id string `xml:"guid"`
  Published string `xml:"http://purl.org/dc/elements/1.1/ date"`
  EntryTitle string `xml:"title"`
  Link string `xml:"link"`
  Author string `xml:"http://purl.org/dc/elements/1.1/ creator"`
  EncodedContent string `xml:"http://purl.org/rss/1.0/modules/content/ encoded"`
  Content string `xml:"description"`
}

func (rss1Feed *RSS1Feed) Marshal() (feed Feed, err error) {
  updated := time.Time {}
  if rss1Feed.Updated != "" {
    updated, err = time.Parse("2006-01-02T15:04-07:00", rss1Feed.Updated)
  }

  feed = Feed {
    Title: rss1Feed.Title,
    Description: rss1Feed.Description,
    Updated: updated,
    WWWURL: rss1Feed.Link,
  }

  if rss1Feed.Entry != nil {
    feed.Entry = make([]*Entry, len(rss1Feed.Entry))
    for i, v := range rss1Feed.Entry {
      var entryError error
      feed.Entry[i], entryError = v.Marshal()

      if entryError != nil && err == nil {
        err = entryError
      }
    }
  }

  return feed, err
}

func (rss1Entry *RSS1Entry) Marshal() (entry *Entry, err error) {
  guid := rss1Entry.Id
  if guid == "" {
    guid = rss1Entry.Link
  }

  content := rss1Entry.EncodedContent
  if content == "" {
    content = rss1Entry.Content
  }

  published := time.Time {}
  if rss1Entry.Published != "" {
    published, err = time.Parse("2006-01-02T15:04-07:00", rss1Entry.Published)
  }

  entry = &Entry {
    GUID: guid,
    Author: rss1Entry.Author,
    Title: rss1Entry.EntryTitle,
    Content: content,
    Published: published,
    WWWURL: rss1Entry.Link,
  }

  return entry, err
}

