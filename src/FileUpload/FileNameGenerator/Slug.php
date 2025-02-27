<?php
/**
 * Created by PhpStorm.
 * User: decola
 * Date: 11.07.14
 * Time: 14:00
 */

namespace FileUpload\FileNameGenerator;

use FileUpload\FileSystem\FileSystem;
use FileUpload\FileUpload;
use FileUpload\PathResolver\PathResolver;
use FileUpload\Util;

class Slug implements FileNameGenerator
{
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

    /**
     * Get file_name
     * @param  string       $source_name
     * @param  string       $type
     * @param  string       $tmp_name
     * @param  integer      $index
     * @param  string       $content_range
     * @param  FileUpload   $upload
     * @return string
     */
    public function getFileName($source_name, $type, $tmp_name, $index, $content_range, FileUpload $upload)
    {
        $this->fileSystem = $upload->getFileSystem();
        $this->pathResolver = $upload->getPathResolver();

        $source_name = $this->getSluggedFileName($source_name);
        $uniqueFileName = $this->getUniqueFilename($source_name, $type, $index, $content_range);

        return $this->getSluggedFileName($uniqueFileName);
    }

    /**
     * Get unique but consistent name
     * @param  string  $name
     * @param  string  $type
     * @param  integer $index
     * @param  array   $content_range
     * @return string
     */
    protected function getUniqueFilename($name, $type, $index, $content_range)
    {
        if (! is_array($content_range)) {
            $content_range = [0];
        }

        while ($this->fileSystem->isDir($this->pathResolver->getUploadPath($this->getSluggedFileName($name)))) {
            $name = $this->pathResolver->upcountName($name);
        }

        $uploaded_bytes = Util::fixIntegerOverflow(intval($content_range[1] ?? $content_range[0]));

        while ($this->fileSystem->isFile($this->pathResolver->getUploadPath($this->getSluggedFileName($name)))) {
            if ($uploaded_bytes == $this->fileSystem->getFilesize($this->pathResolver->getUploadPath($this->getSluggedFileName($name)))) {
                break;
            }

            $name = $this->pathResolver->upcountName($name);
        }

        return $name;
    }

    /**
     * @param string $name
     *
     * @return string
     * */
    public function getSluggedFileName($name)
    {
        $fileNameExploded = explode(".", $name);
        $extension = array_pop($fileNameExploded);
        $fileNameExploded = implode(".", $fileNameExploded);

        return $this->slugify($fileNameExploded) . "." . $extension;
    }

    /**
     * @param $text
     *
     * @return mixed|string
     */
    private function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // trim
        $text = trim($text, '-');
        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // lowercase
        $text = strtolower($text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
}
