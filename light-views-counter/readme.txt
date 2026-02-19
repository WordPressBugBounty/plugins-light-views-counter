=== Light Views Counter – Fast, Scalable View Counter for High-Traffic Sites ===
Contributors: themeruby
Tags: views, popular posts, statistics, counter, tracking
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Lightweight and fast post view counter with smart tracking, built for high-traffic sites and large post databases.

== Description ==

**Light Views Counter** is a professional, high-performance WordPress plugin that tracks post and page views using **intelligent scroll detection technology**.

Designed for **bloggers, news sites, magazines, and content creators**, this plugin helps you understand what content truly resonates with your audience.

Built for **speed and scalability**, Light Views Counter is optimized for **heavy-traffic websites** and large post databases. It delivers reliable view tracking **without adding query overhead** or slowing down your site’s performance.

= 🎯 Why Choose Light Views Counter? =

**Tracking**
* **Scroll Detection Technology** - Only counts views when visitors actually read your content (configurable scroll threshold)
* **Bot Protection** - Automatically filters out search engine crawlers and automated traffic
* **Duplicate Prevention** - Prevent counting the same user multiple times within a configurable time window
* **Short Content Smart Detection** - Intelligently handles posts that fit in viewport without requiring scroll

**Lightning Fast Performance**
* **Zero Impact on Page Speed** - Asynchronous REST API ensures counting happens in the background
* **Built-in Caching** - Transient-based caching system reduces database load
* **sendBeacon API** - Fire-and-forget counting for maximum performance (enabled by default)

**Easy to Use**
* **Automatic Tracking** - Works immediately after activation, no configuration required
* **Flexible Shortcode** - Display views anywhere with `[lightvc_post_views]` shortcode
* **Popular Posts Widgets** - Ready-to-use WordPress and Elementor widgets
* **Auto-Display Option** - Automatically show view counts at the end of posts

**Developer Friendly**
* **Clean Public API** - Simple functions: `lightvc_get_post_views()`, `lightvc_get_popular_posts()`
* **WP_Query Integration** - Sort posts by views: `'orderby' => 'lightvc_views'`
* **Hooks & Filters** - Customize everything: `lightvc_post_views_count`, `lightvc_views_html`, `lightvc_exclude_user`
* **REST API Endpoints** - HTTP access for external integrations

**Enterprise Ready**
* **High Traffic Optimized** - Tested on sites with millions of views per day
* **Cache Plugin Compatible** - Works perfectly with WP Rocket, W3 Total Cache, LiteSpeed Cache, Cloudflare
* **GDPR Compliant** - No personal data collection, no cookies, privacy-focused

= Perfect For =

* **Bloggers** - See which posts your readers love most
* **News Websites** - Track trending stories and breaking news engagement
* **Magazine Sites** - Identify top-performing content and popular topics
* **Content Marketers** - Measure content performance and reader engagement
* **E-commerce** - Track product page views and customer interest
* **Publishers** - Understand content performance across categories

= Key Features =

