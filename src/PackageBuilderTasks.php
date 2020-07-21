<?php
/**
 * GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     PublishPressBuilder
 * @author      PublishPress
 * @copyright   Copyright (C) 2020 PublishPress. All rights reserved.
 */

namespace PublishPressBuilder;

use Symfony\Component\Yaml\Yaml;

abstract class PackageBuilderTasks extends \Robo\Tasks
{
    const DIST_DIR_NAME = 'dist';

    const OUTPUT_SEPARATOR = '----------------------------------------------------------------------';

    const TITLE_SEPARATOR = '######################################################################';

    /**
     * @var string
     */
    private $sourcePath;

    /**
     * @var string
     */
    private $destinationPath;

    /**
     * @var ComposerFileReaderInterface
     */
    private $composerFileReader;

    /**
     * @var PluginFileReaderInterface
     */
    private $pluginFileReader;

    /**
     * @var string
     */
    private $pluginName;

    /**
     * @var string
     */
    private $pluginVersion;

    /**
     * @var array
     */
    private $filesToIgnore = [];

    /**
     * PackageBuilderTasks constructor.
     */
    public function __construct()
    {
        $this->sourcePath = getcwd();

        $this->destinationPath = $this->sourcePath . '/' . self::DIST_DIR_NAME;
        if ($this->envFileExists()) {
            $builderEnv = Yaml::parseFile($this->getEnvFilePath());
            if (isset($builderEnv['destination'])) {
                $this->destinationPath = realpath($builderEnv['destination']);
            }
        }

        $this->composerFileReader = new ComposerFileReader();
        $this->pluginFileReader   = new PluginFileReader();

        $this->pluginName    = $this->composerFileReader->getPluginName($this->sourcePath);
        $this->pluginVersion = $this->pluginFileReader->getPluginVersion(
            $this->sourcePath . '/' . $this->pluginName . '.php'
        );
    }

    private function getEnvFilePath()
    {
        return $this->sourcePath . '/builder.env';
    }

    private function envFileExists()
    {
        return file_exists($this->getEnvFilePath());
    }

    private function sayTitle(): void
    {
        $this->say(self::TITLE_SEPARATOR);
        $this->say('PublishPress Plugin Builder');
        $this->say('');
        $this->say('Plugin Name: ' . $this->pluginName);
        $this->say('Plugin Version: ' . $this->pluginVersion);
        $this->say(self::OUTPUT_SEPARATOR);
    }

    public function build(): void
    {
        $this->sayTitle();

        $this->prepareCleanDistDir();
        $this->buildPackage();
    }

    private function buildPackage(): void
    {
        $zipPath = sprintf(
            '%s/%s-%s.zip',
            $this->destinationPath,
            $this->pluginName,
            $this->pluginVersion
        );

        $destinationPath = $this->destinationPath . '/' . $this->pluginName;
        $this->_mirrorDir($this->sourcePath, $destinationPath);

        $this->removeIgnoredFiles($destinationPath);

        $this->taskPack($zipPath)
             ->add([$this->pluginName => $destinationPath])
             ->run();
    }

    private function removeIgnoredFiles($dirPath): void
    {
        $filesToIgnore = $this->getListOfFilesToIgnore();

        if (empty($filesToIgnore)) {
            return;
        }

        foreach ($filesToIgnore as $file) {
            $path = $dirPath . '/' . $file;
            if (file_exists($path)) {
                $this->_remove($path);
            }
        }
    }

    private function prepareCleanDistDir(): void
    {
        if (file_exists($this->destinationPath)) {
            $this->_cleanDir($this->destinationPath);
        } else {
            $this->_mkdir($this->destinationPath);
        }
    }

    private function getListOfFilesToIgnore(): array
    {
        $defaultList = file(__DIR__ . '/../files-to-ignore.txt', FILE_IGNORE_NEW_LINES);

        return array_merge($defaultList, $this->filesToIgnore);
    }

    protected function appendToFileToIgnore(array $filesToIgnore): void
    {
        if (!empty($filesToIgnore)) {
            $this->filesToIgnore = array_merge($this->filesToIgnore, $filesToIgnore);
        }
    }
}
