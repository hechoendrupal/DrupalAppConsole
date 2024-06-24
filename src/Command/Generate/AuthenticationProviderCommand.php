<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Generate\AuthenticationProviderCommand.
 */

namespace Drupal\Console\Command\Generate;

use Drupal\Console\Command\ModuleAwareCommand;
use Drupal\Console\Command\Shared\ServicesTrait;
use Drupal\Console\Generator\AuthenticationProviderGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AuthenticationProviderCommand extends ModuleAwareCommand
{
    use ServicesTrait;

    /**
     * AuthenticationProviderCommand constructor.
     *
     * @param AuthenticationProviderGenerator $generator
     */
    public function __construct(AuthenticationProviderGenerator $generator)
    {
        parent::__construct($generator);
    }

    protected function configure()
    {
        $this
            ->setName('generate:authentication:provider')
            ->setDescription($this->trans('commands.generate.authentication.provider.description'))
            ->setHelp($this->trans('commands.generate.authentication.provider.help'))
            ->addOption('module', null, InputOption::VALUE_REQUIRED, $this->trans('commands.common.options.module'))
            ->addOption(
                'class',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.generate.authentication.provider.options.class')
            )
            ->addOption(
                'provider-id',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.generate.authentication.provider.options.provider-id')
            )
            ->setAliases(['gap']);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @see use Drupal\Console\Command\Shared\ConfirmationTrait::confirmOperation
        if (!$this->confirmOperation()) {
            return 1;
        }

        $module = $this->validateModule($input->getOption('module'));
        $class = $this->validator()->validateClassName($input->getOption('class'));
        $provider_id = $input->getOption('provider-id');

        $this->generator->generate([
            'module' => $module,
            'class' => $class,
            'provider_id' => $provider_id,
        ]);

        return 0;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $stringUtils = $this->stringConverter();

        // --module option
        $this->getModuleOption();

        // --class option
        $class = $input->getOption('class');
        if (!$class) {
            $class = $this->getIo()->ask(
                $this->trans(
                    'commands.generate.authentication.provider.questions.class'
                ),
                'DefaultAuthenticationProvider',
                function ($class) {
                    return $this->validator()->validateClassName($class);
                }
            );
            $input->setOption('class', $class);
        }
        // --provider-id option
        $provider_id = $input->getOption('provider-id');
        if (!$provider_id) {
            $provider_id = $this->getIo()->ask(
                $this->trans('commands.generate.authentication.provider.questions.provider-id'),
                $stringUtils->camelCaseToUnderscore($class),
                function ($value) use ($stringUtils) {
                    if (!strlen(trim($value))) {
                        throw new \Exception('The Class name can not be empty');
                    }

                    return $stringUtils->camelCaseToUnderscore($value);
                }
            );
            $input->setOption('provider-id', $provider_id);
        }
    }
}
