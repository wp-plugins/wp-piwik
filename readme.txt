=== WP-Piwik ===

Contributors: Braekling
Requires at least: 3.3
Tested up to: 3.3.1
Stable tag: 0.9.1
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6046779
Tags: statistics, stats, analytics, piwik, wpmu

This plugin adds a Piwik stats site to your WordPress or WordPress multisite dashboard.

== Description ==

This plugin adds a Piwik stats site to your WordPress dashboard. It's also able to add the Piwik tracking code to your blog using wp_footer.

**You need a running Piwik (at least 1.6) installation** and at least view access to your stats. Also PHP 5 or higher is strictly required.

Look at the [Piwik website](http://piwik.org/) to get further information about Piwik.

*This plugin is not created or provided by the Piwik project team.*

Languages: English, German, Albanian, Azerbaijani, Belorussian, Dutch, French, Greek, Russian, Swedish, Norwegian

*Note: If you vote "It's broken", please tell me about your problem. It's hard to fix a bug I don't know about! ;-)*

= WP multisite =

See section "Installation".

= Credits =

* Graphs powered by [jqPlot](http://www.jqplot.com/), an open source project by Chris Leonello. Give it a try! (GPL 2.0 and MIT)
* Metabox support inspired by [Heiko Rabe's metabox demo plugin](http://www.code-styling.de/english/how-to-use-wordpress-metaboxes-at-own-plugins).
* Albanian [sq] language file by [Besnik Bleta](http://blogu.programeshqip.org/).
* Azerbaijani [az_AZ] language file by [Galina Miklosic](http://www.webhostinggeeks.com).
* Belorussian [be_BY] language file by [FatCow](http://www.fatcow.com/).
* Dutch [nl_NL] language file by [Rene](http://www.pamukkaleturkey.com/).
* French [fr_FR] language file by Fab.
* Greek [gr_GR] language file by [AggelioPolis](http://www.aggeliopolis.gr).
* Russian [ru_RU] language file by [Natalya](http://www.luxpar.de).
* Swedish [sv_SE] language file by [EzBizNiz](http://ezbizniz.com/).
* Norwegian [nb_NO] language file by Gormer.
* Donations: Marco L., Rolf W., Tobias U., Lars K., Donna F., the Piwik team itself, and all people flattering this.
* All users who send me mails containing criticism, commendation, feature requests and bug reports - you help me to make WP-Piwik much better!

Thank you all!

== Installation ==

= Install WP-Piwik on a simple WordPress blog =

1. Upload the full `wp-piwik` directory into your `wp-content/plugins` directory.

2. Activate the plugin through the 'Plugins' menu in WordPress. 

3. Open the new 'Settings/WP-Piwik Settings' menu, enter your Piwik base URL and your auth token. Save settings.

4. If you have view access to multiple site stats and did not enable "auto config", choose your blog and save settings again.

5. Look at 'Dashboard/WP-Piwik' to get your site stats.

= Install WP-Piwik on a WordPress blog network (WPMU/WP multisite) =

There are two differents methods to use WP-Piwik in a multisite environment:
* As a Site Specific Plugin it behaves like a plugin installed on a simple WordPress blog. Each user can enable, configure and use WP-Piwik on his own. Users can even use their own Piwik instances (and accordingly they have to). 
* Using WP-Piwik as a Network Plugin equates to a central approach. A single Piwik instance is used and the site admin configures the plugin completely. Users are just allowed to see their own statistics, site admins can see each blog's stats.

** Site Specific Plugin **

Just add WP-Piwik to your /wp-content/plugins folder and enable the Plugins page for individual site administrators. Each user has to enable and configure WP-Piwik on his own if he want to use the plugin.

** Network Plugin **

The Network Plugin support is still experimental. Please test it on your own (e.g. using a local copy of your WP multisite) before you use it in an user context.

Add WP-Piwik to your /wp-content/plugins folder and enable it as [Network Plugin](http://codex.wordpress.org/Create_A_Network#WordPress_Plugins). Users can access their own statistics, site admins can access each blog's statistics and the plugin's configuration.

== Screenshots ==

1. WP-Piwik settings.
2. WP-Piwik statistics page.
3. Closer look to a pie chart.

== Changelog ==

= 0.9.1 =
* Bugfix: Usage as "Site Specific Plugin" [mixed up the different sites settings](http://wordpress.org/support/topic/plugin-wp-piwik-as-simple-plugin-with-multisite-fills-auth-with-last-used-token) (network mode)
* Hotfix: Avoid "Unknown site/blog" message without giving a chance to choose an existing site. Thank you, Taimon!

= 0.9.0 =
* Auto-configuration
* No code change required to enable WPMU mode anymore (Still experimental. Please create a backup before trying 0.9.0!)
* All features in WPMU available
* Bugfix: Removed unnecessary API calls done with each site request - Thank you, Martin B.!
* Bugfix: [No stats on dashboard](http://wordpress.org/support/topic/no-stats-on-dashboard-new-install) (sometimes this issue still occured, should be fixed now)
* Code cleanup (still not finished)
* Minor UI fixes
* Minor language/gettext improvements
* Security improvements
* Show SEO rank stats (very slow, caching will be added in 0.9.1)
* WordPress dashboard SEO rank widget (very slow, caching will be added in 0.9.1)
* New option: use js/index.php
* New option: avoid mod_security
* Mulisite: Order blog list alphabetically (Network Admin stats site)
* Settings: Order site list alphabetically (site list shown if order conf is disabled)

= 0.8.10 =
* jqplot update (IE 9 compatibility) - Thank you, Martin!
* Bugfix: [No stats on dashboard](http://wordpress.org/support/topic/no-stats-on-dashboard-new-install)
* Layout fix: [Graph width on dashboard](http://wordpress.org/support/topic/stats-graph-in-dashboard-changed)
* Minor code cleanup

= 0.8.9 =
* WP 3.2 compatible, metabox support

= 0.8.8 =
* Bugfix: Will also work with index.php in Piwik path
* Bugfix: last30 dashboard widget - show correct bounce rate

= 0.8.7 =
* New language files (Azerbaijani, Greek, Russian)
* Fixed hardcoded database prefix (WPMU-Piwik)
* Minor bugfixes: avoid some PHP warnings

= 0.8.6 =
* Added an optional visitor chart to the WordPress dashboard
* [WPMU/multisite bug](http://wordpress.org/support/topic/plugin-wp-piwik-multisite-update-procedure) fixed
* Minor bugfixes

= 0.8.5 =
* Select default date (today or yesterday) shown on statistics page
* Bugfix: Shortcut links are shown again
* German language file update
* Minor optical fixes (text length)

= 0.8.4 =
* New stats in overview box
* WP 3.x compability fixes (capability and deprecated function warnings)
* Some minor bugfixes
* New config handling
* Code clean up (not finished)

= 0.8.3 =
* Piwik 1.1+ compatibility fix

= 0.8.2 =
* Bugfix: [WPMU URL update bug](http://wordpress.org/support/topic/plugin-wp-piwik-jscode-not-updated-when-saving-new-url-in-wpmu-mode)

= 0.8.1 =
* Use load_plugin_textdomain instead of load_textdomain
* Fixed js/css links if symbolic links are used
* Changed experimental WPMU support to experimental WP multisite support
* Try curl() before fopen() to avoid an [OpenSSL bug](http://wordpress.org/support/topic/plugin-wp-piwik-problems-reaching-an-ssl-installation-of-piwiki)
* Added Norwegian language file by Gormer.
* Don't worry - new features will follow soon. ;)

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
