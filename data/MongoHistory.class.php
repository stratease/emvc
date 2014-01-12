<?php
/*
 * This class stores historical updates to other mongo objects via the mongobase.
 * To use, 'use HistoryTrait' - and all updates to the object will be recorded. It also provides some handy helper functions. See the trait for details.
 */
class MongoHistory extends MongoBase
{
	public $collection = 'history';

	protected $setField = array(
		'targetId' => 'mongoId',
	);

	protected function mongoId($val)
	{
		if (is_string($val)
				&& trim($val) !== ''
		)
		{
			$val = new MongoId($val);
		}
		return $val;
	}


	public function getDefaults()
	{
		$user = null;
		if (isset($this->site->user) && get_class($this->site->user) == 'User')
		{
			$user = $this->site->user->get('_id');
		}
		return array(
			'class' => null,
			'subdoc' => null,
			'targetId' => null,
			'created_date' => new MongoDate(gmdate('U')),
			'user' => $user
		);
	}


}
