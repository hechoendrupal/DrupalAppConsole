<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Config\SetCommand.
 */

namespace Drupal\Console\Command\Config;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigFactory;

class SetCommand extends Command
{
    /**
     * @var CachedStorage
     */
    protected $configStorage;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * SetCommand constructor.
     *
     * @param CachedStorage $configStorage
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        CachedStorage $configStorage,
        ConfigFactory $configFactory
    ) {
        $this->configStorage = $configStorage;
        $this->configFactory = $configFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('config:set')
            ->setDescription($this->trans('commands.config.set.description'))
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                $this->trans('commands.config.set.arguments.name')
            )
            ->addOption(
                'key',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                $this->trans('commands.config.set.options.key')
            )
            ->addOption(
                'value',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                $this->trans('commands.config.set.options.value')
            )
            ->setAliases(['co']);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $names = $this->configFactory->listAll();
        if ($name) {
            if (!in_array($name, $names)) {
                $this->getIo()->warning(
                    sprintf(
                        $this->trans('commands.config.set.messages.invalid-name'),
                        $name
                    )
                );
                $name = null;
            }
        }
        if (!$name) {
            $name = $this->getIo()->choiceNoList(
                $this->trans('commands.config.set.questions.name'),
                $names
            );
            $input->setArgument('name', $name);
        }
        $key = $input->getOption('key');
        if (!$key) {
            if (!$this->configStorage->exists($name)) {
                $this->getIo()->newLine();
                $this->getIo()->errorLite($this->trans('commands.config.set.messages.invalid-config-file'));
                $this->getIo()->newLine();
                return 0;
            }

            $configuration = $this->configStorage->read($name);
            $input->setOption('key', $this->getKeysFromConfig($configuration));
        }
        $value = $input->getOption('value');
        if (!$value) {
            foreach ($input->getOption('key') as $name) {
                $value[] = $this->getIo()->ask(
                    sprintf(
                        $this->trans('commands.config.override.questions.value'),
                        $name
                    )
                );
            }
            $input->setOption('value', $value);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configName = $input->getArgument('name');
        $keys = $input->getOption('key');
        $values = $input->getOption('value');

        if(empty($keys)) {
            return 1;
        }

        $config = $this->configFactory->getEditable($configName);

        $configurationOverrideResult = [];
        foreach ($keys as $index => $key) {
          $result = $this->setConfiguration(
              $config,
              $key,
              $values[$index]
          );
          $configurationOverrideResult = array_merge($configurationOverrideResult, $result);
        }

        $config->save();

        $this->getIo()->info($this->trans('commands.config.set.messages.configuration'), false);
        $this->getIo()->comment($configName);

        $tableHeader = [
            $this->trans('commands.config.set.messages.configuration-key'),
            $this->trans('commands.config.set.messages.original'),
            $this->trans('commands.config.set.messages.updated'),
        ];
        $tableRows = $configurationOverrideResult;
        $this->getIo()->table($tableHeader, $tableRows);
    }


    protected function setConfiguration($config, $key, $value)
    {
        $result[] = [
            'configuration' => $key,
            'original' => $config->get($key),
            'updated' => $value,
        ];
        $config->set($key, $value);

        return $result;
    }

    /**
     * Allow to search a specific key to override.
     *
     * @param $configuration
     * @param null $key
     *
     * @return array
     */
    private function getKeysFromConfig($configuration, $key = null)
    {
        $choiceKey = $this->getIo()->choiceNoList(
            $this->trans('commands.config.set.questions.key'),
            array_keys($configuration)
        );

        $key = is_null($key) ? $choiceKey:$key.'.'.$choiceKey;

        if(is_array($configuration[$choiceKey])){
            return $this->getKeysFromConfig($configuration[$choiceKey], $key);
        }

        return [$key];
    }
}
