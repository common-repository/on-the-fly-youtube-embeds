=== On The Fly YouTube Embeds ===
Contributors: Joe Anzalone
Donate link: http://JoeAnzalone.com/plugins/on-the-fly-youtube-embeds/
Tags: YouTube, video, embed, dynamic, URL, media
Requires at least: 2.0.2
Tested up to: 3.4.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Creates a page on your site that will play any YouTube video based on the requested URL without having to create a new page for each individual video.

== Description ==

**On The Fly YouTube Embeds** creates a page on your site that will play any YouTube video based on the requested URL without having to create a new page for each individual video.

You're then able to view any YouTube video on your site simply by navigating to a URL that includes a YouTube video ID.

This is useful for sites that want to host a lot of video content on YouTube, but provide a simple way for users to view them without leaving their site.

For example, if you were to navigate to something like `http://example.com/video/jOyFDvWf83w/` it would show you the YouTube video with the ID of `jOyFDvWf83w`

You could also replace that video ID in the URL with any other YouTube video ID and it'll work as well.

You can play any YouTube video on your site without having to manually create a new page for it. Point visitors to the appropriate URL and it just works.

== Installation ==

Extract the zip file and drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the plugin from the "Plugins" page.

== Screenshots ==
1. Settings screen
2. This page was dynamically created simply by visiting http://JoeAnzalone.com/video/SCsKRbChILA/

== Changelog ==

= 1.1.3 =
* Bug fix: 'Restore defaults upon plugin deactivation/reactivation' checkbox works again

= 1.1.2 =
* Bug fix: child pages no longer display blank video player if options have not been saved
* Bug fix: empty checkbox values will now save even when unchecked
* Video info retrieved via YouTube API is now cached for a user-defined amount of time
* Added 'youtube-embed' class to <body>
* Added .otfye div around video player and video description for easier styling
* Invalid video ID now sends a 404 HTTP status code
* Code refector: options and video information is now only retrieved once

= 1.1.1 =
* Allow customization of YouTube embed code with new textarea on settings page
* Added handy bookmarklet that generates video URLs for your site from YouTube
* Removed the "Edit Page" links from the site's front-end when viewing a YouTube video (for logged in users)
* Fixed "Undefined property" notices when WP_DEBUG is enabled
* Interface tweaks to options page
* Added "URL" tag to list of tags in readme.txt

= 1.1 =
* Added YouTube uploader whitelist option

= 1.0 =
* First public release

== Upgrade Notice ==

= 1.1.3 =
* Bug fix: 'Restore defaults upon plugin deactivation/reactivation' checkbox works again

= 1.1.2 =
* Bug fix: child pages no longer display blank video player if options have not been saved
* Bug fix: empty checkbox values will now save even when unchecked
* Video info retrieved via YouTube API is now cached for a user-defined amount of time
* Added 'youtube-embed' class to <body>
* Added <div class='otfye'> around video player and video description for easier styling
* Invalid video ID now sends a 404 HTTP status code
* Code refector: options and video information is now only retrieved once

= 1.1.1 =
* Allow customization of YouTube embed code with new textarea on settings page
* Added handy bookmarklet that generates video URLs for your site from YouTube
* Removed the "Edit Page" links from the site's front-end when viewing a YouTube video (for logged in users)
* Fixed "Undefined property" notices when WP_DEBUG is enabled
* Interface tweaks to options page
* Added "URL" tag to list of tags in readme.txt

= 1.1 =
* Added YouTube uploader whitelist option