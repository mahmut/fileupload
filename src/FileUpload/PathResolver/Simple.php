<?php

namespace FileUpload\PathResolver;

class Simple implements PathResolver
{
    /**
     * Main path
     * @var string
     */
    protected $mainPath;

    /**
     * A construct to remember
     * @param string $mainPath Where files should be stored
     */
    public function __construct($mainPath)
    {
        $this->mainPath = $mainPath;
    }

    /**
     * @see PathResolver
     */
    public function getUploadPath($name = null)
    {
        return $this->mainPath . '/' . $name;
    }

    /**
     * @see PathResolver
     */
    public function upcountName($name)
    {
        return preg_replace_callback('/(?:(?: \(([\d]+)\))?(\.[^.]+))?$/', function ($matches) {
            $index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            $ext = isset($matches[2]) ? $matches[2] : '';

            return ' (' . $index . ')' . $ext;
        }, $name, 1);
    }

    /**
     * get relative path
     *
     * @param null $name
     * @return mixed
     */
    public function getRelativePath($name = null)
    {
        return $name;
    }

    /**
     * get directory name
     *
     * @return string
     */
    public function getDirectory()
    {
        return '';
    }
}
