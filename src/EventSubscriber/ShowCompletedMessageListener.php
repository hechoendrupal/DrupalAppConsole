<?php

/**
 * @file
 * Contains \Drupal\AppConsole\EventSubscriber\ShowGeneratedFiles.
 */

namespace Drupal\AppConsole\EventSubscriber;

use Drupal\AppConsole\Command\Helper\TranslatorHelper;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\AppConsole\Command\GeneratorCommand;

class ShowCompletedMessageListener implements EventSubscriberInterface
{
    /**
     * @param ConsoleTerminateEvent $event
     */
    public function showCompletedMessage(ConsoleTerminateEvent $event)
    {
        /** @var \Drupal\AppConsole\Command\Command $command */
        $command = $event->getCommand();
        $output = $event->getOutput();

        $application = $command->getApplication();
        $messageHelper = $application->getHelperSet()->get('message');
        /** @var TranslatorHelper */
        $translatorHelper = $application->getHelperSet()->get('translator');

        $messageHelper->showMessages($output);

        if ($event->getExitCode() != 0) {
            return;
        }

        $completedMessageKey = 'application.console.messages.completed';

        if ('self-update' == $command->getName()) {
            return;
        }

        if ($command instanceof GeneratorCommand) {
            $completedMessageKey = 'application.console.messages.generated.completed';
        }

        $completedMessage = $translatorHelper->trans($completedMessageKey);
        if ($completedMessage != $completedMessageKey) {
            $messageHelper->showMessage($output, $completedMessage);
        }
    }

    /**
     * @{@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::TERMINATE => 'showCompletedMessage'];
    }
}
