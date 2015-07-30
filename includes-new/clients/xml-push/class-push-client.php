<?php

namespace Automattic\Syndication\Clients\XML_Push;

use Automattic\Syndication;
use Automattic\Syndication\Puller;
use Automattic\Syndication\Types;

/**
 * Syndication Client: XML Push
 *
 * Create 'syndication sites' to push site content to an external
 * WordPress install via XML-RPC. Includes XPath mapping to map incoming
 * XML data to specific post data.
 *
 * @package Automattic\Syndication\Clients\XML
 */
class Push_Client extends Pusher {

	/**
	 * Hook into WordPress
	 */
	public function __construct() {}


}
