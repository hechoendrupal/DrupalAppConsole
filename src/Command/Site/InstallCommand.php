<?php

/**
 * @file
 * Contains \Drupal\AppConsole\Command\Site\InstallCommand.
 */

namespace Drupal\Console\Command\Site;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Installer\Exception\AlreadyInstalledException;
use Drupal\Console\Command\Shared\DatabaseTrait;
use Drupal\Console\Core\Utils\ConfigurationManager;
use Drupal\Console\Extension\Manager;
use Drupal\Console\Bootstrap\Drupal;
use Drupal\Console\Utils\Site;
use Drupal\Console\Core\Utils\DrupalFinder;

class InstallCommand extends ContainerAwareCommand
{
    use DatabaseTrait;

    /**
     * @var Manager
     */
    protected $extensionManager;

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var  ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var string
     */
    protected $appRoot;

    /**
     * InstallCommand constructor.
     *
     * @param Manager              $extensionManager
     * @param Site                 $site
     * @param ConfigurationManager $configurationManager
     * @param string               $appRoot
     */
    public function __construct(
        Manager $extensionManager,
        Site $site,
        ConfigurationManager $configurationManager,
        $appRoot
    ) {
        $this->extensionManager = $extensionManager;
        $this->site = $site;
        $this->configurationManager = $configurationManager;
        $this->appRoot = $appRoot;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('site:install')
            ->setDescription($this->trans('commands.site.install.description'))
            ->addArgument(
                'profile',
                InputArgument::OPTIONAL,
                $this->trans('commands.site.install.arguments.profile')
            )
            ->addArgument(
                'db-url',
                InputArgument::OPTIONAL,
                $this->trans('commands.site.install.arguments.db-url')
            )
            ->addOption(
                'langcode',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.langcode')
            )
            ->addOption(
                'db-type',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.db-type')
            )
            ->addOption(
                'db-file',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.db-file')
            )
            ->addOption(
                'db-host',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.migrate.execute.options.db-host')
            )
            ->addOption(
                'db-name',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.migrate.execute.options.db-name')
            )
            ->addOption(
                'db-user',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.migrate.execute.options.db-user')
            )
            ->addOption(
                'db-pass',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.migrate.execute.options.db-pass')
            )
            ->addOption(
                'db-prefix',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.migrate.execute.options.db-prefix')
            )
            ->addOption(
                'db-port',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.migrate.execute.options.db-port')
            )
            ->addOption(
                'site-name',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.site-name')
            )
            ->addOption(
                'site-mail',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.site-mail')
            )
            ->addOption(
                'account-name',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.account-name')
            )
            ->addOption(
                'account-mail',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.account-mail')
            )
            ->addOption(
                'account-pass',
                null,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.site.install.arguments.account-pass')
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                $this->trans('commands.site.install.arguments.force')
            )
            ->setAliases(['si']);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        // --profile option
        $profile = $input->getArgument('profile');
        if (!$profile) {
            $profiles = $this->extensionManager
                ->discoverProfiles()
                ->showCore()
                ->showNoCore()
                ->showInstalled()
                ->showUninstalled()
                ->getList(true);

            $profiles = array_filter(
                $profiles,
                function ($profile) {
                    return strpos($profile, 'testing') !== 0;
                }
            );

            $profile = $this->getIo()->choice(
                $this->trans('commands.site.install.questions.profile'),
                array_values($profiles)
            );

            $input->setArgument('profile', $profile);
        }

        // --langcode option
        $langcode = $input->getOption('langcode');
        if (!$langcode) {
            $languages = $this->site->getStandardLanguages();
            $defaultLanguage = $this->configurationManager
                ->getConfiguration()
                ->get('application.language');

            $langcode = $this->getIo()->choiceNoList(
                $this->trans('commands.site.install.questions.langcode'),
                $languages,
                $languages[$defaultLanguage]
            );

            $input->setOption('langcode', $langcode);
        }

        // Use default database setting if is available
        $database = Database::getConnectionInfo();
        if (empty($database['default'])) {

            // --db-url argument
            $db_url = $input->getArgument('db-url');
            $valuesFromUrl = parse_url($db_url);

            $input->setOption('db-prefix', '');
            // --db-type option
            $dbType = $db_url?$valuesFromUrl['scheme']:$input->getOption('db-type');
            if (!$dbType) {
                $databases = $this->site->getDatabaseTypes();
                $dbType = $this->getIo()->choice(
                    $this->trans('commands.migrate.setup.questions.db-type'),
                    array_column($databases, 'name')
                );

                foreach ($databases as $dbIndex => $database) {
                    if ($database['name'] == $dbType) {
                        $dbType = $dbIndex;
                    }
                }
                $input->setOption('db-type', $dbType);
            }

            if ($dbType === 'sqlite') {
                // --db-file option
                $dbFile = $input->getOption('db-file');
                if (!$dbFile) {
                    $dbFile = $this->getIo()->ask(
                        $this->trans('commands.migrate.execute.questions.db-file'),
                        'sites/default/files/.ht.sqlite'
                    );
                }
                $input->setOption('db-file', $dbFile);
            } else {
                // --db-host option
                $dbHost = $db_url?$valuesFromUrl['host']:$input->getOption('db-host');
                if (!$dbHost) {
                    $dbHost = $this->dbHostQuestion();
                    $input->setOption('db-host', $dbHost);
                }

                // --db-name option
                $dbName = $db_url?ltrim($valuesFromUrl['path'], "/"):$input->getOption('db-name');
                if (!$dbName) {
                    $dbName = $this->dbNameQuestion();
                    $input->setOption('db-name', $dbName);
                }

                // --db-user option
                $dbUser = $db_url?$valuesFromUrl['user']:$input->getOption('db-user');
                if (!$dbUser) {
                    $dbUser = $this->dbUserQuestion();
                    $input->setOption('db-user', $dbUser);
                }

                // --db-pass option
                $dbPass = $db_url?$valuesFromUrl['pass']:$input->getOption('db-pass');
                if (!$dbPass) {
                    $dbPass = $this->dbPassQuestion();
                    $input->setOption('db-pass', $dbPass);
                }

                // --db-port prefix
                $dbPort = $db_url?$valuesFromUrl['port']:$input->getOption('db-port');
                if (!$dbPort) {
                    $dbPort = $this->dbPortQuestion();
                    $input->setOption('db-port', $dbPort);
                }
            }

            // --db-prefix option
            $dbPrefix = $input->getOption('db-prefix');
            if ($dbPrefix === null) {
                $dbPrefix = $this->dbPrefixQuestion();
            }
            $input->setOption('db-prefix', $dbPrefix);
        }

        // --site-name option
        $siteName = $input->getOption('site-name');
        if (!$siteName) {
            $siteName = $this->getIo()->ask(
                $this->trans('commands.site.install.questions.site-name'),
                $this->trans('commands.site.install.suggestions.site-name')
            );
            $input->setOption('site-name', $siteName);
        }

        // --site-mail option
        $siteMail = $input->getOption('site-mail');
        if (!$siteMail) {
            $siteMail = $this->getIo()->ask(
                $this->trans('commands.site.install.questions.site-mail'),
                'admin@example.com'
            );
            $input->setOption('site-mail', $siteMail);
        }

        // --account-name option
        $accountName = $input->getOption('account-name');
        if (!$accountName) {
            $accountName = $this->getIo()->ask(
                $this->trans('commands.site.install.questions.account-name'),
                'admin'
            );
            $input->setOption('account-name', $accountName);
        }

        // --account-pass option
        $accountPass = $input->getOption('account-pass');
        if (!$accountPass) {
            $accountPass = $this->getIo()->askHidden(
                $this->trans('commands.site.install.questions.account-pass')
            );
            $input->setOption('account-pass', $accountPass);
        }

        // --account-mail option
        $accountMail = $input->getOption('account-mail');
        if (!$accountMail) {
            $accountMail = $this->getIo()->ask(
                $this->trans('commands.site.install.questions.account-mail'),
                $siteMail
            );
            $input->setOption('account-mail', $accountMail);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $uri = parse_url($input->getParameterOption(['--uri', '-l'], 'default'), PHP_URL_HOST);

        if ($this->site->multisiteMode($uri)) {
            if (!$this->site->validMultisite($uri)) {
                $this->getIo()->error(
                    sprintf($this->trans('commands.site.install.messages.invalid-multisite'), $uri, $uri)
                );
                exit(1);
            }

            // Modify $_SERVER environment information to enable
            // the Drupal installer to use the multi-site configuration.
            $_SERVER['HTTP_HOST'] = $uri;
        }

        $database = Database::getConnectionInfo();

        // If a database connection is already defined we must use that.
        if (!empty($database['default'])) {
            $dbUrl =  sprintf(
                '%s://%s:%s@%s:%s/%s',
                $database['default']['driver'],
                $database['default']['username'],
                $database['default']['password'],
                $database['default']['host'],
                $database['default']['port'],
                $database['default']['database']
            );
            $input->setArgument('db-url', $dbUrl);
            $input->setOption('db-prefix', $database['default']['prefix']['default']);

            if ($database['default']['driver'] === 'sqlite' && file_exists($database['default']['database'])) {
                $input->setOption('db-type', $database['default']['driver']);
                $input->setOption('db-file', $database['default']['database']);
                $input->setArgument('db-url', '');
            }
            $this->getIo()->info(
                sprintf(
                    $this->trans('commands.site.install.messages.using-current-database'),
                    $database['default']['driver'],
                    $database['default']['database'],
                    $database['default']['username']
                )
            );
        }

        // Database option defaults.
        $dbType = $input->getOption('db-type')?:'mysql';
        $dbFile = $input->getOption('db-file');
        $dbHost = $input->getOption('db-host')?:'127.0.0.1';
        $dbName = $input->getOption('db-name')?:'drupal_'.time();
        $dbUser = $input->getOption('db-user')?:'root';
        $dbPass = $input->getOption('db-pass');
        $dbPrefix = $input->getOption('db-prefix');
        $dbPort = $input->getOption('db-port')?:'3306';
        $force = $input->getOption('force');

        //Check if there is a url to db connection
        $db_url = $input->getArgument('db-url');
        if ($db_url) {
            $valuesFromUrl = parse_url($db_url);
            $dbType = $valuesFromUrl['scheme'];
            if($this->validateUrlConnection($valuesFromUrl)){
                $this->getIo()->error($this->trans('commands.site.install.messages.invalid-db-url'));
                return 1;
            }

            if($dbType === 'sqlite'){
                $this->getIo()->error($this->trans('commands.site.install.messages.invalid-url-driver'));
                return 1;
            }

            $dbHost = $valuesFromUrl['host'];
            $dbName = ltrim($valuesFromUrl['path'], "/");
            $dbUser = $valuesFromUrl['user'];
            $dbPass = $valuesFromUrl['pass'];
            $dbPort = $valuesFromUrl['port']?:$dbPort;

            // Set null options if a db url is provided
            $input->setOption('db-type', '');
            $input->setOption('db-host', '');
            $input->setOption('db-name', '');
            $input->setOption('db-user', '');
            $input->setOption('db-pass', '');
            $input->setOption('db-port', '');
        }

        $databases = $this->site->getDatabaseTypes();

        if ($dbType === 'sqlite') {
            $database = [
                'database' => $dbFile,
                'prefix' => $dbPrefix,
                'namespace' => $databases[$dbType]['namespace'],
                'driver' => $dbType,
            ];
            if ($force && Database::isActiveConnection()) {
                $tables = Database::getConnection()->query('SELECT name FROM sqlite_master WHERE type = "table" AND name NOT LIKE "sqlite_%";')
                    ->fetchAllAssoc('name');
                foreach (array_keys($tables) as $table) {
                    Database::getConnection()->schema()->dropTable($table);
                }
            }
        } else {
            $database = [
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
                'prefix' => $dbPrefix,
                'port' => $dbPort,
                'host' => $dbHost,
                'namespace' => $databases[$dbType]['namespace'],
                'driver' => $dbType,
            ];

            if ($force && Database::isActiveConnection()) {
                $schema = Database::getConnection()->schema();
                $tables = $schema->findTables('%');
                foreach ($tables as $table) {
                    $schema->dropTable($table);
                }
            }
        }

        try {
            $drupalFinder = new DrupalFinder();
            $drupalFinder->locateRoot(getcwd());
            $this->runInstaller($database, $uri);

            $autoload = $this->container->get('class_loader');
            $drupal = new Drupal(
                $autoload,
                $drupalFinder,
                $this->configurationManager
            );
            $container = $drupal->boot();
            $this->getApplication()->setContainer($container);
            $this->getApplication()->validateCommands();
            $this->getApplication()->loadCommands();
        } catch (Exception $e) {
            $this->getIo()->error($e->getMessage());
            return 1;
        }

        $this->restoreSitesFile();

        return 0;
    }

    /**
     * Backs up sites.php to backup.sites.php (if needed).
     *
     * This is needed because of a bug with install_drupal() that causes the
     * install files to be placed directly under /sites instead of the
     * appropriate subdir when run from a script and a sites.php file exists.
     *
     * @return boolean
     */
    protected function backupSitesFile()
    {
        if (!file_exists($this->appRoot . '/sites/sites.php')) {
            return true;
        }

        $renamed = rename($this->appRoot . '/sites/sites.php', $this->appRoot . '/sites/backup.sites.php');

        $this->getIo()->info($this->trans('commands.site.install.messages.sites-backup'));

        return $renamed;
    }

    /**
     * Restores backup.sites.php to sites.php (if needed).
     *
     * @return boolean
     */
    protected function restoreSitesFile()
    {
        if (!file_exists($this->appRoot . '/sites/backup.sites.php')) {
            return true;
        }

        $renamed = rename($this->appRoot . '/sites/backup.sites.php', $this->appRoot . '/sites/sites.php');

        $this->getIo()->info($this->trans('commands.site.install.messages.sites-restore'));

        return $renamed;
    }

    protected function runInstaller($database, $uri) {

        if(!Database::isActiveConnection() && !is_null(Database::getConnectionInfo())) {
            $this->getIo()->error($this->trans('commands.site.install.messages.connection-failed'));
            return 1;
        }

        $input = $this->getIo()->getInput();
        $this->site->loadLegacyFile('/core/includes/install.core.inc');

        $driver = (string)$database['driver'];

        $settings = [
            'parameters' => [
                'profile' => $input->getArgument('profile') ?: 'standard',
                'langcode' => $input->getOption('langcode') ?: 'en',
            ],
            'forms' => [
                'install_settings_form' => [
                    'driver' => $driver,
                    $driver => $database,
                    'op' => 'Save and continue',
                ],
                'install_configure_form' => [
                    'site_name' => $input->getOption('site-name') ?: 'Drupal 8',
                    'site_mail' => $input->getOption('site-mail') ?: 'admin@example.org',
                    'account' => [
                        'name' => $input->getOption('account-name') ?: 'admin',
                        'mail' => $input->getOption('account-mail') ?: 'admin@example.org',
                        'pass' => [
                            'pass1' => $input->getOption('account-pass') ?: 'admin',
                            'pass2' => $input->getOption('account-pass') ?: 'admin'
                        ],
                    ],
                    'update_status_module' => [
                        1 => true,
                        2 => true,
                    ],
                    'clean_url' => true,
                    'op' => 'Save and continue',
                ],
            ]
        ];

        if (!$this->site->multisiteMode($uri)) {
            $this->backupSitesFile();
        }

        $this->getIo()->newLine();
        $this->getIo()->info($this->trans('commands.site.install.messages.installing'));

        try {
            $autoload = $this->site->getAutoload();
            install_drupal($autoload, $settings);
        } catch (AlreadyInstalledException $e) {
            $this->getIo()->error($this->trans('commands.site.install.messages.already-installed'));
            return 1;
        } catch (\Exception $e) {
            $this->getIo()->error($e->getMessage());
            return 1;
        }

        if (!$this->site->multisiteMode($uri)) {
            $this->restoreSitesFile();
        }

        $this->getIo()->success($this->trans('commands.site.install.messages.installed'));

        return 0;
    }

    private function validateUrlConnection($url) {
        if($url['host'] && $url['path'] && $url['user'] && $url['pass']) {
            return 0;
        }
        return 1;
    }
}