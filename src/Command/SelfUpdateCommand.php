<?php

/**
 * @file
 * Contains \Drupal\Console\Command\SelfUpdateCommand.
 */

namespace Drupal\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;

class SelfUpdateCommand extends Command
{
    const DRUPAL_CONSOLE_MANIFEST = 'http://drupalconsole.com/manifest.json';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription($this->trans('commands.self-update.description'))
            ->setHelp($this->trans('commands.self-update.help'));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new Manager(
            Manifest::loadFile(
                self::DRUPAL_CONSOLE_MANIFEST
            )
        );

        if ($manager->update($this->getApplication()->getVersion(), true)) {
            $output->writeln($this->trans('commands.self-update.messages.success'));
        } else {
            $output->writeln(
                sprintf(
                    $this->trans('commands.self-update.messages.current-version'),
                    $this->getApplication()->getVersion()
                )
            );
        }
    }
}
