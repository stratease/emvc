<?php
class UploadedFiles implements Iterator
{
    /*
     * var Site
     */
    public $site = null;
    protected $_items = [];

    /**
     * @param $site
     */
    public function __construct($site)
    {
        $this->site = $site;
        $this->_items = $this->generateNormalizedFileList();
    }

    /**
     * @return array
     */
    protected function generateNormalizedFileList()
    {
        $nList = [];
        if (isset($_FILES)) {
            // dojo uploads... ?
            if (isset($_FILES['uploadedfile0'])) // iframe sends in diff structure? ... lame
            {
                foreach ($_FILES['uploadedfile0'] as $k => $fData) {
                    $f = new File($this->site, $fData['tmp_name']);
                    $f->meta->upload = new stdClass();
                    foreach ($fData as $meta => $val) {
                        $f->meta->upload->$meta = $val;
                    }
                    $nList[] = $f;
                }
            } else {
                foreach ($_FILES as $mk => $fData) {
                    if (is_string($mk)) { // @TODO handle POST array of files
                        foreach ($fData as $field => $rows) {
							if(is_string($rows)) {
								$flat = true;
								break 2;
							}
                            foreach ($rows as $i => $val) {
                                if (isset($nList[$i]) === false) {
                                    $nList[$i] = new File($this->site, $_FILES[$mk]['tmp_name'][$i]);
                                    $nList[$i]->meta->upload = new stdClass();
                                    $nList[$i]->meta->upload->$field = $val;
                                } else {
                                    $nList[$i]->meta->upload->$field = $val;
                                }
                            }
                        }
                    }
                }
				if(isset($flat)) {
					$nList[0] = new File($this->site, $_FILES[$mk]['tmp_name']);
					$nList[0]->meta->upload = new stdClass();
					foreach($_FILES[$mk] as $field => $val) {
						$nList[0]->meta->upload->$field = $val;
					}
				}
            }
        }
        // remove the error 4 (no file uploaded)
        foreach ($nList as $key => $file) {
            if(isset($file->meta->upload->error)) {
                if($file->meta->upload->error == UPLOAD_ERR_NO_FILE) {
                    unset($nList[$key]);
                }
            }
        }

        return $nList;
    }

    public function rewind()
    {
        reset($this->_items);
    }

    public function current()
    {
        $var = current($this->_items);
        return $var;
    }

    public function key()
    {
        $var = key($this->_items);
        return $var;
    }

    public function next()
    {
        $var = next($this->_items);
        return $var;
    }

	public function pop()
	{
		return array_pop($this->_items);
	}

    public function valid()
    {
        $key = key($this->_items);
        $var = ($key !== NULL && $key !== FALSE);
        return $var;
    }

    public function count()
    {
        return count($this->_items);
    }
}
