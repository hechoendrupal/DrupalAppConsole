<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Database\DatabaseLogBase.
 */

namespace Drupal\Console\Command\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\UserStorageInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\user\Entity\User;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class DatabaseLogBase
 *
 * @package Drupal\Console\Command\Database
 */
abstract class DatabaseLogBase extends Command
{
    /**
     * @var Connection
     */
    protected $database;

    /**
     * @var DateFormatterInterface
     */
    protected $dateFormatter;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var TranslationInterface
     */
    protected $stringTranslation;

    /**
     * @var UserStorageInterface
     */
    protected $userStorage;

    /**
     * @var TranslatableMarkup[]
     */
    protected $severityList;

    /**
     * @var null|string
     */
    protected $eventType;

    /**
     * @var null|string
     */
    protected $eventSeverity;

    /**
     * @var null|string
     */
    protected $userId;

    /**
     * LogDebugCommand constructor.
     *
     * @param Connection                 $database
     * @param DateFormatterInterface     $dateFormatter
     * @param EntityTypeManagerInterface $entityTypeManager
     * @param TranslationInterface       $stringTranslation
     */
    public function __construct(
        Connection $database,
        DateFormatterInterface $dateFormatter,
        EntityTypeManagerInterface $entityTypeManager,
        TranslationInterface $stringTranslation
    ) {
        $this->database = $database;
        $this->dateFormatter = $dateFormatter;
        $this->entityTypeManager = $entityTypeManager;
        $this->stringTranslation = $stringTranslation;
        $this->userStorage = $this->entityTypeManager->getStorage('user');
        $this->severityList = RfcLogLevel::getLevels();
        parent::__construct();
    }

    /**
     * addDefaultLoggingOptions.
     */
    protected function addDefaultLoggingOptions()
    {
        $this
            ->addOption(
                'type',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.database.log.common.options.type')
            )
            ->addOption(
                'severity',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.database.log.common.options.severity')
            )
            ->addOption(
                'user-id',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.database.log.common.options.user-id')
            );
    }

    /**
     * @param InputInterface $input
     */
    protected function getDefaultOptions(InputInterface $input)
    {
        $this->eventType = $input->getOption('type');
        $this->eventSeverity = $input->getOption('severity');
        $this->userId = $input->getOption('user-id');
    }

    /**
     * @param null        $offset
     * @param int         $range
     * @return bool|\Drupal\Core\Database\Query\SelectInterface
     */
    protected function makeQuery($offset = null, $range = 1000)
    {
        $query = $this->database->select('watchdog', 'w');
        $query->fields(
            'w',
            [
                'wid',
                'uid',
                'severity',
                'type',
                'timestamp',
                'message',
                'variables',
            ]
        );

        if ($this->eventType) {
            $query->condition('type', $this->eventType);
        }

        if ($this->eventSeverity) {
            if (!in_array($this->eventSeverity, $this->severityList)) {
                $this->getIo()->error(
                    sprintf(
                        $this->trans('database.log.common.messages.invalid-severity'),
                        $this->eventSeverity
                    )
                );
                return false;
            }
            $query->condition(
                'severity',
                array_search(
                    $this->eventSeverity,
                    $this->severityList
                )
            );
        }

        if ($this->userId) {
            $query->condition('uid', $this->userId);
        }

        $query->orderBy('wid', 'ASC');

        if ($offset !== null) {
            $query->range($offset, $range);
        }

        return $query;
    }

    /**
     * Generic logging table header
     *
     * @return array
     */
    protected function createTableHeader()
    {
        return [
            $this->trans('commands.database.log.common.messages.event-id'),
            $this->trans('commands.database.log.common.messages.type'),
            $this->trans('commands.database.log.common.messages.date'),
            $this->trans('commands.database.log.common.messages.message'),
            $this->trans('commands.database.log.common.messages.user'),
            $this->trans('commands.database.log.common.messages.severity'),
        ];
    }

    /**
     * @param \stdClass $dblog
     * @return array
     */
    protected function createTableRow(\stdClass $dblog)
    {

        /**
         * @var User $user
         */
        if ($user = $this->userStorage->load($dblog->uid)) {
            $user_id = $user->id();
            $user_name = $user->getAccountName();
        } else {
            $user_id = $dblog->uid;
            $user_name = $this->trans('commands.database.log.common.messages.user-deleted');
        }

        return [
            $dblog->wid,
            $dblog->type,
            $this->dateFormatter->format($dblog->timestamp, 'short'),
            Unicode::truncate(
                Html::decodeEntities(strip_tags($this->formatMessage($dblog))),
                500,
                true,
                true
            ),
            $user_name . ' (' . $user_id . ')',
            $this->severityList[$dblog->severity]->render(),
        ];
    }

    /**
     * Formats a database log message.
     *
     * @param $event
     *   The record from the watchdog table. The object properties are: wid, uid,
     *   severity, type, timestamp, message, variables, link, name.
     *
     * @return string|false
     *   The formatted log message or FALSE if the message or variables properties
     *   are not set.
     */
    protected function formatMessage(\stdClass $event)
    {
        $message = false;

        // Check for required properties.
        if (isset($event->message, $event->variables)) {
            // Messages without variables or user specified text.
            if ($event->variables === 'N;') {
                return $event->message;
            }

            return $this->stringTranslation->translate(
                $event->message,
                unserialize($event->variables)
            );
        }

        return $message;
    }

    /**
     * @param $dblog
     * @return array
     */
    protected function formatSingle($dblog)
    {
        return array_combine(
            $this->createTableHeader(),
            $this->createTableRow($dblog)
        );
    }
}
