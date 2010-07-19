=== WP-Piwik ===

Contributors: braekling

Requires at least: 2.9.1

Tested up to: 3.0.0

Stable tag: 0.8.0

Donate link: http://www.amazon.de/gp/registry/wishlist/111VUJT4HP1RA?reveal=unpurchased&filter=all&sort=priority&layout=standard&x=12&y=14
Tags: statistics, stats, analytics, piwik, wpmu

This plugin adds a piwik stats site to your WordPress or WordPressMU dashboard.


== Description ==

This plugin adds a Piwik stats site to your WordPress dashboard. It's also able to add the Piwik tracking code to your blog using wp_footer.

You need a running Piwik installation and at least view access to your stats.

Look at the [Piwik website](http://piwik.org/) to get further information about Piwik.

*This plugin is not created or provided by the Piwik project team.*

License: GNU General Public License Version 3, 29 June 2007

Languages: English, Albanian, Belorussian, Dutch, French, German, Swedish

= WPMU =
Version 0.6.0 includes experimental WPMU support.

**Experimental**

The WPMU support is currently experimental. Please test it on your own (e.g. using a local copy of your WPMU) before you use it in an user context.

**Simple**

Just add WP-Piwik to your /wp-content/plugins folder. So each user can enable WP-Piwik and use his own Piwik instance.

**Extended**

1. Add the whole WP-Piwik folder to /wp-content/mu-plugins.
2. Copy /wp-content/mu-plugins/wp-piwik/wpmu/wpmu-piwik.php to /wp-content/mu-plugins/wpmu-piwik.php.
3. Go to the WPMU-Piwik settings page and enter the Piwik URL and the auth token. You should use a clear Piwik installation and a token with full admin rights due to avoid conflicts. WPMU-Piwik will add a new site to Piwik each time a new blog is visited the first time.
4. Users have access to their own statistics, site admins can access each blog's statistics.

= Credits =

* Graphs powered by [jqPlot](http://www.jqplot.com/), an open source project by Chris Leonello. Give it a try! (License: GPL 2.0 and MIT)
* Albanian [sq] language file by [Besnik Bleta](http://blogu.programeshqip.org/).
* Belorussian [be_BY] language file by [FatCow](http://www.fatcow.com/).
* Dutch [nl_NL] language file by [Rene](http://www.pamukkaleturkey.com/).
* French [fr_FR] language file by Fab.
* Swedish [sv_SE] lanuage file by [EzBizNiz](http://ezbizniz.com/).

Thank you, guys!

== Installation ==

1. Upload the full `wp-piwik` directory into your `wp-content/plugins` directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.

3. Open the new 'Settings/WP-Piwik Settings' menu, enter your Piwik base URL and your auth token. Save settings.
4. If you have view access to multiple site stats, choose your blog and save settings again.
5. Look at 'Dashboard/WP-Piwik' to get your site stats.


== Screenshots ==

1. WP-Piwik settings.
2. WP-Piwik statistics page.
3. Closer look to a pie chart.

== Changelog ==

= 0.8.0 =
* Using jqPlot instead of Google Chart API
* Some facelifting
* Some minor bugfixes

= 0.7.1 =
* Track 404-pages in an own category
* Get some page (and article) details
* Language updates

= 0.7.0 =
* Bugfix: Percent calculation fixed
* Bugfix: Visitor chart: No label overlapping if < 50 visitory/day
* Visitor chart: Added a red unique visitor average line
* Visitor table: Added a TOTAL stats line
* Pie charts: Show top 9 + "others", new color range
* Option: Show Piwik shortcut in overview box
* Some performance optimization

= 0.6.4 =
* Unnecessary debug output removed
* German language file update
* WordPress dashboard widget: last 30 days view added

= 0.6.3 =
* Click at a visitor stats day-row to load its details.
* Add stats overview to your WordPress dashboard

= 0.6.0 =
* Added experimental WPMU support
* Switch to disable Google Chart API
* Added Albanian [sq] language file
* Added Belorussian [be_BY] language file

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
