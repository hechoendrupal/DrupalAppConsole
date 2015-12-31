<?php

/**
 * @file
 * Contains Drupal\Console\Helper\DrupalApiHelper.
 */

namespace Drupal\Console\Helper;

use Symfony\Component\DomCrawler\Crawler;
use Drupal\Console\Helper\Helper;
use Drupal\Console\Utils\Create\Nodes;
use Drupal\Console\Utils\Create\Terms;
use Drupal\Console\Utils\Create\Vocabularies;
use Drupal\Console\Utils\Create\Users;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Class DrupalApiHelper
 * @package Drupal\Console\Helper
 */
class DrupalApiHelper extends Helper
{
    /* @var array */
    protected $bundles = [];
    protected $roles = [];
    protected $vocabularies = [];

    /**
     * @return \Drupal\Console\Utils\Create\Nodes
     */
    public function getCreateNodes()
    {
        $createNodes = new Nodes(
            $this->hasGetService('entity.manager'),
            $this->hasGetService('date.formatter'),
            $this->getBundles()
        );

        return $createNodes;
    }

    /**
     * @return \Drupal\Console\Utils\Create\Terms
     */
    public function getCreateTerms()
    {
        $createTerms = new Terms(
            $this->hasGetService('entity.manager'),
            $this->hasGetService('date.formatter'),
            $this->getVocabularies()
        );

        return $createTerms;
    }

    /**
     * @return \Drupal\Console\Utils\Create\Vocabularies
     */
    public function getCreateVocabularies()
    {
        $createVocabularies = new Vocabularies(
            $this->hasGetService('entity.manager'),
            $this->hasGetService('date.formatter')
        );

        return $createVocabularies;
    }

    /**
     * @return \Drupal\Console\Utils\Create\Nodes
     */
    public function getCreateUsers()
    {
        $createUsers = new Users(
            $this->hasGetService('entity.manager'),
            $this->hasGetService('date.formatter'),
            $this->getRoles()
        );

        return $createUsers;
    }

    /**
     * @return array
     */
    public function getBundles()
    {
        if (!$this->bundles) {
            $entityManager = $this->hasGetService('entity.manager');
            $nodeTypes = $entityManager->getStorage('node_type')->loadMultiple();

            foreach ($nodeTypes as $nodeType) {
                $this->bundles[$nodeType->id()] = $nodeType->label();
            }
        }

        return $this->bundles;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        if (!$this->roles) {
            $roles = Role::loadMultiple();

            unset($roles[RoleInterface::ANONYMOUS_ID]);

            foreach ($roles as $role) {
                $this->roles[$role->id()] = $role->label();
            }
        }

        return $this->roles;
    }

    /**
     * @return array
     */
    public function getVocabularies()
    {
        if (!$this->vocabularies) {
            $entityManager = $this->hasGetService('entity.manager');
            $vocabularies = $entityManager->getStorage('taxonomy_vocabulary')->loadMultiple();

            foreach ($vocabularies as $vocabulary) {
                $this->vocabularies[$vocabulary->id()] = $vocabulary->label();
            }
        }

        return $this->vocabularies;
    }

    /**
     * @param $serviceId
     * @return mixed
     */
    public function hasGetService($serviceId)
    {
        if (!$this->getContainer()) {
            return null;
        }

        if ($this->getContainer()->has($serviceId)) {
            return $this->getContainer()->get($serviceId);
        }

        return null;
    }

    /**
     * Gets the current container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     *   A ContainerInterface instance.
     */
    protected function getContainer()
    {
        if (!$this->getKernelHelper()) {
            return null;
        }

        if (!$this->getKernelHelper()->getKernel()) {
            return null;
        }

        return $this->getKernelHelper()->getKernel()->getContainer();
    }

    /**
     * @param $module
     * @return array
     * @throws \Exception
     */
    public function getProjectReleases($module)
    {
        if (!$module) {
            return [];
        }

        $projectPageContent = $this->getHttpClientHelper()->getUrlAsString(
            sprintf(
                'https://updates.drupal.org/release-history/%s/8.x',
                $module
            )
        );

        if (!$projectPageContent) {
            throw new \Exception('Invalid path.');
        }

        $releases = [];
        $crawler = new Crawler($projectPageContent);
        foreach ($crawler->filterXPath('./project/releases/release/version') as $element) {
            $releases[] = $element->nodeValue;
        }

        return $releases;
    }

    /**
     * @param $project
     * @param $release
     * @param null    $destination
     * @return null|string
     */
    public function downloadProjectRelease($project, $release, $destination = null)
    {
        if (!$destination) {
            $destination = sprintf(
                '%s/%s.tar.gz',
                sys_get_temp_dir(),
                $project
            );
        }

        $releaseFilePath = sprintf(
            'http://ftp.drupal.org/files/projects/%s-%s.tar.gz',
            $project,
            $release
        );

        if ($this->getHttpClientHelper()->downloadFile($releaseFilePath, $destination)) {
            return $destination;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'api';
    }
}
