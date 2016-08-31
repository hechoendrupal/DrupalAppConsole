<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Locale\LanguageAddCommand.
 */

namespace Drupal\Console\Command\Locale;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Style\DrupalStyle;
use Drupal\Console\Command\Shared\LocaleTrait;
use Drupal\Console\Command\Shared\CommandTrait;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Console\Utils\DrupalApi;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * @DrupalCommand(
 *     extension = "locale",
 *     extensionType = "module"
 * )
 */
class LanguageAddCommand extends Command
{
    use CommandTrait;
    use LocaleTrait;

    /**
     * @var ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
      * @var DrupalApi
      */
    protected $drupalApi;

    /**
     * LanguageAddCommand constructor.
     * @param ModuleHandlerInterface $moduleHandler
     */
    public function __construct(
      ModuleHandlerInterface $moduleHandler,
      DrupalApi $drupalApi
    ) {
        $this->moduleHandler = $moduleHandler;
        $this->drupalApi = $drupalApi;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('locale:language:add')
            ->setDescription($this->trans('commands.locale.language.add.description'))
            ->addArgument(
                'language',
                InputArgument::REQUIRED,
                $this->trans('commands.locale.translation.status.arguments.language')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);
        $moduleHandler = $this->moduleHandler;
        $moduleHandler->loadInclude('locale', 'inc', 'locale.translation');
        $moduleHandler->loadInclude('locale', 'module');

        $language = $input->getArgument('language');

        $languages = $this->getLanguages();


        if (isset($languages[$language])) {
            $langcode = $language;
        } elseif (array_search($language, $languages)) {
            $langcode = array_search($language, $languages);
        } else {
            $io->error(sprintf($this->trans('commands.locale.language.add.messages.invalid-language'), $language));
            return;
        }

        try {
            $language = ConfigurableLanguage::createFromLangcode($langcode);
            $language->type = LOCALE_TRANSLATION_REMOTE;
            $language->save();

            $io->info(sprintf($this->trans('commands.locale.language.add.messages.language-add-successfully'), $language->getName()));
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }
    }
}
