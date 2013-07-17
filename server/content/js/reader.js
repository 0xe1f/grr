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
  var lastGPressTime = 0;
  var itemsLoaded = false;
  var prefs = 
  {
    singleItemMode: true,
  };

  var lastPageRequested = null;

  // Key bindings

  $(document)
    .bind('keypress', '', function()
    {
      $('.shortcuts').hide();
      $('.menu').hide();
      $('#floating-nav').hide();
    })
    .bind('keypress', 'n', function()
    {
      selectArticle(1);
    })
    .bind('keypress', 'p', function()
    {
      selectArticle(-1);
    })
    .bind('keypress', 'shift+n', function()
    {
      highlightFeed(1);
    })
    .bind('keypress', 'shift+p', function()
    {
      highlightFeed(-1);
    })
    .bind('keypress', 'shift+o', function()
    {
      openFeed($('.feed.highlighted'));
    })
    .bind('keypress', 'j', function()
    {
      openArticle(1);
    })
    .bind('keypress', 'k', function()
    {
      openArticle(-1);
    })
    .bind('keypress', 'o', function()
    {
      openArticle(0);
    })
    .bind('keypress', 'g', function()
    {
      lastGPressTime = new Date().getTime();
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
    .bind('keypress', 't', function()
    {
      editTags($('.entry.selected'));
    })
    .bind('keypress', 'v', function()
    {
      openLink($('.entry.selected'));
    })
    .bind('keypress', 'm', function()
    {
      if ($('.entry.selected').length)
        toggleUnread($('.entry.selected'));
    })
    .bind('keypress', 'shift+a', function()
    {
      markAllAsRead();
    })
    .bind('keypress', 'r', function()
    {
      refreshFeeds();
    })
    .bind('keypress', 'a', function()
    {
      if (isGModifierActive())
        openFeed($('.subscriptions'));
      else
        subscribe();
    })
    .bind('keypress', 'u', function()
    {
      toggleNavMode();
    })
    .bind('keypress', 'shift+?', function()
    {
      $('.shortcuts').show();
    });

  // Default click handler

  $('html').click(function() 
  {
    $('.shortcuts').hide();
    $('.menu').hide();
    $('#floating-nav').hide();
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

  $('button.navigate').click(function(e)
  {
    $('#floating-nav')
      .css( { top: $('button.navigate').offset().top, left: $('button.navigate').offset().left })
      .show();

    e.stopPropagation();
  });

  $('button.filter').click(function(e)
  {
    var topOffset = 0;
    var selected = $('#menu-filter').find('.selected-menu-item');

    $('.menu').hide();
    $('#menu-filter')
      .show();

    if (selected.length)
      topOffset += selected.position().top;

    $('#menu-filter')
      .css(
      {
        top: $(this).offset().top - topOffset, 
        left: $(this).offset().left,
      });

    e.stopPropagation();
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
    markAllAsRead();
  });

  $('.select-article.up').click(function()
  {
    openArticle(-1);
  });

  $('.select-article.down').click(function()
  {
    openArticle(1);
  });

  // Functions

  var l = function(str, args)
  {
    var localized = null;
    if (typeof grrStrings !== 'undefined' && grrStrings != null)
      localized = grrStrings[str];

    if (localized == null)
      localized = str; // No localization

    if (args)
      return vsprintf(localized, args);

    return localized;
  };

  var initHelp = function()
  {
    var categories = 
    [
      {
        'title': l('Navigation'),
        'shortcuts': 
        [
          { keys: l('j/k'),       action: l('open next/previous article') },
          { keys: l('n/p'),       action: l('scan next/previous article') },
          { keys: l('Shift+n/p'), action: l('scan next/previous subscription') },
          { keys: l('Shift+o'),   action: l('open subscription or folder') },
          { keys: l('g, then a'), action: l('open subscriptions') },
        ]
      },
      {
        'title': l('Application'),
        'shortcuts': 
        [
          { keys: l('r'), action: l('refresh') },
          { keys: l('u'), action: l('toggle navigation mode') },
          { keys: l('a'), action: l('add subscription') },
          { keys: l('?'), action: l('help') },
        ]
      },
      {
        'title': l('Articles'),
        'shortcuts': 
        [
          { keys: l('m'),       action: l('mark as read/unread') },
          { keys: l('s'),       action: l('star article') },
          { keys: l('t'),       action: l('tag article') },
          { keys: l('l'),       action: l('like article') },
          { keys: l('v'),       action: l('open link') },
          { keys: l('o'),       action: l('open article') },
          { keys: l('Shift+a'), action: l('mark all as read') },
        ]
      }
    ];

    var maxColumns = 2; // Number of columns in the resulting table

    // Build the table
    var table = $('<table/>');
    for (var i = 0, n = categories.length; i < n; i += maxColumns)
    {
      var keepGoing = true;
      for (var k = -1; keepGoing; k++)
      {
        var row = $('<tr/>');
        table.append(row);
        keepGoing = false;

        for (var j = 0; j < maxColumns && i + j < n; j++)
        {
          var category = categories[i + j];

          if (k < 0) // Header
          {
            row.append($('<th/>', { 'colspan': 2 })
              .text(category.title));

            keepGoing = true;
          }
          else if (k < category.shortcuts.length)
          {
            row.append($('<td/>', { 'class': 'sh-keys' })
              .text(category.shortcuts[k].keys + ':'))
            .append($('<td/>', { 'class': 'sh-action' })
              .text(category.shortcuts[k].action));

            keepGoing = true;
          }
          else // Empty cell
          {
            row.append($('<td/>', { 'colspan': 2 }));
          }
        }
      }
    }

    $('body').append($('<div />', { 'class': 'shortcuts' }).append(table));
  };

  var initMenus = function()
  {
    // Build the menus

    $('body')
    .append($('<ul />', { 'id': 'menu-root', 'class': 'menu' })
      .append($('<li />', { 'class': 'menu-new-folder' }).text(l("New folder…")))
      .append($('<li />', { 'class': 'menu-sub' }).text(l("Subscribe…"))))
    .append($('<ul />', { 'id': 'menu-filter', 'class': 'menu', 'data-dropdown': 'button.filter' })
      .append($('<li />', { 'class': 'menu-all-items group-filter' , 'data-value': 'all' }).text(l("All items")))
      .append($('<li />', { 'class': 'menu-new-items group-filter' , 'data-value': 'new' }).text(l("New items")))
      .append($('<li />', { 'class': 'menu-starred-items group-filter' , 'data-value': 'star' }).text(l("Starred"))))
    .append($('<ul />', { 'id': 'menu-feed', 'class': 'menu' })
      .append($('<li />', { 'class': 'menu-rename-sub' }).text(l("Rename subscription…")))
      .append($('<li />', { 'class': 'menu-unsub' }).text(l("Unsubscribe…"))))
    .append($('<ul />', { 'id': 'menu-folder', 'class': 'menu' })
      .append($('<li />', { 'class': 'menu-new-folder' }).text(l("New folder…")))
      .append($('<li />', { 'class': 'menu-sub' }).text(l("Subscribe…")))
      .append($('<div />', { 'class': 'divider' }))
      .append($('<li />', { 'class': 'menu-rename-sub' }).text(l("Rename subscription…")))
      .append($('<li />', { 'class': 'menu-unsub' }).text(l("Unsubscribe from all…"))));

    // Attach listeners

    $('.menu').click(function(event)
    {
      event.stopPropagation();
    });

    $('.menu li').click(function()
    {
      var item = $(this);
      var menu = item.closest('ul');

      var groupName = null;
      $.each(item.attr('class').split(/\s+/), function()
      {
        if (this.indexOf('group-') == 0)
        {
          groupName = this;
          return false;
        }
      });

      if (groupName)
      {
        $('.' + groupName).removeClass('selected-menu-item');
        item.addClass('selected-menu-item');
      }

      $(menu.data('dropdown')).text(item.text());

      menu.hide();
      onMenuItemClick(menu.data('object'), item);
    });
  };

  var toggleNavMode = function(floatedNavEnabled)
  {
    $('body').toggleClass('floated-nav', floatedNavEnabled);

    if ($('body').hasClass('floated-nav'))
      $('#floating-nav')
        .append($('.feeds-container'));
    else
      $('#reader')
        .prepend($('.feeds-container'));

    $.cookie('floated-nav', 
      $('body').hasClass('floated-nav'));
  };

  var getPublishedDate = function(unixTimestamp)
  {
    var now = new Date();
    var date = new Date(unixTimestamp * 1000);
    
    var sameDay = now.getDate() == date.getDate() 
      && now.getMonth() == date.getMonth() 
      && now.getFullYear() == date.getFullYear();

    return dateTimeFormatter(date, sameDay);
  };

  // which: < 0 to select previous; > 0 to select next
  var selectArticle = function(which, scrollIntoView)
  {
    if (which < 0)
    {
      if ($('.entry.selected').prev('.entry').length > 0)
        $('.entry.selected')
          .removeClass('selected')
          .prev('.entry')
          .addClass('selected');
    }
    else if (which > 0)
    {
      var selected = $('.entry.selected');
      if (selected.length < 1)
        next = $('#entries .entry:first');
      else
        next = selected.next('.entry');

      $('.entry.selected').removeClass('selected');
      next.addClass('selected');

      if (next.next('.entry').length < 1)
        loadNextPage(); // Load another page - this is the last item
    }

    scrollIntoView = (typeof scrollIntoView !== 'undefined') ? scrollIntoView : true;
    if (scrollIntoView)
      $('.entry.selected').scrollintoview({ duration: 0});
  };

  // which: < 0 to open previous; > 0 to open next; 0 to toggle current
  var openArticle = function(which)
  {
    selectArticle(which, false);

    if (!$('.entry-content', $('.entry.selected')).length || which === 0)
      $('.entry.selected')
        .click()
        .scrollintoview();
  };

  var highlightFeed = function(which, scrollIntoView)
  {
    var highlighted = $('.feed.highlighted');
    var next;

    if (which < 0)
    {
      var allFeeds = $('#feeds .feed');
      var highlightedIndex = allFeeds.index(highlighted);

      if (highlightedIndex - 1 >= 0)
        next = $(allFeeds[highlightedIndex - 1]);
    }
    else if (which > 0)
    {
      if (highlighted.length < 1)
        next = $('#feeds .feed:first');
      else
      {
        var allFeeds = $('#feeds .feed');
        var highlightedIndex = allFeeds.index(highlighted);

        if (highlightedIndex + 1 < allFeeds.length)
          next = $(allFeeds[highlightedIndex + 1]);
      }
    }

    if (next)
    {
      $('.feed.highlighted').removeClass('highlighted');
      next.addClass('highlighted');

      scrollIntoView = (typeof scrollIntoView !== 'undefined') ? scrollIntoView : true;
      if (scrollIntoView)
        $('.feed.highlighted').scrollintoview({ duration: 0});
    }
  };

  var isGModifierActive = function()
  {
    return new Date().getTime() - lastGPressTime < 1000;
  };

  var openFeed = function(feedDom)
  {
    if (!feedDom.length)
      return;

    $('.feed.selected').removeClass('selected');
    feedDom.addClass('selected');

    reloadItems();
  };

  var showToast = function(message, isError)
  {
    $('#toast span').text(message);
    $('#toast').attr('class', isError ? 'error' : 'info');

    if ($('#toast').is(':hidden'))
    {
      // Don't fade in & out if the toast is already visible
      $('#toast')
        .fadeIn()
        .delay(8000)
        .fadeOut('slow'); 
    }
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
        updateFeedDom(response.allItems);
        showToast(l('Successfully subscribed to "%s"', [response.feed.title]), false);
      }, 'json');
    }
  };

  var markAllAsRead = function()
  {
    var selected = getSelectedFeed();
    if (!selected)
      return;

    $.post('?c=articles', 
    { 
      toggleUnreadUnder: getSelectedFeedId(),
      filter: $('.group-filter.selected-menu-item').data('value'),
    },
    function(response)
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
          entry.is_unread = false;
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
    delete feedCopy.subs; // No need to carry the entire tree around

    if (typeof feedCopy.title === 'undefined')
      feedCopy.title = l('Subscriptions');

    return $('<li />', { 'class' : 'feed feed-' + feedCopy.id })
      .data('object', feedCopy)
      .append($('<div />', { 'class' : 'feed-item' })
        .append($('<span />', { 'class' : 'chevron' })
          .click(function(e)
          {
            $('.menu').hide();
            $('#menu-' + feedCopy.type)
              .css( { top: e.pageY, left: e.pageX })
              .data('object', feedCopy)
              .show();

            e.stopPropagation();
          }))
        .append($('<div />', { 'class' : 'feed-icon icon-' + feedCopy.type }))
        .append($('<span />', { 'class' : 'feed-title' })
          .text(feedCopy.title))
        .attr('title', feedCopy.title)
        .append($('<span />', { 'class' : 'feed-unread-count' }))
        .click(function() 
        {
          openFeed($(this).closest('.feed'));
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
    if (allItems.subs)
      allItemsDom.append(buildFeedDom(allItems.subs));

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

      feedDom.find('.feed-unread-count').text('(' + feed.unread + ')');
      feedDom.find('.feed-item').toggleClass('has-unread', feed.unread > 0);
    });

    // Update the title bar

    var allItems = $('.subscriptions').data('object');
    var title = '>:(';

    if (allItems.unread > 0)
      title += ' (' + allItems.unread + ')';

    document.title = title;
    
    updateUnreadCounts();
  };

  var updateUnreadCounts = function()
  {
    // Update the 'new items' caption in the dropdown to reflect
    // the unread count

    var selected = getSelectedFeed();
    var caption;

    if (!selected || selected.unread === null)
      caption = l('New items');
    else if (selected.unread == 0)
      caption = l('No new items');
    else if (selected.unread == 1)
      caption = l('1 new item');
    else
      caption = l('%1$s new items', [selected.unread]);

    var newItems = $('.menu-new-items');

    newItems.text(caption);
    if (newItems.is('.selected-menu-item'))
      $('.filter').text(caption);
  };

  var buildFeedDom = function(feeds)
  {
    var feedGroupDoms = $('<ul />');

    $.each(feeds, function(key, feed)
    {
      var feedGroupDom = createFeedDom(feed);
      if (feed.subs)
        feedGroupDom.append(buildFeedDom(feed.subs));

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
      updateFeedDom(response.allItems);
      reloadItems();
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

  var editTags = function(entryDom)
  {
    if (entryDom.length < 1)
      return;

    var entry = entryDom.data('object');
    var tags = prompt(l('Separate multiple tags with commas'), entry.tags.join(', '));

    if (tags != null)
    {
      $.post('?c=articles',
      { 
        setTagsFor : entry.id, 
        tags       : tags
      },
      function(response)
      {
        entry.tags = response.entry.tags;
        refreshEntry(entryDom);
      }, 'json');
    }
  };

  var openLink = function(entryDom)
  {
    if (entryDom.length > 0)
      $('.entry-link', entryDom)[0].click();
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
          // .append($('<span />', { 'class' : 'action-share-gplus entry-action'})
          //   .append($('<a />',
          //   {
          //     'href'    : 'https://plus.google.com/share?url=' + entry.link,
          //     'onclick' : "javascript:window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600');return false;"
          //   })
          //     .append($('<img />', 
          //     { 
          //       'src' : 'https://www.gstatic.com/images/icons/gplus-16.png', 
          //       'alt' : l('Share on Google+')
          //     }))
          //     .append($('<span />')
          //       .text(l('Share')))))
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
              editTags(entryDom);
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
      filter: $('.group-filter.selected-menu-item').data('value'),
      continue: continueAfter,
    },
    function(response)
    {
      var canContinue = typeof response.continue !== 'undefined';

      appendEntries(response.entries, canContinue);
      $('#entries').data('continue', canContinue ? response.continue : null);
    });

    return continueAfter;
  };

  var reloadItems = function()
  {
    lastPageRequested = null;

    $('.entries-container').scrollTop(0); // Scroll to top first, to prevent auto-paging
    $('#entries .entry').remove();

    var feed = getSelectedFeed();
    var feedHasLink = (typeof feed.link !== 'undefined') && (feed.link != null);

    if (!feedHasLink)
      $('.entries-header').text(feed.title);
    else
      $('.entries-header').html($('<a />', { 'href' : feed.link, 'target' : '_blank' })
        .text(feed.title)
        .append($('<span />')
          .text(' »')));

    $('.entries-container').toggleClass('single-feed', feedHasLink);

    $.getJSON('?c=articles', 
    {
      fetch: feed.id,
      filter: $('.group-filter.selected-menu-item').data('value'),
    }, 
    function(response) 
    {
      var canContinue = typeof response.continue !== 'undefined';

      appendEntries(response.entries, canContinue);
      $('#entries').data('continue', canContinue ? response.continue : null);
    });

    updateUnreadCounts();
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
          .append($('<a />', { 'class' : 'entry-link', 'href' : entry.link, 'target' : '_blank' })
            .click(function(e)
            {
              e.stopPropagation();
            }))
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
    var newName = prompt(l('New name:'), feed.title);

    if (newName && newName != feed.title)
    {
      $.post('?c=feeds', 
      {
        renameSubscription : feed.id,
        newName : newName,
      },
      function(response)
      {
        updateFeedDom(response.allItems);
        showToast(l('Subscription successfully renamed to "%s"', [response.feed.title]), false);
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
        updateFeedDom(response.allItems);
        showToast(l('"%s" successfully added', [response.folder.title]), false);
      }, 'json');
    }
  };

  var unsubscribe = function(feed)
  {
    var message = (feed.type == 'feed')
      ? l('Unsubscribe from "%s"?', [feed.title])
      : l('Unsubscribe from all feeds under "%s"?', [feed.title]);

    if (confirm(message))
    {
      $.post('?c=feeds', 
      {
        unsubscribeFrom : feed.id,
      },
      function(response)
      {
        updateFeedDom(response.allItems);
        showToast(l('Successfully unsubscribed from "%s"', [feed.title]), false);
      }, 'json');
    }
  };

  var onMenuItemClick = function(contextObject, menuItem)
  {
    if (menuItem.is('.menu-rename-sub'))
      renameSubscription(contextObject);
    else if (menuItem.is('.menu-unsub'))
      unsubscribe(contextObject);
    else if (menuItem.is('.menu-sub'))
      subscribe(contextObject);
    else if (menuItem.is('.menu-new-folder'))
      createFolder(contextObject);
    else if (menuItem.is('.menu-all-items, .menu-new-items, .menu-starred-items'))
    {
      reloadItems();
    }
  };

  // Schedule routine updates (also handles initial feed update)

  (function feedUpdater() 
  {
    $.ajax(
    {
      url: '?c=feeds', 
      success: function(response) 
      {
        updateFeedDom(response.allItems);
        if (!itemsLoaded)
        {
          itemsLoaded = true;
          reloadItems();
        }
      },
      complete: function() 
      {
        setTimeout(feedUpdater, 300000);
      }
    });
  })();

  if ($.cookie('floated-nav') === 'true')
    toggleNavMode(true);

  // Set up the JSON error handler
  $(document).ajaxError(function(event, jqxhr, settings, exception) 
  {
    var errorMessage;

    try 
    {
      var errorJson = $.parseJSON(jqxhr.responseText)
      errorMessage = errorJson.error.message;
    }
    catch (exception)
    {
      errorMessage = l("An unexpected error has occurred. Please try again later.");
    }

    showToast(errorMessage, true);
  });

  (function()
  {
    // Select first item in filter dropdown

    var menu = $('#menu-filter');
    var selected = menu.find('li:first');

    selected.addClass('selected-menu-item');
    $(menu.data('dropdown')).text(selected.text());
  })();

  initMenus();
  initHelp();
});
