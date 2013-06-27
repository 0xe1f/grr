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

type AtomFeed struct {
  XMLName xml.Name `xml:"feed"`
  Id string `xml:"id"`
  Title string `xml:"title"`
  Description string `xml:"subtitle"`
  Updated string `xml:"updated"`
  Link []AtomLink `xml:"link"`
  Entry []*AtomEntry `xml:"entry"`
}

type AtomLink struct {
  Type string `xml:"type,attr"`
  Rel string `xml:"rel,attr"`
  Href string `xml:"href,attr"`
}

type AtomAuthor struct {
  Name string `xml:"name"`
  URI string `xml:"uri"`
}

type AtomEntry struct {
  Id string `xml:"id"`
  Published string `xml:"published"`
  Updated string `xml:"updated"`
  Link []AtomLink `xml:"link"`
  EntryTitle AtomText `xml:"title"`
  Content AtomText `xml:"content"`
  Summary AtomText `xml:"summary"`
  Author AtomAuthor `xml:"author"`
}

type AtomText struct {
  Type string `xml:"type,attr"`
  Content string `xml:",chardata"`
}

var supportedAtomTimeFormats = []string {
  time.RFC3339,
}

func (atomFeed *AtomFeed) Marshal() (feed Feed, err error) {
  updated := time.Time {}
  if atomFeed.Updated != "" {
    updated, err = parseTime(supportedAtomTimeFormats, atomFeed.Updated)
  }

  linkUrl := ""
  for _, link := range atomFeed.Link {
    if link.Rel == "alternate" {
      linkUrl = link.Href
    }
  }

  feed = Feed {
    Title: atomFeed.Title,
    Description: atomFeed.Description,
    Updated: updated,
    WWWURL: linkUrl,
  }

  if atomFeed.Entry != nil {
    feed.Entry = make([]*Entry, len(atomFeed.Entry))
    for i, v := range atomFeed.Entry {
      var entryError error
      feed.Entry[i], entryError = v.Marshal()

      if entryError != nil && err == nil {
        err = entryError
      }
    }
  }

  return feed, err
}

func (atomEntry *AtomEntry) Marshal() (entry *Entry, err error) {
  linkUrl := ""
  for _, link := range atomEntry.Link {
    if link.Rel == "alternate" {
      linkUrl = link.Href
    }
  }

  guid := atomEntry.Id
  if guid == "" {
    guid = linkUrl
  }

  content := atomEntry.Content.Content
  if content == "" && atomEntry.Summary.Content != "" {
    content = atomEntry.Summary.Content
  }

  published := time.Time {}
  if atomEntry.Published != "" {
    published, err = parseTime(supportedAtomTimeFormats, atomEntry.Published)
  }

  updated := time.Time {}
  if atomEntry.Updated != "" {
    updated, err = parseTime(supportedAtomTimeFormats, atomEntry.Updated)
  }

  entry = &Entry {
    GUID: guid,
    Author: atomEntry.Author.Name,
    Title: atomEntry.EntryTitle.Content,
    Content: content,
    Published: published,
    Updated: updated,
    WWWURL: linkUrl,
  }

  return entry, err
}
