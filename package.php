<?php
/**
 * Script to generate package.xml file
 *
 * Taken from PEAR::Log, thanks Jon ;)
 *
 * $Id$
 */
require_once 'PEAR/PackageFileManager.php';
require_once 'Console/Getopt.php';

$version = '0.3.10';

$notes = <<<EOT
 - Fix Bug #11051 Seems class variable is not declared
EOT;

$description = <<<EOT
  LiveUser_Admin is meant to be used with the LiveUser package.
  It is composed of all the classes necessary to administrate
  data used by LiveUser.

  You'll be able to add/edit/delete/get things like:
  * Rights
  * Users
  * Groups
  * Areas
  * Applications
  * Subgroups
  * ImpliedRights

  And all other entities within LiveUser.

  At the moment we support the following storage containers:
  * PEAR::DB
  * PEAR::MDB
  * PEAR::MDB2
  * PECL::PDO

  But it takes no time to write up your own storage container,
  so if you like to use native mysql functions straight, then it's possible
  to do so in under a hour!
EOT;

$package = new PEAR_PackageFileManager();

$result = $package->setOptions(array(
    'package'           => 'LiveUser_Admin',
    'summary'           => 'User authentication and permission management framework',
    'description'       => $description,
    'version'           => $version,
    'state'             => 'beta',
    'license'           => 'LGPL',
    'filelistgenerator' => 'cvs',
    'ignore'            => array('package.php', 'package.xml'),
    'notes'             => $notes,
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'baseinstalldir'    => '/LiveUser/Admin',
    'packagedirectory'  => './',
    'installexceptions' => array(
        'Admin.php'            => '/LiveUser',
    ),
    'exceptions'         => array(
        'lgpl.txt' => 'doc',
    ),
    'dir_roles'         => array('sql'               => 'data',
                                 'docs'              => 'doc',
                                 'scripts'           => 'script')
));

if (PEAR::isError($result)) {
    echo $result->getMessage();
}

$package->addMaintainer(
    'mw21st', 'lead', 'Markus Wolff', 'mw21st@php.net'
);
$package->addMaintainer(
    'arnaud', 'lead', 'Arnaud Limbourg', 'arnaud@php.net'
);
$package->addMaintainer(
    'lsmith', 'lead', 'Lukas Kahwe Smith', 'smith@pooteeweet.org'
);
$package->addMaintainer(
    'dufuz', 'lead', 'Helgi �ormar', 'dufuz@php.net'
);
$package->addMaintainer(
    'mahono', 'developer', 'Matthias Nothhaft', 'mahono@php.net'
);

$package->addDependency('php',       '4.3.0', 'ge',  'php', false);
$package->addDependency('PEAR',      '1.5.4', 'ge',  'pkg', false);
$package->addDependency('LiveUser','0.16.12', 'ge',  'pkg', false);
$package->addDependency('Log',       '1.7.0', 'ge',  'pkg', true);
$package->addDependency('DB',        '1.6.0', 'ge',  'pkg', true);
$package->addDependency('MDB',       '1.1.4', 'ge',  'pkg', true);
$package->addDependency('MDB2',      '2.1.0', 'ge',  'pkg', true);

if (array_key_exists('make', $_GET) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    echo "package.xml generated\n";
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
}
