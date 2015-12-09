=== Content Cards ===

Contributors: ideag, khromov
Tags: opengraph, open graph, oembed, link cards, snippet, rich snippet, content card
Donate link: http://arunas.co#coffee
Requires at least: 4.1.0
Tested up to: 4.4.0
Stable tag: 0.9.4
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embed any link from the web easily as a beautiful Content Card.

== Description ==

Content Cards make ordinary web links great by making it possible to embed a beautiful Content Card to link to any web site.

By using OpenGraph data, Content Cards grabs the title, description and associated image to the links you embed - it's similar to how you can embed other websites, such as YouTube, Twitter, SoundCloud and more.

For individual links, You can insert a Content Card via shortcode `[contentcards url="http://yoursite.com/article-1"]`. If you often use Content Cards for some website, you can register the domain name (for example: `yoursite.com`) as an oEmbed provider via Plugin's Settings page and then it will behave the same way other oEmbed providers do - you will just have to paste plaintext link to a separate paragraph an Content Card will be generated automatically.

This plugin was built by [Arūnas Liuiza](http://arunas.co) and [Stanislav Khromov](http://snippets.khromov.se/). It is being developed on [GitHub](http://github.com/ideag/content-cards). If you have any questions, issues, need support, or want to contribute, please let us know [here](http://github.com/ideag/content-cards/issues).

We are machines that convert coffee into code, so feel free to [buy us a cup](http://arunas.co#coffee). Or two. Or ten.

Also, please check out our other plugins:

* Arūnas: [Gust](http://arunas.co/gust), [tinyCoffee](http://arunas.co/tinycoffee), [tinySocial](http://arunas.co/tinysocial), [tinyTOC](http://arunas.co/tinytoc), [tinyIP](http://arunas.co/tinyip) (premium) and [Post Section Voting](http://arunas.co/gust)
* Stanislav: [WordPress Instant Articles](http://wordpress.org/plugins/instant-articles), [Content Template Widget for Toolset Views](https://wordpress.org/plugins/view-template-widget-for-toolset-types-views/), [Email Obfuscate Shortcode](https://wordpress.org/plugins/email-obfuscate-shortcode/), [English WordPress Admin](https://wordpress.org/plugins/english-wp-admin/), [Distraction Free Writing mode Themes](https://wordpress.org/plugins/distraction-free-writing-mode-themes/), [Thumbnail Upscale](https://wordpress.org/plugins/thumbnail-upscale/), [WP Users Media](https://wordpress.org/plugins/wp-users-media/) and [Views Output Formats](https://wordpress.org/plugins/views-output-formats/)

== Frequently Asked Questions ==

There are two ways of inserting Content Cards into WordPress posts - shortcode and oEmbed.

= Shortcode =

Shortcode is the simplest way - You just put `[contentcards url="http://yourdomain.com/article/1"]` into your post content and it gets replaced with a content card.
The shortcode accepts two attributes:

* `url` (requried) - link to the site you want to display Content Card for.
* `target` (optional) - if you want force links to open in new tab, use `target="_blank"` the same you would in actual links. This overrides the global option in Content Cards Settings page.

You can also insert the shortcode via a button in your visual editor. Start by pressing the Content Cards icon in WordPress visual editor's (TinyMCE) toolbar. If no other plugins are adding their buttons, our button should be the last one in the top toolbar.

= oEmbed = 

If You find that you are adding a lot of Content Cards from some single domain, You can save yourself some work, by white-listing that website as oEmbed provider in Content Card Settings page.

White-listed sites work the same way any other oEmbed provider in WordPress (YouTube, Twitter, SoundCloud, etc.) - You just need to put a plaintext link in a separate line in the WordPress editor and it will be replaced with a Content Card.

In Content Cards Settings page you can provide a list of white-listed sites. Put only domain name (`example.com`), one domain per line.

= Skins = 

Content Cards come with two default skins - `Default` and `Default Dark` - created by Stanislav Khromov. These skins are designed to provide minimal structural styling and blend in nicely with active theme by inheriting the font from the theme.

All skin template files can be found in `content-cards/skins/*` directory and they can be overwritten by providing the same template in active theme. For example, if you want to overwrite Content Cards stylesheet, You should add `content-cards.css` to Your theme directory.

Main skin template is `content-cards.php`. If no other skin templates are defined, Content Cards will fall back to this one, the same way WordPress falls back to `index.php`. If you want more granular templates, you can provide `content-cards-{$type}.php` templates, (`content-cards-website.php`, `content-cards-article.php`, etc.). `$type` is based on `og:type` meta data provided by website.

Content Cards provides five new template tags: `get_cc_data()`, `the_cc_data()` and `the_cc_target()`:

* `get_cc_data( $key, $sanitize = false )` - **returns** `$key` OpenGraph data field (i.e. 'title', 'description', etc.). If valid `$sanitize` function is provided, the data is escaped using it.
* `the_cc_data( $key, $sanitize = false )` - according to WordPress tradition, it **prints** the same data that `get_cc_data()` would return.
* `the_cc_target()` - a special helper function, that prints ` target="_blank"` to links if needed (according to plugin/shortcode settings). Usage: `<a href=""<?php the_cc_target() ?>>`.
* `get_cc_image( $size, $sanitize = false )` - *new in v0.9.1* - returns a link to image if there is one. Defaults to image, cached in Media Library, then to remote image. For cached images, you can use `$size` parameter to get specific WordPress image size.
* `the_cc_image( $size, $attrs = array() )` - *new in v0.9.1* - prints an image tag. Uses `get_cc_images()`.
* `the_cc_css_classes( $classes )` - *new in v0.9.4* - a filterable template tag to print out CSS classes for the main div of Content Card. Custom CSS classes can also be passed via `class` attribute in the shortcode 

Also since `v0.9.1` you can use `'favicon'` key in `get_cc_data()/the_cc_data()` to display favicon if the remote site provides one.

= Adding CSS classes to the content card container

**Filter** Adding a class is easy thanks to the `content_cards_css_classes` filter.

    add_filter('content_cards_css_classes', function($classes) {
        $classes[] = 'my_class';
        return $classes;
    });

**Shortcode** You can also add custom CSS classes on card-by-card basis using `class` attribute in the shortcode:

`[contentcards url="http://arunas.co" class="my_class"]`

= Requirements =

This plugin requires WP_Cron to be in proper working order.

= Override the default options =

If you are running this plugin on a multisite, you may wish to set site-wide settings and disable the Content Cards settings page on each separate blog.

To do this, you can use the `content_cards_options` hook, like this:

    add_filter('content_cards_options', function($data) {

        //Disable admin page
        $data['enable_admin_page'] = false;

        return $data;
    });

You can also override a number of other options using this hook. For example, here we set the theme to "default-dark":

    add_filter('content_cards_options', function($data) {

        //Disable admin page
        $data['skin'] = 'default-dark';

        return $data;
    });

== Installation ==

* Go to your admin area and select Plugins -> Add new from the menu.
* Search for "Content Cards".
* Click install.
* Click activate.

== Screenshots ==

1. A Content Card embedded in post content
2. Content-cards are fully integrated with the visual editor
3. The dialog to insert Content Cards shortcode into post content
4. The Content Cards settings page
5. The Content Cards appearance on the official "Twenty" family of WordPress themes

== Changelog ==

= 0.9.4 =

* bugfix - WordPress 4.4 breaks relative requires/includes, so converted them to absolute ones
* enhanced OpenGrahp image detection - uses first image if more than one is available
* cached images are now removed on plugin deactivation/removal
* added a toggle to disable local image cacheing

= 0.9.3 = 

* bugfix 'undefined' `download_url()` function.
* bugfix `force_absolute_url()` method to work correctly with protocol-agnostic (//domain.com) URIs.
* enhanced favicon detection mechanism.

= 0.9.2 = 

* fixes a bug where `wp-admin` became unaccessible due to 'undefined' `get_current_screen()` function

= 0.9.1 =

* New feature - Content Card images are now cached in Media Library.
* Added `'favicon'` key to display site icon.
* Content Cards' `max-width` limited to 600px via CSS. 
* Added an option to limit how many words should be displayed in `'description'`.
* Fixed a bug where non absolute URIs were provided for favicon and/or image in OG:data
* Added an icon for TinyMCE editor button.
* Shortcode loading screen now is configurable via Skins.

= 0.9.0 =

* initial release to `WordPress.org`
