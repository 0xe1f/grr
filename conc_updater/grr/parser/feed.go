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
)

type Feed struct {
  Title string
  Description string
  Updated time.Time
  WWWURL string
  Entry []*Entry
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

type FeedMarshaler interface {
  Marshal() (Feed, error)
}

type GenericFeed struct {
  XMLName xml.Name
}

func UnmarshalStream(reader io.Reader) (*Feed, error) {
  // Read the stream into memory (we'll need to parse it twice)
  var contentReader *bytes.Reader
  if buffer, err := ioutil.ReadAll(reader); err == nil {
    contentReader = bytes.NewReader(buffer)
  } else {
    return nil, err
  }

  genericFeed := &GenericFeed{}
  decoder := xml.NewDecoder(contentReader)

  if err := decoder.Decode(genericFeed); err != nil {
    return nil, err
  }

  var xmlFeed FeedMarshaler
  if genericFeed.XMLName.Space == "http://www.w3.org/1999/02/22-rdf-syntax-ns#" && genericFeed.XMLName.Local == "RDF" {
    xmlFeed = &RSS1Feed{}
  } else if genericFeed.XMLName.Local == "rss" {
    xmlFeed = &RSS2Feed{}
  } else if genericFeed.XMLName.Space == "http://www.w3.org/2005/Atom" && genericFeed.XMLName.Local == "feed" {
    xmlFeed = &AtomFeed{}
  } else {
    return nil, errors.New("Unsupported type of feed (" +
      genericFeed.XMLName.Space + ":" + genericFeed.XMLName.Local + ")")
  }

  contentReader.Seek(0, 0)

  decoder = xml.NewDecoder(contentReader)
  if err := decoder.Decode(xmlFeed); err != nil {
    return nil, err
  }
  
  feed, err := xmlFeed.Marshal()
  if err != nil {
    return nil, err
  }

  return &feed, nil
}