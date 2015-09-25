<?php

namespace Drupal\Console\Test;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Drupal\Console\Helper\DialogHelper;
use Drupal\Console\Helper\TwigRendererHelper;

abstract class BaseTestCase extends \PHPUnit_Framework_TestCase
{
    public $dir;

    /**
     * @var \Symfony\Component\Console\Helper\HelperSet
     */
    protected $helperSet;

    protected function setup()
    {
        $this->setUpTemporalDirectory();

        if (!defined('DRUPAL_ROOT')) {
            define('DRUPAL_ROOT', getcwd());
        }
    }

    public function setUpTemporalDirectory()
    {
        $this->dir = sys_get_temp_dir() . "/modules";
    }

    public function getHelperSet($input = null)
    {
        if (!$this->helperSet) {
            $dialog = new DialogHelper();
            $dialog->setInputStream($this->getInputStream($input));

            $stringUtils = $this->getMockBuilder('Drupal\Console\Utils\StringUtils')
                ->disableOriginalConstructor()
                ->setMethods(['createMachineName'])
                ->getMock();

            $stringUtils->expects($this->any())
                ->method('createMachineName')
                ->will($this->returnArgument(0));

            $validators = $this->getMockBuilder('Drupal\Console\Utils\Validators')
                ->disableOriginalConstructor()
                ->setMethods(['validateModuleName'])
                ->getMock();

            $validators->expects($this->any())
                ->method('validateModuleName')
                ->will($this->returnArgument(0));

            $translator = $this->getTranslatorHelper();

            $message = $this
                ->getMockBuilder('Drupal\Console\Helper\MessageHelper')
                ->disableOriginalConstructor()
                ->setMethods(['showMessages', 'showMessage'])
                ->getMock();

            $chain = $this
                ->getMockBuilder('Drupal\Console\Helper\ChainCommandHelper')
                ->disableOriginalConstructor()
                ->setMethods(['addCommand', 'getCommands'])
                ->getMock();

            $drupal = $this
                ->getMockBuilder('Drupal\Console\Helper\DrupalHelper')
                ->setMethods(['isBootable', 'getDrupalRoot'])
                ->getMock();

            $siteHelper = $this
                ->getMockBuilder('Drupal\Console\Helper\SiteHelper')
                ->disableOriginalConstructor()
                ->setMethods(['setModulePath', 'getModulePath'])
                ->getMock();

            $siteHelper->expects($this->any())
                ->method('getModulePath')
                ->will($this->returnValue($this->dir));

            $this->helperSet = new HelperSet(
                [
                    'formatter' => new FormatterHelper(),
                    'renderer' => new TwigRendererHelper(),
                    'dialog' => $dialog,
                    'stringUtils' => $stringUtils,
                    'validators' => $validators,
                    'translator' => $translator,
                    'site' => $siteHelper,
                    'message' => $message,
                    'chain' => $chain,
                    'drupal' => $drupal,
                ]
            );
        }

        return $this->helperSet;
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input . str_repeat("\n", 10));
        rewind($stream);

        return $stream;
    }

    public function getTranslatorHelper()
    {
        $translatorHelper = $this
            ->getMockBuilder('Drupal\Console\Helper\TranslatorHelper')
            ->disableOriginalConstructor()
            ->setMethods(['loadResource', 'trans', 'getMessagesByModule', 'writeTranslationsByModule'])
            ->getMock();

        $translatorHelper->expects($this->any())
            ->method('getMessagesByModule')
            ->will($this->returnValue([]));

        return $translatorHelper;
    }
}
