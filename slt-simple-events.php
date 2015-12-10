<?php

/**
 * @package Simple Events
 */

/*
Plugin Name: Simple Events
Plugin URI: http://wordpress.org/extend/plugins/simple-events/
Description: If a custom post type called "event" or "*_event" is registered, this plugin steps in and does the rest for simple event functionality.
Author: Steve Taylor
Version: 0.3
Author URI: http://sltaylor.co.uk
License: GPLv2
*/

/* Inspired by code in Professional WordPress Development by Brad Williams, Ozh Richard and Justin Tadlock */

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
global $slt_se_plugin_slug;
$slt_se_plugin_slug = 'slt-simple-events';

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	_e( "Hi there! I'm just a plugin, not much I can do when called directly.", $slt_se_plugin_slug );
	exit;
}

// Initialize - low priority so that post types are already registered before this runs
add_action( 'init', 'slt_se_init', 100000 );
function slt_se_init() {
	global $slt_se_plugin_slug;

	$slt_se_event_post_type = '';
	if ( post_type_exists( 'event' ) ) {
		$slt_se_event_post_type = 'event';
	} else {
		foreach ( get_post_types( array( 'public' => true, '_builtin' => false ) ) as $cpt ) {
			if ( preg_match( '#[^_]+_event#', $cpt ) ) {
				$slt_se_event_post_type = $cpt;
				break;
			}
		}
	}
	define( 'SLT_SE_EVENT_POST_TYPE', $slt_se_event_post_type );
	if ( ! defined( 'SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT' ) ) {
		define( 'SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT', null );
	}
	if ( ! defined( 'SLT_SE_END_DATE_IS_FUTURE_CUT_OFF' ) ) {
		define( 'SLT_SE_END_DATE_IS_FUTURE_CUT_OFF', false );
	}
	define( 'SLT_SE_PLUGIN_EXISTS_DCF', function_exists( 'slt_cf_register_box' ) );
	define( 'SLT_SE_PLUGIN_EXISTS_CMB2', defined( 'CMB2_LOADED' ) && CMB2_LOADED );
	$event_date_field = 'event_date';
	$event_end_date_field = 'event_end_date';
	// DCF adds a prefix - need to add one here for CMB2
	if ( SLT_SE_PLUGIN_EXISTS_CMB2 ) {
		$event_date_field = '_' . $event_date_field;
		$event_end_date_field = '_' . $event_end_date_field;
	}
	define( 'SLT_SE_EVENT_DATE_FIELD', $event_date_field );
	define( 'SLT_SE_EVENT_END_DATE_FIELD', $event_end_date_field );

	// Internationalization
	$locale = apply_filters( 'plugin_locale', get_locale(), $slt_se_plugin_slug );
	load_textdomain( $slt_se_plugin_slug, trailingslashit( WP_LANG_DIR ) . $slt_se_plugin_slug . '/' . $slt_se_plugin_slug . '-' . $locale . '.mo' );
	load_plugin_textdomain( $slt_se_plugin_slug, FALSE, basename( dirname( __FILE__ ) ) . '/languages' );

	// Check dependencies
	if ( SLT_SE_EVENT_POST_TYPE ) {

		if ( SLT_SE_PLUGIN_EXISTS_DCF ) {

			// Developer's Custom Fields
			slt_cf_register_box( array(
				'type'		=> 'post',
				'title'		=> __( 'Event dates', $slt_se_plugin_slug ),
				'id'		=> 'event_date_box',
				'context'	=> 'side',
				'priority'	=> 'high',
				'fields'	=> array(
					array(
						'name'					=> SLT_SE_EVENT_DATE_FIELD,
						'label'					=> __( 'Start date / time', $slt_se_plugin_slug ),
						'type'					=> 'datetime',
						'make_query_var'		=> (bool) SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT,
						'datepicker_format'		=> 'yy/mm/dd',
						'timepicker_format'		=> 'hh:mm',
						'timepicker_am_pm'		=> false,
						'scope'					=> array( SLT_SE_EVENT_POST_TYPE ),
						'capabilities'			=> array( 'edit_pages' )
					),
					array(
						'name'					=> SLT_SE_EVENT_END_DATE_FIELD,
						'label'					=> __( 'End date / time', $slt_se_plugin_slug ),
						'type'					=> 'datetime',
						'datepicker_format'		=> 'yy/mm/dd',
						'timepicker_format'		=> 'hh:mm',
						'timepicker_am_pm'		=> false,
						'scope'					=> array( SLT_SE_EVENT_POST_TYPE ),
						'capabilities'			=> array( 'edit_pages' )
					),
				)
			));

		} else if ( SLT_SE_PLUGIN_EXISTS_CMB2 ) {

			// CMB2
			$cmb = new_cmb2_box( array(
				'id'				=> 'event_date_box',
				'title'				=> __( 'Event dates', $slt_se_plugin_slug ),
				'object_types'		=> array( SLT_SE_EVENT_POST_TYPE ),
				'context'			=> 'side',
				'priority'			=> 'high',
				'show_names'		=> true,
			));
			$cmb->add_field( array(
				'name'				=> __( 'Start date / time' ),
				'id'				=> SLT_SE_EVENT_DATE_FIELD,
				'type'				=> 'text_datetime_timestamp',
				'on_front'			=> false,
			) );
			$cmb->add_field( array(
				'name'				=> __( 'End date / time' ),
				'id'				=> SLT_SE_EVENT_END_DATE_FIELD,
				'type'				=> 'text_datetime_timestamp',
				'on_front'			=> false,
			) );

		}

		// Hooks
		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {

			/*  By default, front-end event queries should return future events only, based on the
			event_date custom field, unless overriden by custom query parameter 'slt_all_events'.
			In any case, they need to be ordered in chronological order by this field. */
			add_filter( 'query_vars', 'slt_se_register_query_vars', 10, 1 );
			add_action( 'parse_query', 'slt_se_parse_query', 10, 1 );
			add_filter( 'posts_where', 'slt_se_where_sql', 10, 2 );
			// Not necessary since 3.1.1
			if ( version_compare( get_bloginfo( 'version' ), '3.1.1', '<' ) ) {
				add_filter( 'posts_join', 'slt_se_join_sql', 10, 2 );
			}

		} else if ( is_admin() ) {

			// Admin stuff
			add_filter( "manage_edit-{$slt_se_event_post_type}_columns", "slt_se_columns" );
			add_action( "manage_posts_custom_column", "slt_se_columns_display" );
			// Sortable not working - see http://scribu.net/wordpress/custom-sortable-columns.html
			//add_filter( 'manage_edit-{$slt_se_event_post_type}_sortable_columns', 'slt_se_columns_sortable' );
			//add_filter( 'request', 'slt_se_columns_orderby' );

		}

	}
}

