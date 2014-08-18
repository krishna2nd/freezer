<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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

/**
 * Path/File watching object
 */
class PathWatcherTest extends \PHPUnit_Framework_TestCase
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string
     */
    protected static $_testPath;
    /**
     * @var string
     */
    protected static $_testFile;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $dir
     *
     * @return bool
     */
    public static function rmdir_recursive( $dir )
    {
        $_files = array_diff( scandir( $dir ), array('.', '..') );

        foreach ( $_files as $_file )
        {
            $_filePath = $dir . '/' . $_file;

            if ( is_dir( $_filePath ) )
            {
                static::rmdir_recursive( $_filePath );
            }
            else
            {
                unlink( $_filePath );
            }
        }

        return rmdir( $dir );
    }

    /**
     * @covers PathWatcher::available
     */
    public static function setUpBeforeClass()
    {
        PathWatcher::available();

        static::$_testPath = getcwd() . '/path.watcher.test.dir';
        static::$_testFile = static::$_testPath . '/test.file.txt';

        if ( static::$_testPath && is_dir( static::$_testPath ) )
        {
            static::rmdir_recursive( static::$_testPath );
        }

        mkdir( static::$_testPath );

        file_put_contents( static::$_testFile, time() );

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        if ( is_dir( static::$_testPath ) )
        {
            static::rmdir_recursive( static::$_testPath );
        }

        parent::tearDownAfterClass();
    }

    /**
     * Watch a file/path
     *
     * @covers PathWatcher::watch
     * @covers PathWatcher::unwatch
     * @covers PathWatcher::ProcessEvents
     *
     * @return int The ID of this watch
     */
    public function testPathWatch()
    {
        $this->_watchTest( static::$_testPath );
    }

    /**
     * Watch a file/path
     *
     * @covers PathWatcher::watch
     * @covers PathWatcher::unwatch
     * @covers PathWatcher::processEvents
     *
     * @return int The ID of this watch
     */
    public function testFileWatch()
    {
        $this->_watchTest( static::$_testFile );
    }

    /**
     * Watch a file/path
     *
     * @covers PathWatcher::watch
     * @covers PathWatcher::unwatch
     * @covers PathWatcher::processEvents
     *
     * @param string $path /path/to/dir/or/file to watch
     *
     * @return int The ID of this watch
     */
    protected function _watchTest( $path )
    {
        $_watcher = new PathWatcher();
        $_id = $_watcher->watch( $path, INotify::IN_CREATE + INotify::IN_DELETE + INotify::IN_MODIFY + INotify::IN_ATTRIB );

        $this->assertTrue( $_id > 0 );

        //  Make a change
        if ( is_dir( $path ) )
        {
            $path .= '/watch.test.path';

            if ( !is_dir( $path ) )
            {
                mkdir( $path, 0777, true );
            }

            $path .= '/test.file.txt';
        }

        //  change the file
        file_put_contents( $path, md5( time() ) );

        //  Check for a change, don't fire events
        $_changes = $_watcher->processEvents( false );

        $this->assertTrue( !empty( $_changes ) );

        echo count( $_changes ) . ' change(s) detected.' . PHP_EOL;

        //  Unwatch the file
        $this->assertTrue( $_watcher->unwatch( $path ) );
    }
}
