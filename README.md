Bazukachan - A lightweight and full featured PHP imageboard.
========================================================

About
------------
This is the a fork of vichan/Bazukinha running on [27chan.org](https://27chan.org), a free light-weight, fast, highly configurable and user-friendly
imageboard software package. It is written in PHP and has few dependencies.

=======
In November 2017, Marcin Łabanowski (@czaks) retired as maintainer for personal reasons. His retirement may be temporary, but in his absence, Fredrick Brennan (@ctrlcctrlv), co-maintainer since 2013, and #3 in terms of number of commits, took his place as interim sole maintianer and point of contact. (See [issue #266](https://github.com/vichan-devel/vichan/issues/266))

*Security problems can be reported to Fredrick Brennan at his email: COPYPASTE \<AT\> KITTENS \<DOT\> PH.*
	
Vichan is still accepting patches, but there is at the moment no active development besides fixing security problems and other serious bugs as they emerge. Given the lack of active development, we strongly urge you to consider other imageboard packages. It is the opinion of the vichan development team that no new vichan imageboards should be deployed, and other imageboard packages, such as lynxchan, used instead.

History
------------

vichan is a fork of (now defunc'd) [Tinyboard](http://github.com/savetheinternet/Tinyboard),
a great imageboard package, actively building on it and adding a lot of features and other
improvements.

Some documentation may be found on our [wiki](https://github.com/vichan-devel/vichan/wiki). (feel free to contribute)

Requirements
------------
1.	PHP >= 5.6
2.	MySQL >= 5.6 or MariaDB
3.	[mbstring](http://www.php.net/manual/en/mbstring.installation.php) 
4.	[PHP GD](http://www.php.net/manual/en/intro.image.php)
5.	[PHP PDO](http://www.php.net/manual/en/intro.pdo.php)
6.	A Unix-like OS, preferrably FreeBSD or Linux

We try to make sure vichan is compatible with all major web servers. vichan does not include an Apache ```.htaccess``` file nor does it need one.

### Recommended
1.	MySQL >= 5.7
2.	ImageMagick (command-line ImageMagick or GraphicsMagick preferred).
3.	[APC (Alternative PHP Cache)](http://php.net/manual/en/book.apc.php),
	[XCache](http://xcache.lighttpd.net/) or
	[Memcached](http://www.php.net/manual/en/intro.memcached.php)

Contributing
------------
You can contribute to Bazukachan by:
*	Developing patches/improvements/translations and using GitHub to submit pull requests
*	Providing feedback and suggestions
*	Writing/editing documentation

Installation
-------------
See the [Installation Guide](https://github.com/fallenPineapple/NPFchan/wiki/Installation-Guide)

Please remember to change the administrator account password.

See also: [Configuration Basics](https://github.com/fallenPineapple/NPFchan/wiki/config).

Upgrade
-------
To upgrade from any version of Tinyboard or vichan or NFPchan:

Either run ```git pull``` to update your files if you use git, or replace all
your files in place (don't remove boards etc.) and then run ```install.php```.

To migrate from a Kusaba X board, use http://github.com/vichan-devel/Tinyboard-Migration

Support
--------

As it stands Bazukachan has no public support system.

### vichan support
vichan is still beta software -- there are bound to be bugs. If you find a
bug, please report it.

CLI tools
-----------------
There are a few command line interface tools, based on Tinyboard-Tools. These need
to be launched from a Unix shell account (SSH, or something). They are located in a ```tools/```
directory.

You actually don't need these tools for your imageboard functioning, they are aimed
at the power users. You won't be able to run these from shared hosting accounts
(i.e. all free web servers).

Oekaki
------
Bazukachan makes use of [wPaint](https://github.com/websanova/wPaint) for oekaki.

To enable oekaki, add all the scripts listed in `js/wpaint.js` to your `instance-config.php`.

WebM support
------------
Read `inc/lib/webm/README.md` for information about enabling webm.

bazukachan API
----------
Bazukachan provides by default a 4chan-compatible JSON API. For documentation on this, see:
https://github.com/vichan-devel/vichan-API/ .

License
--------
See [LICENSE.md](http://github.com/fallenPineapple/NPFchan/blob/master/LICENSE.md).