// Register query vars
function slt_se_register_query_vars( $vars ) {
	$vars[] = 'slt_all_events';
	$vars[] = 'slt_past_events';
	$vars[] = 'slt_reverse_events';
	$vars[] = 'disable_simple_events';
	return $vars;
}

// Test for whether a hook should be applied or not
function slt_se_apply_hook( $query, $hook ) {
	return (
		// We have query vars
		property_exists( $query, 'query_vars' ) &&
		// The query is for events
		( array_key_exists( 'post_type', $query->query_vars ) && $query->query_vars['post_type'] == SLT_SE_EVENT_POST_TYPE ) &&
		// It's not a single view
		! $query->is_singular &&
		// disable_simple_events isn't set, or is set to false
		( ! array_key_exists( 'disable_simple_events', $query->query_vars ) || ! $query->query_vars['disable_simple_events'] ) &&
		// For join and where, slt_all_events isn't set, or is set to false
		( ! in_array( $hook, array( 'join', 'where' ) ) || ( ! array_key_exists( 'slt_all_events', $query->query_vars ) || ! $query->query_vars['slt_all_events'] ) )
	);
}

// Parse query
function slt_se_parse_query( $query ) {
	if ( slt_se_apply_hook( $query, 'parse' ) ) {

		// This sets the meta key for cut-off date comparison
		$future_cut_off_field			= SLT_SE_END_DATE_IS_FUTURE_CUT_OFF ? SLT_SE_EVENT_END_DATE_FIELD : SLT_SE_EVENT_DATE_FIELD;
		$query->query_vars['meta_key']	= function_exists( 'slt_cf_field_key' ) ? slt_cf_field_key( $future_cut_off_field ) : $future_cut_off_field;

		// Determine forward or reverse chronological order
		$order = ( isset( $query->query_vars['slt_reverse_events'] ) && $query->query_vars['slt_reverse_events'] ) ? 'DESC' : 'ASC';
		$query->query_vars['order']		= $order;

		if ( version_compare( get_bloginfo( 'version' ), '4.2', '<' ) ) {

			// Below 4.2, we have to order by the same field used for the cut-off
			$query->query_vars['orderby']	= 'meta_value';

		} else {

			// 4.2+, we always order by start date, which may not be the cut-off field
			$query->query_vars['meta_query']['event_start_date_clause'] = array(
				'key'		=> function_exists( 'slt_cf_field_key' ) ? slt_cf_field_key( SLT_SE_EVENT_DATE_FIELD ) : SLT_SE_EVENT_DATE_FIELD,
				'compare'	=> 'EXISTS',
			);
			$query->query_vars['orderby']	= 'event_start_date_clause';

		}

	}
}

