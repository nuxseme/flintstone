<?php

/*
 * This file is part of the Flintstone package.
 *
 * (c) Jason M <emailfire@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Flintstone;

use SplFileObject;
use SplTempFileObject;

class Database
{
    /**
     * File read flag.
     *
     * @var int
     */
    const FILE_READ = 1;

    /**
     * File write flag.
     *
     * @var int
     */
    const FILE_WRITE = 2;

    /**
     * File append flag.
     *
     * @var int
     */
    const FILE_APPEND = 3;

    /**
     * File access mode.
     *
     * @var array
     */
    protected $fileAccessMode = array(
        self::FILE_READ => array(
            'mode' => 'rb',
            'operation' => LOCK_SH,
        ),
        self::FILE_WRITE => array(
            'mode' => 'wb',
            'operation' => LOCK_EX,
        ),
        self::FILE_APPEND => array(
            'mode' => 'ab',
            'operation' => LOCK_EX,
        ),
    );

    /**
     * Database name.
     *
     * @var string
     */
    protected $name;

    /**
     * Config class.
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param string $name
     * @param Config|null $config
     */
    public function __construct($name, Config $config = null)
    {
        $this->setName($name);

        if ($config) {
            $this->setConfig($config);
        }
    }

    /**
     * Get the database name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the database name.
     *
     * @param string $name
     *
     * @throws Exception
     */
    public function setName($name)
    {
        if (empty($name) || !preg_match('/^[\w-]+$/', $name)) {
            throw new Exception('Invalid characters in database name');
        }

        $this->name = $name;
    }

    /**
     * Get the config.
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set the config.
     *
     * @param Config $config
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get the path to the database file.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->config->getDir() . $this->getName() . $this->config->getExt();
    }

    /**
     * Open the database file.
     *
     * @param int $mode
     *
     * @throws Exception
     *
     * @return SplFileObject
     */
    public function openFile($mode)
    {
        $path = $this->getPath();

        if (!is_file($path) && !@touch($path)) {
            throw new Exception('Could not create file: ' . $path);
        }

        if (!is_readable($path) || !is_writable($path)) {
            throw new Exception('File does not have permission for read and write: ' . $path);
        }

        if ($this->getConfig()->useGzip()) {
            $path = 'compress.zlib://' . $path;
        }

        $res = $this->fileAccessMode[$mode];
        $file = new SplFileObject($path, $res['mode']);

        if (self::FILE_READ == $mode) {
            $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        }

        if (!$this->getConfig()->useGzip() && !$file->flock($res['operation'])) {
            throw new Exception('Could not lock file: ' . $path);
        }

        return $file;
    }

    /**
     * Open a temporary file.
     *
     * @return SplTempFileObject
     */
    public function openTempFile()
    {
        return new SplTempFileObject($this->getConfig()->getSwapMemoryLimit());
    }

    /**
     * Close the database file.
     *
     * @param SplFileObject $file
     *
     * @throws Exception
     */
    public function closeFile(SplFileObject &$file)
    {
        if (!$this->getConfig()->useGzip() && !$file->flock(LOCK_UN)) {
            $file = null;
            throw new Exception('Could not unlock file');
        }

        $file = null;
    }
}
