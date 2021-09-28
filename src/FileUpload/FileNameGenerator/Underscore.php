<?php
/**
 * author : Mahmut Özdemir
 * web    : www.mahmutozdemir.com.tr
 * email  : bilgi@mahmutozdemir.com.tr
 * ----------------------------------------
 * Date   : 2021-09-28 15:21
 * File   : Underscore.php
 */

namespace FileUpload\FileNameGenerator;

use Cocur\Slugify\Slugify;
use FileUpload\FileSystem\FileSystem;
use FileUpload\FileUpload;
use FileUpload\PathResolver\PathResolver;
use FileUpload\Util;

class Underscore implements FileNameGenerator
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

        return ($this->getUniqueFilename($source_name, $type, $index, $content_range));
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

        $slugify = new Slugify(['separator' => '_', 'rulesets' => ['turkish'], 'strip_tags' => true]);
        $slugify->activateRuleSet('turkish');
        return $slugify->slugify($fileNameExploded).'.'.$extension;
    }
}