**Smart Counting System**
* Scroll-based view detection
* Automatic short content detection and handling
* Configurable time window to prevent duplicate counts
* Admin user exclusion (don't count your own views)
* Bot and crawler filtering

**Widgets & Integration**
* Standard WordPress widget for popular posts
* Display options: thumbnails, view counts, dates
* Fully customizable layouts
* Works in any widget area

**Analytics & Reporting**
* Admin dashboard with statistics
* Total views across all posts
* Most viewed posts list
* Average views per post
* Views column in posts list (sortable)

**Performance Features**
* Custom database table with optimized indexes
* Transient caching system
* Object cache support (Redis/Memcached)
* sendBeacon API for fire-and-forget requests
* Conditional script loading
* No external dependencies

**Developer Tools**
* WordPress hooks and filters
* view data REST API endpoints
* WP_Query orderby support
* Compatible with any theme

= 💻 For Developers =

Light Views Counter provides a complete developer toolkit:

**Basic Functions**

Get post views:
`<?php
$views = lightvc_get_post_views( $post_id );
echo number_format( $views ) . ' views';
?>`

Get popular posts:
`<?php
$popular = lightvc_get_popular_posts( array(
    'limit' => 10,
    'date_range' => 7  // Last 7 days
) );
?>`

**WP_Query Integration**

Sort posts by views:
`<?php
$query = new WP_Query( array(
    'post_type' => 'post',
    'orderby' => 'lightvc_views',
    'order' => 'DESC',
    'posts_per_page' => 10
) );
?>`

**Hooks & Filters**

Modify view count:
`add_filter( 'lightvc_post_views_count', 'my_custom_views', 10, 2 );`

Customize HTML output:
`add_filter( 'lightvc_views_html', 'my_custom_html', 10, 3 );`

Exclude specific users:
`add_filter( 'lightvc_exclude_user', 'my_user_exclusion' );`

Track view events:
`add_action( 'lightvc_views_counted', 'my_view_tracker' );`

**REST API Endpoints**

* `GET /wp-json/lightvc/v1/views/{post_id}` - Get view count for a post

= 📱 Shortcode Usage =

Display views anywhere using the flexible shortcode:

**Basic usage:**
`[lightvc_post_views]`

**With custom style:**
`[lightvc_post_views style="badge"]`

**With custom label:**
`[lightvc_post_views label="Total Reads"]`

**For specific post:**
`[lightvc_post_views post_id="123"]`

**All options:**
`[lightvc_post_views post_id="123" style="badge" label="Views" icon="👁️" show_label="true"]`

Available styles: `default`, `minimal`, `badge`, `compact`

= Translations & Compatibility =

**Language Support**
* English (default)
* Translation ready with .pot file included

**Theme Compatibility**
* Works with any WordPress theme
* Deep integration with Foxiz News theme

**Plugin Compatibility**
* **Cache Plugins**: WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, Autoptimize
* **CDN Services**: Cloudflare, StackPath, KeyCDN, BunnyCDN

= 🔒 Privacy & Security =

**GDPR Compliant**
* No personal data collection
* No cookies used
* No cross-site tracking
* localStorage is client-side only
* Complete transparency

**Security Features**
* Rate limiting to prevent abuse
* Admin capability checks

= 🔗 Useful Links =

* [Plugin Homepage](https://themeruby.com/light-views-counter)
* [Documentation](https://themeruby.com/light-views-counter/docs/)
* [ThemeRuby Website](https://themeruby.com)
* [Support Forum](https://wordpress.org/support/plugin/light-views-counter/)

== Installation ==

= Automatic Installation (Recommended) =

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "Light Views Counter"
4. Click **Install Now** button
5. Click **Activate** button
6. Done! The plugin starts tracking views immediately

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Click **Choose File** and select the downloaded ZIP file
5. Click **Install Now**
6. Click **Activate Plugin**

= Manual Upload via FTP =

1. Download and extract the plugin ZIP file
2. Upload the `light-views-counter` folder to `/wp-content/plugins/` directory via FTP
3. Log in to WordPress admin dashboard
4. Navigate to **Plugins** page
5. Find **Light Views Counter** and click **Activate**

= Configuration (Optional) =

After activation, you can customize settings:

* Navigate to **Settings > Light Views Counter**
* **With Foxiz Theme**: Navigate to **Foxiz Admin > Light Views Counter**

The plugin features a modern admin interface with **AJAX auto-save** (settings save automatically), toast notifications, and organized sections.

**Available Settings:**

*Tracking Settings:*
* **Scroll Threshold** (default: 50%) - How far users must scroll before counting a view
* **Time Window** (default: 1800 seconds / 30 minutes) - Prevents duplicate counts from same user
* **Supported Post Types** (default: Posts) - Select which post types to track (Posts, Pages, Custom Types)
* **Fast Mode** (default: Enabled) - Uses sendBeacon API for maximum performance

*Performance Settings:*
* **Enable Caching** (default: Enabled) - Cache view counts for better performance
* **Cache Duration** (default: 300 seconds / 5 minutes) - How long to cache data
* **Query Method** (default: Subquery) - Choose Subquery (<100k posts) or JOIN (100k+ posts) for orderby queries

*Display Settings:*
* **Show Views on Content** (default: Disabled) - Auto-display view count at end of posts

*API Settings:*
* **Enable GET Endpoint** (default: Disabled) - Allow public REST API access to view counts

= Getting Started =

1. **Verify Tracking** - Visit any post on your site, scroll down, then check the admin dashboard statistics
2. **Add Widgets** - Go to **Appearance > Widgets** and add "Popular Posts (Light Views Counter)"
4. **View Statistics** - Check the Views column in your Posts list (admin area)

That's it! Your site is now tracking post views intelligently.

== Frequently Asked Questions ==

= General Questions =

= Will this plugin slow down my website? =

Absolutely not! Light Views Counter is designed for **zero performance impact**:

* View counting happens **asynchronously** after page load
* Uses **sendBeacon API** for fire-and-forget requests
* Scripts only load on single posts/pages (not on homepage or archives)
* Built-in caching reduces database load
* Tested on high-traffic sites with millions of views

Your visitors won't notice any difference in page load speed.

= Is this plugin GDPR compliant? =

Yes, 100% GDPR compliant! Light Views Counter:

No privacy policy update needed.

= Does it work with caching plugins? =

Yes! Light Views Counter is specifically designed to work with all major caching plugins:

* WP Rocket ✓
* W3 Total Cache ✓
* WP Super Cache ✓
* LiteSpeed Cache ✓
* Autoptimize ✓
* Cloudflare ✓

View counting uses REST API which automatically bypasses page cache.

= What happens when I deactivate or delete the plugin? =

**Deactivation**: View data is preserved, tracking stops temporarily
**Deletion**: All plugin data (including view counts) is permanently removed from database

= Counting & Tracking =

= How does the smart counting work? =

Light Views Counter uses **intelligent scroll detection**:

**For long posts:**
* Waits until visitor scrolls to threshold (default: 50%)
* Confirms visitor is actually reading content
* Filters out bots and accidental clicks

**For short posts:**
* Automatically detects content fits in viewport
* Counts after 1 seconds (confirms visitor sees content)
* No need to wait for scroll that won't happen

This ensures only **genuine engagement** is counted.

= Why are my view counts lower than other analytics? =

This is actually a **good thing**! Here's why:

**Light Views Counter counts:** Real engagement (people who actually read)
**Google Analytics counts:** Every page load (including bounces, bots, accidental clicks)

Lower, more accurate numbers give you **true insights** into content performance.

= Will it count the same visitor multiple times? =

Not within the configured time window (default: 1800 seconds / 30 minutes):

* Uses **localStorage** to remember recently viewed posts
* Prevents counting page refreshes and back-button clicks
* Configurable time window in settings (in seconds)
* Respects user privacy (no cookies)

This gives you **accurate unique views** per time period.

= Does it count bot traffic? =

No! Bots are automatically filtered. Your view counts reflect **real human readers** only.

= How do I display view counts? =

You have **4 flexible options**:

**1. Shortcode (easiest):**
`[lightvc_post_views]` - Add anywhere in post content

**2. Automatic display:**
Enable in Settings > Light Views Counter

**3. PHP function:**
`<?php echo lightvc_get_post_views( get_the_ID() ); ?>`

**4. Widgets:**
Use Popular Posts widget in sidebars

= How do I add a Popular Posts widget? =

**For WordPress:**
1. Go to Appearance > Widgets
2. Drag "Popular Posts (Light Views Counter)" to widget area
3. Configure options and save

**For Elementor:**
1. Edit page with Elementor
2. Search for "Popular Posts (Light Views Counter)"
3. Drag widget to desired location
4. Customize styling

= Can I show popular posts from specific time periods? =

Yes! Both widgets support:

* All time
* Last 7 days
* Last 15 days
* Last 30 days

Perfect for showing "Trending Now" vs "All-Time Popular" sections.

= Is there an API for developers? =

Yes! Comprehensive developer tools:

**PHP Functions:**
* `lightvc_get_post_views( $post_id )`
* `lightvc_get_popular_posts( $args )`

**WP_Query Integration:**
* `'orderby' => 'lightvc_views'`

**Hooks & Filters:**
* `lightvc_post_views_count`
* `lightvc_views_html`
* `lightvc_exclude_user`
* `lightvc_views_counted`

**REST API:**
* `GET /wp-json/lightvc/v1/views/{id}`
* `POST /wp-json/lightvc/v1/count`

== Changelog ==

= 1.1.0 =

* Security Enhancements

= 1.0.0  =

**Initial Release**
