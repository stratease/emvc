<?php
class MongoBaseCursor extends MongoCursor
{
	var $site; // site object
	var $collectionType;

	function __construct($site, $className, $collectionName, $query)
	{
		$this->site = $site;
		$this->collectionType = $className;
		parent::__construct( $this->site->mongoClient, $collectionName, $query, array());
	}

	/**
	 * grabs the array from the parent, and creates an object that matches the passed collection and returns it populated with the data.
	 *
	 * @return object    instance of the populated collection object
	 */
	function current()
	{
		$current = parent::current();
		if ($current === null)
		{
			return null; // nothing is nothing
		}
		// if it's a temp collection, we need to pass the generated name over.
		if ($this->collectionType == 'MongoTempCollection')
		{
			$object = new $this->collectionType($this->site, $collectionName);
		}
		else
		{
			$object = new $this->collectionType($this->site);
		}

		$object->loadRow($current);
		return $object;
	}

    function getNext()
    {
        $row = parent::getNext();
        // if it's a temp collection, we need to pass the generated name over.
        if ($this->collectionType == 'MongoTempCollection')
        {
            $object = new $this->collectionType($this->site, $collectionName);
        }
        else
        {
            $object = new $this->collectionType($this->site);
        }
        $object->loadRow($row);

        return $object;
    }

}
