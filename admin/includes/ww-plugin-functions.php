<?php
/**
 * MYPLUGIN_Reusable_Functions.php
 *
 * Contains various utility functions designed for reuse across a custom WordPress plugin.
 * All functions are prefixed with 'myplugin_' to prevent naming conflicts.
 */

// Exit if accessed directly (security measure)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ===========================================
 * 1. Date Formatting Utility
 * ===========================================
 *
 * Converts a standard MySQL datetime string to a human-readable format.
 *
 * @param string $datetime_string The date/time string (e.g., '2025-10-20 22:14:19').
 * @param string $format          The desired output format (e.g., 'd-m-Y', 'F j, Y').
 * @return string                 The formatted date string or an empty string on failure.
 */
if ( ! function_exists( 'ww_format_datetime' ) ) {
    function ww_format_datetime( $datetime_string, $format = 'd-m-Y' ) {
        if ( empty( $datetime_string ) ) {
            return '';
        }

        // Use strtotime to convert to a Unix timestamp
        $timestamp = strtotime( $datetime_string );

        if ( false === $timestamp ) {
            // Handle case where strtotime could not parse the string
            return $datetime_string;
        }

        // Return the formatted date
        return date( $format, $timestamp );
    }
}

// Logic to Generate a Unique 16-Digit ID
if ( ! function_exists( 'generateUniqueId' ) ) {
	function generateUniqueId() {
        $uniqueId = substr(time() . random_int(100000, 999999), 0, 16);
	    return $uniqueId;
	}
}


/**
 * ===========================================
 * 2. Database Value Sanitization Utility
 * ===========================================
 *
 * Sanitizes a string for use as a unique ID (slug) in the database.
 * This is the WordPress-safe way to use the concept of a "one-word ID."
 *
 * @param string $name The original string (e.g., 'Adult / 1 Rod').
 * @return string      The sanitized slug (e.g., 'adult-1-rod').
 */
if ( ! function_exists( 'myplugin_generate_slug' ) ) {
    function myplugin_generate_slug( $name ) {
        // Uses the WordPress core function to create a safe slug
        return sanitize_title( $name );
    }
}


/**
 * ===========================================
 * 3. Array Data Getter with Default Fallback
 * ===========================================
 *
 * Safely retrieves a value from an array (or object) by key,
 * preventing PHP notices/warnings if the key does not exist.
 *
 * @param string $key     The key to retrieve.
 * @param array|object $array The array or object to search.
 * @param mixed $default  The default value to return if the key is not found (default: null).
 * @return mixed          The value if found, otherwise the default value.
 */
if ( ! function_exists( 'myplugin_get_array_value' ) ) {
    function myplugin_get_array_value( $key, $array, $default = null ) {
        if ( is_array( $array ) && isset( $array[ $key ] ) ) {
            return $array[ $key ];
        } elseif ( is_object( $array ) && isset( $array->$key ) ) {
            return $array->$key;
        }

        return $default;
    }
}


/**
 * Example Usage (Comment out or remove this block in a live plugin):
 *
 * $my_data = array(
 * 'timestamp' => '2025-10-20 22:14:19',
 * 'title'     => 'My New Post Title',
 * 'cost'      => 50,
 * );
 *
 * // 1. Date Formatting
 * $formatted_date = myplugin_format_datetime( myplugin_get_array_value( 'timestamp', $my_data ), 'F j, Y' );
 * // Outputs: October 20, 2025
 *
 * // 2. Slug Generation
 * $post_slug = myplugin_generate_slug( myplugin_get_array_value( 'title', $my_data ) );
 * // Outputs: my-new-post-title
 *
 * // 3. Safe Data Retrieval (Cost exists)
 * $cost_value = myplugin_get_array_value( 'cost', $my_data, 0 );
 * // Outputs: 50
 *
 * // 3. Safe Data Retrieval (Non-existent key)
 * $status_value = myplugin_get_array_value( 'status', $my_data, 'draft' );
 * // Outputs: draft
 */
