<?php

interface wp_client {

	/**
	 * Creates a new post in the slave site.
	 *
	 * @param   int  $post_ID  The post ID to push.
	 *
	 * @return  boolean true on success false on failure.
	 */
	public function new_post( $post_ID );

	/**
	 * Edits an existing post in the slave site.
	 *
	 * @param   int  $post_ID  The post ID to push.
	 * @param   int  $ext_ID   Slave post ID to edit.
	 *
	 * @return  boolean true on success false on failure.
	 */
	public function edit_post( $post_ID, $ext_ID );

	/**
	 * Deletes an existing post in the slave site.
	 *
	 * @param   int  $ext_ID  Slave post ID to delete.
	 *
	 * @return  boolean true on success false on failure.
	 */
	public function delete_post( $ext_ID );

	/**
	 * Test the connection with the slave site.
	 *
	 * @return  boolean  true on success false on failure.
	 */
	public function test_connection();

	/**
	 * Checks whether the given post exists in the slave site.
	 *
	 * @param   int  $ext_ID  Slave post ID to check.
	 *
	 * @return  boolean  true on success false on failure.
	 */
	public function is_post_exists( $ext_ID );

	/**
	 * Get the response message sent from the slave site.
	 *
	 * @return  string  response message.
	 */
	public function get_response();

	/**
	 * Get the error code.
	 *
	 * @return  int  error code.
	 */
	public function get_error_code();

	/**
	 * Get the error message sent from the slave site.
	 *
	 * @return string error message.
	 */
	public function get_error_message();

	/**
	 * Display the client settings for the slave site.
	 *
	 * @param   object  $site  The site object to display settings.
	 */
	public static function display_settings( $site );

	/**
	 * Save the client settings for the slave site.
	 *
	 * @param   int  $site_ID  The site ID to save settings.
	 *
	 * @return  boolean  true on success false on failure.
	 */
	public static function save_settings( $site_ID );

}