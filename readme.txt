=== Content Cards ===

Contributors: ideag
Tags: opengraph, open graph, oembed, link cards, snippet, rich snippet
Donate link: https://arunas.co#coffee
Requires at least: 4.0.0
Tested up to: 4.3.0
Stable tag: 0.2.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin allows you to display links to other websites as rich snippets, utilizing the OpenGraph data that they provide.

You can insert a snippet via shortcode `[contentcards url="http://yoursite.com/article-1"]` or whitelist it as an oEmbed provider via plugnin's Settings page.

== Frequently Asked Questions ==

= Shortcode =

The shortcode accepts a single attribute - `url`, all other attributes and the content of shortcode will be ignored.

= oEmbed = 

Whitelist the site in question in the plugin's Settings and use it like any other oEmbed provider - just put plaintext link in a separate line. Whitelist accepts domain names, one name per line.

== Installation ==

* Go to your admin area and select Plugins -> Add new from the menu.
* Search for "Content Cards".
* Click install.
* Click activate.

== Changelog ==

= 0.1.0 =

* initial commit