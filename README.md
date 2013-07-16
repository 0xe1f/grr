grr >:(
=======

**grr** is an attempt to replace the gaping hole that the departure of [Google Reader] [1] has left in my daily routine. Its (initially temporary) name is an abbreviation for _Google Reader Replacement_, but seeing as how it also doubles as a decent onomatopoeic representation of my feelings towards the discontinuation of one of Google's most useful products, it's now the app's de-facto name.

_grr_ consists of two components - the web application, with which the user interacts, and the background updater, which routinely updates feeds in the background.

![Screenshot](http://i.imgur.com/WtY2LAT.png "Screenshot")

See a (rather outdated) video of it here: http://www.youtube.com/watch?v=6gIGjweNu7Q

Clients
-------

There's an [official Android client] [9] for _grr_.

Features
--------

Primary focus of _grr_ was to replicate the core functionality of [Google Reader] [1] - specifically, features like starring, reading/unreading, navigation, and subscription categories. To that extent, _grr_ supports:

* Subscription categories
* Starring
* Marking as read/unread (single articles as well as entire views)
* Filtering by status (All/Unread/Starred)
* Paging
* Tagging
* Simple navigation using Previous/Next buttons
* Keyboard shortcuts
* Ability to subscribe to individual feeds

Additional features include:

* [Localization support](LOCALIZATION.md)
* Infinite nesting for subscription categories
* Article 'liking', along with 'like' counts (this feature was available on Google Reader at some point)
* Regular client-side update polling
* Ability to mass-import feeds from OPML-formatted documents (such as those generated by [Google Takeout] [7])
* Support for any number of user accounts
* Built-in [OpenID] [6] support
* Choice of two background updaters - standard sequential updater written in [PHP] [2], or high-performance concurrent updater written in [Go] [10]

Requirements
------------

On the server side, _grr_ requires:

* [Apache] [8] server with [PHP] [2] support
* [MySQL] [3] server
* [cron] [4], or any other scheduling service to routinely update feed information
* (optional) [Go] [10] runtimes with the [go-mysql-library] [11] package to use the alternative concurrent feed updater, instead of the default sequential updater written in PHP

On the client side, _grr_ relies heavily on [JavaScript] [5] and probably needs a decent modern browser.

Installation
------------

To install:

1. Copy the contents of the [server](server) directory to a www-accessible directory
2. Copy the contents of the [shell](shell) directory to a private area not accessible via www
3. Import the MySQL schema in [mysql/schema_full.sql](etc/mysql/schema_full.sql) to a MySQL database
4. Rename web application's [default.config.php](server/include/default.config.php) to 'config.php' and set all the necessary configuration information
5. Rename shell application's [default.config.php](shell/default.config.php) to 'config.php' and set the timezone and database configuration
6. Log in to the web application and create a new administrative account
7. Schedule [crawl.sh](shell/crawl.sh) to run routinely

Note that the web application, the database and shell application components do not have to reside on the same system.

Creating User Accounts
----------------------

Depending on configuration, _grr_ can allow anyone to create a new account (see the "CREATE_UNKNOWN_ACCOUNTS" setting in the configuration, disabled by default), or require a "welcome token" link to create a new account. Welcome token links can be generated in the Administrator section of the application and are valid for 2 weeks.

Help Needed
-----------

Want to see support for your language/region? _grr_ can now be extended to support any language/locale - [see documentation](LOCALIZATION.md) for more information and examples.

Limitations
-----------

_grr_ is currently in a late-beta stage, and as a result:

* The design of the web application is atrocious, because _dammit Jim, I'm a programmer, not a designer!_
* Administrative features were mostly an afterthought and could certainly do with a redesign/overhaul

  [1]: http://www.google.com/reader/  "Google Reader"
  [2]: http://us.php.net/ "PHP"  
  [3]: http://www.mysql.com/ "MySQL"
  [4]: http://en.wikipedia.org/wiki/Cron "cron"
  [5]: http://en.wikipedia.org/wiki/JavaScript "JavaScript"
  [6]: http://openid.net/ "OpenID"
  [7]: https://www.google.com/takeout/ "Google Takeout"
  [8]: http://httpd.apache.org/ "Apache"
  [9]: https://github.com/melllvar/angrroid "angrroid"
  [10]: http://golang.org/ "Go"
  [11]: https://code.google.com/p/go-mysql-driver/ "go-mysql-driver"
