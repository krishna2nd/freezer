<?php

/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Tools\Freezer\Tests;

use DreamFactory\Tools\Freezer\PathZipArchive;
use Kisma\Core\Utility\FileSystem;

/**
 * PathZipArchiveTest
 *
 * @package DreamFactory\Tools\Freezer\Tests
 */
class PathZipArchiveTest extends \PHPUnit_Framework_TestCase
{
    protected static $_testPath = '/src/pza-test-dir';
    protected static $_testFile = '/src/pza-test-dir.zip';

    public static function setUpBeforeClass()
    {
        static::$_testPath = dirname( __DIR__ ) . static::$_testPath;
        parent::setUpBeforeClass();
    }

    /**
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public function testBackup()
    {
        $_root = dirname( __DIR__ );
        $_testFile = $_root . static::$_testFile;

        $_zip = new PathZipArchive( $_testFile );
        $_result = $_zip->backup( static::$_testPath );

        $this->assertTrue( md5_file( $_testFile ) == $_result );
        $this->assertTrue( unlink( $_testFile ) );
    }

    /**
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public function testRestore()
    {
        $_root = dirname( __DIR__ );
        $_testFile = $_root . static::$_testFile;

        $_zip = new PathZipArchive( $_testFile );
        $_result = $_zip->backup( static::$_testPath );

        $this->assertTrue( md5_file( $_testFile ) == $_result );
        $_zip = null;

        rename( static::$_testPath, static::$_testPath . '.save' );

        $_zip = new PathZipArchive( $_testFile );
        $_zip->restore( static::$_testPath );
        $_zip = null;

        $this->assertTrue( unlink( $_testFile ) );
        FileSystem::rmdir( static::$_testPath, true );
        rename( static::$_testPath . '.save', static::$_testPath );
    }

}
 