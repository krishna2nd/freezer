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
namespace DreamFactory\Tools\Freezer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defrosts a path
 */
class DefrostCommand extends Command
{

    protected function configure()
    {
        $_path = null;

        $_db = array(
            'host'     => 'localhost',
            'port'     => 3306,
            'db_name'  => 'dreamfactory',
            'user'     => 'dsp_user',
            'password' => 'dsp_user',
        );

        $this->setName( 'defrost' )
            ->setDescription( 'Defrosts a frozen directory back to its original state.' )
            ->setDefinition(
                array(
                    new InputOption( 'path', 'a', InputOption::VALUE_REQUIRED, 'The path to freeze' ),
                    new InputOption( 'host', 's', InputOption::VALUE_OPTIONAL, 'The database host name', $_db['host'] ),
                    new InputOption( 'port', null, InputOption::VALUE_OPTIONAL, 'The database port', $_db['port'] ),
                    new InputOption( 'db_name', 'n', InputOption::VALUE_OPTIONAL, 'The database name', $_db['name'] ),
                    new InputOption( 'user', 'u', InputOption::VALUE_OPTIONAL, 'The database user name', $_db['user'] ),
                    new InputOption( 'password', 'p', InputOption::VALUE_OPTIONAL, 'The database password', $_db['password'] ),
                )
            )
            ->setHelp(
                <<<EOT
                Defrosts a frozen directory back to its original state.

Usage:

<info>freezer defrost -a /path/to/freeze <env></info>
EOT
            );
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $header_style = new OutputFormatterStyle( 'white', 'green', array('bold') );
        $output->getFormatter()->setStyle( 'header', $header_style );

        $_path = $input->getOption( 'path' );

        $output->writeln( '<header>Defrosting...</header>' );
    }
}