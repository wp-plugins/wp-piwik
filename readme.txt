=== WP-Piwik ===
Contributors: braekling
Requires at least: 2.7
Tested up to: 2.8.4
Stable tag: 0.5.0
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6046779
Tags: statistics, stats, analytics, piwik

This plugin adds a piwik stats site to your WordPress dashboard.

== Description ==
This plugin adds a Piwik stats site to your WordPress dashboard. It's also able to add the Piwik tracking code to your blog using wp_footer.

You need a running Piwik installation and at least view access to your stats.

Look at the [Piwik website](http://piwik.org/) to get further information about Piwik.

*This plugin is not created or provided by the Piwik project team.*

License: GNU General Public License Version 3, 29 June 2007

Languages: English, German

== Installation ==
1. Upload the full `wp-piwik` directory into your `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Open the new 'Settings/WP-Piwik Settings' menu, enter your Piwik base URL and your auth token. Save settings.
4. If you have view access to multiple site stats, choose your blog and save settings again.
5. Look at 'Dashboard/WP-Piwik' to get your site stats.

== Screenshots ==

1. WP-Piwik stats page.
2. WP-Piwik settings.

== Changelog ==

= 0.5.0 =
    * Display statistics to selected user roles
    * Some HTML fixes (settings page)

= 0.4.0 =
    * Tracking filter added
    * Resolution stats
    * Operating System stats
    * Plugin stats

= 0.3.2 =
    * If allow_url_fopen is disabled in php.ini, WP-Piwik tries to use CURL instead of file_get_contents.

= 0.3.1 =
    * WordPress 2.8 compatible
    * Bugfix: Warnings on WP 2.8 plugins site
    * Dashboard revised
    * Partly optimized code

= 0.3.0 =
    * WP-Piwik dashboard widgetized.
    * Stats-boxes sortable and closeable.
    * German language file added
    * Browser stats and bounced visitors

= 0.2.0 =
    * First public version.
