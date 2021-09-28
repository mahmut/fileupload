<?php
/**
 * author : Mahmut Özdemir
 * web    : www.mahmutozdemir.com.tr
 * email  : bilgi@mahmutozdemir.com.tr
 * ----------------------------------------
 * Date   : 2021-09-28 15:28
 * File   : RelativeSystem.php
 */

namespace FileUpload\FileSystem;

class RelativeSystem implements FileSystem
{
    /**
     * create directory
     *
     * @param $path
     */
    public function createDir($path)
    {
        $paths = explode('/', $path);
        array_pop($paths);
        $path = implode('/', $paths);

        if(!$this->isDir($path)){
            mkdir($path, 0775, true);
        }
    }

    /**
     * @see FileSystem
     */
    public function isFile($path)
    {
        return is_file($path);
    }

    /**
     * @see FileSystem
     */
    public function isDir($path)
    {
        return is_dir($path);
    }

    /**
     * @see FileSystem
     */
    public function isUploadedFile($path)
    {
        return is_uploaded_file($path);
    }

    /**
     * {@inheritdoc}
     */
    public function doesFileExist($path)
    {
        return file_exists($path) ? true : false;
    }

    /**
     * @see FileSystem
     */
    public function moveUploadedFile($fromPath, $toPath)
    {
        $this->createDir($toPath);
        return copy($fromPath, $toPath) && unlink($fromPath);
    }

    /**
     * @see FileSystem
     */
    public function writeToFile($path, $stream, $append = false)
    {
        $this->createDir($path);
        return file_put_contents($path, $stream, $append ? \FILE_APPEND : 0);
    }

    /**
     * @see FileSystem
     */
    public function getInputStream()
    {
        return fopen('php://input', 'r');
    }

    /**
     * @see FileSystem
     */
    public function getFileStream($path)
    {
        return fopen($path, 'r');
    }

    /**
     * @see FileSystem
     */
    public function unlink($path)
    {
        return unlink($path);
    }

    /**
     * @see FileSystem
     */
    public function clearStatCache($path)
    {
        return clearstatcache(true, $path);
    }

    /**
     * @see FileSystem
     */
    public function getFilesize($path)
    {
        return filesize($path);
    }
}
