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

use DreamFactory\Tools\Freezer\Commands\ToolCommand;
use DreamFactory\Tools\Freezer\PathZipArchive;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Freezes a path
 */
class Path extends ToolCommand
{
    /**
     * @param string $name
     * @param array  $config
     */
    public function __construct( $name = 'freeze:path', array $config = array() )
    {
        $_config = array(
            'description' => 'Freezes a directory to a zip file.',
            'definition'  => array(
                new InputArgument( 'path', InputArgument::REQUIRED, 'The path to freeze' ),
                new InputArgument( 'zip-file-name', InputArgument::OPTIONAL, 'The name of the created output file', 'frozen.zip' ),
                new InputArgument(
                    'local-path',
                    InputArgument::OPTIONAL,
                    'The local path of the files in the archive. If specified, the absolute path is written to each entry.',
                    null
                ),
                new InputOption( 'checksum', 'c', InputOption::VALUE_NONE, 'If specified an MD5 checksum will be generated for the zip archive.' ),
            ),
            'help'        => <<<EOT
The <info>freeze:path</info> command creates a zip file of the directory
specified by the <comment>path</comment> argument. The name of the zip file
may be specified by using the optional <comment>zip-file-name</comment> argument.

If <comment>zip-file-name</comment> is not specified, the zip file name defaults
to "frozen.zip".

The <comment>local-path</comment> option allows you to add a prefix to the zipped
paths and files. When this is not specified, the zip is made as if from the current
directory (i.e. "./").


<info>freezer freeze:path [-c|--checksum] path [zip-file-name] [local-path]</info>

EOT
        );

        parent::__construct( $name, array_merge( $_config, $config ) );
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
        //  Get started...
        $_path = $input->getArgument( 'path' );
        $_zipFileName = $input->getArgument( 'zip-file-name' );
        $_localName = $input->getArgument( 'local-path' );
        $_checksum = $input->getOption( 'checksum' );
        $_zip = new PathZipArchive( $_zipFileName );

        $this->writeInPlace( 'Freezing...' );

        $_md5 = $_zip->backup( $_path, $_localName, $_checksum );

        $output->writeln(
            'Frozen in ' . sprintf( '%01.4f', $this->_elapsed() ) . 's, ' . $_zipFileName . ( $_checksum ? ', md5: ' . $_md5 : null )
        );
    }
}