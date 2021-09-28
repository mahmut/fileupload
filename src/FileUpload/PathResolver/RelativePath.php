<?php
/**
 * author : Mahmut Özdemir
 * web    : www.mahmutozdemir.com.tr
 * email  : bilgi@mahmutozdemir.com.tr
 * ----------------------------------------
 * Date   : 2021-09-28 15:10
 * File   : RelativePath.php
 */

namespace FileUpload\PathResolver;

class RelativePath implements PathResolver
{
    /**
     * Main path
     * @var string
     */
    protected $mainPath;

    /**
     * directory
     *
     * @var string
     */
    protected $directory;

    /**
     * RelativePath constructor.
     *
     * @param $mainPath - where files should be stored
     * @param string $directory
     */
    public function __construct($mainPath, $directory = '')
    {
        $this->mainPath = $mainPath;
        $this->directory = $directory;
    }

    /**
     * get upload path
     *
     * @param null $name
     * @return string
     */
    public function getUploadPath($name = null)
    {
        $paths = [rtrim($this->mainPath,'/')];
        if($this->directory){
            $paths[] = $this->directory;
        }
        if($name){
            $paths[] = $name;
        }

        return implode('/', $paths);
    }

    /**
     * get relative path
     *
     * @param null $name
     * @return string
     */
    public function getRelativePath($name = null)
    {
        $paths = [rtrim($this->directory, '/')];
        if($name){
            $paths[] = $name;
        }
        return implode('/', $paths);
    }

    /**
     * get directory name
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * upcount filename if exists
     *
     * @param string $name
     * @return string|string[]|null
     */
    public function upcountName($name)
    {
        return preg_replace_callback('/(?:(?:\_([\d]+))?(\.[^.]+))?$/', function ($matches) {
            $index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            $ext = isset($matches[2]) ? $matches[2] : '';

            return '_'.$index. $ext;
        }, $name, 1);
    }
}
