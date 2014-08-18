<?php
namespace DreamFactory\Tools\Freezer;

use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use Kisma\Core\Utility\Storage;

/**
 * Backs up and restores a directory for use on systems without persistent storage.
 */
class DirectoryStorage extends \ZipArchive
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const DEFAULT_TABLE_NAME = 'dir_store';
    /**
     * @type string
     */
    const MARKER_FILE_NAME = '.dir_stored';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \PDO The PDO instance to use
     */
    protected $_pdo;
    /**
     * @var string A name to associate with this directory store
     */
    protected $_storageId;
    /**
     * @var string The prefix of the storage table's name.
     */
    protected $_tablePrefix = SystemManager::SYSTEM_TABLE_PREFIX;
    /**
     * @var string The base name of the table
     */
    protected $_tableName = self::DEFAULT_TABLE_NAME;
    /**
     * @var PathWatcher
     */
    protected $_watcher = false;
    /**
     * @var array The paths I'm managing
     */
    protected $_paths = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $storageId   The name of this directory store
     * @param \PDO   $pdo         The PDO instance to use
     * @param string $tableName   The base name of the table. Defaults to "dir_store"
     * @param string $tablePrefix The prefix of the storage table. Defaults to "df_sys_"
     *
     * @throws \Kisma\Core\Exceptions\StorageException
     */
    public function __construct( $storageId, \PDO $pdo, $tableName = self::DEFAULT_TABLE_NAME, $tablePrefix = SystemManager::SYSTEM_TABLE_PREFIX )
    {
        $this->_storageId = $storageId;
        $this->_pdo = $pdo;
        $this->_tablePrefix = $tablePrefix;
        $this->_tableName = $tablePrefix . $tableName;

        if ( PathWatcher::available() )
        {
            $this->_watcher = new PathWatcher();
        }

        $this->_initDatabase();
    }

    public function __destruct()
    {
        if ( $this->_watcher )
        {
            $_paths = $this->_paths;

            Platform::on(
                DspEvents::STORAGE_CHANGE,
                /**
                 * @param string             $eventName
                 * @param StorageChangeEvent $event
                 * @param EventDispatcher    $dispatcher
                 */
                function ( $eventName, StorageChangeEvent $event, $dispatcher ) use ( $_paths )
                {
                    if ( null !== ( $_path = Option::get( $_paths, $event->getWatchId() ) ) )
                    {
                        $this->backup( $this->_storageId, $_path );
                    }
                }
            );

            $this->_watcher->checkForEvents();
        }
    }

    /**
     * Creates a zip of a directory and writes to the config table
     *
     * @param string $storageId   The name of this directory store
     * @param string $sourcePath  The directory to store
     * @param string $localName   The local name of the directory
     * @param bool   $newRevision If true, backup will be created as a new row with an incremented revision number
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public function backup( $storageId, $sourcePath, $localName = null, $newRevision = false )
    {
        $_path = $this->_validatePath( $sourcePath );

        //  Make a temp file name...
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( !$this->open( $_zipName, \ZipArchive::CREATE ) )
        {
            throw new FileSystemException( 'Unable to create temporary zip file.' );
        }

        if ( $localName )
        {
            $this->addEmptyDir( $localName );
        }

        $this->_addPath( $_path, $localName );
        $this->close();

        //  Get the latest revision
        $_currentRevision = $this->_getCurrentRevisionId( $storageId );

        if ( $newRevision || false === $_currentRevision )
        {
            $_currentRevision = ( false === $_currentRevision ) ? 0 : ++$_currentRevision;

            $_sql = <<<MYSQL
INSERT INTO {$this->_tableName}
(
    storage_id,
    revision_id,
    data_blob,
    time_stamp
)
VALUES
(
    :storage_id,
    :revision_id,
    :data_blob,
    :time_stamp
)
MYSQL;
        }
        else
        {
            $_sql = <<<MYSQL
UPDATE {$this->_tableName} SET
    data_blob = :data_blob
WHERE
    storage_id = :storage_id AND
    revision_id = :revision_id AND
    time_stamp = :time_stamp
MYSQL;
        }

        try
        {
            if ( false === ( $_data = file_get_contents( $_zipName ) ) )
            {
                throw new \RuntimeException( 'Error reading temporary zip file for storage.' );
            }

            if ( !empty( $_data ) )
            {
                $_payload = array(
                    $storageId => $_data,
                );

                $_timestamp = time();

                $_result = Sql::execute(
                    $_sql,
                    array(
                        ':storage_id'  => $storageId,
                        ':revision_id' => $_currentRevision,
                        ':data_blob'   => Storage::freeze( $_payload ),
                        ':time_stamp'  => $_timestamp,
                    )
                );

                if ( false === $_result )
                {
                    throw new StorageException( print_r( $this->_pdo->errorInfo(), true ) );
                }

                //  Dump a marker that we've backed up
                if ( !$this->_setDirStoreTimestamp( $_path, $_timestamp ) )
                {
                    Log::error( 'Error creating storage marker file. No biggie, but you may be out of disk space.' );
                }
            }

            //  Remove temporary file
            \unlink( $_zipName );

            //  Watch for changes...
            if ( $this->_watcher )
            {
                $_id = $this->_watcher->watch( $_path );

                if ( false !== $_id )
                {
                    $this->_paths[$_id] = array(
                        'storage_id'   => $storageId,
                        'path'         => $_path,
                        'new_revision' => $newRevision,
                        'local_name'   => $localName
                    );
                }
            }

            return true;
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception storing backup data: ' . $_ex->getMessage() );
            throw $_ex;
        }
    }

    /**
     * Reads the private storage from the configuration table and restores the directory
     *
     * @param string $storageId The name of this directory store
     * @param string $path      The path in which to restore the data
     *
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public function restore( $storageId, $path )
    {
        $_path = $this->_validatePath( $path );
        $_timestamp = null;

        //  Get the latest revision
        if ( false === ( $_currentRevision = $this->_getCurrentRevisionId( $storageId ) ) )
        {
            return;
        }

        $_timestamp = $this->_getDirStoreTimestamp( $_path );

        $_sql = <<<MYSQL
SELECT
    *
FROM 
    {$this->_tableName}
WHERE
    storage_id = :storage_id AND
    revision_id = :revision_id AND
    time_stamp > :time_stamp
MYSQL;

        $_params = array(
            ':storage_id'  => $storageId,
            ':revision_id' => $_currentRevision,
            ':time_stamp'  => $_timestamp
        );

        if ( false === ( $_row = Sql::find( $_sql, $_params ) ) )
        {
            if ( $this->_pdo->errorCode() )
            {
                throw new StorageException( 'Error retrieving data to restore: ' . print_r( $this->_pdo->errorInfo(), true ) );
            }

            //  No rows...
            return;
        }

        //  Nothing to restore, bail...
        $_payload = Storage::defrost( Option::get( $_row, 'data_blob' ) );

        if ( empty( $_payload ) )
        {
            return;
        }

        $_data = Option::get( $_payload, $storageId );

        //  Make a temp file name...
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( false === ( $_bytes = file_put_contents( $_zipName, $_data ) ) )
        {
            throw new FileSystemException( 'Error creating temporary zip file for restoration.' );
        }

        //  Open our new zip and extract...
        if ( !$this->open( $_zipName ) )
        {
            throw new FileSystemException( 'Unable to open temporary zip file.' );
        }

        try
        {
            $this->extractTo( $_path );
            $this->close();
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception restoring private backup: ' . $_ex->getMessage() );
            throw $_ex;
        }

        //  Remove temporary file
        \unlink( $_zipName );

        //  Remove marker, ignore result (may not be one...)
        $this->_setDirStoreTimestamp( $_path, null, true );
    }

    /**
     * Recursively add a path to myself
     *
     * @param string $path
     * @param string $localName
     */
    protected function _addPath( $path, $localName = null )
    {
        $_files = FileSystem::glob( $path . '/*.*', GlobFlags::GLOB_NODOTS | GlobFlags::GLOB_RECURSE );

        if ( empty( $_files ) )
        {
            return;
        }

        foreach ( $_files as $_file )
        {
            $_filePath = $path . DIRECTORY_SEPARATOR . $_file;
            $_localFilePath = ( $localName ? $localName . DIRECTORY_SEPARATOR . $_file : $_file );

            if ( is_dir( $_filePath ) )
            {
                $this->addEmptyDir( $_file );
            }
            else
            {
                $this->addFile( $_filePath, $_localFilePath );
            }
        }
    }

    /**
     * @param string $path
     * @param bool   $restoring If true, the directory will be created if it does not exist
     *
     * @return string
     */
    protected function _validatePath( $path, $restoring = false )
    {
        if ( empty( $path ) )
        {
            throw new \InvalidArgumentException( 'Invalid path specified.' );
        }

        if ( !is_dir( $path ) )
        {
            //  Try and make the directory if wanted
            if ( !$restoring || false === ( $_result = mkdir( $path, 0777, true ) ) )
            {
                throw new \InvalidArgumentException( 'The path "' . $path . '" does not exist and/or cannot be created. Please validate installation.' );
            }
        }

        //  Make sure we can read/write there...
        if ( !is_readable( $path ) || !is_writable( $path ) )
        {
            throw new \InvalidArgumentException( 'The path "' . $path . '" exists but cannot be accessed. Please validate installation.' );
        }

        return rtrim( $path, '/' );
    }

    /**
     * Ensures the storage table exists. Creates if not
     * @throws \Kisma\Core\Exceptions\StorageException
     */
    protected function _initDatabase()
    {
        Sql::setConnection( $this->_pdo );

        //  Create table...
        $_ddl = <<<MYSQL
CREATE TABLE IF NOT EXISTS `{$this->_tableName}` 
(
    `storage_id` VARCHAR(64) NOT NULL,
    `revision_id` int(11) NOT NULL DEFAULT '0',
    `data_blob` MEDIUMTEXT NULL,
    `time_stamp` int(11) not null,
    PRIMARY KEY (`storage_id`,`revision_id`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8
MYSQL;

        if ( false === ( $_result = Sql::execute( $_ddl ) ) )
        {
            throw new StorageException( 'Unable to create storage table "' . $this->_tableName . '".' );
        }
    }

    /**
     * @param string $storageId The name of the directory store to check
     *
     * @return bool|int
     * @throws \Kisma\Core\Exceptions\StorageException
     */
    protected function _getCurrentRevisionId( $storageId )
    {
        $_sql = <<<MYSQL
SELECT
    MAX(revision_id)
FROM
    {$this->_tableName}
WHERE
    storage_id = :storage_id
MYSQL;

        if ( false === ( $_revisionId = Sql::scalar( $_sql, 0, array(':storage_id' => $storageId) ) ) )
        {
            throw new StorageException( 'Database error during revision check: ' . print_r( $this->_pdo->errorInfo(), true ) );
        }

        //  If no revisions, return false...
        return null === $_revisionId ? false : $_revisionId;
    }

    /**
     * Retrieves the timestamp from a marker file, if any
     *
     * @param string $path
     *
     * @return int|null|string
     */
    protected function _getDirStoreTimestamp( $path )
    {
        $_marker = $path . DIRECTORY_SEPARATOR . static::MARKER_FILE_NAME;

        if ( !file_exists( $_marker ) || false === ( $_timestamp = file_get_contents( $_marker ) ) )
        {
            return 0;
        }

        return $_timestamp;
    }

    /**
     * Sets the timestamp in a marker file
     *
     * @param string $path      The path
     * @param int    $timestamp The timestamp to use or null (which will use the value of time().
     * @param bool   $delete    If true, the marker file will be deleted.
     *
     * @return int|null|string
     */
    protected function _setDirStoreTimestamp( $path, $timestamp = null, $delete = false )
    {
        $_marker = $path . DIRECTORY_SEPARATOR . static::MARKER_FILE_NAME;

        if ( false !== $delete )
        {
            return @\unlink( $_marker );
        }

        return false !== file_put_contents( $_marker, $timestamp ?: time() );
    }
}
