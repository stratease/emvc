<?php
class File
{
    /**
     * Any relevant meta data. Will contain upload data for the file in question.
     * @var stdClass
     */
    public $meta = null;
    /**
     * @var string The current file path of this file.
     */
    public $filePath = '';
    /**
     * @var null|Site
     */
    public $site = null;

    /**
     * @param Site $site
     * @param $filePath
     */
    public function __construct(Site $site, $filePath)
    {
        $this->site = $site;
        $this->filePath = $filePath;
        $this->meta = $this->generateMetaData($this->filePath);
    }

    /**
     * @param $destinationPath
     * @return bool
     */
    public function move($destinationPath)
    {
        if ($this->isUploadedFile === true) {
            $success = move_uploaded_file($this->filePath, $destinationPath);
        } else {
            if ($success = copy($this->filePath, $destinationPath)) {
                $success = unlink($this->filePath);
            }
        }
        // if we moved successfully, change our path
        if ($success === true) {
            $this->filePath = $destinationPath;
            $this->meta = $this->generateMetaData($this->filePath); // new file path, reset meta data with any changes to the file
        }
        return $success;
    }

	public function getFileExtension($fileName = null)
	{
		if($fileName === null) {
			$fileName = $this->filePath;
		}
		return strtolower(substr($fileName, strrpos($fileName, '.') + 1));
	}
    /**
     * @todo Parse file for any relevant meta data.
     * @param string $filePath
     * @return stdClass
     */
    public function generateMetaData($filePath)
    {
		$meta = new stdClass();
		if($this->filePath != ''
		   && ($this->isUploadedFile === false ||
				$this->hasUploadError() === false)) {
			// @todo determine more meta data...
			$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
			$meta->mimetype = finfo_file($finfo, $this->filePath);
			finfo_close($finfo);
		}
        return $meta;
    }

    /**
     * @return bool
     */
    public function hasUploadError()
    {
        return (isset($this->meta->upload->error) == true
                && $this->meta->upload->error != UPLOAD_ERR_OK);
    }

    /**
     * @return bool|string
     */
    public function getError()
    {
        if ($this->isUploadedFile === true
			|| isset($this->meta->upload) === true) {
            switch ($this->meta->upload->error) { // todo cleanup error messages?
                case UPLOAD_ERR_INI_SIZE:
                    return "The uploaded file (" . $this->meta->upload->name . ") exceeds the max file size. (" . ini_get('upload_max_filesize') . ')';
                case UPLOAD_ERR_FORM_SIZE:
                    return "The uploaded file (" . $this->meta->upload->name . ") exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                case UPLOAD_ERR_PARTIAL:
                    return "The uploaded file (" . $this->meta->upload->name . ") was only partially uploaded.";
                case UPLOAD_ERR_NO_FILE:
                    return "No file (" . $this->meta->upload->name . ") was uploaded.";
                case UPLOAD_ERR_NO_TMP_DIR:
                    return "Server error attempting to upload files. Contact your system administrator.";
                case UPLOAD_ERR_CANT_WRITE:
                    return "Server error attempting to upload files. Contact your system administrator.";
                case UPLOAD_ERR_EXTENSION:
                    return "Server error attempting to upload files. Contact your system administrator.";
            }
        }
        return false;
    }

    /**
     * @param $field
     * @return bool|null
     */
    public function __get($field)
    {
        switch ($field) {
			case 'fileName':
				return basename($this->filePath);
            case 'isUploadedFile':
                return is_uploaded_file($this->filePath);
			case 'isImage':
				switch($this->meta->mimetype) {
					case 'image/svg+xml':
					case 'image/png':
					case 'image/jpg':
					case 'image/gif':
					case 'image/jpeg':
						return true;
				}
				return false;
			case 'fileExtension':
				return $this->getFileExtension($this->filePath);
            default:
                return null;
        }
    }
}
