<?
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

class RssParser extends FeedParser
{
  public function parse()
  {
    $feed = new Feed();

    $this->xml->channel->registerXPathNamespace('sy', 
      'http://purl.org/rss/1.0/modules/syndication/');

    $feed->type = "rss";
    $feed->url = $this->url;
    $feed->setLastBuildDate((string)$this->xml->channel->lastBuildDate);
    $feed->title = (string)$this->xml->channel->title;
    $feed->summary = (string)$this->xml->channel->description;
    $feed->link = (string)$this->xml->channel->link;

    $sy = $this->xml->channel->children('http://purl.org/rss/1.0/modules/syndication/');
    $feed->setUpdateInformation((string)$sy->updatePeriod, (string)$sy->updateFrequency);

    if (!$feed->link)
      $feed->link = $feed->url; // Default link is the URL to the feed itself

    $rss1 = false;
    if ($this->xml->channel->items)
    {
      $rdf = $this->xml->channel->items->children('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
      if (count($rdf) > 0) 
        $rss1 = true;
    }

    if ($rss1)
    {
      // RSS 1.0
      $parent = $this->xml;
    }
    else
    {
      // RSS 2.0 and higher
      $parent = $this->xml->channel;
    }

    foreach ($parent->item as $item)
    {
      $item->registerXPathNamespace('dc', 
        'http://purl.org/dc/elements/1.1/');
      $item->registerXPathNamespace('content',
        'http://purl.org/rss/1.0/modules/content/');

      $article = new Article();
      $article->guid = (string)$item->guid;
      $article->published = 0;

      if ($rss1)
        $pubDate = (string)current($item->xpath('dc:date'));
      else
        $pubDate = (string)$item->pubDate;

      if ($pubDate)
      {
        if (($timestamp = strtotime($pubDate)) !== false)
          $article->published = $timestamp;
      }

      $article->link_url = (string)$item->link;
      $article->title = (string)$item->title;
      $article->author = current($item->xpath('dc:creator'));

      // If a post has no GUID, use its link as a GUID instead
      if (!$article->guid)
        $article->guid = $article->link_url;
      
      if (!$article->guid)
        continue;
      
      $encoded = $item->xpath('content:encoded');
      if ($encoded)
        $article->text = (string)current($encoded);
      else
        $article->text = (string)$item->description;

      $feed->articles[] = $article;
    }
    
    return $feed;
  }
}

?>
