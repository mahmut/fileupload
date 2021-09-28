<?php

namespace FileUpload;

use FileUpload\FileNameGenerator\FileNameGenerator;
use FileUpload\FileNameGenerator\Simple;
use FileUpload\FileSystem\FileSystem;
use FileUpload\PathResolver\PathResolver;
use FileUpload\Validator\Validator;
use Psr\Log\LoggerInterface;

class FileUpload
{
    /**
     * Our own error constants
     */
    const UPLOAD_ERR_PHP_SIZE = 20;

    /**
     * $_FILES
     * @var array
     */
    protected $upload;

    /**
     * The array of uploaded files
     * @var array
     */
    protected $files;

    /**
     * $_SERVER
     * @var array
     */
    protected $server;

    /**
     * Path resolver instance
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * Path resolver instance
     * @var FileNameGenerator
     */
    protected $fileNameGenerator;

    /**
     * File system instance
     * @var FileSystem
     */
    protected $fileSystem;

    /**
     * Optional logger
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * File Container instance
     * @var File
     */
    protected $fileContainer;

    /**
     * Validators to be run
     * @var array
     */
    protected $validators = [];

    /**
     * Callbacks to be run
     * @var array
     */
    protected $callbacks = [];

    /**
     * Default messages
     * @var array
     */
    protected $messages = [
        // PHP $_FILES-own
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',

        // Our own
        self::UPLOAD_ERR_PHP_SIZE => 'The upload file exceeds the post_max_size or the upload_max_filesize directives in php.ini',
    ];

    /**
     * Construct this mother
     * @param array             $upload
     * @param array             $server
     * @param FileNameGenerator $generator
     */
    public function __construct($upload, $server, FileNameGenerator $generator = null)
    {
        $this->upload = isset($upload) ? $upload : null;
        $this->server = $server;
        $this->fileNameGenerator = $generator ?: new Simple();
        $this->prepareMessages();
    }

    /**
     * Converts $messages array into a hash with strings as keys
     * This allows us to work with the keys and values as if it was a hash
     * Which it really should be but, well, arrays in PHP, am I right?
     */
    private function prepareMessages()
    {
        $prepared = [];

        foreach ($this->messages as $key => $msg) {
            $prepared[(string)$key] = $msg;
        }

        $this->messages = $prepared;
    }

    /**
     * @return PathResolver
     */
    public function getPathResolver()
    {
        return $this->pathResolver;
    }

    /**
     * Set path resolver
     * @param PathResolver $pr
     */
    public function setPathResolver(PathResolver $pr)
    {
        $this->pathResolver = $pr;
    }

    /**
     * @return FileNameGenerator
     */
    public function getFileNameGenerator()
    {
        return $this->fileNameGenerator;
    }

    /**
     * Set filename generator
     * @param FileNameGenerator $fng
     */
    public function setFileNameGenerator(FileNameGenerator $fng)
    {
        $this->fileNameGenerator = $fng;
    }

    /**
     * @return FileSystem
     */
    public function getFileSystem()
    {
        return $this->fileSystem;
    }

