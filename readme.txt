=== Plugin Name ===
Contributors: davidcochrum
Version: 1.0.0
Plugin URI: http://docof.me/shortn-it
Tags: url shortener, url shortening, shorturl, short_url, shortlink, short permalink, short url, custom short url, custom url
Requires at least: 2.5
Tested up to: 3.8.1
Stable tag: trunk
Author: David Cochrum
Author URI: http://www.docofmedia.com/

Shortn.It is a personal, self-hosted, URL shortener for WordPress.

== Description ==

Shortn.It is a personal, customizable, URL shortener. Using your own Wordpress (2.5+) installation, Shortn.It allows the user to create shortened, unique, permalinks to their content using a combination of lowercase, uppercase, and numeric characters, which originate from their own domain name or a shorter alias domain. By default Shortn.It generates a 5-character combination of lowercase letters only, for ease of use in entering on a mobile device or handset. Shortn.It also allows for a personalized string of characters to be used instead of a random one.

Shortn.It supports <a href="//sites.google.com/a/snaplog.com/wiki/short_url" title="read more about shorturl">shorturl auto discovery</a> and <a href="//microformats.org/wiki/rel-shortlink" title="Read more about shortlink">rel="shortlink"</a>.

Shortn.It provides easy functions for accessing it's generated URLs outside of Shortn.It, in your themes, or other plugins. The `the_full_shortn_url` will output just the full URL, without any formatting, which can be used in various Twitter or other microblogging plugins. `the_shortn_url_link` will output an HTML link using the configuration from Shortn.It's options page.

== Installation ==

Installing Shortn.It is a breeze.

1. Upload the `shortn-it` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Add the Shortn.It sidebar widget to a sidebar, or add `<?php the_shortn_url_link(); ?>` in your template where you'd like the shortened URL link to show up.
1. If you'd like to use a separate domain for your shortened links, <a href="#">see this article</a>.

== Screenshots ==

1. Shortn.It's Domains and Prefix options
2. Shortn.It's URL generation options
3. Shortn.It's auto-detection settings

== Changelog ==