<?php

namespace FileUpload;

class File extends \SplFileInfo
{
    /**
     * Preset no errors
     * @var mixed
     */
    public $error = 0;

    /**
     * Preset no errors
     * @var mixed
     */
    public $errorCode = 0;

    /**
     * directory name
     *
     * @var string
     */
    public $directory;

    /**
     * file relative path
     *
     * @var string
     */
    public $relativePath;

    /**
     * Preset unknown mime type
     * @var string
     */
    protected $mimeType = 'application/octet-stream';

    /**
     * @var string
     */
    protected $clientFileName;

    /**
     * Is the file completely downloaded
     * @var boolean
     */
    public $completed = false;

    /**
     * File constructor.
     *
     * @param $fileName
     * @param string $clientFileName
     */
    public function __construct($fileName, $clientFileName = '')
    {
        $this->setMimeType($fileName);
        $this->clientFileName = $clientFileName;
        parent::__construct($fileName);
    }

    /**
     * set mime type
     *
     * @param $fileName
     */
    protected function setMimeType($fileName)
    {
        if (file_exists($fileName)) {
            $this->mimeType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $fileName);
        }
    }

    /**
     * Returns the "original" name of the file
     *
     * @return string
     */
    public function getClientFileName()
    {
        return $this->clientFileName;
    }

    /**
     * get mime type
     *
     * @return string
     * @throws \Exception
     */
    public function getMimeType()
    {
        if ($this->getType() !== 'file') {
            throw new \Exception('You cannot get the mimetype for a ' . $this->getType());
        }

        return $this->mimeType;
    }

    /**
     * Does this file have an image mime type?
     *
     * @return boolean
     */
    public function isImage()
    {
        return in_array(
            $this->mimeType,
            ['image/gif', 'image/jpeg', 'image/pjpeg', 'image/png']
        );
    }
}
