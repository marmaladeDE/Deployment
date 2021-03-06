<?php
/**
 * Define basic tasks for deployment
 */

require 'recipe/common.php';
require 'recipe/composer.php';

env('git', []);
env('shared_files', []);
env('shared_dirs', []);
env('app_sources', []);
env('database', []);
env('php_bin', 'php');

$baseDir    = dirname(__DIR__);
$projectDir = dirname($baseDir);
$configDir  = "$projectDir/config/deploy";
$archiveDir = "$projectDir/archives";

serverList("$configDir/servers.yaml");
set('repository', '{{repository}}');

task(
    'deploy:update_code',
    function () {
        upload('ssh-git', env('deploy_path') . '/ssh-git');
        run('chmod +x {{deploy_path}}/ssh-git');
        $git           = env('git');
        $path          = isset($git['path']) ? $git['path'] : '';
        $gitPrivateKey = "";
        if (isset($git['identity_file'])) {
            upload($git['identity_file'], env('deploy_path') . '/id_rsa.deploy');
            run("chmod og-rwx {{deploy_path}}/id_rsa.deploy");
            $gitPrivateKey = 'PKEY="{{deploy_path}}/id_rsa.deploy" GIT_SSH="{{deploy_path}}/ssh-git"';
        }
        $branch = env('git.branch');
        if (input()->hasOption('tag')) {
            $tag = input()->getOption('tag');
        }

        $at = '';
        if (!empty($tag)) {
            $at = "-b $tag";
        } else if (!empty($branch)) {
            $at = "-b $branch";
        }

        run("$gitPrivateKey git clone $at --depth 1 --recursive -q {{git.repository}} {{release_path}}/{$path} 2>&1");
    }
);

task(
    'deploy:prepare:shared',
    function () {
        $sharedFiles = [];
        foreach (env('shared_files') as $sharedFile) {
            $sharedFiles[] = $sharedFile;
        }
        set('shared_files', $sharedFiles);

        $sharedFolders = [];
        foreach (env('shared_dirs') as $sharedFolder) {
            $sharedFolders[] = $sharedFolder;
        }
        set('shared_dirs', $sharedFolders);
    }
);

task(
    'deploy:app_sources',
    function () {
        $appSources   = env('app_sources');
        $cleanupFiles = [];
        foreach ($appSources as $appSource) {
            $packageName    = basename($appSource['url']);
            $packageRoot    = isset($appSource['package_root']) ? $appSource['package_root'] : false;
            $exclude        = isset($appSource['exclude']) ? $appSource['exclude'] : false;
            $excludeCommand = "";
            if ($exclude) {
                $excludeCommand = '--exclude="' . implode('" --exclude="', $exclude) . '"';
            }

            writeln("Downloading package <info>{$appSource['url']}</info>.");

            if ('.tar.gz' == substr($packageName, -7)) {
                $unpackCommand = "tar xf";
            } else if ('.tar.bz2' == substr($packageName, -8)) {
                $unpackCommand = "tar xf";
            } else if ('.tar' == substr($packageName, -4)) {
                $unpackCommand = "tar xf";
            } else if ('.zip' == substr($packageName, -4)) {
                $unpackCommand = "unzip";
                if ($exclude) {
                    $excludeCommand = '-x "' . implode('" "', $exclude) . '"';
                }
            } else {
                throw new \InvalidArgumentException(
                    "Unsupported package-type '{$packageName}'! Supported types are: .zip, .tar.gz, .tar.bz2 and .tar"
                );
            }

            run(
                "if [ -d {{release_path}}/{$appSource['target_dir']} ]; then mv {{release_path}}/{$appSource['target_dir']} {{release_path}}/{$appSource['target_dir']}.git; fi"
            );
            run("wget '{$appSource['url']}' -O {{release_path}}/$packageName");
            $cleanupFiles[] = env('release_path') . "/$packageName";

            writeln("Extracting package <info>{$packageName}</info> to <info>{$appSource['target_dir']}</info>.");

            run("mkdir -p {{release_path}}/{$appSource['target_dir']}");
            run(
                "cd {{release_path}}/{$appSource['target_dir']} && {$unpackCommand} {{release_path}}/$packageName $excludeCommand"
            );
            if ($packageRoot) {
                run(
                    "mv {{release_path}}/{$appSource['target_dir']}/{$packageRoot}/* {{release_path}}/{$appSource['target_dir']}"
                );
                run("rm -rf {{release_path}}/{$appSource['target_dir']}/{$packageRoot}/");
            }
            run(
                "if [ -d {{release_path}}/{$appSource['target_dir']}.git ]; then cp -rf {{release_path}}/{$appSource['target_dir']}.git/* {{release_path}}/{$appSource['target_dir']}; fi"
            );
            run(
                "if [ -d {{release_path}}/{$appSource['target_dir']}.git ]; then cd {{release_path}}/{$appSource['target_dir']}.git && for f in $(find -regex '^.*/\\.[^\\.]*'); do cp -f \$f {{release_path}}/{$appSource['target_dir']}/\$f; done; fi"
            );
            run("rm -rf {{release_path}}/{$appSource['target_dir']}.git");
        }

        env('cleanup_files', $cleanupFiles);
    }
);

