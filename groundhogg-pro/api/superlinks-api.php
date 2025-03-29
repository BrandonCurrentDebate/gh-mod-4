<?php

namespace GroundhoggPro\Api;

use Groundhogg\Api\V4\Base_Object_Api;
use GroundhoggPro\Classes\Superlink;

class Superlinks_Api extends Base_Object_Api{

	public function get_db_table_name() {
		return 'superlinks';
	}

	protected function get_object_class() {
		return Superlink::class;
	}
}
