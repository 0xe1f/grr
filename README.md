grr >:(
=======

**grr** is an attempt at replacing the gaping hole that the impending departure of [Google Reader] [1] will leave in my daily routine. Its (initially temporary) name is an abbreviation for _Google Reader Replacement_, but seeing as how it also doubles as a decent onomatopoeic representation of my feelings towards the discontinuation of one of Google's most useful products, it's now the app's de-facto name.

_grr_ consists of two components - the web server application, which interacts with the user, and the shell application, which routinely updates feeds in the background.

Features
--------

Primary focus of _grr_ was replicating the core functionality of [Google Reader] [1] - specifically, features like starring, reading/unreading, navigation, and feed hierarchy. To that extent, _grr_ supports:

* Feed hierarchy (nesting feeds inside folders)
* Starring
* Marking as read/unread (single articles as well as entire views)
* Filtering by status (All/Unread/Starred)
* Paging

It should eventually support the rest of Reader's core features - such as sharing, tagging and searching.

Requirements
------------

On the server side, _grr_ requires:

* Web server with [PHP] [2] support
* [MySQL] [3] server
* [cron] [4], or any other scheduling service to routinely update feed information

On the client side, _grr_ relies heavily on [JavaScript] [5] and probably a decent modern browser.

Installation
------------

To install:

1. Copy the contents of the [server](server) directory to a www-accessible directory
2. Copy the contents of the [shell](shell) directory to a private area not accessible via www
3. Import the MySQL schema in [schema.mysql](etc/schema.mysql) to a MySQL database
4. Rename web application's [default.config.php](server/include/default.config.php) to 'config.php' and set all the necessary configuration information
5. Rename shell application's [default.config.php](shell/default.config.php) to 'config.php' and set the timezone and database configuration

Note that the web application, the database and shell application do not have to reside on the same system.

Post-Install
------------

1. Verify that the web application is accessible by navigating to its directory
2. Once you create an account and import an OPML file, run [crawl.sh](shell/crawl.sh) to make sure that the crawler works as expected
3. Schedule [crawl.sh](shell/crawl.sh) to run routinely

Creating User Accounts
----------------------

Depending on configuration, _grr_ can create new accounts for any incoming user (see the "CREATE_UNKNOWN_ACCOUNTS" setting, disabled by default), or require a "welcome token" link to create new accounts. Welcome token links can be generated in the Administrator section of the application and are valid for 2 weeks - users following such a link are allowed to create a _grr_ account following successful authentication.

Limitations
-----------

_grr_ is currently in a late-alpha/early-beta stage, and as a result:

* The only way to import feeds is by using the Import subscriptions tool to import data from Google Takeout (or any other OPML-formatted file). There are currently no facilities to add singular feeds/folders.
* The design of the web application is atrocious, since dammit Kirk, I'm a programmer, not a designer!
* Administrative features were mostly an afterthought and could certainly do with a redesign/overhaul
* Authentication without [OpenID] [6] is currently not available

  [1]: http://www.google.com/reader/  "Google Reader"
  [2]: http://us.php.net/ "PHP"  
  [3]: http://www.mysql.com/ "MySQL"
  [4]: http://en.wikipedia.org/wiki/Cron "cron"
  [5]: http://en.wikipedia.org/wiki/JavaScript "JavaScript"
  [6]: http://openid.net/ "OpenID"