// Join SQL, not necessary since 3.1.1
function slt_se_join_sql( $join, $query ) {

	if ( slt_se_apply_hook( $query, 'join' ) ) {
		global $wpdb;
		$join .= ", $wpdb->postmeta ";
	}

	return $join;
}

// Where SQL
function slt_se_where_sql( $where, $query ) {

	if ( slt_se_apply_hook( $query, 'where' ) ) {
		global $wpdb;
		$comparison_operator = ( isset( $query->query_vars['slt_past_events'] ) && $query->query_vars['slt_past_events'] ) ? '<' : '>=';
		$future_cut_off_field = SLT_SE_END_DATE_IS_FUTURE_CUT_OFF ? SLT_SE_EVENT_END_DATE_FIELD : SLT_SE_EVENT_DATE_FIELD;

		if ( version_compare( get_bloginfo( 'version' ), '3.1.1', '<' ) ) {
			// Not necessary since 3.1.1
	 		$where .= " AND $wpdb->posts.ID = $wpdb->postmeta.post_id ";
 			$where .= " AND $wpdb->postmeta.meta_key = '" . ( function_exists( 'slt_cf_field_key' ) ? slt_cf_field_key( $future_cut_off_field ) : $future_cut_off_field ) . "' ";
		}

		$today = date( 'Y-m-d H:i:s', time() + apply_filters( 'slt_se_listing_time_offset', 0 ) );
 		$where .= " AND STR_TO_DATE( $wpdb->postmeta.meta_value, '%Y/%m/%d' ) $comparison_operator '$today' ";

	}

	return $where;
}

// Columns for admin listing
function slt_se_columns( $columns ) {
	global $slt_se_plugin_slug;

	$columns[ SLT_SE_EVENT_DATE_FIELD ] = __( "Event date", $slt_se_plugin_slug );

	return $columns;
}
function slt_se_columns_display( $column ) {
	if ( $column == SLT_SE_EVENT_DATE_FIELD && function_exists( 'slt_cf_field_value' ) ) {
		echo slt_cf_field_value( SLT_SE_EVENT_DATE_FIELD );
	}
}

/*
// Order columns
function slt_event_columns_sortable( $columns ) {
	$columns[ slt_cf_field_key(  ) ] = SLT_SE_EVENT_DATE_FIELD;
	return $columns;
}
function slt_event_columns_orderby( $vars ) {
	if ( isset( $vars['orderby'] ) && $vars['orderby'] == SLT_SE_EVENT_DATE_FIELD ) {
		$vars = array_merge( $vars, array(
			'meta_key'	=> slt_cf_field_key( SLT_SE_EVENT_DATE_FIELD ),
			'orderby'	=> 'meta_value_num'
		) );
	}
	return $vars;
}
*/


/**
 * Return event date if it's an event post, WP post date otherwise
 *
 * @since	0.1
 * @param	object		$the_post
 * @param	string		$which_date		'start' | 'end'
 * @return	string						Timestamp
 */
function slt_se_get_date( $the_post = null, $which_date = 'start' ) {

	if ( ! is_object( $the_post ) ) {
		global $post;
		$the_post = $post;
	}
	$date = null;

	if ( get_post_type( $the_post ) == SLT_SE_EVENT_POST_TYPE && function_exists( 'slt_cf_field_value' ) ) {
		$date_field = SLT_SE_EVENT_DATE_FIELD;
		if ( $which_date == 'end' ) {
			$date_field = SLT_SE_EVENT_END_DATE_FIELD;
		}
		$date_value = slt_cf_field_value( $date_field, 'post', $the_post->ID );
		if ( $date_value ) {
			$date = slt_se_date_to_timestamp( $date_value );
		}
	}

	if ( ! $date ) {
		$date = strtotime( $the_post->post_date );
	}

	return $date;
}


/**
 * Convert stored date value to timestamp
 *
 * @since	0.3
 * @param	string	$date_value
 * @return	string					Timestamp
 */
function slt_se_date_to_timestamp( $date_value ) {
	$ts = null;
	$date_time_parts = explode( ' ', $date_value );
	$date_parts = explode( '/', $date_time_parts[0] );
	$time_parts = array( 0, 0 );
	if ( count( $date_time_parts ) == 2 ) {
		$time_parts = explode( ':', $date_time_parts[1] );
	}
	if ( count( $date_parts ) == 3 && checkdate( $date_parts[1], $date_parts[2], $date_parts[0] ) ) {
		$ts = mktime( $time_parts[0], $time_parts[1], 0, $date_parts[1], $date_parts[2], $date_parts[0] );
	}
	return $ts;
}