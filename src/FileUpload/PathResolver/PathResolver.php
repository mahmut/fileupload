<?php

namespace FileUpload\PathResolver;

interface PathResolver
{
    /**
     * Get absolute final destination path
     * @param  string $name
     * @return string
     */
    public function getUploadPath($name = null);

    /**
     * get relative path
     *
     * @param null $name
     * @return mixed
     */
    public function getRelativePath($name = null);

    /**
     * get directory name
     *
     * @return string
     */
    public function getDirectory();

    /**
     * Ensure consistent name
     * @param  string $name
     * @return string
     */
    public function upcountName($name);
}
