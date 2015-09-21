<?php

namespace Drupal\Console\Test\Console;

use Drupal\Console\Console\Application;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Console\Helper\HelperSet
     */
    protected $helperSet;

    /**
     * @var \Drupal\Console\Command\Helper\BootstrapFinderHelper
     */
    protected $bootstrapFinder;

    /**
     * @var \Drupal\Console\Helper\RegisterCommandsHelper
     */
    protected $register_commands;

    /**
     * @var \Drupal\Console\Helper\DrupalHelper
     */
    protected $drupal;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->helperSet = $this
            ->getMockBuilder('Symfony\Component\Console\Helper\HelperSet')
            ->getMock();

        $this->drupal = $this
            ->getMockBuilder('Drupal\Console\Helper\DrupalHelper')
            ->disableOriginalConstructor()
            ->setMethods(['getDrupalRoot'])
            ->getMock();

        $this->register_commands = $this
            ->getMockBuilder('Drupal\Console\Helper\RegisterCommandsHelper')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testCanRunApplication()
    {
        $this->expectsThatAutoloadFinderHelperIsRegistered();

        $config = $this
            ->getMockBuilder('Drupal\Console\Config')
            ->disableOriginalConstructor()
            ->getMock();

        $translatorHelper = $this
            ->getMockBuilder('Drupal\Console\Command\Helper\TranslatorHelper')
            ->disableOriginalConstructor()
            ->setMethods(['loadResource', 'trans'])
            ->getMock();

        $application = new Application($config, $translatorHelper);
        $application->setAutoExit(false);
        $application->setHelperSet($this->helperSet);
        $application->setSearchSettingsFile(false);

        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    protected function expectsThatAutoloadFinderHelperIsRegistered()
    {
        $this->helperSet->expects($this->any(1))
            ->method('get')
            ->with('drupal')
            ->will($this->returnValue($this->drupal));
    }
}
