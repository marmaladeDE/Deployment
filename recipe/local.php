<?php
/**
 * Defines local deployment tasks
 */
env('sharedFolder', function() {return '/media/project';});

task("deploy:local:init:webroot", function() {
    $tempDir = env('deploy_path') . '/tmp';
    $sharedDir = env('sharedFolder');
    run("umask 0002");
    // Create tmp/ directory if it does not exist
    run("if [ -d {$tempDir} ]; then rm -rf {$tempDir}; fi");
    // Move files in webroot to tmp/ directory
    run("if [ $(find {{deploy_path}}/web/ | wc -l) -gt 1 ]; then mv {{deploy_path}}/web {$tempDir}; fi");
    // Extract web.zip in webroot
    run("unzip {$sharedDir}/archives/web.zip -d {{deploy_path}}/web");
    // Move files from tmp/ directory to webroot, overwriting existing files
    run("if [ $(find {{deploy_path}}/tmp/ | wc -l) -gt 1 ]; then cp -rf {$tempDir}/* {{deploy_path}}/web; fi");
    run("chmod -R ug+rwX {{deploy_path}}/web");
})->desc("Initialize webroot")->setPrivate();

task("deploy:local:db:create", function() {
    // retrieve db config
    $databaseConfiguration = env('db');
    if (is_array($databaseConfiguration)) {
        foreach ($databaseConfiguration as $dbName => $dbCredential) {
            run("mysql -u {$dbCredential['user']} -p{$dbCredential['password']} -e 'CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8 COLLATE utf8_general_ci' 2> /dev/null");
        }
    }
})->desc("Creating database")->setPrivate();

task("deploy:local:db:import", function() {
    cd(env('sharedFolder') . "/archives/");
    $databaseConfiguration = env('db');
    if (is_array($databaseConfiguration)) {
        foreach ($databaseConfiguration as $dbName => $dbCredential) {
            run("gzip -dc {$dbName}.sql.gz | mysql -u {$dbCredential['user']} -p{$dbCredential['password']} -s {$dbName} 2> /dev/null");
        }
    }
})->desc("Importing sql")->setPrivate();

task("deploy:local:init", ["deploy:local:init:webroot","deploy:local:db:create", "deploy:local:db:import"])->desc("Initialize local environment");