task(
    'deploy:write_revision',
    function () {
        $rev = env('git.branch');
        if (input()->hasOption('tag')) {
            $rev = input()->getOption('tag');
        }

        run("echo '{$rev}' > {{release_path}}/.rev");
    }
);

task(
    'deploy:db:create-tag',
    function () {
    }
)->desc('Creating a tag for later rollbacks.');

task(
    'deploy:db:update',
    function () {
        $dbStatusFile = "{{deploy_path}}/shared/db-status.json";
        $filePath = run("readlink -f {$dbStatusFile}")->toString();
        $dbStatus     = [];
        if ('' !== $filePath) {
            $dbStatusJson = run("cat {{deploy_path}}/shared/db-status.json")->toString();
            $dbStatus = json_decode($dbStatusJson, true);
        } else {
            $dbStatusFile = "../config/deploy/db-status.json";
            if (file_exists($dbStatusFile)) {
                $dbStatus = json_decode(file_get_contents($dbStatusFile), true);
            }
        }

        $dbConfig = env('database');
        if (empty($dbConfig)) {
            writeln("No database configured. Skipping step.");

            return;
        }
        $dbUser = isset($dbConfig['user']) ? escapeshellarg("-u{$dbConfig['user']}") : "";
        $dbPass = isset($dbConfig['password']) ? escapeshellarg("-p{$dbConfig['password']}") : "";
        $dbHost = isset($dbConfig['host']) ? "-h {$dbConfig['host']}" : "";
        $dbPort = isset($dbConfig['port']) ? "-P {$dbConfig['port']}" : "";
        $dbName = isset($dbConfig['database']) ? escapeshellarg("{$dbConfig['database']}") : "";

        $serverHost = env('server.host');
        if (!isset($dbStatus[$serverHost])) {
            $dbStatus[$serverHost] = [];
        }

        $sqlFiles = run("find {{release_path}}/archives/deploy/sql/ -iname '*.sql.gz'|sort -n")->toArray();
        foreach ($sqlFiles as $sqlFile) {
            if ('' == $sqlFile) {
                continue;
            }

            $relativeSqlFile = str_replace(env('release_path'), '', $sqlFile);

            if (in_array($relativeSqlFile, $dbStatus[$serverHost])) {
                continue;
            }

            writeln("Applying database script <info>$relativeSqlFile</info>.");

            run("gzip -dc $sqlFile | mysql $dbUser $dbPass $dbHost $dbPort $dbName -f");
            $dbStatus[$serverHost][] = $relativeSqlFile;
        }

        $sqlFiles = run("find {{release_path}}/archives/deploy/sql/ -iname '*.sql'|sort -n")->toArray();
        foreach ($sqlFiles as $sqlFile) {
            if ('' == $sqlFile) {
                continue;
            }

            $relativeSqlFile = str_replace(env('release_path'), '', $sqlFile);

            if (in_array($relativeSqlFile, $dbStatus[$serverHost])) {
                continue;
            }

            writeln("Applying database script <info>$relativeSqlFile</info>.");

            run("mysql $dbUser $dbPass $dbHost $dbPort $dbName -f < $sqlFile");
            $dbStatus[$serverHost][] = $relativeSqlFile;
        }
        $statusFile = tempnam(sys_get_temp_dir(),'db-status');
        file_put_contents($statusFile, json_encode($dbStatus), JSON_PRETTY_PRINT);
        upload($statusFile, env('deploy_path').'/shared/db-status.json');
    }
)->desc('Updating the database.');

task(
    'deploy:db:rollback',
    function () {
    }
)->desc('Rolling back database.');

task(
    'deploy:cleanup',
    function () {
        run('rm -f {{deploy_path}}/ssh-git {{deploy_path}}/id_rsa.deploy');
        foreach (env('cleanup_files') as $cleanupFile) {
            run("rm -f $cleanupFile");
        }

        run('rm -f {{release_path}}/config/deploy/id_rsa*');
    }
);

task(
    'deploy:vendors',
    function () {
        $hasComposerJson = run("if [ -f {{release_path}}/composer.json ]; then echo 'true'; fi")->toBool();
        if (!$hasComposerJson) {
            return;
        }

        if (commandExist('composer')) {
            $composer = 'composer';
        } else {
            run("cd {{release_path}} && curl -sS https://getcomposer.org/installer | {{php_bin}}");
            $composer = '{{php_bin}} composer.phar';
        }

        run(
            "cd {{release_path}} && {{env_vars}} $composer install --no-dev --verbose --prefer-dist --optimize-autoloader --no-progress --no-interaction"
        );
    }
)->desc('Installing vendors');

task(
    'deploy',
    [
        'deploy:prepare',
        'deploy:prepare:shared',
        'deploy:release',
        'deploy:update_code',
        'deploy:app_sources',
        'deploy:write_revision',
        'deploy:shared',
        'deploy:db:create-tag',
        'deploy:db:update',
        'deploy:writable',
        'deploy:vendors',
        'deploy:symlink',
        'deploy:cleanup',
        'success'
    ]
);

task('rollback', array('deploy:db:rollback', 'rollback'));

if (file_exists(__DIR__ . '/../../config/deploy/recipe/custom.php')) {
    include __DIR__ . '/../../config/deploy/recipe/custom.php';
    after('deploy:app_sources', 'deploy:custom');
}
