<?php

/**
 * @file
 * Contains \Drupal\Console\Command\ModuleAwareCommand.
 */

namespace Drupal\Console\Command;

use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Core\Generator\GeneratorInterface;
use Drupal\Console\Command\Shared\ConfirmationTrait;
use Drupal\Console\Extension\Manager;
use Drupal\Console\Utils\Validator;

/**
 * Class ModuleAwareCommand
 *
 * @package Drupal\Console\Command
 */
abstract class ModuleAwareCommand extends ContainerAwareCommand
{
    use ConfirmationTrait;

    /**
     * @var Manager
     */
    protected $extensionManager;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @var GeneratorInterface
     */
    protected $generator;

    /**
     * @var StringConverter
     */
    protected $stringConverter;

    /**
     * ModuleAwareCommand constructor.
     *
     * @param GeneratorInterface $generator
     */
    public function __construct(GeneratorInterface $generator) {
        $this->generator = $generator;
        parent::__construct();
    }

    /**
     * Retrieves string converter.
     *
     * @return Manager
     *   String converter.
     */
    protected function stringConverter() {
      if (!isset($this->stringConverter)) {
        $this->stringConverter = $this->container->get('console.string_converter');
      }
      return $this->stringConverter;
    }

    /**
     * Retrieves the extension manager.
     *
     * @return Manager
     *   The extension manager.
     */
    protected function extensionManager() {
        if (!isset($this->extensionManager)) {
            $this->extensionManager = $this->container->get('console.extension_manager');
        }
        return $this->extensionManager;
    }

    /**
     * Retrieves the console validator.
     *
     * @return Validator
     *   The console validator.
     */
    protected function validator() {
        if (!isset($this->validator)) {
            $this->validator = $this->container->get('console.validator');
        }
        return $this->validator;
    }

    /**
     * Ask the user to choose a module or profile.
     *
     * @param bool        $showProfile
     *   If profiles should be discovered.
     *
     * @throws \Exception
     *   When no modules are found.
     *
     * @return string
     */
    public function moduleQuestion($showProfile = true)
    {
        $modules = $this->extensionManager()->discoverModules()
            ->showInstalled()
            ->showUninstalled()
            ->showNoCore()
            ->getList(true);

        if ($showProfile) {
            $profiles = $this->extensionManager()->discoverProfiles()
                ->showInstalled()
                ->showUninstalled()
                ->showNoCore()
                ->showCore()
                ->getList(true);

            $modules = array_merge($modules, $profiles);
        }

        if (empty($modules)) {
            throw new \Exception('No extension available, execute the proper generator command to generate one.');
        }

        $module = $this->getIo()->choiceNoList(
            $this->trans('commands.common.questions.module'),
            $modules
        );

        return $module;
    }

    /**
     * Get module name from user.
     *
     * @return mixed|string
     *   Module name.
     */
    public function getModuleOption()
    {
        $input = $this->getIo()->getInput();
        $module = $input->getOption('module');
        if (!$module) {
            // @see Drupal\Console\Command\Shared\ModuleTrait::moduleQuestion
            $module = $this->moduleQuestion();
            $input->setOption('module', $module);
        } else {
            $this->validateModule($module);
        }

        return $module;
    }

    /**
     * Validate module.
     *
     * @param string $module
     *   Module name.
     * @return string
     *   Module name.
     *
     * @throws \Exception
     *   When module is not found.
     */
    public function validateModule($module) {
        $missing_modules = $this->validator()->getMissingModules([$module]);
        if ($missing_modules) {
            throw new \Exception(
                sprintf(
                    $this->trans(
                        'commands.module.download.messages.no-releases'
                    ),
                    $module
                )
            );
        }
        return $module;
    }
}
