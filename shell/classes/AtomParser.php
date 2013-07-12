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

class AtomParser extends FeedParser
{
  public function parse()
  {
    $this->xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
    $this->xml->feed->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');

    $feed = new Feed();

    $feed->type = "atom";
    $feed->url = $this->url;
    $feed->setLastBuildDate((string)$this->xml->updated);
    $feed->title = (string)$this->xml->title;
    $feed->summary = (string)$this->xml->subtitle;

    $links = $this->xml->xpath('atom:link[@rel="alternate"]');
    if (!empty($links))
    {
      $link = $links[0]->attributes();
      $feed->link = (string)$link->href;
    }

    if (!$feed->link)
      $feed->link = $feed->url; // Default link is the URL to the feed itself

    foreach ($this->xml->xpath('/atom:feed/atom:entry') as $entry) 
    {
      $entry->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');

      $article = new Article();
      $article->guid = (string)$entry->id;
      $article->published = 0;

      $pubDate = (string)$entry->published;
      if (!$pubDate)
        $pubDate = (string)$entry->updated;

      if ($pubDate)
      {
        if (($timestamp = strtotime($pubDate)) !== false)
          $article->published = $timestamp;
      }

      $article->link_url = null;

      $links = $entry->xpath('atom:link[@rel="alternate"]');
      if ($links)
      {
        $link = $links[0]->attributes();
        $article->link_url = (string)$link->href;
      }

      // If still no link_url, try first link of any type
      if (!$article->link_url)
      {
        if (($links = $entry->xpath('atom:link')))
        {
          $link = $links[0]->attributes();
          $article->link_url = (string)$link->href;
        }
      }

      // If a post has no GUID, use its link as a GUID instead
      if (!$article->guid)
        $article->guid = $article->link_url;

      if (!$article->guid)
        continue;

      if ((string)$entry->content)
        $article->text = (string)$entry->content;
      else
        $article->text = (string)$entry->summary;

      if (isset($entry->author) && isset($entry->author->name))
        $article->author = (string)$entry->author->name;

      $article->title = (string)$entry->title;

      $feed->articles[] = $article;
    }

    return $feed;
  }
}

?>