    /**
     * Set file system
     * @param FileSystem $fs
     */
    public function setFileSystem(FileSystem $fs)
    {
        $this->fileSystem = $fs;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set logger, optionally
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Register callback for an event
     * @param string   $event
     * @param \Closure $callback
     */
    public function addCallback($event, \Closure $callback)
    {
        $this->callbacks[$event][] = $callback;
    }

    /**
     * Merge (overwrite) default error messages
     * @param array $new_messages
     */
    public function setMessages(array $new_messages)
    {
        $this->messages = array_merge($this->messages, $new_messages);
    }

    /**
     * Returns an array of all uploaded files
     * @return array
     */
    public function getFiles()
    {
        return ($this->files);
    }

    /**
     * Process entire submitted request
     * @return array Files and response headers
     */
    public function processAll()
    {
        $contentRange = $this->getContentRange();
        $size = $this->getSize();
        $this->files = [];
        $upload = $this->upload;

        if ($this->logger) {
            $this->logger->debug('Processing uploads', [
                'Content-range' => $contentRange,
                'Size' => $size,
                'Upload array' => $upload,
                'Server array' => $this->server,
            ]);
        }

        if ($upload && is_array($upload['tmp_name'])) {
            foreach ($upload['tmp_name'] as $index => $tmpName) {
                if (empty($tmpName)) {
                    // Discard empty uploads
                    continue;
                }

                $this->files[] = $this->process(
                    $tmpName,
                    $upload['name'][$index],
                    $size ? $size : $upload['size'][$index],
                    $upload['type'][$index],
                    $upload['error'][$index],
                    $index,
                    $contentRange
                );
            }
        } else {
            if ($upload && ! empty($upload['tmp_name'])) {
                $this->files[] = $this->process(
                    $upload['tmp_name'],
                    $upload['name'],
                    $size ? $size : (isset($upload['size']) ? $upload['size'] : $this->getContentLength()),
                    isset($upload['type']) ? $upload['type'] : $this->getContentType(),
                    $upload['error'],
                    0,
                    $contentRange
                );
            } else {
                if ($upload && $upload['error'] != 0) {
                    // $this->fileContainer is empty at this point
                    // $upload['tmp_name'] is also empty
                    // So we create a File instance from $upload['name']
                    $file = new File($upload['name'], basename($upload['name']));
                    $file->error = $this->getMessage($upload['error']);
                    $file->errorCode = $upload['error'];
                    $this->files[] = $file;
                }
            }
        }

        return [$this->files, $this->getNewHeaders($this->files, $contentRange)];
    }

    /**
     * Content-range header
     * @return array
     */
    protected function getContentRange()
    {
        return isset($this->server['HTTP_CONTENT_RANGE']) ?
            preg_split('/[^0-9]+/', $this->server['HTTP_CONTENT_RANGE']) : null;
    }

    /**
     * Request size
     * @return integer
     */
    protected function getSize()
    {
        $range = $this->getContentRange();

        return $range ? $range[3] : null;
    }

    /**
     * Process single submitted file
     * @param  string  $tmpName
     * @param  string  $name
     * @param  integer $size
     * @param  string  $type
     * @param  integer $error
     * @param  integer $index
     * @param  array   $contentRange
     * @return File
     */
    protected function process($tmpName, $name, $size, $type, $error, $index = 0, $contentRange = null)
    {
        $this->fileContainer = $file = new File($tmpName, $name);
        $file->name = $this->getFilename($name, $type, $index, $contentRange, $tmpName);
        $file->size = $this->fixIntegerOverflow(intval($size));

        $completed = false;

        if ($file->name) {
            //since the md5 filename generator would return false if it's allowDuplicate property is set to false and the file already exists.

            if ($this->validate($tmpName, $file, $error, $index))
            {
                $uploadPath = $this->pathResolver->getUploadPath();
                $directory = $this->pathResolver->getDirectory();
                $relativePath = $this->pathResolver->getRelativePath($file->name);
                $filePath = $this->pathResolver->getUploadPath($file->name);

                //$this->fileSystem->createDir($uploadPath);

                $appendFile = $contentRange && $this->fileSystem->isFile($filePath) && $file->size > $this->getFilesize($filePath);
                if ($tmpName && $this->fileSystem->isUploadedFile($tmpName)) {
                    // This is a normal upload from temporary file
                    if ($appendFile) {
                        // Adding to existing file (chunked uploads)
                        $this->fileSystem->writeToFile($filePath, $this->fileSystem->getFileStream($tmpName), true);
                    } else {
                        // Upload full file
                        $this->fileSystem->moveUploadedFile($tmpName, $filePath);
                    }
                } else {
                    // This is a PUT-type upload
                    $this->fileSystem->writeToFile($filePath, $this->fileSystem->getInputStream(), $appendFile);
                }

                $file_size = $this->getFilesize($filePath, $appendFile);

                if ($this->logger) {
                    $this->logger->debug('Processing ' . $file->name, [
                        'File path' => $filePath,
                        'File object' => $file,
                        'Append to file?' => $appendFile,
                        'File exists?' => $this->fileSystem->isFile($filePath),
                        'File size' => $file_size,
                    ]);
                }

                if ($file->size == $file_size) {
                    // Yay, upload is complete!
                    $completed = true;
                } else {
                    if (! $contentRange) {
                        // The file is incomplete and it's not a chunked upload, abort
                        $this->fileSystem->unlink($filePath);
                        $file->error = 'abort';
                    }
                }

                $file = new File($filePath, $name);
                $file->completed = $completed;
                $file->size = $file_size;
                $file->directory = $directory;
                $file->relativePath = $relativePath;

                if ($completed) {
                    $this->processCallbacksFor('completed', $file);
                }
            }
        }

        return $file;
    }

    /**
     * Get filename for submitted filename
     * @param  string  $name
     * @param  string  $type
     * @param  integer $index
     * @param  array   $contentRange
     * @param  string  $tmpName
     * @return string
     */
    protected function getFilename($name, $type, $index, $contentRange, $tmpName)
    {
        $name = $this->trimFilename($name, $type, $index, $contentRange);

        return ($this->fileNameGenerator->getFileName($name, $type, $tmpName, $index, $contentRange, $this));
    }

    /**
     * Remove harmful characters from filename
     * @param  string  $name
     * @param  string  $type
     * @param  integer $index
     * @param  array   $contentRange
     * @return string
     */
    protected function trimFilename($name, $type, $index, $contentRange)
    {
        $name = trim(basename(stripslashes($name)), ".\x00..\x20");

        if (! $name) {
            $name = str_replace('.', '-', microtime(true));
        }

        return $name;
    }

    /**
     * Ensure correct value for big integers
     * @param  integer $int
     * @return float
     */
    protected function fixIntegerOverflow($int)
    {
        if ($int < 0) {
            $int += 2.0 * (PHP_INT_MAX + 1);
        }

        return $int;
    }

    /**
     * Validate upload using some default rules, and custom
     * validators added via addValidator. Default rules:
     *
     * - No PHP errors from $_FILES
     * - File size permitted by PHP config
     *
     * @param  string  $tmpName
     * @param  File    $file
     * @param  integer $error
     * @param  integer $index
     * @return boolean
     */
    protected function validate($tmpName, File $file, $error, $index)
    {
        $this->processCallbacksFor('beforeValidation', $file);

        if ($error !== 0) {
            // PHP error
            $file->error = $this->getMessage($error);
            $file->errorCode = $error;

            return false;
        }

        $content_length = $this->getContentLength();
        $post_max_size = $this->getConfigBytes(ini_get('post_max_size'));
        $upload_max_size = $this->getConfigBytes(ini_get('upload_max_filesize'));

        if (($post_max_size && ($content_length > $post_max_size)) || ($upload_max_size && ($content_length > $upload_max_size))) {
            // Uploaded file exceeds maximum filesize PHP accepts in the configs
            $file->error = $this->getMessage(self::UPLOAD_ERR_PHP_SIZE);
            $file->errorCode = self::UPLOAD_ERR_PHP_SIZE;

            return false;
        }

        if ($tmpName && $this->fileSystem->isUploadedFile($tmpName)) {
            $current_size = $this->getFilesize($tmpName);
        } else {
            $current_size = $content_length;
        }

        // Now that we passed basic, implementation-agnostic tests,
        // let's do custom validators
        foreach ($this->validators as $validator) {
            if (! $validator->validate($file, $current_size)) {
                return false;
            }
        }

        $this->processCallbacksFor('afterValidation', $file);

        return true;
    }

    /**
     * Process callbacks for a given event
     * @param string $eventName
     * @param File   $file
     * @return void
     */
    protected function processCallbacksFor($eventName, File $file)
    {
        if (! array_key_exists($eventName, $this->callbacks) || empty($this->callbacks[$eventName])) {
            return;
        }

        foreach ($this->callbacks[$eventName] as $callback) {
            $callback($file);
        }
    }

    /**
     * Get an error message
     * @param  int $code
     * @return string
     */
    public function getMessage($code)
    {
        return $this->messages[((string)$code)];
    }

    /**
     * Content-length header
     * @return integer
     */
    protected function getContentLength()
    {
        return isset($this->server['CONTENT_LENGTH']) ? $this->server['CONTENT_LENGTH'] : null;
    }

    /**
     * Convert size format from PHP config into bytes
     * @param  string $val
     * @return float
     */
    protected function getConfigBytes($val)
    {
        $val = trim($val);
        $bytes = (int)(substr($val, 0, -1));
        $last = strtolower($val[strlen($val) - 1]);

        switch ($last) {
            case 'g':
                $bytes *= 1024;
            case 'm':
                $bytes *= 1024;
            case 'k':
                $bytes *= 1024;
        }

        return $this->fixIntegerOverflow($bytes);
    }

    /**
     * Get size of file
     * @param  string  $path
     * @param  boolean $clear_cache
     * @return float
     */
    protected function getFilesize($path, $clear_cache = false)
    {
        if ($clear_cache) {
            $this->fileSystem->clearStatCache($path);
        }

        return $this->fixIntegerOverflow($this->fileSystem->getFilesize($path));
    }

    /**
     * Content-type header
     * @return string
     */
    protected function getContentType()
    {
        return isset($this->server['CONTENT_TYPE']) ? $this->server['CONTENT_TYPE'] : null;
    }

    /**
     * @return File
     */
    public function getFileContainer()
    {
        return $this->fileContainer;
    }

    /**
     * Generate headers for response
     * @param  array $files
     * @param  array $contentRange
     * @return array
     */
    protected function getNewHeaders(array $files, $contentRange)
    {
        $headers = [
            'pragma' => 'no-cache',
            'cache-control' => 'no-store, no-cache, must-revalidate',
            'content-disposition' => 'inline; filename="files.json"',
            'x-content-type-options' => 'nosniff'
        ];

        if ($contentRange && is_object($files[0]) && isset($files[0]->size) && $files[0]->size) {
            $headers['range'] = '0-' . ($this->fixIntegerOverflow($files[0]->size) - 1);
        }

        return $headers;
    }

    /**
     * Add another validator
     * @param Validator $v
     */
    public function addValidator(Validator $v)
    {
        $this->validators[] = $v;
    }
}
