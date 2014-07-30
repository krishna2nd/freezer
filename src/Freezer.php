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

use DreamFactory\Tools\Freezer\Commands\CleanCommand;
use DreamFactory\Tools\Freezer\Commands\DefrostCommand;
use DreamFactory\Tools\Freezer\Commands\FreezeCommand;
use DreamFactory\Tools\Freezer\Commands\IcemakerCommand;
use Symfony\Component\Console\Application;

/**
 * Freezes a directory
 */
class Freezer extends Application
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const VERSION = '1.0.0';

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Constructor.
     *
     * @param string $name    The name of the application
     * @param string $version The version of the application
     *
     * @api
     */
    public function __construct( $name = 'UNKNOWN', $version = 'UNKNOWN' )
    {
        parent::__construct( 'DreamFactory Freezer', static::VERSION );

        $this->add( new CleanCommand() );
        $this->add( new DefrostCommand() );
        $this->add( new FreezeCommand() );
        $this->add( new IcemakerCommand() );
//      $this->add( new DumpCommand() );
    }

}
