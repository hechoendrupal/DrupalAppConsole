<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Test\DebugCommand.
 */

namespace Drupal\Console\Command\Test;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Component\Utility\Timer;
use Drupal\Console\Command\ContainerAwareCommand;
use Drupal\Console\Style\DrupalStyle;

class RunCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('test:run')
            ->setDescription($this->trans('commands.test.run.description'))
            ->addArgument(
                'test-class',
                InputArgument::REQUIRED,
                $this->trans('commands.test.run.arguments.test-class')
            )
            ->addOption(
                'url',
                '',
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.test.run.arguments.url')
            );


        $this->addDependency('simpletest');
    }

    /*
     * Set Server variable to be used in test cases.
     */
    protected function setEnvironment($url)
    {
        $base_url;
        $host = 'localhost';
        $path = '';
        $port = '80';

        $parsed_url = parse_url($url);
        $host = $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');
        $path = isset($parsed_url['path']) ? rtrim(rtrim($parsed_url['path']), '/') : '';
        $port = (isset($parsed_url['port']) ? $parsed_url['port'] : $port);
        if ($path == '/') {
            $path = '';
        }
        // If the passed URL schema is 'https' then setup the $_SERVER variables
        // properly so that testing will run under HTTPS.
        if ($parsed_url['scheme'] == 'https') {
            $_SERVER['HTTPS'] = 'on';
        }


        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $base_url = 'https://';
        } else {
            $base_url = 'http://';
        }
        $base_url .= $host;
        if ($path !== '') {
            $base_url .= $path;
        }
        putenv('SIMPLETEST_BASE_URL=' . $base_url);
        $_SERVER['HTTP_HOST'] = $host;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_PORT'] = $port;
        $_SERVER['SERVER_SOFTWARE'] = null;
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['REQUEST_URI'] = $path .'/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = $path .'/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $path .'/index.php';
        $_SERVER['PHP_SELF'] = $path .'/index.php';
        $_SERVER['HTTP_USER_AGENT'] = 'Drupal Console';
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);

        //Registers namespaces for disabled modules.
        $this->getTestDiscovery()->registerTestNamespaces();

        $test_class = $input->getArgument('test-class');

        $url = $input->getOption('url');

        if (!$url) {
            $io->error($this->trans('commands.test.run.messages.url-required'));
            return;
        }

        $this->setEnvironment($url);

        // Create simpletest test id
        $test_id = db_insert('simpletest_test_id')
          ->useDefaults(array('test_id'))
          ->execute();

        if (is_subclass_of($test_class, 'PHPUnit_Framework_TestCase')) {
            $io->info($this->trans('commands.test.run.messages.phpunit-pending'));
            return;
        } else {
            $test = new $test_class($test_id);
            $io->info($this->trans('commands.test.run.messages.starting-test'));
            Timer::start('run-tests');

            $test->run();

            $end = Timer::stop('run-tests');

            $io->simple($this->trans('commands.test.run.messages.test-duration') . ': ' .  \Drupal::service('date.formatter')->formatInterval($end['time'] / 1000));
            $io->simple($this->trans('commands.test.run.messages.test-pass') . ': ' . $test->results['#pass']);
            $io->commentBlock($this->trans('commands.test.run.messages.test-fail') . ': ' . $test->results['#fail']);
            $io->commentBlock($this->trans('commands.test.run.messages.test-exception') . ': ' . $test->results['#exception']);
            $io->simple($this->trans('commands.test.run.messages.test-debug') . ': ' . $test->results['#debug']);

            $this->getModuleHandler()->invokeAll('test_finished', array($test->results));

            print "\n";
            $io->info($this->trans('commands.test.run.messages.test-summary'));
            print "\n";

            $current_class = null;
            $current_group = null;
            $current_status = null;

            $messages = $this->simpletestScriptLoadMessagesByTestIds(array($test_id));

            foreach ($messages as $message) {
                if ($current_class === null || $current_class != $message->test_class) {
                    $current_class = $message->test_class;
                    $io->comment($message->test_class);
                }

                if ($current_group === null || $current_group != $message->message_group) {
                    $current_group =  $message->message_group;
                }

                if ($current_status === null || $current_status != $message->status) {
                    $current_status =  $message->status;
                    if ($message->status == 'fail') {
                        $io->error($this->trans('commands.test.run.messages.group') . ':' . $message->message_group . ' ' . $this->trans('commands.test.run.messages.status') . ':' . $message->status);
                        print "\n";
                    } else {
                        $io->info($this->trans('commands.test.run.messages.group') . ':' . $message->message_group . ' ' . $this->trans('commands.test.run.messages.status') . ':' . $message->status);
                        print "\n";
                    }
                }

                $io->simple($this->trans('commands.test.run.messages.file') . ': ' . str_replace($this->getDrupalHelper()->getRoot(), '', $message->file));
                $io->simple($this->trans('commands.test.run.messages.method') . ': ' . $message->function);
                $io->simple($this->trans('commands.test.run.messages.line') . ': ' . $message->line);
                $io->simple($this->trans('commands.test.run.messages.message') . ': ' . $message->message);
                print "\n";
            }
            return;
        }
    }

    /*
     * Get Simletests log after execution
     */
    protected function simpletestScriptLoadMessagesByTestIds($test_ids)
    {
        $results = array();

        foreach ($test_ids as $test_id) {
            $result = \Drupal::database()->query(
                "SELECT * FROM {simpletest} WHERE test_id = :test_id ORDER BY test_class, message_group, status", array(
                ':test_id' => $test_id,
                )
            )->fetchAll();
            if ($result) {
                $results = array_merge($results, $result);
            }
        }

        return $results;
    }
}
