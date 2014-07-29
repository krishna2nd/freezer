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
namespace DreamFactory\Tools\Freezer\Enums;

use Kisma\Core\Enums\SeedEnum;
use Kisma\Core\Utility\Option;

/**
 * Constants for use with the inotify extension
 */
class INotify extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int
     */
    const IN_ACCESS = 1;
    /**
     * @type int
     */
    const IN_MODIFY = 2;
    /**
     * @type int
     */
    const IN_ATTRIB = 4;
    /**
     * @type int
     */
    const IN_CLOSE_WRITE = 8;
    /**
     * @type int
     */
    const IN_CLOSE_NOWRITE = 16;
    /**
     * @type int
     */
    const IN_OPEN = 32;
    /**
     * @type int
     */
    const IN_MOVED_FROM = 64;
    /**
     * @type int
     */
    const IN_MOVED_TO = 128;
    /**
     * @type int
     */
    const IN_CREATE = 256;
    /**
     * @type int
     */
    const IN_DELETE = 512;
    /**
     * @type int
     */
    const IN_DELETE_SELF = 1024;
    /**
     * @type int
     */
    const IN_MOVE_SELF = 2048;
    /**
     * @type int
     */
    const IN_UNMOUNT = 8192;
    /**
     * @type int
     */
    const IN_Q_OVERFLOW = 16384;
    /**
     * @type int
     */
    const IN_IGNORED = 32768;
    /**
     * @type int
     */
    const IN_CLOSE = 24;
    /**
     * @type int
     */
    const IN_MOVE = 192;
    /**
     * @type int
     */
    const IN_ALL_EVENTS = 4095;
    /**
     * @type int
     */
    const IN_ONLYDIR = 16777216;
    /**
     * @type int
     */
    const IN_DONT_FOLLOW = 33554432;
    /**
     * @type int
     */
    const IN_MASK_ADD = 536870912;
    /**
     * @type int
     */
    const IN_ISDIR = 1073741824;
    /**
     * @type int
     */
    const IN_ONESHOT = 2147483648;

    //*************************************************************************
    //	Members
    //*************************************************************************

    protected static $_messages = array(
        self::IN_ACCESS        => 'File was accessed (read)',
        self::IN_MODIFY        => 'File was modified',
        self::IN_ATTRIB        => 'Metadata changed (e.g. permissions, mtime, etc.)',
        self::IN_CLOSE_WRITE   => 'File opened for writing was closed',
        self::IN_CLOSE_NOWRITE => 'File not opened for writing was closed',
        self::IN_OPEN          => 'File was opened',
        self::IN_MOVED_TO      => 'File moved into watched directory',
        self::IN_MOVED_FROM    => 'File moved out of watched directory',
        self::IN_CREATE        => 'File or directory created in watched directory',
        self::IN_DELETE        => 'File or directory deleted in watched directory',
        self::IN_DELETE_SELF   => 'Watched file or directory was deleted',
        self::IN_MOVE_SELF     => 'Watch file or directory was moved',
        self::IN_CLOSE         => 'Equals to IN_CLOSE_WRITE | IN_CLOSE_NOWRITE',
        self::IN_MOVE          => 'Equals to IN_MOVED_FROM | IN_MOVED_TO',
        self::IN_ALL_EVENTS    => 'Bitmask of all the above constants',
        self::IN_UNMOUNT       => 'File system containing watched object was unmounted',
        self::IN_Q_OVERFLOW    => 'Event queue overflowed (wd is -1 for this event)',
        self::IN_IGNORED       => 'Watch was removed (explicitly by inotify_rm_watch() or because file was removed or filesystem unmounted',
        self::IN_ISDIR         => 'Subject of this event is a directory',
        self::IN_CLOSE_NOWRITE => 'High-bit: File not opened for writing was closed',
        self::IN_OPEN          => 'High-bit: File was opened',
        self::IN_CREATE        => 'High-bit: File or directory created in watched directory',
        self::IN_DELETE        => 'High-bit: File or directory deleted in watched directory',
        self::IN_ONLYDIR       => 'Only watch pathname if it is a directory (Since Linux 2.6.15)',
        self::IN_DONT_FOLLOW   => 'Do not dereference pathname if it is a symlink (Since Linux 2.6.15)',
        self::IN_MASK_ADD      => 'Add events to watch mask for this pathname if it already exists (instead of replacing mask).',
        self::IN_ONESHOT       => 'Monitor pathname for one event, then remove from watch list.',
    );

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param int $mask
     *
     * @return mixed
     */
    public static function getMessage( $mask )
    {
        return Option::get( static::$_messages, $mask );
    }

}
