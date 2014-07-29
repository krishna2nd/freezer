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

/**
 * Path/File watching object
 */
class PathWatcher implements WatcherLike
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var resource Our inotify instance
     */
    protected $_stream;
    /**
     * @var array Array of watch information
     */
    protected $_watches = array();
    /**
     * @var array Array of watch descriptor => path mappings for quick lookups
     */
    protected $_descriptors = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Creates a watcher instance and registers a shutdown function for itself to do gc
     *
     * @throws Exceptions\FreezerException
     */
    public function __construct()
    {
        if ( !static::available( true ) )
        {
            throw new FreezerException( 'The PECL "inotify" extension is required to use this class.' );
        }

        $this->_descriptors = $this->_watches = array();

        \register_shutdown_function(
            function ( $watcher )
            {
                /** @var PathWatcher $watcher */
                $watcher->flush();
            },
            $this
        );

        /** @noinspection PhpUndefinedFunctionInspection */
        $this->_stream = inotify_init();
    }

    /**
     * Clean up stream and remove any watches
     */
    public function flush()
    {
        if ( static::streamValid( $this->_stream ) && !empty( $this->_watches ) )
        {
            foreach ( $this->_watches as $_watch )
            {
                /** @noinspection PhpUndefinedFunctionInspection */
                inotify_rm_watch( $this->_stream, $_watch['wd'] );
            }

            fclose( $this->_stream );
        }
    }

    /** @InheritDoc */
    public static function available()
    {
        return function_exists( '\\inotify_init' );
    }

    /** @InheritDoc */
    public function watch( $path, $mask = INotify::IN_ATTRIB, $callback = null, $overwrite = true )
    {
        if ( isset( $this->_watches[$path] ) && !$overwrite )
        {
            $mask |= INotify::IN_MASK_ADD;
        }

        /** @noinspection PhpUndefinedFunctionInspection */
        $_wd = inotify_add_watch( $this->_stream, $path, $mask );

        if ( !$_wd )
        {
            throw new FreezerException(
                'Unexpected watch descriptor returned from "inotify_add_watch": ' . print_r( $_wd, true )
            );
        }

        $this->_descriptors[$_wd] = $path;

        $this->_watches[$path] = array(
            'wd'       => $_wd,
            'mask'     => $mask,
            'callback' => is_callable( $callback ) ? $callback : null,
        );

        return $_wd;
    }

    /** @InheritDoc */
    public function unwatch( $path )
    {
        if ( !isset( $this->_watches[$path] ) )
        {
            return true;
        }

        /** @noinspection PhpUndefinedFunctionInspection */
        $_result = inotify_rm_watch( $this->_stream, $this->_watches[$path] );

        //  Remove from our view
        unset( $this->_descriptors[$this->_watches[$path]['wd']], $this->_watches[$path] );

        return $_result;
    }

    /** @InheritDoc */
    public function processEvents( $timeout = self::DEFAULT_TIMEOUT, $timeout_us = self::DEFAULT_TIMEOUT_US )
    {
        $_result = array();

        //  Check for changes
        if ( 0 == $this->_processStream( $timeout, $timeout_us ) )
        {
            return $_result;
        }

        //  Process watch events
        /** @noinspection PhpUndefinedFunctionInspection */
        while ( ( $_length = inotify_queue_len( $this->_stream ) ) )
        {
            /** @noinspection PhpUndefinedFunctionInspection */
            if ( false !== ( $_events = inotify_read( $this->_stream ) ) )
            {
                //  Handle events
                foreach ( $_events as $_watchEvent )
                {
                    $_result[] = $_watchEvent;

                    foreach ( $this->_watches as $_path => $_payload )
                    {
                        if ( $_payload['wd'] == $_watchEvent['wd'] )
                        {
                            if ( isset( $_payload['callback'] ) )
                            {
                                call_user_func( $_payload['callback'], $_watchEvent );
                            }
                        }
                    }
                }
            }
        }

        return $_result;
    }

    /**
     * @param int $timeout
     * @param int $timeout_us
     *
     * @return int
     */
    protected function _processStream( $timeout = self::DEFAULT_TIMEOUT, $timeout_us = self::DEFAULT_TIMEOUT_US )
    {
        if ( !$this->streamValid( $this->_stream ) )
        {
            return array();
        }

        $_read = array( $this->_stream );
        $_except = $_write = array();

        return stream_select( $_read, $_write, $_except, $timeout, $timeout_us );
    }

    /**
     * @param resource $stream A stream resource to check
     *
     * @return bool True if the stream given is valid
     */
    public function streamValid( $stream = null )
    {
        $_stream = $stream ?: $this->_stream;

        return $_stream && is_resource( $_stream ) && 'Unknown' != get_resource_type( $_stream );
    }

}