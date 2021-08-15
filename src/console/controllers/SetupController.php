<?php
namespace sgroup\sitescripts\console\controllers;

use Craft;
use craft\helpers\Console;
use craft\helpers\StringHelper;

use nystudio107\seomatic\Seomatic;
use yii\console\Controller;
use yii\console\ExitCode;

class SetupController extends Controller
{
    // Properties
    // =========================================================================

    public $defaultAction = 'index';


    // Public Methods
    // =========================================================================

    public function actionIndex(): int
    {
        $dbOptions = [
            'driver' => 'mysql',
            'user' => 'root',
            'server' => 'localhost',
            'port' => '3306',
            'tablePrefix' => 'none',
        ];

        $installOptions = [
            'email' => 'web@sgroup.com.au',
            'username' => 'web@sgroup.com.au',
            'password' => StringHelper::randomString(18, true),
            'siteUrl' => '$BASE_URL',
            'language' => 'en-AU',
        ];

        Craft::$app->runAction('setup/app-id');
        Craft::$app->runAction('setup/security-key');
        Craft::$app->runAction('setup/db-creds', $dbOptions);
        Craft::$app->runAction('install/craft', $installOptions);

        $site = Craft::$app->getSites()->getPrimarySite();
        Craft::$app->getProjectConfig()->set('system.name', $site->name);

        // The site settings for the appropriate meta bundle
        Seomatic::$previewingMetaContainers = true;
        $metaBundle = Seomatic::getInstance()->metaBundles->getGlobalMetaBundle($site->id);
        Seomatic::$previewingMetaContainers = false;

        if ($metaBundle) {
            $metaBundle->metaSiteVars->siteName = $site->name;

            Seomatic::getInstance()->metaBundles->syncBundleWithConfig($metaBundle, true);
            Seomatic::getInstance()->metaBundles->updateMetaBundle($metaBundle, $site->id);

            Seomatic::getInstance()->clearAllCaches();

            $this->stdout(PHP_EOL . 'SEOmatic site settings saved.', Console::FG_YELLOW);
        }

        $this->stdout(PHP_EOL . '---------------------------------' . PHP_EOL . PHP_EOL, Console::FG_YELLOW);
        $this->stdout('The password for ' . $installOptions['username'] . ' is below. Store it somewhere safe, it will never be shown again.' . PHP_EOL, Console::FG_YELLOW);
        $this->stdout($installOptions['password'] . PHP_EOL . PHP_EOL, Console::FG_YELLOW);
        $this->stdout('---------------------------------' . PHP_EOL . PHP_EOL, Console::FG_YELLOW);

        return ExitCode::OK;
    }

}
