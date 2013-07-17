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

var initializeStagingQuery = `
  INSERT INTO stages(user_id, started) 
       VALUES (NULL, FROM_UNIXTIME(?))`
var stageFeedQuery = `
  INSERT INTO staged_feeds (feed_url,
                            feed_hash,
                            html_url,
                            title,
                            summary,
                            last_built,
                            last_updated,
                            next_update,
                            stage_id)
       VALUES (?,SHA1(?),?,?,?,FROM_UNIXTIME(?),UTC_TIMESTAMP(),FROM_UNIXTIME(?),?)`
var stageArticleQuery = `
  INSERT INTO staged_articles (feed_id,
                               guid,
                               link_url,
                               title,
                               author,
                               summary,
                               content,
                               published,
                               crawled,
                               stage_id)
       VALUES (?,?,?,?,?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?),?)`
var retrieveFeedsQuery = `
  SELECT id, 
         feed_url,
         UNIX_TIMESTAMP(last_built), 
         UNIX_TIMESTAMP(last_updated), 
         UNIX_TIMESTAMP(next_update) 
    FROM feeds`
var writeUpdatedFeedsQuery = `
      UPDATE feeds f
  INNER JOIN staged_feeds sf ON sf.feed_hash = f.feed_hash
         SET f.html_url = sf.html_url,
             f.title = sf.title,
             f.summary = sf.summary,
             f.last_built = sf.last_built,
             f.last_updated = sf.last_updated,
             f.next_update = sf.next_update
       WHERE stage_id = ?`
var writeUpdatedLinksQuery = `
  INSERT INTO feed_links (feed_id, url, url_hash)
       SELECT f.id,
              f.html_url,
              sha1(f.html_url)
         FROM feeds f 
   INNER JOIN staged_feeds sf ON sf.feed_hash = f.feed_hash
    LEFT JOIN feed_links fl ON fl.feed_id = f.id AND fl.url = f.html_url
        WHERE stage_id = ? 
              AND fl.id IS NULL`
var writeUpdatedArticlesQuery = `
      UPDATE articles a
  INNER JOIN staged_articles sa ON sa.feed_id = a.feed_id AND sa.guid = a.guid
         SET a.link_url = sa.link_url,
             a.title = sa.title,
             a.author = sa.author,
             a.summary = sa.summary,
             a.content = sa.content,
             a.published = sa.published,
             a.crawled = sa.crawled
       WHERE sa.published != a.published
             AND stage_id = ?`
var writeNewArticlesQuery = `
   INSERT INTO articles (feed_id,
                         guid,
                         link_url,
                         title,
                         author,
                         summary,
                         content,
                         published,
                         crawled)
        SELECT sa.feed_id,
               sa.guid,
               sa.link_url,
               sa.title,
               sa.author,
               sa.summary,
               sa.content,
               sa.published,
               sa.crawled
          FROM staged_articles sa
     LEFT JOIN articles a ON a.feed_id = sa.feed_id AND a.guid = sa.guid
         WHERE a.id IS NULL
               AND stage_id = ?`
var personalizeArticlesQuery = `
  INSERT INTO user_articles (user_id, article_id) 
       SELECT u.id user_id, 
              a.id article_id
         FROM articles a 
   INNER JOIN users u 
   INNER JOIN feed_folders ff ON ff.feed_id = a.feed_id AND ff.user_id = u.id 
    LEFT JOIN user_articles ua ON ua.article_id = a.id AND ua.user_id = u.id 
        WHERE ua.id IS NULL`
var deleteStagedDataQuery = `
  DELETE 
    FROM stages
   WHERE id = ?`
