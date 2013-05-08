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

$().ready(function()
{
  var itemsLoaded = false;
  var prefs = 
  {
    singleItemMode: true,
  };

  var lastPageRequested = null;

  // Key bindings

  $(document)
    .bind('keypress', 'j', function()
    {
      selectArticle(false);
    })
    .bind('keypress', 'k', function()
    {
      selectArticle(true);
    })
    .bind('keypress', 's', function()
    {
      if ($('.entry.selected').length)
        toggleStarred($('.entry.selected'));
    })
    .bind('keypress', 'l', function()
    {
      if ($('.entry.selected').length)
        toggleLiked($('.entry.selected'));
    })
    .bind('keypress', 'm', function()
    {
      if ($('.entry.selected').length)
        toggleUnread($('.entry.selected'));
    })
    .bind('keypress', 'r', function()
    {
      refreshFeeds();
    })
    .bind('keypress', 'a', function()
    {
      subscribe();
    });

  // Buttons

  $('button.refresh').click(function()
  {
    refreshFeeds();
  });

  $('button.subscribe').click(function()
  {
    subscribe();
  });

  $('.article-filter').change(function()
  {
    reloadItems();
  });

  $('.entries-container').scroll(function()
  {
    var pagerHeight = $('.next-page').outerHeight();
    if (!pagerHeight)
      return; // No pager

    var continueAfter = $('#entries').data('continue');
    if (lastPageRequested == continueAfter)
      return;

    var offset = $('#entries').height() - ($('.entries-container').scrollTop() + $('.entries-container').height()) - pagerHeight;
    if (offset < 36)
      lastPageRequested = loadNextPage();
  });

  $('button.mark-all-as-read').click(function()
  {
    var selected = getSelectedFeed();
    if (selected)
      markAllAs(false);
  });

  $('.select-article.up').click(function()
  {
    selectArticle(true);
  });

  $('.select-article.down').click(function()
  {
    selectArticle(false);
  });

  // Functions

  var l = function(str, args)
  {
    // Localization stub

    if (args)
      return vsprintf(str, args);

    return str;
  };

  var getPublishedDate = function(unixTimestamp)
  {
    var now = new Date();
    var then = new Date(unixTimestamp * 1000);

    if (now.getDate() == then.getDate() 
      && now.getMonth() == then.getMonth() 
      && now.getYear() == then.getYear())
      return then.toLocaleTimeString();
    else 
      return then.toLocaleDateString();
  };

  var selectArticle = function(selectPrevious)
  {
    if (selectPrevious)
      $('.entry.selected').prev('.entry').click();
    else
    {
      var selected = $('.entry.selected');
      var next;

      if (selected.length < 1)
        next = $('#entries .entry:first');
      else
        next = selected.next('.entry');

      next.click(); // TODO: Do this less hackily

      if (next.next('.entry').length < 1)
        loadNextPage(); // Load another page - this is the last item
    }

    $('.entry.selected').scrollintoview();
  };

  var showToast = function(message, isError)
  {
    $('#toast span').text(message);
    $('#toast')
      .attr('class', isError ? 'error' : 'info')
      .fadeIn()
      .delay(8000)
      .fadeOut('slow'); 
  };

  var subscribe = function(parentFolder)
  {
    if (!parentFolder)
      parentFolder = $('.subscriptions').data('object');

    var feedUrl = prompt(l('Enter the feed URL'));
    if (feedUrl)
    {
      $.post('?c=feeds', 
      {
        subscribeTo : feedUrl,
        createUnder : parentFolder.id,
      },
      function(response)
      {
        if (!response.error)
        {
          updateFeedDom(response.allItems);
          showToast(l('Successfully subscribed to "%s"', [response.feed.title]), false);
        }
        else
        {
          showToast(response.error.message, true);
        }
      }, 'json');
    }
  };

  var markAllAs = function(unread)
  {
    $.post('?c=articles', 
    { 
      toggleUnreadUnder: getSelectedFeedId(),
      isUnread: unread,
      filter: $('.article-filter').val(),
    },
    function(response)
    {
      if (!response.error)
      {
        var unreadCounts = response.unreadCounts;
        var feedIds = $.map(unreadCounts, function(v, i) 
        {
          return i * 1;
        });

        // Update the entries

        $.each($('#entries .entry'), function()
        {
          var entryDom = $(this);
          var entry = entryDom.data('object');

          if ($.inArray(entry.source_id, feedIds) > -1)
          {
            entry.is_unread = unread;
            entryDom.toggleClass('read', !entry.is_unread);
          }
        });

        // Update the feeds
        
        $.each(feedIds, function()
        {
          var feedId = this;
          var feedDom = $('.feed-' + feedId);
          var feed = feedDom.data('object');

          if (feed)
            feed.unread = unreadCounts[feed.id];
        });

        synchronizeFeedDom();

        // TODO: include more details (e.g. selected feed)
        showToast(l('Items marked as read'));
      }
      else
      {
        showToast(response.error.message, true);
      }
    }, 'json');
  };

  var refreshEntry = function(entryDom)
  {
    var entry = entryDom.data('object');

    entryDom.toggleClass('read', !entry.is_unread);
    entryDom.toggleClass('starred', entry.is_starred);
    entryDom.toggleClass('liked', entry.is_liked);
    entryDom.toggleClass('open', entry.is_expanded);

    var content = entryDom.find('.entry-content');

    if (!entry.is_expanded && content.length > 0)
    {
      collapseEntry(entryDom);
    }

    // Update the tags
    entryDom.find('.action-tag')
      .text(entry.tags.length ? l('Edit tags: %s', [ entry.tags.join(', ') ]) : l('Add tags'))
      .toggleClass('has-tags', entry.tags.length > 0);

    // Update 'like' count
    entryDom.find('.action-like')
      .text((entry.like_count < 1) ? l('Like') : l('Like (%s)', [entry.like_count]));

    if (entry.is_expanded)
    {
      if (content.length < 1)
        expandEntry(entryDom);
    }
    else // if (!entry.is_expanded)
    {
      if (content.length > 0)
        collapseEntry(entryDom);
    }
  };

  var createFeedDom = function(feed)
  {
    var feedCopy = jQuery.extend({}, feed);
    delete feedCopy.feeds; // No need to carry the entire tree around

    return $('<li />', { 'class' : 'feed feed-' + feed.id })
      .data('object', feedCopy)
      .append($('<div />', { 'class' : 'feed-item' })
        .append($('<span />', { 'class' : 'chevron' })
          .click(function(e)
          {
            $('.menu').hide();
            $('#menu-' + feed.type)
              .css( { top: e.pageY, left: e.pageX })
              .data('object', feedCopy)
              .show();

            e.stopPropagation();
          }))
        .append($('<div />', { 'class' : 'feed-icon ' + feed.type }))
        .append($('<span />', { 'class' : 'feed-title' })
          .text(feed.source))
        .attr('title', feed.source)
        .append($('<span />', { 'class' : 'feed-unread-count' }))
        .click(function() 
        {
          $('.feed.selected').removeClass('selected');
          $(this).closest('.feed').addClass('selected');

          reloadItems();
        }));
  };

  var updateFeedDom = function(allItems)
  {
    var selectedFeedDom = $('.feed.selected');
    var selectedFeedId = null;

    if (selectedFeedDom.length > 0)
      selectedFeedId = selectedFeedDom.data('object').id;

    $('#feeds').empty();

    var allItemsDom = createFeedDom(allItems);
    allItemsDom.addClass('subscriptions');
    if (allItems.feeds)
      allItemsDom.append(buildFeedDom(allItems.feeds));

    $('#feeds').append(allItemsDom);

    if (selectedFeedId)
      $('.feed-' + selectedFeedId).addClass('selected');
    else
      allItemsDom.addClass('selected');

    synchronizeFeedDom();
  };

  var synchronizeFeedDom = function()
  {
    $.each($('#feeds .feed'), function()
    {
      var feedDom = $(this);
      var feed = feedDom.data('object');

      if (!feed)
        return true;

      feedDom.find('.feed-unread-count').text(l('(%s)', [feed.unread]));
      feedDom.find('.feed-item').toggleClass('has-unread', feed.unread > 0);
    });

    var allItems = $('.subscriptions').data('object');

    var title = '>:(';
    if (allItems.unread > 0)
      title += ' (' + allItems.unread + ')';

    document.title = title;
  };

  var buildFeedDom = function(feeds)
  {
    var feedGroupDoms = $('<ul />');

    $.each(feeds, function(key, feed)
    {
      var feedGroupDom = createFeedDom(feed);
      if (feed.feeds)
        feedGroupDom.append(buildFeedDom(feed.feeds));

      feedGroupDoms.append(feedGroupDom);
    });

    return feedGroupDoms;
  };

  var refreshFeeds = function()
  {
    $.getJSON('?c=feeds', 
    {
    },
    function(response)
    {
      if (!response.error)
      {
        updateFeedDom(response.allItems);
        reloadItems();
      }
      else
      {
        showToast(response.error.message, true);
      }
    });
  };

  var updateEntry = function(entryDom, args)
  {
    var entry = entryDom.data('object');

    $.post('?c=articles', $.extend({ }, 
    { 
      toggleStatusOf : entry.id, 
      isStarred : entry.is_starred, 
      isUnread : entry.is_unread,
      isLiked : entry.is_liked
    }, args),
    function(response)
    {
      if (!response.error)
      {
        var deltaUnread = 0;
        if (entry.is_unread && !response.entry.is_unread)
          deltaUnread--;
        else if (!entry.is_unread && response.entry.is_unread)
          deltaUnread++;

        if (entry.is_liked && !response.entry.is_liked)
          entry.like_count--;
        else if (!entry.is_liked && response.entry.is_liked)
          entry.like_count++;

        if (deltaUnread != 0)
        {
          $.each($('.feed'), function()
          {
            var feedDom = $(this);
            var feed = feedDom.data('object');

            if (feed && feed.id == entry.source_id)
            {
              feed.unread += deltaUnread;

              feedDom.parents('.feed').each(function()
              {
                feedDom = $(this);
                feed = feedDom.data('object');

                feed.unread += deltaUnread;
              });

              return false;
            }
          });
        }

        entry.is_unread = response.entry.is_unread;
        entry.is_starred = response.entry.is_starred;
        entry.is_liked = response.entry.is_liked;

        refreshEntry(entryDom);
        synchronizeFeedDom();
      }
      else
      {
        showToast(response.error.message, true);
      }
    }, 'json');
  };

  var toggleStarred = function(entryDom)
  {
    updateEntry(entryDom, { isStarred: !entryDom.data('object').is_starred });
  };

  var toggleUnread = function(entryDom)
  {
    updateEntry(entryDom, { isUnread: !entryDom.data('object').is_unread });
  };

  var toggleLiked = function(entryDom)
  {
    updateEntry(entryDom, { isLiked: !entryDom.data('object').is_liked });
  };

  var editTags = function(entryDom, tags)
  {
    var entry = entryDom.data('object');
    
    $.post('?c=articles',
      { 
        setTagsFor : entry.id, 
        tags       : tags
      },
      function(response)
      {
        if (!response.error)
        {
          entry.tags = response.entry.tags;

          refreshEntry(entryDom);
        }
        else
        {
          showToast(response.error.message, true);
        }
      }, 'json');
  };

  var collapseAllEntries = function()
  {
    $.each($('#entries').find('.entry.open'), function()
    {
      var entryDom = $(this);
      var entry = entryDom.data('object');

      entry.is_expanded = false;
      refreshEntry(entryDom);
    });
  };

  var expandEntry = function(entryDom)
  {
    var entry = entryDom.data('object');

    var content = 
      $('<div />', { 'class' : 'entry-content' })
        .append($('<div />', { 'class' : 'article' })
          .append($('<a />', { 'href' : entry.link, 'target' : '_blank', 'class' : 'article-title' })
            .append($('<h2 />')
              .text(entry.title)))
          .append($('<div />', { 'class' : 'article-author' })
            .append('from ')
            .append($('<a />', { 'href' : entry.source_www, 'target' : '_blank' })
              .text(entry.source)))
          .append($('<div />', { 'class' : 'article-body' })
            .append(entry.content)))
        .append($('<div />', { 'class' : 'entry-footer'})
          .append($('<span />', { 'class' : 'action-star' })
            .click(function(e)
            {
              toggleStarred(entryDom);
            }))
          .append($('<span />', { 'class' : 'action-unread entry-action'})
            .text(l('Keep unread'))
            .click(function(e)
            {
              toggleUnread(entryDom);
            }))
          .append($('<span />', { 'class' : 'action-tag entry-action'})
            .text(entry.tags.length ? l('Edit tags: %s', [ entry.tags.join(', ') ]) : l('Add tags'))
            .toggleClass('has-tags', entry.tags.length > 0)
            .click(function(e)
            {
                var tags = prompt(l('Separate multiple tags with commas'), entry.tags.join(', '));
                if (tags != null)
                  editTags(entryDom, tags);
            }))
          .append($('<span />', { 'class' : 'action-like entry-action'})
            .text((entry.like_count < 1) ? l('Like') : l('Like (%s)', [entry.like_count]))
            .click(function(e)
            {
              toggleLiked(entryDom);
            }))
        )
        .click(function(e)
        {
          e.stopPropagation();
        });

    if (entry.author)
      content.find('.article-author')
        .append(' by ')
        .append($('<span />')
          .text(entry.author));

    // Links in the content should open in a new window
    content.find('.article-body a').attr('target', '_blank');

    entryDom.append(content);
  };

  var collapseEntry = function(entryDom)
  {
    entryDom.find('.entry-content').remove();
  };

  var getSelectedFeedId = function()
  {
    var feed = getSelectedFeed();
    if (!feed)
      return null;

    return feed.id;
  };

  var getSelectedFeed = function()
  {
    return $('.feed.selected').data('object');
  };

  var loadNextPage = function()
  {
    var continueAfter = $('#entries').data('continue');
    if (!continueAfter)
      return;

    var spinner = new Spinner({ width: 3, length: 5, lines: 9, radius: 5, corners: 2}).spin();
    $('.next-page').empty().append(spinner.el);
    
    $.getJSON('?c=articles',
    {
      fetch: getSelectedFeedId(),
      filter: $('.article-filter').val(),
      continue: continueAfter,
    },
    function(response)
    {
      if (!response.error)
      {
        appendEntries(response.entries, response.continue != null);
        $('#entries').data('continue', response.continue);
      }
      else
      {
        showToast(response.error.message, true);
      }
    });

    return continueAfter;
  };

  var reloadItems = function()
  {
    lastPageRequested = null;
    
    $.getJSON('?c=articles', 
    {
      fetch: getSelectedFeedId(),
      filter: $('.article-filter').val(),
    }, 
    function(response) 
    {
      $('#entries .entry').remove();

      if (!response.error)
      {
        appendEntries(response.entries, response.continue != null);
        $('#entries').data('continue', response.continue);

        // Update the 'new items' caption in the dropdown to reflect
        // the unread count

        var selected = getSelectedFeed();
        if (!selected)
          $('.filter-new').text(l('New items'));
        else if (selected.unread == 0)
          $('.filter-new').text(l('No new items'));
        else if (selected.unread == 1)
          $('.filter-new').text(l('1 new item'));
        else
          $('.filter-new').text(l('%1$s new items', [selected.unread]));
      }
      else
      {
        showToast(response.error.message, true);
      }
    });
  };

  var appendEntries = function(entries, canContinue)
  {
    var entriesDom = [];

    $.each(entries, function(key, entry) 
    {
      entry.is_expanded = false;

      var entryDom = $('<div />', { 'class' : 'entry' });

      entryDom
        .append($('<div />', { 'class' : 'entry-item' })
          .append($('<div />', { 'class' : 'action-star' })
            .click(function(e)
            {
              updateEntry($(this).closest('.entry'), { isStarred : !entry.is_starred });
              e.stopPropagation();
            }))
          .append($('<span />', { 'class' : 'entry-source' }).text(entry.source))
          .append($('<span />', { 'class' : 'entry-pubDate' })
            .text(getPublishedDate(entry.published)))
          .append($('<div />', { 'class' : 'entry-excerpt' })
            .append($('<h2 />', { 'class' : 'entry-title' }).text(entry.title))))
        .data('object', entry)
        .click(function() 
        {
          $('.entry.selected').removeClass('selected');
          entryDom.addClass('selected');

          if (prefs.singleItemMode)
          {
            var wasExpanded = entry.is_expanded;
            collapseAllEntries();

            if (!wasExpanded)
              entry.is_expanded = true;
          }
          else // if (!prefs.singleItemMode)
          {
            entry.is_expanded = !entry.is_expanded;
          }

          if (entry.is_unread && entry.is_expanded)
            updateEntry(entryDom, { isUnread : false }); // Mark as read

          refreshEntry(entryDom);
        });

      if (entry.summary)
      {
        entryDom.find('.entry-excerpt')
          .append($('<span />', { 'class' : 'entry-spacer' }).text(' - '))
          .append($('<span />', { 'class' : 'entry-summary' }).text(entry.summary));
      }

      refreshEntry(entryDom);
      entriesDom.push(entryDom);
    });

    $('.next-page').remove();

    if (canContinue)
    {
      entriesDom.push($('<div />', { 'class' : 'next-page' })
        .text(l('Continue'))
        .click(function(e)
        {
          loadNextPage();
        }));
    }
    
    $('#entries').append(entriesDom);
  };

  var renameSubscription = function(feed)
  {
    var newName = prompt(l('New name:'), feed.source);

    if (newName && newName != feed.source)
    {
      $.post('?c=feeds', 
      {
        renameSubscription : feed.id,
        newName : newName,
      },
      function(response)
      {
        if (!response.error)
        {
          updateFeedDom(response.allItems);
          showToast(l('Subscription successfully renamed to "%s"', [response.feed.title]), false);
        }
        else
        {
          showToast(response.error.message, true);
        }
      }, 'json');
    }
  };

  var createFolder = function(feed)
  {
    var folderName = prompt(l('Name of folder:'));
    if (folderName)
    {
      $.post('?c=feeds', 
      {
        createFolderUnder : feed.id,
        folderName : folderName,
      },
      function(response)
      {
        if (!response.error)
        {
          updateFeedDom(response.allItems);
          showToast(l('"%s" successfully added', [response.folder.title]), false);
        }
        else
        {
          showToast(response.error.message, true);
        }
      }, 'json');
    }
  };

  var unsubscribe = function(feed)
  {
    var message = (feed.type == 'feed')
      ? l('Unsubscribe from "%s"?', [feed.source])
      : l('Unsubscribe from all feeds under "%s"?', [feed.source]);

    if (confirm(message))
    {
      $.post('?c=feeds', 
      {
        unsubscribeFrom : feed.id,
      },
      function(response)
      {
        if (!response.error)
        {
          updateFeedDom(response.allItems);
          showToast(l('Successfully unsubscribed from "%s"', [feed.source]), false);
        }
        else
        {
          showToast(response.error.message, true);
        }
      }, 'json');
    }
  };

  var onMenuItemClick = function(contextObject, menuItem)
  {
    if (menuItem.hasClass('menu-rename-sub'))
      renameSubscription(contextObject);
    else if (menuItem.hasClass('menu-unsub'))
      unsubscribe(contextObject);
    else if (menuItem.hasClass('menu-sub'))
      subscribe(contextObject);
    else if (menuItem.hasClass('menu-new-folder'))
      createFolder(contextObject);
  };

  // Schedule routine updates (also handles initial feed update)

  (function feedUpdater() 
  {
    $.ajax(
    {
      url: '?c=feeds', 
      success: function(response) 
      {
        if (!response.error)
        {
          updateFeedDom(response.allItems);
          if (!itemsLoaded)
          {
            itemsLoaded = true;
            reloadItems();
          }
        }
      },
      complete: function() 
      {
        setTimeout(feedUpdater, 300000);
      }
    });
  })();

  // Menus

  $('html').click(function() 
  {
    $('.menu').hide();
  });

  $('.menu').click(function(event)
  {
    event.stopPropagation();
  });

  $('.menu li').click(function()
  {
    var item = $(this);
    var menu = item.closest('ul');

    menu.hide();
    onMenuItemClick(menu.data('object'), item);
  });
});
