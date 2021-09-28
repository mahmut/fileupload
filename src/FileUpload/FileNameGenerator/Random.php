<?php


namespace FileUpload\FileNameGenerator;

use FileUpload\FileUpload;
use FileUpload\Util;

class Random implements FileNameGenerator
{

    /**
     * Maximum length of the filename
     * @var int
     */
    private $nameLength = 32;

    /**
     * PathResolver
     * @var PathResolver
     */
    private $pathResolver;

    /**
     * Filesystem
     * @var FileSystem
     */
    private $fileSystem;

    public function __construct($nameLength = 32)
    {
        $this->nameLength = $nameLength;
    }

    /**
     * Get file_name
     * @param  string     $source_name
     * @param  string     $type
     * @param  string     $tmp_name
     * @param  integer    $index
     * @param  string     $content_range
     * @param  FileUpload $upload
     * @return string
     */
    public function getFileName($source_name, $type, $tmp_name, $index, $content_range, FileUpload $upload)
    {
        $this->pathResolver = $upload->getPathResolver();
        $this->fileSystem = $upload->getFileSystem();
        $extension = pathinfo($source_name, PATHINFO_EXTENSION);

        return ($this->getUniqueFilename($source_name, $type, $index, $content_range, $extension));
    }

    /**
     * Get unique but consistent name
     * @param  string  $name
     * @param  string  $type
     * @param  integer $index
     * @param  array   $content_range
     * @param  string  $extension
     * @return string
     */
    protected function getUniqueFilename($name, $type, $index, $content_range, $extension)
    {
        $name = $this->generateRandom() . "." . $extension;
        while ($this->fileSystem->isDir($this->pathResolver->getUploadPath($name))) {
            $name = $this->generateRandom() . "." . $extension;
        }

        $uploaded_bytes = Util::fixIntegerOverflow(intval($content_range[1]));

        while ($this->fileSystem->isFile($this->pathResolver->getUploadPath($name))) {
            if ($uploaded_bytes == $this->fileSystem->getFilesize($this->pathResolver->getUploadPath($name))) {
                break;
            }

            $name = $this->generateRandom() . "." . $extension;
        }

        return $name;
    }

    protected function generateRandom()
    {
        return substr(
            str_shuffle(
                "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
            ),
            0,
            $this->nameLength
        );
    }
}
