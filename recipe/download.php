<?php
/**
 * Defines local deployment tasks
 */
env('sharedFolder', function() {return '/media/project';});
env('download_path', '../archives/');
env('temp_dir', '');

function getDownloadDir() {
    if (!env('temp_dir')) {
        $downloadDir = rtrim(env('download_path'), '/') . '/' . time() . '/';
        mkdir($downloadDir, 0775, true);
        env('temp_dir', $downloadDir);
    }

    return env('temp_dir');
}

task("download:database", function() {
    $databaseConfiguration = env('db');
    if (is_array($databaseConfiguration)) {
        $dumpName = time() . '_db.sql';
        $downloadDir = getDownloadDir();
        foreach ($databaseConfiguration as $dbName => $dbCredential) {
            run("mysqldump -u {$dbCredential['user']} -p{$dbCredential['password']} {$dbName} > {{deploy_path}}/{$dumpName}");
            download($downloadDir . $dumpName, env('deploy_path') ."/".$dumpName);
            run("rm {{deploy_path}}/{$dumpName}");
        }
    }
})->desc("Downloading database");

task("download:files", function() {
    $download = env('download');
    if (isset($download['files']) && is_array($download)) {
        $downloadDir = getDownloadDir();
        foreach ($download['files'] as $fileName) {
            download($downloadDir . end(explode('/', $fileName)), env('deploy_path') . "/" . $fileName);
        }
    }
})->desc("Downloading files");

task("download:folders", function() {
    $download = env('download');
    if (isset($download['folders']) && is_array($download)) {
        $downloadDir = getDownloadDir();
        foreach ($download['folders'] as $folderName) {
            $folderName = rtrim($folderName, '/');
            cd("{{deploy_path}}");
            run("tar -zcf {$folderName}.tar.gz $folderName");
            $fileName = end(explode('/', $folderName));
            download("{$downloadDir}/{$fileName}.tar.gz", env('deploy_path') . "/{$folderName}.tar.gz");
            run("rm {$folderName}.tar.gz");
        }
    }
})->desc("Downloading folders");

task("download:files:all", ["download:folders", "download:files"])->desc("Downloading files and folders");