<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;

/**
 * Class CloudinaryCopyCommand
 */
class CloudinaryCopyCommand extends AbstractCloudinaryCommand
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription(
                'Copy bunch of images from a local storage to a cloudinary storage'
            )
            ->addOption(
                'silent',
                's',
                InputOption::VALUE_OPTIONAL,
                'Mute output as much as possible',
                false
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_OPTIONAL,
                'Accept everything by default',
                false
            )
            ->addOption(
                'base-url',
                '',
                InputArgument::OPTIONAL,
                'A base URL where to download missing files',
                ''
            )
            ->addOption(
                'filter',
                '',
                InputArgument::OPTIONAL,
                'A flexible filter containing wild cards, ex. %.youtube, /foo/bar/%',
                ''
            )
            ->addOption(
                'filter-file-type',
                '',
                InputArgument::OPTIONAL,
                'Add a possible filter for file type as defined by FAL (e.g 1,2,3,4,5)',
                ''
            )
            ->addOption(
                'limit',
                '',
                InputArgument::OPTIONAL,
                'Add a possible offset, limit to restrain the number of files. (eg. 0,100)',
                ''
            )
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source storage identifier'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target storage identifier'
            )
            ->setHelp(
                'Usage: ./vendor/bin/typo3 cloudinary:copy 1 2'
            );
    }

    /**
     * Move file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if (!$this->checkDriverType()) {
            $this->log('Look out! target storage is not of type "cloudinary"');
            return 1;
        }

        $files = $this->getFiles($input);

        if (count($files) === 0) {
            $this->log('No files found, no work for me!');
            return 0;
        }

        $this->log(
            'Copying %s files from storage "%s" (%s) to "%s" (%s)',
            [
                count($files),
                $this->sourceStorage->getName(),
                $this->sourceStorage->getUid(),
                $this->targetStorage->getName(),
                $this->targetStorage->getUid(),
            ]
        );

        // A chance to the user to confirm the action
        if ($input->getOption('yes') === false) {

            $response = $this->io->confirm('Shall I continue?', true);

            if (!$response) {
                $this->log('Script aborted');
                return 0;
            }
        }

        $counter = 0;
        foreach ($files as $file) {
            $fileObject = ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(
                $this->sourceStorage->getUid(),
                $file['identifier']
            );

            // Get the chance to download it
            if (!$fileObject->exists() && $input->getOption('base-url')) {
                $url = rtrim($input->getOption('base-url'), DIRECTORY_SEPARATOR) . $fileObject->getIdentifier();
                $this->log(
                    'Missing file, try downloading it from %s%s', [$url]
                );
                $this->download($fileObject, $url);
            }
            if ($fileObject->exists()) {
                $this->log('Copying %s', [$fileObject->getIdentifier()]);
                $this->targetStorage->addFile(
                    $fileObject->getForLocalProcessing(),
                    $fileObject->getParentFolder(),
                    $fileObject->getName(),
                    DuplicationBehavior::REPLACE
                );
                $counter++;
            } else {
                $this->log('Missing file %s', [$fileObject->getIdentifier()], self::WARNING);
                // We could log the missing files
                $this->missingFiles[] = $fileObject->getIdentifier();
                continue;
            }
        }
        $this->log(LF);
        $this->log('Number of files copied: %s', [$counter]);

        // Write possible log
        if ($this->missingFiles) {
            $this->writeLog('missing', $this->missingFiles);
            print_r($this->missingFiles);
        }

        return 0;
    }

    /**
     * @param File $fileObject
     * @param string $url
     *
     * @return bool
     */
    public function download(File $fileObject, string $url): bool
    {
        $this->ensureDirectoryExistence($fileObject);

        $contents = file_get_contents($url);
        return $contents
            ? (bool)file_put_contents($this->getAbsolutePath($fileObject), $contents)
            : false;
    }

    /**
     * @param File $fileObject
     *
     * @return string
     */
    protected function getAbsolutePath(File $fileObject): string
    {
        // Compute the absolute file name of the file to move
        $configuration = $fileObject->getStorage()->getConfiguration();
        $fileRelativePath = rtrim($configuration['basePath'], '/') . $fileObject->getIdentifier();
        return GeneralUtility::getFileAbsFileName($fileRelativePath);
    }

    /**
     * @param File $fileObject
     */
    protected function ensureDirectoryExistence(File $fileObject)
    {
        // Make sure the directory exists
        $directory = dirname($this->getAbsolutePath($fileObject));
        if (!is_dir($directory)) {
            GeneralUtility::mkdir_deep($directory);
        }
    }
}
