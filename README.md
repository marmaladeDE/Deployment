deployment :: Deployment tool for php
=====================================

This repository contains a [Deployer](https://github.com/deployphp/deployer) based tool for php deployments.

Requirements
------------
*    git
*    php runtime

Downloading files/database dump
-------------------------------

### Configuration

For downloading to work, some configuration should be added to `servers.yaml` server configuration.

For database dump downloading, database info should be added, for example:

```yaml
serverName:
  db:
    mydb:
      user: myuser
      password: mysecretpassword
```

For file or folder downloading `download` configuration should be added, example:

```yaml
serverName:
  download:
    files:                      # for downloading signle files
      - myFile.txt
      - myFolder/myFyle.txt
    folders:                    # for folders
      - myfolder
      - otherFolder/myFolder
```

Single files will be downloaded without compressing, folders will be compressed to *.tar.gz and then downloaded. By default all the files are downloaded to `../archives/` directory (according to `config` directory) and placed under new directory with name as current timestamp. To change it, simply add `download_path` configuration with location of new download directory, example:

```yaml
serverName:
  download_path: ../archives/download/
```

### Download commands

| Command                                           |   Description                         |
|:------------------------------------------------- |:------------------------------------- |
| php deployer.phar download:database serverName    | Downloads database dump               |
| php deployer.phar download:files serverName       | Downloads configured files            |
| php deployer.phar download:folders serverName     | Downloads configured folders          |
| php deployer.phar download:files:all serverName   | Downloads configured files & folders  |

### Add custom deployment tasks
1. Create a PHP file `../config/deploy/recipe/custom.php`.
2. Create a new deployer task `deploy:custom`. This can be a task itself or a group of tasks.

#### Single Task
```php
<?php
task('deploy:custom', function() {
    // Put your task definition here.
});
```

#### Multiple Task
```php
<?php
task('deploy:custom:step1', function() {
    // Put your task definition here.
});

task('deploy:custom:step2', function() {
    // Put your task definition here.
});

task('deploy:custom', ['deploy:custom:step1', 'deploy:custom:step2']);
```
