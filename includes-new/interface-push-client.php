<?php

namespace Automattic\Syndication;

interface Push_Client {

	public function insert( $post );

	public function update( $identifier, $post );

	public function delete( $identifier );

	public function post_exists( $identifier );
}