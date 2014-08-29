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
namespace DreamFactory\Tools\Freezer\Commands\Freeze;

use DreamFactory\Tools\Freezer\PathZipArchive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Freezes a path
 */
class Path extends Command
{
    protected function configure()
    {
        $_path = null;

        $this->setName( 'freeze:path' )->setDescription( 'Freezes a directory to a zip file.' )->setDefinition(
            array(
                new InputOption( 'path', 'p', InputOption::VALUE_REQUIRED, 'The path to freeze' ),
                new InputOption( 'zip-file-name', 'z', InputOption::VALUE_OPTIONAL, 'The name of the created output file', 'frozen.zip' ),
                new InputOption( 'local-name', 'l', InputOption::VALUE_OPTIONAL, 'The "local name" inside the zip file', null ),
            )
        )->setHelp(
            <<<EOT
                Freezes a directory to the database for later

Usage:

<info>freezer freeze -a /path/to/freeze <env></info>
EOT
        );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
//        $_hs = new OutputFormatterStyle( 'white', 'green', array('bold') );
//        $output->getFormatter()->setStyle( 'header', $_hs );

        //  Get started...
        $_path = $input->getOption( 'path' );
        $_zipFileName = $input->getOption( 'zip-file-name' );
        $_localName = $input->getOption( 'local-name' );
        $_zip = new PathZipArchive( $_zipFileName );

        $output->write( "\0337" );
        $output->write( 'Freezing...' );
        $output->write( "\0338" );

        $_start = microtime( true );
        $_md5 = $_zip->backup( $_path, $_localName );

        $output->writeln( 'Frozen in ' . sprintf( '%01.2f', microtime( true ) - $_start ) . 's, ' . $_zipFileName . ', md5: ' . $_md5 );
    }
}