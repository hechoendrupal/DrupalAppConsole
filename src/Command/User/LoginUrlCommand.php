<?php

/**
 * @file
 * Contains Drupal\Console\Command\User\LoginUrlCommand.
 */

namespace Drupal\Console\Command\User;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;

/**
 * Class UserLoginCommand.
 *
 * @package Drupal\Console
 */
class LoginUrlCommand extends UserBase
{
    /**
     * LoginUrlCommand constructor.
     *
     * @param EntityTypeManagerInterface $entityTypeManager
     */
    public function __construct(EntityTypeManagerInterface $entityTypeManager)
    {
        parent::__construct($entityTypeManager);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('user:login:url')
            ->setDescription($this->trans('commands.user.login.url.description'))
            ->addArgument(
                'user',
                InputArgument::REQUIRED,
                $this->trans('commands.user.login.url.options.user'),
                null
            )
            ->setAliases(['ulu']);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->getUserArgument();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $user = $input->getArgument('user');
        $userEntity = $this->getUserEntity($user);

        if (!$userEntity) {
            $this->getIo()->error(
                sprintf(
                    $this->trans('commands.user.login.url.errors.invalid-user'),
                    $user
                )
            );

            return 1;
        }

        if($input->hasOption('uri')){
          //validate if https is on uri
          $regx = '/^https:.*/s';
          if(preg_match($regx, $input->getOption('uri'))){
              $timestamp = \Drupal::time()->getRequestTime();
              $langcode = $userEntity->getPreferredLangcode();
              $url = Url::fromRoute('user.reset',
                  [
                  'uid' => $userEntity->id(),
                  'timestamp' => $timestamp,
                  'hash' => user_pass_rehash($userEntity, $timestamp),
                  ],
                  [
                  'absolute' => TRUE,
                  'language' => \Drupal::languageManager()->getLanguage($langcode),
                  'https' => TRUE,
                  ]
              )->toString();

            } else{
              $url = user_pass_reset_url($userEntity) . '/login';
            }
        } else{
          $url = user_pass_reset_url($userEntity) . '/login';
        }
        $this->getIo()->success(
            sprintf(
                $this->trans('commands.user.login.url.messages.url'),
                $userEntity->getAccountName(),
                null
            )
        );

        $this->getIo()->simple($url);
        $this->getIo()->newLine();

        return 0;
    }
}
