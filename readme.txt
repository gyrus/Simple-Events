=== Simple Events ===
Contributors: gyrus
Donate link: http://www.babyloniantimes.co.uk/index.php?page=donate
Tags: events, custom post types
Requires at least: 3.0
Tested up to: 4.1
Stable tag: 0.2

Provides theme developers with simple extensions to sites with events as a custom post type.

== Description ==
If the plugin detects that there is a custom post type registered with a particular name (either 'event' or '*_event'), it steps in and does a number of useful additional things:

* For front-end queries fetching events, returned posts are sorted chronologically, the oldest first.
* For front-end queries fetching events, by default only future events are returned. This can be overridden by setting the custom parameter `slt_all_events` in your posts query to `true`. Alternatively, use `slt_past_events` (set to `true`) to get only past events. Also, because end dates were added recently, the start date is the default cut-off for 'future' events. In order to use end dates as the cut-off, set the constant `SLT_SE_END_DATE_IS_FUTURE_CUT_OFF` in your theme to `true`.
* For front-end queries fetching events, ordering with WordPress 4.2+ is always by start date, whatever the cut-off field. For below 4.2, ordering is by the cut-off field.
* By default the current time is used to compare dates for selecting past or future events. To change the time used for the cut-off, use the filter `slt_se_listing_time_offset`. It defaults to `0` (no change from the current time). To set the cut-off to 24 hours ahead of the current time, hook a function to this filter that returns that value in seconds, i.e. `60 * 60 * 24`.
* By default events are ordered chronologically. To reverse the order, set `slt_reverse_events` to `true`.
* In the admin listing page for events, an 'Event date' column is added.
* For particular queries, all actions performed by this plugin can be disabled by setting the custom parameter `disable_simple_events` in your posts query to `true`.
* If the event (start) date is being used for filtering and you want to use the automatic query var filtering provided in the Developer's Custom Fields plugin, set the constant `SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT` in your theme to the format that's being passed. Currently accepted values: `Y`, `mY`

**IMPORTANT:** The automatic event filtering only kicks in with front-end queries done using `WP_Query`. Use this rather than `get_posts`.

In addition, this function is provided for convenience. If an event date exists for the post in question, it returns that; if not, it returns the standard post date.

`<?php slt_se_get_date( $the_post = null, $date = 'start' ) ?>`

* **$the_post** (object) (optional) (default: global $post object)
* **$which_date** (string) (optional) (default: 'start') Pass 'end' to get end date

**NOTE:** An Event Date custom field will be added to the event edit screen automatically if [my Custom Fields plugin](http://sltaylor.co.uk/wordpress/plugins/slt-custom-fields/) is active. Otherwise, make sure your event post type supports `custom-fields` (see `[register_post_type](http://codex.wordpress.org/Function_Reference/register_post_type)`, and add dates to events with the format YYYY/MM/DD, e.g. 2011/12/21 - this format is required to allow sorting by this field.

**NOTE:** The filtering performed by this plugin currently doesn't work well with `get_posts` - for now, create custom loops with `WP_Query`.

Development code hosted at [GitHub](https://github.com/gyrus/Simple-Events).

== Installation ==
1. Upload the `simple-events` directory into the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==
= 0.3 =
* Added start/end date, plus times
* Added `SLT_SE_END_DATE_IS_FUTURE_CUT_OFF`
* Changed ordering to use WP 4.2+ syntax which allows for ordering by start date while cutting off by end date
* Changed `slt_se_get_date` to also handle end date
* Added `slt_se_date_to_timestamp()`
* Added `SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT`
* Made plugin translation-ready

= 0.2 =
* Added `slt_se_listing_time_offset` filter
* Fixed bug in `slt_se_get_date()` testing of `$date_parts` length
* More tests for Developer's Custom Fields functions

= 0.1.6 =
* Set the priority of the init action very low so that any custom post types are definitely registered first (thanks lowe_22!)

= 0.1.5 =
* Changed `slt_se_get_date` so the call to `slt_cf_field_value` works when a post object is passed

= 0.1.4 =
* Added `slt_past_events` and `slt_reverse_events` query vars
* Corrected mistake in adding admin listing event date column, needed to use post type in filter name

= 0.1.3 =
* Added a test for whether the query is singular - previously the hooks were preventing the display of past event singular pages

= 0.1.2 =
* Added `disable_simple_events` parameter for posts queries
* Added function to consolidate tests for whether a hook should be applied
* Changed order in `slt_se_parse_query` to `ASC` (nearest upcoming events first in list)
* Changed detection of event post type to "event" or "*_event"

= 0.1.1 =
* Changed date format to allow proper sorting
* Removed `posts_join` and `posts_where` filters for WP 3.1.1+ - doesn't seem to be necessary

= 0.1 =
* First version