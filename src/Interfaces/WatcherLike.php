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
namespace DreamFactory\Tools\Freezer\Interfaces;

use DreamFactory\Tools\Freezer\Enums\INotify;

/**
 * Something that acts like a watcher
 */
interface WatcherLike
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type int
     */
    const DEFAULT_TIMEOUT = 1;
    /**
     * @type int
     */
    const DEFAULT_TIMEOUT_US = 0;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Checks to see if this service is available.
     *
     * @return bool Returns TRUE if this watcher's requirements are met, otherwise FALSE
     */
    public static function available();

    /**
     * Watch a file/path
     *
     * @param string   $path
     * @param int      $mask
     * @param callable $callback  The callback to execute when something occurs
     * @param bool     $overwrite If true, the mask will be added to any existing mask on the path. If false, the $mask
     *                            will replace an existing watch mask.
     *
     * @throws \DreamFactory\Tools\Freezer\Exceptions\FreezerException
     * @return int The ID of this watch
     */
    public function watch( $path, $mask = INotify::IN_ATTRIB, $callback = null, $overwrite = false );

    /**
     * Stop watching a file/path
     *
     * @param string $path The path to stop watching
     *
     * @return bool
     */
    public function unwatch( $path );

    /**
     * Checks the stream for new watch events
     *
     * @param int $timeout    The number of seconds to wait for events. {@see stream_select}
     * @param int $timeout_us The number microseconds to wait, must use $timeout as well. {@see stream_select}
     *
     * @return array The array of watch events
     */
    public function processEvents( $timeout = self::DEFAULT_TIMEOUT, $timeout_us = self::DEFAULT_TIMEOUT_US );
}
