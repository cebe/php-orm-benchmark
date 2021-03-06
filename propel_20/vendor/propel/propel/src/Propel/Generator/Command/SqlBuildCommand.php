<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Generator\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Propel\Generator\Manager\SqlManager;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class SqlBuildCommand extends AbstractCommand
{
    const DEFAULT_OUTPUT_DIRECTORY   = 'generated-sql';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->addOption('mysql-engine', null, InputOption::VALUE_REQUIRED,  'MySQL engine (MyISAM, InnoDB, ...)')
            ->addOption('output-dir',   null, InputOption::VALUE_REQUIRED,  'The output directory', self::DEFAULT_OUTPUT_DIRECTORY)
            ->addOption('validate',     null, InputOption::VALUE_NONE,      '')
            ->addOption('connection',   null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use', array())
            ->addOption('schema-name',  null, InputOption::VALUE_REQUIRED,  'The schema name for RDBMS supporting them', '')
            //->addOption('encoding',     null, InputOption::VALUE_REQUIRED,  'The encoding to use for the database')
            ->addOption('table-prefix', null, InputOption::VALUE_REQUIRED,  'Add a prefix to all the table names in the database', '')
            ->setName('sql:build')
            ->setAliases(array('sql'))
            ->setDescription('Build SQL files')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configOptions = array();

        foreach ($input->getOptions() as $key => $option) {
            switch ($key) {
                case 'schema-name';
                    $configOptions['propel']['generator']['schema']['basename'] = $option;
                    break;
                case 'table-prefix':
                    $configOptions['propel']['generator']['tablePrefix'] = $option;
                    break;
                case 'mysql-engine';
                    $configOptions['propel']['database']['adapters']['mysql']['tableType'] = $option;
                    break;
            }
        }

        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);

        $this->createDirectory($input->getOption('output-dir'));

        $manager = new SqlManager();

        $connections = array();
        $optionConnections = $input->getOption('connection');
        if (!$optionConnections) {
            $connections = $generatorConfig->getBuildConnections($input->getOption('input-dir'));
        } else {
            foreach ($optionConnections as $connection) {
                list($name, $dsn, $infos) = $this->parseConnection($connection);
                $connections[$name] = array_merge(array('dsn' => $dsn), $infos);
            }
        }
        $manager->setConnections($connections);

        $manager->setValidate($input->getOption('validate'));
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas($input->getOption('input-dir'), $input->getOption('recursive')));
        $manager->setLoggerClosure(function ($message) use ($input, $output) {
            if ($input->getOption('verbose')) {
                $output->writeln($message);
            }
        });
        $manager->setWorkingDirectory($input->getOption('output-dir'));

        if ($manager->existSqlMap()) {
            $output->writeln("<info>sqldb.map won't be saved because it already exists. Remove it to generate a new map.</info>");
        }

        $manager->buildSql();
    }
}
