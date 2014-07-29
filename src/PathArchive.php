<?php
/**
 * This file is part of the DreamFactory Freezer(tm)
 *
 * Copyright 2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Tools\Freezer;

use DreamFactory\Tools\Freezer\Enums\INotify;
use DreamFactory\Tools\Freezer\Exceptions\FreezerException;
use DreamFactory\Tools\Freezer\Interfaces\WatcherLike;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use Kisma\Core\Utility\Storage;

/**
 * Backs up and restores a directory for use on systems without persistent storage.
 */
class PathArchive extends \ZipArchive
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The default table name
     */
    const DEFAULT_TABLE_NAME = 'persist';
    /**
     * @type string The default table prefix
     */
    const DEFAULT_TABLE_PREFIX = 'df_sys_';
    /**
     * @type string
     */
    const CHECKSUM_FILE_NAME = '.md5';

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
    protected $_tablePrefix = null;
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
     * @param string      $storageId   The name of this directory store
     * @param \PDO        $pdo         The PDO instance to use
     * @param WatcherLike $watcher     A watcher instance, if you have one
     * @param string      $tableName   The base name of the table. Defaults to "dir_store"
     * @param string      $tablePrefix The prefix of the storage table. Defaults to "df_sys_"
     */
    public function __construct( $storageId, \PDO $pdo, WatcherLike $watcher = null, $tableName = self::DEFAULT_TABLE_NAME, $tablePrefix = null )
    {
        $this->_storageId = $storageId;
        $this->_pdo = $pdo;
        $this->_tablePrefix = $tablePrefix;
        $this->_tableName = $tablePrefix . $tableName;

        //  Flush myself before shutdown...
        \register_shutdown_function(
            function ( $store )
            {
                /** @var PathArchive $store */
                $store->flush();
            },
            $this
        );

        //  Now create the watcher so his shutdown is after mine...
        $this->_watcher = $watcher ?: ( PathWatcher::available() ? new PathWatcher() : null );

        $this->_initDatabase();
    }

    /**
     * @param string   $path        The path to monitor
     * @param string   $localName   The local name of the directory
     * @param callable $callback    A callback to be executed when a watch event occurs
     * @param int      $mask        The bitmask of events to watch for
     * @param bool     $newRevision If true, backup will be created as a new row with an incremented revision number
     *
     * @throws Exceptions\FreezerException
     * @return bool|int The watch descriptor as registered or FALSE on failure
     */
    public function addSourcePath( $path, $localName = null, $callback = null, $mask = null, $newRevision = false )
    {
        if ( !$this->_watcher )
        {
            return false;
        }

        $_path = $this->_validatePath( $path );
        $_mask = $mask ?: INotify::IN_CREATE | INotify::IN_DELETE | INotify::IN_MODIFY;

        //  Watch for changes...
        $_wd = $this->_watcher->watch( $path, $_mask, $callback );

        if ( false !== $_wd )
        {
            $this->_paths[$_wd] = array(
                'storage_id'   => $this->_storageId,
                'path'         => $_path,
                'new_revision' => $newRevision,
                'local_name'   => $localName
            );
        }

        return $_wd;
    }

    /**
     * Check for changes, possibly blocking whilst waiting. Defaults to wait 250ms (0.25s)
     *
     * @param int $iterations The number of times to check for events
     * @param int $timeout    Defaults to 1
     * @param int $timeout_us Defaults to 0
     *
     * @return array|bool An array of events or FALSE if no watcher is available.
     */
    public function monitor( $iterations = 1, $timeout = WatcherLike::DEFAULT_TIMEOUT, $timeout_us = WatcherLike::DEFAULT_TIMEOUT_US )
    {
        if ( !$this->_watcher )
        {
            return false;
        }

        $_events = array();

        for ( $_i = 0; $_i < $iterations; $_i++ )
        {
            $_events += $this->_watcher->processEvents( $timeout, $timeout_us );
        }

        return $_events;
    }

    /**
     * Flush out any pesky events
     */
    public function flush()
    {
        $this->monitor();
    }

    /**
     * Creates a zip of a directory and writes to the config table
     *
     * @param string $storageId   The name of this directory store
     * @param string $sourcePath  The directory to store
     * @param string $localName   The local name of the directory
     * @param bool   $newRevision If true, backup will be created as a new row with an incremented revision number
     *
     * @throws Exceptions\FreezerException
     * @throws \Exception
     * @return bool
     */
    public function backup( $storageId, $sourcePath, $localName = null, $newRevision = false )
    {
        $_path = $this->_validatePath( $sourcePath );

        //  Make a temp file name...
        $_zipName = $this->_buildZipFile( $_path, $localName );
        $_checksum = md5_file( $_zipName );

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
    time_stamp,
    check_sum
)
VALUES
(
    :storage_id,
    :revision_id,
    :data_blob,
    :time_stamp,
    :check_sum
)
MYSQL;
        }
        else
        {
            $_sql = <<<MYSQL
UPDATE {$this->_tableName} SET
    data_blob = :data_blob,
    time_stamp = :time_stamp,
    check_sum = :check_sum
WHERE
    storage_id = :storage_id AND
    revision_id = :revision_id
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
                        ':check_sum'   => $_checksum,
                    )
                );

                if ( false === $_result )
                {
                    throw new FreezerException( print_r( $this->_pdo->errorInfo(), true ) );
                }

                //  Dump a marker that we've backed up
                if ( !$this->_saveStoreMarker( $_path, $_timestamp, $_checksum ) )
                {
                    echo
                        'path_archive: error creating storage checksum file. No biggie, but you may be out of disk space.' .
                        PHP_EOL;
                }

                echo 'path_archive: backup created: ' . $_checksum . '@' . $_timestamp . PHP_EOL;
            }

            //  Remove temporary file
            \unlink( $_zipName );

            return true;
        }
        catch ( \Exception $_ex )
        {
            echo 'path_archive: exception storing backup data: ' . $_ex->getMessage() . PHP_EOL;
            throw $_ex;
        }
    }

    /**
     * Reads the private storage from the configuration table and restores the directory
     *
     * @param string $storageId The name of this directory store
     * @param string $path      The path in which to restore the data
     * @param string $localName The local name of the directory
     *
     * @return bool Only returns false if no backup exists, otherwise TRUE
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public function restore( $storageId, $path, $localName = null )
    {
        $_path = $this->_validatePath( $path );
        $_timestamp = null;

        //  Get the latest revision
        if ( false === ( $_currentRevision = $this->_getCurrentRevisionId( $storageId ) ) )
        {
            //  No backups...
            return false;
        }

        $_marker = $this->_loadStoreMarker( $_path );

        $_sql = <<<MYSQL
SELECT
    *
FROM
    {$this->_tableName}
WHERE
    storage_id = :storage_id AND
    revision_id = :revision_id AND
    time_stamp >= :time_stamp
ORDER BY
    time_stamp DESC
MYSQL;

        $_params = array(
            ':storage_id'  => $storageId,
            ':revision_id' => $_currentRevision,
            ':time_stamp'  => $_marker['time_stamp'],
        );

        if ( false === ( $_row = Sql::find( $_sql, $_params ) ) )
        {
            if ( '00000' != $this->_pdo->errorCode() )
            {
                throw new FreezerException(
                    'Error retrieving data to restore: ' . print_r( $this->_pdo->errorInfo(), true )
                );
            }

            //  No rows, nothing to do...
            return false;
        }

        //  Nothing to restore...
        if ( $_marker['time_stamp'] == $_row['time_stamp'] && $_marker['check_sum'] == $_row['check_sum'] )
        {
            return true;
        }

        //  Nothing to restore, bail...
        $_payload = Storage::defrost( Option::get( $_row, 'data_blob' ) );

        if ( empty( $_payload ) )
        {
            //  No data, but has backup
            return true;
        }

        $_data = isset( $_payload[$storageId] ) ? $_payload[$storageId] : null;

        //  Make a temp file name...
        $_zipName = $this->_buildZipFile( $_path, $localName, $_data );

        //  Checksum different?
        if ( $_row['check_sum'] != ( $_checksum = md5_file( $_zipName ) ) )
        {
            //  Open our new zip and extract...
            if ( !$this->open( $_zipName ) )
            {
                throw new FreezerException( 'Unable to open temporary zip file.' );
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

            //  Make a new marker with stored info...
            $this->_saveStoreMarker( $_path, $_row['time_stamp'], $_row['check_sum'] );
        }

        return true;
    }

    /**
     * Recursively add a path to myself
     *
     * @param string $path
     * @param string $localName
     *
     * @return bool
     */
    protected function _addPath( $path, $localName = null )
    {
        $_excluded = array(
            ltrim( static::CHECKSUM_FILE_NAME, DIRECTORY_SEPARATOR ),
        );

        $_excludedDirs = array(
            '.private/app.store',
            'swagger',
        );

        $_files =
            FileSystem::glob( $path . DIRECTORY_SEPARATOR . '*.*', GlobFlags::GLOB_NODOTS | GlobFlags::GLOB_RECURSE );

        if ( empty( $_files ) )
        {
            return false;
        }

        //  Clean out the stuff we don't want in there...
        foreach ( $_files as $_index => $_file )
        {
            foreach ( $_excludedDirs as $_mask )
            {
                if ( 0 === strpos( $_file, $_mask, 0 ) )
                {
                    unset( $_files[$_index] );
                }
            }

            if ( in_array( $_file, $_excluded ) && isset( $_files[$_index] ) )
            {
                unset( $_files[$_index] );
            }
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

        return true;
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
                throw new \InvalidArgumentException(
                    'The path "' . $path . '" does not exist and/or cannot be created. Please validate installation.'
                );
            }
        }

        //  Make sure we can read/write there...
        if ( !is_readable( $path ) || !is_writable( $path ) )
        {
            throw new \InvalidArgumentException(
                'The path "' . $path . '" exists but cannot be accessed. Please validate installation.'
            );
        }

        return rtrim( $path, '/' );
    }

    /**
     * Ensures the storage table exists. Creates if not
     *
     * @throws Exceptions\FreezerException
     */
    protected function _initDatabase()
    {
        Sql::setConnection( $this->_pdo );

        //  Create table...
        $_ddl = <<<MYSQL
CREATE TABLE IF NOT EXISTS `{$this->_tableName}`
(
    `storage_id` VARCHAR(64) NOT NULL,
    `revision_id` INT(11) NOT NULL DEFAULT '0',
    `data_blob` MEDIUMTEXT NULL,
    `time_stamp` INT(11) not null,
    `check_sum` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`storage_id`,`revision_id`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8
MYSQL;

        if ( false === ( $_result = Sql::execute( $_ddl ) ) )
        {
            throw new FreezerException( 'Unable to create storage table "' . $this->_tableName . '".' );
        }
    }

    /**
     * @param string $storageId The name of the directory store to check
     *
     * @throws FreezerException
     * @return bool|int
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

        if ( false === ( $_revisionId = Sql::scalar( $_sql, 0, array( ':storage_id' => $storageId ) ) ) )
        {
            throw new FreezerException(
                'Database error during revision check: ' . print_r( $this->_pdo->errorInfo(), true )
            );
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
    protected function _loadStoreMarker( $path )
    {
        $_marker = $path . DIRECTORY_SEPARATOR . static::CHECKSUM_FILE_NAME;

        if ( !file_exists( $_marker ) || false === ( $_data = file_get_contents( $_marker ) ) )
        {
            return array( 'time_stamp' => 0, 'check_sum' => null );
        }

        return json_decode( $_data, true );
    }

    /**
     * Sets the timestamp in a marker file
     *
     * @param string $path      The path
     * @param int    $timestamp The timestamp to use or null (which will use the value of time().
     * @param string $checksum  MD5/SHA1 checksum of zip file
     * @param bool   $delete    If true, the marker file will be deleted.
     *
     * @return int|null|string
     */
    protected function _saveStoreMarker( $path, $timestamp, $checksum, $delete = false )
    {
        $_marker = $path . DIRECTORY_SEPARATOR . static::CHECKSUM_FILE_NAME;

        if ( false !== $delete )
        {
            return @\unlink( $_marker );
        }

        $_data = $this->_buildMarkerContent( $path, $timestamp, $checksum );

        return false !== file_put_contents( $_marker, json_encode( $_data ) );
    }

    /**
     * @param string $path
     * @param int    $timestamp
     * @param string $checksum
     *
     * @return array
     */
    protected function _buildMarkerContent( $path, $timestamp, $checksum )
    {
        return array(
            'path'       => $path,
            'time_stamp' => $timestamp,
            'check_sum'  => $checksum,
        );
    }

    /**
     * Given a path, build a zip file and return the name
     *
     * @param string $path      The path to zip up
     * @param string $localName The local name of the path
     * @param string $data      If provided, write to zip file instead of building from path
     *
     * @throws Exceptions\FreezerException
     * @return string
     */
    protected function _buildZipFile( $path, $localName = null, $data = null )
    {
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( !$this->open( $_zipName, static::CREATE ) )
        {
            throw new FreezerException( 'Unable to create temporary zip file.' );
        }

        //  Restore prior zipped content?
        if ( null !== $data )
        {
            if ( false === ( $_bytes = file_put_contents( $_zipName, $data ) ) )
            {
                $this->close();
                @\unlink( $_zipName );

                throw new FreezerException( 'Error creating temporary zip file for restoration.' );
            }

            return $_zipName;
        }

        //  Build from $path
        if ( $localName )
        {
            $this->addEmptyDir( $localName );
        }

        $this->_addPath( $path, $localName );
        $this->close();

        return $_zipName;
    }
}
