<?php

namespace Drupal\Console\Bootstrap;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Console\Core\Utils\DrupalFinder;
use Drupal\Core\Extension\ExtensionDiscovery;

/**
 * Trait DrupalKernelTrait
 *
 * @package Drupal\Console\Bootstrap
 */
trait DrupalKernelTrait
{
    /**
     * @var ServiceModifierInterface[]
     */
    protected $serviceModifiers = [];

    /**
     * @inheritdoc
     */
    public static function createFromRequest(Request $request, $class_loader, $environment, $allow_dumping = true, $app_root = null)
    {
        $kernel = new static($environment, $class_loader, $allow_dumping, $app_root);
        static::bootEnvironment($app_root);
        $kernel->initializeSettings($request);

        return $kernel;
    }

    /**
     * @param \Drupal\Core\DependencyInjection\ServiceModifierInterface $serviceModifier
     */
    public function addServiceModifier(ServiceModifierInterface $serviceModifier)
    {
        $this->serviceModifiers[] = $serviceModifier;
    }

    /**
     * @inheritdoc
     */
    protected function getContainerBuilder()
    {
        $container = parent::getContainerBuilder();
        foreach ($this->serviceModifiers as $serviceModifier) {
            $serviceModifier->alter($container);
        }

        return $container;
    }

    /**
     * {@inheritdoc}
     */
    public function discoverServiceProviders()
    {
        // Discover Drupal service providers
        parent::discoverServiceProviders();

        // Discover Drupal Console service providers
        $this->discoverDrupalConsoleServiceProviders();
    }

    public function getContainerKey()
    {
        return hash("sha256", $this->getContainerCacheKey());
    }

    public function discoverDrupalConsoleServiceProviders()
    {
        $drupalFinder = new DrupalFinder();
        $drupalFinder->locateRoot(getcwd());

        // Load DrupalConsole services
        $this->addDrupalConsoleServices($drupalFinder->getComposerRoot());

        // Load DrupalConsole services
        $this->addDrupalConsoleConfigServices($drupalFinder->getComposerRoot());

        // Load DrupalConsole extended services
        $this->addDrupalConsoleExtendedServices($drupalFinder->getComposerRoot());

        // Add DrupalConsole module(s) services
        $this->addDrupalConsoleModuleServices($drupalFinder->getDrupalRoot());

        // Add DrupalConsole theme(s) services
        $this->addDrupalConsoleThemeServices($drupalFinder->getDrupalRoot());
    }

    protected function addDrupalConsoleServices($root)
    {
        $servicesFiles = array_filter(
            [
                $root. DRUPAL_CONSOLE_CORE . 'services.yml',
                $root. DRUPAL_CONSOLE . 'uninstall.services.yml',
                $root. DRUPAL_CONSOLE . 'services.yml'
            ],
            function ($file) {
                return file_exists($file);
            }
        );

        $this->addDrupalServiceFiles($servicesFiles);
    }

    protected function addDrupalConsoleConfigServices($root)
    {
        $finder = new Finder();
        $finder->files()
            ->name('*.yml')
            ->in(
                sprintf(
                    '%s/config/services',
                    $root.DRUPAL_CONSOLE
                )
            );

        $servicesFiles  = [];
        foreach ($finder as $file) {
            $servicesFiles[] = $file->getPathname();
        }

        $this->addDrupalServiceFiles($servicesFiles);
    }

    protected function addDrupalConsoleExtendedServices($root)
    {
        $servicesFiles = array_filter(
            [
                $root . DRUPAL_CONSOLE . 'extend.console.services.yml',
                $root . DRUPAL_CONSOLE . 'extend.console.uninstall.services.yml',
            ],
            function ($file) {
                return file_exists($file);
            }
        );

        $this->addDrupalServiceFiles($servicesFiles);
    }

    protected function addDrupalConsoleModuleServices($root)
    {
        $servicesFiles  = [];
        $moduleFileNames = $this->getModuleFileNames();
        foreach ($moduleFileNames as $module => $filename) {
            $servicesFile = $root . '/' .
                dirname($filename) .
                "/console.services.yml";
            if (file_exists($servicesFile)) {
                $servicesFiles[] = $servicesFile;
            }
        }

        $this->addDrupalServiceFiles($servicesFiles);
    }

    public function addDrupalServiceFiles($servicesFiles)
    {
        $this->serviceYamls['site'] = array_merge(
            $this->serviceYamls['site'],
            $servicesFiles
        );
    }

    protected function addDrupalConsoleThemeServices($root)
    {
        $themes = $this->getThemeFileNames($root);
        $servicesFiles  = [];
        foreach ($themes as $theme => $filename ) {
            $servicesFile = $root . '/' .
                dirname($filename) .
                "/console.services.yml";
            if (file_exists($servicesFile)) {
                $servicesFiles[] = $servicesFile;
            }
        }

        $this->addDrupalServiceFiles($servicesFiles);
    }

    private function getThemeFileNames($root)
    {
        $listing = new ExtensionDiscovery($root);
        $listing->setProfileDirectories(array());

        $themes = [];
        foreach ($listing->scan('theme') as $theme => $value ) {
            $themes[$theme] = $value->getPathname();
        }

        return $themes;
    }

}
