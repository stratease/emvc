<?php
trait MongoHistoryTrait
{
	public $historyCollection = 'history';
	public function startup()
	{
		call_user_func_array(array('parent', 'startup'), func_get_args());
		$this->onBeforeUpdate[] = '_insertHistory'; // merge our on before update action
	}

	protected function _insertHistory()
	{
		// record updates
		$history = new MongoHistory($this->site, array('collection' => $this->historyCollection));
		// get new vals
		$nvals = array();
		foreach($this->changed[$this->registry] as $k => $v)
		{
			$nvals[$k] = $this->get($k);
		}
		$history->set(array(
			'class' => $this->__CLASS__,
			'targetId' => $this->get('_id'),
			'before' => $this->changed[$this->registry],
			'after' => $nvals
		));
		// if a user is avail..
		if(isset($this->site->user)
		   && $this->site->user->isLoaded)
		{
			$history->set('user_id', $this->site->user->get('_id'));
		}
		$history->insert();
	}
	/**
	 * Adds a note item to history for this object.
	 *
	 * @param string $note   Arbitrary string. Try to deal with control codes, xml stuff, etc since most of the time this will just be output to the screen in the history lists.
	 *
	 * @return mixes    Returns an array containing the status of the insertion if the "w" option is set. Otherwise, returns TRUE if the inserted array is not empty (a MongoException will be thrown if the inserted array is empty).
	 */
	function createNote($note)
	{
		if (!$this->isLoaded)
		{
			return array(); // don't bother if we're note loaded.
		}
		$history = new MongoHistory($this->site, array('collection' => $this->historyCollection));
		$history->set(array(
			'class' => __CLASS__,
			'targetId' => $this->get('_id'),
			'note' => $note
		));
		// if a user is avail..
		if(isset($this->site->user)
		   && $this->site->user->isLoaded)
		{
			$history->set('user_id', $this->site->user->get('_id'));
		}
		return $history->insert();
	}

	/**
	 * Returns an array of all notes for the current object, ordered by date
	 *
	 *
	 * @return array    array of notes
	 */
	function getNotes()
	{
		if (!$this->isLoaded)
		{
			return array(); // don't bother if we're note loaded.
		}
		$history = new MongoHistory($this->site, array('collection' => $this->historyCollection));
		return $history->find(array(
			'class' => __CLASS__,
			'targetId' => $this->get('_id'),
			'note' => array('$exists' => true)
		))->sort(array('created_date' => -1));
	}

	/**
	 * Returns an array of all history, including notes for the current object, ordered by date
	 *
	 *
	 * @return array    array of notes
	 */
	function getHistory()
	{
		if (!$this->isLoaded)
		{
			return array(); // don't bother if we're note loaded.
		}
		$history = new MongoHistory($this->site, array('collection' => $this->historyCollection));
		return $history->find(array(
			'class' => __CLASS__,
			'targetId' => $this->get('_id'),
		))->sort(array('created_date' => -1));
	}


}
