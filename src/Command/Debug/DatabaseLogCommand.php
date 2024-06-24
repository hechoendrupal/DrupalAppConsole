<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Debug\DatabaseLogCommand.
 */

namespace Drupal\Console\Command\Debug;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Console\Command\Database\DatabaseLogBase;

/**
 * Class DatabaseLogCommand
 *
 * @package Drupal\Console\Command\Debug
 */
class DatabaseLogCommand extends DatabaseLogBase
{
    /**
   * @var
   */
    protected $eventId;

    /**
   * @var
   */
    protected $asc;
    /**
   * @var
   */
    protected $limit;
    /**
   * @var
   */
    protected $offset;

    /**
   * Print in yml style if true
   *
   * @var bool
   */
    protected $ymlStyle;

    /**
   * {@inheritdoc}
   */
    protected function configure()
    {
        $this
            ->setName('debug:database:log')
            ->setDescription($this->trans('commands.debug.database.log.description'));

        $this->addDefaultLoggingOptions();

        $this
            ->addArgument(
                'event-id',
                InputArgument::OPTIONAL,
                $this->trans('commands.debug.database.log.arguments.event-id')
            )
            ->addOption(
                'asc',
                null,
                InputOption::VALUE_NONE,
                $this->trans('commands.debug.database.log.options.asc')
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.debug.database.log.options.limit')
            )
            ->addOption(
                'offset',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.debug.database.log.options.offset'),
                0
            )
            ->addOption(
                'yml',
                null,
                InputOption::VALUE_NONE,
                $this->trans('commands.debug.database.log.options.yml'),
                null
            )
            ->setAliases(['dbb']);
    }

    /**
   * {@inheritdoc}
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getDefaultOptions($input);
        $this->eventId = $input->getArgument('event-id');
        $this->asc = $input->getOption('asc');
        $this->limit = $input->getOption('limit');
        $this->offset = $input->getOption('offset');
        $this->ymlStyle = $input->getOption('yml');

        if ($this->eventId) {
            return $this->getEventDetails();
        } else {
            return $this->getAllEvents();
        }
    }

    /**
   * @param $eventId
   * @return bool
   */
    private function getEventDetails()
    {
        $dblog = $this->database
            ->query(
                'SELECT w.*, u.uid FROM {watchdog} w LEFT JOIN {users} u ON u.uid = w.uid WHERE w.wid = :id',
                [':id' => $this->eventId]
            )
            ->fetchObject();

        if (!$dblog) {
            $this->getIo()->error(
                sprintf(
                    $this->trans('commands.debug.database.log.messages.not-found'),
                    $this->eventId
                )
            );
            return 1;
        }

        if ($this->ymlStyle) {
            $this->getIo()->writeln(Yaml::encode($this->formatSingle($dblog)));
        } else {
            $this->getIo()->table(
                $this->createTableHeader(),
                [$this->createTableRow($dblog)]
            );
        }
    }

    /**
   * @return bool
   */
    private function getAllEvents()
    {
        if (!$this->offset) {
            $this->offset = 0;
        }

        if (!$this->limit) {
            $this->limit = 1000;
        }

        $query = $this->makeQuery($this->offset, $this->limit);

        $result = $query->execute();

        $tableRows = [];
        foreach ($result as $dblog) {
            $tableRows[] = $this->createTableRow($dblog);
        }

        $this->getIo()->table(
            $this->createTableHeader(),
            $tableRows
        );

        return true;
    }
}
