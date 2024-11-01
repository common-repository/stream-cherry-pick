<?php
/**
 * Plugin Name: Stream Cherry-Pick
 * Depends: Stream
 * Plugin URI: http://wordpress.org/plugins/stream/
 * Description: Requries Stream Plugin.  Stream Cherry-Pick allows for deletion of individual and bulk records within the Stream Plugin.
 * Version: 0.1
 * Author: X-Team
 * Author URI: http://x-team.com/wordpress/
 * License: GPLv2+
 * Text Domain: wp-stream-cherry-pick
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 X-Team (http://x-team.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Founda
 * tion, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


class WP_Stream_Cherry_Pick {


	/**
	 * Checks to see if Stream is active.  If so, run setup(), else, do nothing.
	 * Not using is_plugin_active because we need to fire earlier than admin_init.
	 *
	 * @since  0.1
	 * @uses   setup
	 * @return void
	 */
	static function active_plugin_check() {
		if ( class_exists( 'WP_Stream' ) ) {
			self::setup();
		}
	}


	/**
	 * Manages activity used in Stream Cherry Pick
	 *
	 * @since 0.1
	 * @see   active_plugin_check
	 * @uses  delete_record
	 * @uses  delete_bulk_records
	 */
	public static function setup() {
		//Register new checkbox column
		add_filter( 'wp_stream_list_table_columns', array( __CLASS__, 'checkbox_column' ) );

		//add filter for "Delete Record" link for all connector types
		$connectors = WP_Stream_Connectors::$term_labels['stream_connector'];
		foreach ( $connectors as $connector ) {
			$connector = strtolower( $connector );
			add_filter( 'wp_stream_custom_action_links_' . $connector, array( __CLASS__, 'delete_record_link' ), 10, 2 );
		}

		//Insert "Delete Selected Records" button after list table
		add_action( 'wp_stream_after_list_table', array( __CLASS__, 'bulk_delete_button' ) );

		//Deletion methods
		self::delete_record();
		self::delete_bulk_records();
	}


	/**
	 * Register "Checkbox" column with checkbox as column header
	 *
	 * @filter wp_stream_list_table_columns
	 * @since  0.1
	 * @see    WP_Stream_List_Table::get_columns
	 * @param  array  Default Columns
	 * @return array  Udated Columns
	 */
	public static function checkbox_column( $columns ) {
		$new_column = array(
			'cb' => '<span class="check-column"><input type="checkbox" /></span>',
		);

		//add to front of $columns
		$columns = array_merge( $new_column, $columns );
		return $columns;
	}


	/**
	 * Add "Delete Record" links to row actions
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @since  0.1
	 * @param  array  $links   Previous links registered
	 * @param  int    $record  Stream record
	 * @return array           Action links
	 */
	public static function delete_record_link( $links, $record ) {
		$nonce   = wp_create_nonce( 'wp_stream_delete_' . $record->ID );
		$links[] = '<span class="delete"><a href="' . admin_url( 'admin.php?page=wp_stream' ) . '&delete_record=' . $record ->ID . '&wp_stream_nonce=' . $nonce . '">' . __( 'Delete Record', 'wp-stream-cherry-pick' ) . '</a></span>';
		return $links;
	}


	/**
	 * Add "Delete Selected Records" button after List Table
	 *
	 * @filter wp_stream_after_list_table
	 * @since  0.1
	 * @return void
	 */
	public static function bulk_delete_button() {
		$nonce = wp_nonce_field( 'wp_stream_bulk_delete' );
		echo '<input type="submit" class="button" value="' . __( 'Delete Selected Records', 'wp-stream-cherry-pick' ) . '" />';
	}


	/**
	 * Prepares slected item for deletion when using the "Delete Record" row actions link
	 *
	 * @since  0.1
	 * @uses   delete_action
	 * @return void
	 */
	public static function delete_record() {
		if ( ! isset( $_GET['delete_record'] ) || ! isset( $_GET['wp_stream_nonce'] ) ) {
			return;
		}

		$record_id = $_GET['delete_record'];
		$nonce     = $_GET['wp_stream_nonce'];

		if ( ! wp_verify_nonce( $nonce, 'wp_stream_delete_' . $record_id ) ) {
			return;
		}

		self::delete_action( $record_id );
	}


	/**
	 * Prepares slected items for deletion when using the "Delete Selected Records" button.
	 *
	 * @since  0.1
	 * @uses   delete_action
	 * @return void
	 */
	public static function delete_bulk_records() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wp_stream_bulk_delete' ) ) {
			return;
		}

		if ( ! isset( $_GET['wp_stream_checkbox'] ) || ! is_array( $_GET['wp_stream_checkbox'] ) ) {
			return;
		}

		foreach ( $_GET['wp_stream_checkbox'] as $record_id ) {
			self::delete_action( $record_id );
		}
	}


	/**
	 * Removes requested Records from the database
	 *
	 * @since  0.1
	 * @see    delete_bulk_records
	 * @see    delete_record
	 * @return void
	 */
	public static function delete_action( $record_id ) {
		global $wpdb;

		$wpdb->delete(
			WP_Stream_DB::$table_context,
			array(
				'record_id' => $record_id,
			)
		);
	}


}

add_action( 'admin_init', array( 'WP_Stream_Cherry_Pick', 'active_plugin_check' ) );
