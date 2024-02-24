<?php

/**
 * This script converts Joomla to psr-12 coding standard
 *
 * @package    Joomla.Build
 * @copyright  (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// Set defaults
$root      = dirname(dirname(__DIR__));
$php       = 'php';
$git       = 'git';
$checkPath = false;
$tasks     = [
    'CBF'   => false,
    'CS'    => false,
    'CLEAN' => false,
    'CMS'   => false,
    'BRANCH' => false,
];

$script = array_shift($argv);

if (empty($argv)) {
    echo <<<TEXT
        Joomla! PSR-12 Converter
        =======================
        Usage:
            php {$script} --task=<CBF,CS,CLEAN,CMS> [path]

        Description:
            The converter has several tasks which can be run separately.
            You can combine them separated by a comma (,).

            --tasks:
              * CBF
                This task executes the PHP Code Beautifier and Fixer. It
                does the heavy lifting making the code PSR-12 compatible.
                (beware this tasks modifies many files)
              * CS
                This task executes the PHP Code Sniffer. It collects all
                issues and generates a HTML report in build/tmp/psr12.
                Also it generates a collection of issues which can't be
                fixed by CBF. This information is saved as json in
                build/tmp/psr12/cleanup.json. If this option is activated
                the tmp directory is cleaned before running.
              * CLEAN
                This tasks loads the cleanup.json generated by the CS task
                and changes the cms specific files. After completing this
                task it re-runs the CBF and CS task.
              * CMS
                This tasks activates all other tasks and automatically
                commits the changes after both CBF runs. Usually only
                needed for the first cms conversion.
              * BRANCH
                This tasks updates all files changed by the current
                branch compared to the psr12anchor tag. This allows
                to update a create pull request.

            --repo:
              The path to the repository root.

            Path:
              Providing a path will only check the directories or files
              specified. It's possible to add multiple files and folder
              separated by a comma (,).


        TEXT;
    die(1);
}

foreach ($argv as $arg) {
    if (substr($arg, 0, 2) === '--') {
        $argi = explode('=', $arg, 2);
        switch ($argi[0]) {
            case '--task':
                foreach ($tasks as $task => $value) {
                    if (stripos($argi[1], $task) !== false) {
                        $tasks[$task] = true;
                    }
                }
                break;
            case '--repo':
                $root = $argi[1];
                break;
        }
    } else {
        $checkPath = $arg;
        break;
    }
}

$tmpDir = __DIR__ . '/../tmp/psr12';

if ($tasks['CMS']) {
    $tasks['CBF']   = true;
    $tasks['CS']    = true;
    $tasks['CLEAN'] = true;
}

if ($tasks['BRANCH']) {
    $tasks['CMS']    = true;
    $tasks['CBF']    = true;
    $tasks['CS']     = true;
    $tasks['CLEAN']  = true;

    $cmd = $git . ' --no-pager diff --name-only psr12anchor..HEAD';
    exec($cmd, $output, $result);
    if ($result !== 0) {
        die('Unable to find changes for this branch');
    }

    foreach ($output as $k => $line) {
        if (substr($line, -4) !== '.php') {
            unset($output[$k]);
        }
    }

    $checkPath = implode(',', $output);
    if (empty($checkPath)) {
        die(0);
    }
}

$items = [];
if ($checkPath) {
    $items = explode(',', $checkPath);
} else {
    $items[] = 'index.php';
    $items[] = 'administrator/index.php';

    $baseFolders = [
        'administrator/components',
        'administrator/includes',
        'administrator/language',
        'administrator/modules',
        'administrator/templates',
        'api',
        'cli',
        'components',
        'includes',
        'installation',
        'language',
        'layouts',
        'libraries',
        'modules',
        'plugins',
        'templates',
        'tests',
    ];

    foreach ($baseFolders as $folder) {
        $dir = dir($root . '/' . $folder);
        while (false !== ($entry = $dir->read())) {
            if (($entry === ".") || ($entry === "..")) {
                continue;
            }
            if (!is_dir($dir->path . '/' . $entry)) {
                if (substr($entry, -4) !== '.php') {
                    continue;
                }
            }
            if (
                $folder === 'libraries'
                && (
                    $entry === 'php-encryption'
                    || $entry === 'phpass'
                    || $entry === 'vendor'
                )
            ) {
                continue;
            }
            $items[] = str_replace($root . '/', '', $dir->path) . '/' . $entry;
        }
        $dir->close();
    }
}
$executedTasks = implode(
    ',',
    array_keys(
        array_filter($tasks, function ($task) {
            return $task;
        })
    )
);
$executedPaths = implode("\n", $items);

echo <<<TEXT
        Joomla! PSR-12 Converter
        =======================

        Tasks will be executed: {$executedTasks}
        Files and Folders:
        {$executedPaths}


        TEXT;

// Recreate temp dir
$cleanItems = glob($tmpDir . '/{,.}*', GLOB_MARK | GLOB_BRACE);
foreach ($cleanItems as $item) {
    if (basename($item) == '.' || basename($item) == '..') {
        continue;
    }
    unlink($item);
}
unset($cleanItems, $item);

@mkdir($tmpDir, 0777, true);

$cbfOptions = "-p --standard=" . __DIR__ . "/ruleset.xml --extensions=php";
$csOptions  = "--standard=" . __DIR__ . "/ruleset.xml --extensions=php";
$csOptions  .= " --report=" . __DIR__ . "/phpcs.joomla.report.php";

foreach ($items as $item) {
    if ($tasks['CBF']) {
        echo 'Fix ' . $item . "\n";

        passthru($php . ' ' . $root . '/libraries/vendor/bin/phpcbf ' . $cbfOptions . ' ' . $item, $result);

        if ($result !== 0) {
            echo "Error PHPCBF completed with error code: $result \n\n";
        }
    }

    if ($tasks['CS']) {
        echo 'Check ' . $item . "\n";
        passthru($php . ' ' . $root . '/libraries/vendor/bin/phpcs ' . $csOptions . ' ' . $item, $result);

        if ($result !== 0) {
            echo "Error PHPCS completed with error code: $result \n\n";
        }
    }
}

if ($tasks['CMS']) {
    passthru($git . ' add ' . $root);
    passthru($git . ' commit -m "Phase 1 convert ' . ($tasks['BRANCH'] ? 'BRANCH' : 'CMS') . ' to PSR-12"');
}

if ($tasks['CLEAN'] && file_exists($tmpDir . '/cleanup.json')) {
    echo "Cleaning Error\n" .
        passthru($php . ' ' . __DIR__ . '/clean_errors.php', $result);

    foreach ($items as $item) {
        if ($tasks['CBF']) {
            echo 'Fix ' . $item . "\n";
            passthru($php . ' ' . $root . '/libraries/vendor/bin/phpcbf ' . $cbfOptions . ' ' . $item, $result);

            if ($result !== 0) {
                echo "Error PHPCBF complete with error code: $result \n\n";
            }
        }

        if ($tasks['CS']) {
            echo 'Check ' . $item . "\n";
            passthru($php . ' ' . $root . '/libraries/vendor/bin/phpcs ' . $csOptions . ' ' . $item, $result);

            if ($result !== 0) {
                echo "Error PHPCS complete with error code: $result \n\n";
            }
        }
    }
}

if ($tasks['CMS']) {
    passthru($git . ' add ' . $root);
    passthru($git . ' commit -m "Phase 2 convert ' . ($tasks['BRANCH'] ? 'BRANCH' : 'CMS') . ' to PSR-12"');
}

if (!empty($tasks['CMS']) && empty($tasks['BRANCH'])) {
    echo <<<Text
        Conversion completed please complete the following manual tasks:
        =================================================================

         * Update .drone.yml to use PSR-12
           change phpcs --standard parameter to --standard=ruleset.xml

         * Remove Joomla! coding standards
           composer remove joomla/cms-coding-standards joomla/coding-standards

         * Replace the .editorconfig in the root folder with
           the version in the build/psr12 folder

         * Move the file build/psr12/ruleset.xml to the root folder


        Text;
}
