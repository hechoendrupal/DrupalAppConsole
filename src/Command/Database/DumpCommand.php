<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Database\DumpCommand.
 */

namespace Drupal\Console\Command\Database;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Command\Shared\ConnectTrait;
use Drupal\Console\Core\Utils\ShellProcess;
use Drupal\Core\Database\Connection;
use Symfony\Component\Process\Process;

class DumpCommand extends Command
{
    use ConnectTrait;


    protected $appRoot;
    /**
     * @var ShellProcess
     */
    protected $shellProcess;
    /**
     * @var Connection
     */
    protected $database;

    /**
     * DumpCommand constructor.
     *
     * @param $appRoot
     * @param ShellProcess $shellProcess
     * @param Connection $database
     */
    public function __construct(
        $appRoot,
        ShellProcess $shellProcess,
        Connection $database
    ) {
        $this->appRoot = $appRoot;
        $this->shellProcess = $shellProcess;
        $this->database = $database;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('database:dump')
            ->setDescription($this->trans('commands.database.dump.description'))
            ->addArgument(
                'database',
                InputArgument::OPTIONAL,
                $this->trans('commands.database.dump.arguments.database'),
                'default'
            )
            ->addArgument(
                'target',
                InputArgument::OPTIONAL,
                $this->trans('commands.database.dump.arguments.target'),
                'default'
            )
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.database.dump.options.file')
            )
            ->addOption(
                'gz',
                null,
                InputOption::VALUE_NONE,
                $this->trans('commands.database.dump.options.gz')
            )
            ->addOption(
                'exclude-cache',
                null,
                InputOption::VALUE_NONE,
                $this->trans('commands.database.dump.options.exclude.cache')
            )
            ->setHelp($this->trans('commands.database.dump.help'))
            ->setAliases(['dbdu']);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $database = $input->getArgument('database');
        $target = $input->getArgument('target');
        $file = $input->getOption('file');
        $learning = $input->getOption('learning');
        $gz = $input->getOption('gz');
        $excludeCache = $input->getOption('exclude-cache');

        $databaseConnection = $this->escapeConnection($this->resolveConnection($database, $target));

        if ($excludeCache) {
            $query = '';
            if ($databaseConnection['driver'] == 'mysql') {
                $query = "SHOW TABLES LIKE 'cache_%'";
            } elseif ($databaseConnection['driver'] == 'pgsql') {
                $query = "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename LIKE 'cache_%'";
            }

            $result = $this->database
                ->query($query)
                ->fetchAll();

            $excludeTables = [];
            foreach ($result as $record) {
                $table = array_values(json_decode(json_encode($record), true));
                if ($databaseConnection['driver'] == 'mysql') {
                    $excludeTables[] = $databaseConnection['database'] . '.' . $table[0];
                } elseif ($databaseConnection['driver'] == 'pgsql') {
                    $excludeTables[] = 'public' . '.' . $table[0];
                }
            }
        }

        if (!$file) {
            $date = new \DateTime();
            $file = sprintf(
                '%s/%s-%s.sql',
                $this->appRoot,
                $databaseConnection['database'],
                $date->format('Y-m-d-H-i-s')
            );
        }

        $command = null;

        if ($databaseConnection['driver'] == 'mysql') {
            $command = sprintf(
                "mysqldump --user='%s' --password='%s' --host='%s' --port='%s' '%s' > '%s'",
                $databaseConnection['username'],
                $databaseConnection['password'],
                $databaseConnection['host'],
                $databaseConnection['port'],
                $databaseConnection['database'],
                $file
            );

            if ($excludeCache) {
                $ignoreTable = '';
                foreach ($excludeTables as $table) {
                    $ignoreTable .= "--ignore-table=\"{$table}\" ";
                }

                $command = sprintf(
                    "mysqldump --user='%s' --password='%s' --host='%s' --port='%s' %s '%s'> '%s'",
                    $databaseConnection['username'],
                    $databaseConnection['password'],
                    $databaseConnection['host'],
                    $databaseConnection['port'],
                    $ignoreTable,
                    $databaseConnection['database'],
                    $file
                );

            }
        } elseif ($databaseConnection['driver'] == 'pgsql') {
            $command = sprintf(
                "PGPASSWORD='%s' pg_dumpall -w -U '%s' -h '%s' -p '%s' -l '%s' -f '%s'",
                $databaseConnection['password'],
                $databaseConnection['username'],
                $databaseConnection['host'],
                $databaseConnection['port'],
                $databaseConnection['database'],
                $file
            );

            if ($excludeCache) {
                $ignoreTable = '';
                foreach ($excludeTables as $table) {
                    $ignoreTable .= "-T \"{$table}\" ";
                }

                $command = sprintf(
                    "PGPASSWORD='%s' pg_dump -w -U '%s' -h '%s' -p '%s' -f '%s' %s-d '%s'",
                    $databaseConnection['password'],
                    $databaseConnection['username'],
                    $databaseConnection['host'],
                    $databaseConnection['port'],
                    $file,
                    $ignoreTable,
                    $databaseConnection['database']
                );
            }
        }

        if ($learning) {
            $this->getIo()->commentBlock($command);
        }


        $process = new Process($command);
        $process->setTimeout(null);
        $process->setWorkingDirectory($this->appRoot);
        $process->mustRun();

        $resultFile = $file;
        if ($gz) {
            if (substr($file, -3) != '.gz') {
                $resultFile = $file . '.gz';
            }
            file_put_contents(
                $resultFile,
                gzencode(
                    file_get_contents(
                        $file
                    )
                )
            );
            if ($resultFile != $file) {
                unlink($file);
            }
        }

        $this->getIo()->success(
            sprintf(
                '%s %s',
                $this->trans('commands.database.dump.messages.success'),
                $resultFile
            )
        );
    }
}
