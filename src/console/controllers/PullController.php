<?php
namespace sgroup\sitescripts\console\controllers;

use Craft;
use craft\helpers\Console;

use yii\console\Controller;
use yii\console\ExitCode;

class PullController extends Controller
{
    // Properties
    // =========================================================================

    public $db;
    public $defaultAction = 'index';


    // Public Methods
    // =========================================================================

    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'db';
        return $options;
    }

    public function actionIndex(): int
    {
        $this->runAction('assets');

        $this->stdout(PHP_EOL);

        $this->runAction('db');

        return ExitCode::OK;
    }

    public function actionAssets(): int
    {
        $localCraftPath = './';
        $remoteCraftPath = getenv('REMOTE_CRAFT_PATH') . '/';

        // Some error-checking
        if (!getenv('REMOTE_LOGIN')) {
            $this->stderr('error: REMOTE_LOGIN is not set in your .env file' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!getenv('REMOTE_CRAFT_PATH')) {
            $this->stderr('error: REMOTE_CRAFT_PATH is not set in your .env file' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $remoteSshPort = getenv('REMOTE_PORT');
        $remoteSshLogin = getenv('REMOTE_LOGIN');

        $folders = [
            'public_html/uploads/',
        ];

        foreach ($folders as $folder) {
            $localPath = $localCraftPath . $folder;
            $remotePath = $remoteCraftPath . $folder;

            // Make sure the local directory exists
            $this->stdout("Ensuring directory exists at `${localPath}` ... ", Console::FG_YELLOW);
            exec("mkdir -p '${localPath}'");
            $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

            // Pull down the dir files via rsync
            $this->stdout("Downloading assets from `${remotePath}` ... ", Console::FG_YELLOW);
            exec("rsync -F -L -a -z -e 'ssh -p ${remoteSshPort}' --delete-after --progress '${remoteSshLogin}:${remotePath}' '${localPath}'");
            $this->stdout('done' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        }

        $this->stdout('Done pulling assets from server.' . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionDb(): int
    {
        // Some error-checking
        if (!$this->db) {
            if (getenv('REMOTE_DB_TABLE')) {
                $this->db = getenv('REMOTE_DB_TABLE');
            } else {
                $this->stderr('error: Provide a database name via --db=production, or via `REMOTE_DB_TABLE` in your .env file' . PHP_EOL, Console::FG_RED);

                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        if (!getenv('REMOTE_DB_USER')) {
            $this->stderr('error: REMOTE_DB_USER is not set in your .env file' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!getenv('REMOTE_DB_PASSWORD')) {
            $this->stderr('error: REMOTE_DB_PASSWORD is not set in your .env file' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!getenv('REMOTE_LOGIN')) {
            $this->stderr('error: REMOTE_LOGIN is not set in your .env file' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $localDsn = getenv('DB_DSN');
        preg_match('/dbname=([A-Za-z0-9_]+)/', $localDsn, $results);

        $localDbName = $results[1] ?? getenv('DB_DATABASE');
        $localDbUser = getenv('DB_USER');
        $localDbPassword = getenv('DB_PASSWORD');
        $localDbServer = getenv('DB_SERVER');
        $localDbPort = getenv('DB_PORT');
        $localDbCreds = "--user='${localDbUser}' --password='${localDbPassword}' ${localDbName} --port=${$localDbPort} --host=${$localDbServer}";
        $localBackupDbPath = './storage/local-backups/';

        $remoteDbName = $this->db;
        $remoteDbUser = getenv('REMOTE_DB_USER');
        $remoteDbPassword = getenv('REMOTE_DB_PASSWORD');
        $remoteDbCreds = "--user='${remoteDbUser}' --password='${remoteDbPassword}' ${remoteDbName}";

        $remoteSshPort = getenv('REMOTE_PORT');
        $remoteSshLogin = getenv('REMOTE_LOGIN');

        $mysqlDumpAdditionalArgs = '--add-drop-table --comments --create-options --dump-date --no-autocommit --routines --set-charset --triggers ';
        $mysqlDumpSchemaArgs = '--single-transaction --no-data ' . $mysqlDumpAdditionalArgs;
        $mysqlDumpDataArgs = '--no-create-info ' . $mysqlDumpAdditionalArgs;

        $date = date('Y-m-d-H-i-s');
        $tmpDbPath = "/tmp/${remoteDbName}-db-dump-${date}.sql";

        // Prepare the remote DB
        $this->stdout("Generating backup of `${remoteDbName}` on remote server ... ", Console::FG_YELLOW);
        exec("ssh ${remoteSshLogin} -p ${remoteSshPort} \"mysqldump ${remoteDbCreds} ${mysqlDumpSchemaArgs} > ${tmpDbPath}; mysqldump ${remoteDbCreds} ${mysqlDumpDataArgs} >> ${tmpDbPath}; gzip -f ${tmpDbPath}\"");
        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

        // Pull the DB locally
        $this->stdout("Downloading remote backup of `${remoteDbName}` ... ", Console::FG_YELLOW);
        exec("scp -P ${remoteSshPort} -- ${remoteSshLogin}:'${tmpDbPath}.gz' '${tmpDbPath}.gz'");
        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

        // Backup the local DB
        $this->stdout("Creating backup of local database `${localDbName}` ... ", Console::FG_YELLOW);
        exec("mkdir -p '${localBackupDbPath}'");

        // Ignore output - local MySQL will complain about using password on command line. MariaDB on the server doesn't
        $localDbBackupPath = "${localBackupDbPath}${localDbName}-db-dump-${date}.sql";
        exec("mysqldump ${localDbCreds} ${mysqlDumpSchemaArgs} > '${localDbBackupPath}' 2>&1");
        exec("mysqldump ${localDbCreds} ${mysqlDumpDataArgs} >> '${localDbBackupPath}' 2>&1");
        exec("gzip -f '${localDbBackupPath}'");
        $this->stdout('done' . PHP_EOL, Console::FG_GREEN);

        // Add remote DB to local DB
        $this->stdout("Importing remote backup into local database `${localDbName}` ... ", Console::FG_YELLOW);
        exec("gunzip -c '${tmpDbPath}.gz' | mysql ${localDbCreds} 2>&1");
        $this->stdout('done' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $this->stdout('Done pulling db from server.' . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

}
