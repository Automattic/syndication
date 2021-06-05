<?php

/**
 * Base unit test class for Table of Contents
 */
class TableofContents_TestCase extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		global $table_of_contents;
		$this->_toc = $table_of_contents;
	}
}
