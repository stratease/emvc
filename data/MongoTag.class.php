<?php
trait MongoTag
{
	protected $tagsField = 'tags';
	/**
	 *@param string $tag The tag to add. Assumes spaces are for separate tags and will treat it as a list of tags.
	 *@return bool True if successfully added (or if it already exists)
	 */
	public function addTag($tag)
	{
		// clean up
		$nTags = explode(' ', strtolower(trim($tag)));
		if(!$tags = $this->get($this->tagsField))
			$tags = array();
		foreach($nTags as $tag)
		{
			$tag = trim($tag);
			if(!in_array($tag, $tags))
			{
				$tags[] = $tag;
				$this->set($this->tagsField, $tags);
			}
		}
		return true;
	}
	/**
	 *@param string $tag The tag to remove
	 *@return bool True if successfully removed (or if it doesn't exist)
	 */
	public function removeTag($tag)
	{
		// clean up
		if($tags = $this->get($this->tagsField))
		{
			$nTags = explode(' ', strtolower(trim($tag)));
			foreach($nTags as $tag)
			{
				foreach($tags as $i => $t)
				{
					if($t === trim($tag))
					{
						unset($tags[$i]); // ditch it!
					}
				}
			}
			$this->set($this->tagsField, array_values($tags));
		}
		return true;
	}
}
