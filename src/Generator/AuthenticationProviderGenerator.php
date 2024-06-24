<?php

/**
 * @file
 * Contains \Drupal\Console\Generator\AuthenticationProviderGenerator.
 */

namespace Drupal\Console\Generator;

use Drupal\Console\Extension\Manager;
use Drupal\Console\Core\Generator\Generator;

class AuthenticationProviderGenerator extends Generator
{
    /**
     * @var Manager
     */
    protected $extensionManager;

    /**
     * AuthenticationProviderGenerator constructor.
     *
     * @param Manager $extensionManager
     */
    public function __construct(Manager $extensionManager)
    {
        $this->extensionManager = $extensionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $parameters)
    {
        $module = $parameters['module'];
        $class = $parameters['class'];
        $provider_id = $parameters['provider_id'];
        $moduleInstance = $this->extensionManager->getModule($module);
        $modulePath = "{$moduleInstance->getPath()}/{$module}";

        $this->renderFile(
          'module/src/Authentication/Provider/authentication-provider.php.twig',
          "{$moduleInstance->getAuthenticationPath('Provider')}/{$class}.php",
          $parameters
        );

        $moduleServicePath =  "$modulePath.services.yml";

        $parameters = array_merge($parameters, [
          'module' => $module,
          'class' => $class,
          'class_path' => sprintf('Drupal\%s\Authentication\Provider\%s', $module, $class),
          'name' => "authentication.$module",
          'services' => [
            ['name' => 'config.factory'],
            ['name' => 'entity_type.manager'],
          ],
          'file_exists' => file_exists($moduleServicePath),
          'tags' => [
            'name' => 'authentication_provider',
            'provider_id' => $provider_id,
            'priority' => '100',
          ],
        ]);

        $this->renderFile(
            'module/services.yml.twig',
             $moduleServicePath,
             $parameters,
            FILE_APPEND
        );
    }
}
