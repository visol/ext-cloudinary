<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\PathUtility;
use Visol\Cloudinary\Services\FileMoveService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryMoveCommand
 */
class CloudinaryMoveCommand extends AbstractCloudinaryCommand
{
    /**
     * @var array
     */
    protected $faultyUploadedFiles;

    /**
     * @var array
     */
    protected $skippedFiles;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $message = 'Move bunch of images from a local storage to a cloudinary storage.';
        $message .= ' CAUTIOUS!';
        $message .= ' 1. Moving means: we are "manually" uploading a file (skipping FAL API)';
        $message .= ' to the Cloudinary storage and deleting the one from the local storage (rm -f FILE) ';
        $message .= ' Finally we are changing the `sys_file.storage value` to the cloudinary storage.';
        $message .= ' The file uid will be kept!';
        $this
            ->setDescription(
                $message
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
                'Usage: ./vendor/bin/typo3 cloudinary:move 1 2'
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
        $files = $this->getFiles($input);

        if (count($files) === 0) {
            $this->log('No files found, no work for me!');
            return 0;
        }

        $this->log(
            'I will process %s files to be moved from storage "%s" (%s) to "%s" (%s)',
            [
                count($files),
                $this->sourceStorage->getUid(),
                $this->sourceStorage->getName(),
                $this->targetStorage->getUid(),
                $this->targetStorage->getName(),
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

            $this->log();
            $this->log('Starting migration with %s', [$file['identifier']]);

            /** @var  $fileObject */
            $fileObject = ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(
                $this->sourceStorage->getUid(),
                $file['identifier']
            );

            if ($this->isFileSkipped($fileObject)) {
                $this->log('Skipping file ' . $fileObject->getIdentifier());
                // $this->skippedFiles[] = $fileObject->getIdentifier();
                continue;
            }

            if ($this->getFileMoveService()->fileExists($fileObject, $this->targetStorage)) {
                $this->log('File has already been uploaded, good for us %s', [$fileObject->getIdentifier()]);
            } else {

                // Detect if the file is existing on storage "source" (1)
                if (!$fileObject->exists() && !$input->getOption('base-url')) {

                    $this->log('Missing file %s', [$fileObject->getIdentifier()], self::WARNING);
                    // We could log the missing files
                    $this->missingFiles[] = $fileObject->getIdentifier();
                    continue;
                }

                // Upload the file
                $this->log(
                    'Uploading file from %s%s',
                    [
                        $input->getOption('base-url'),
                        $fileObject->getIdentifier()
                    ]
                );
                $isUploaded = $this->getFileMoveService()->cloudinaryUploadFile($fileObject, $this->targetStorage, $input->getOption('base-url'));

                if (!$isUploaded) {
                    $this->log('Mmm..., I could not upload file %s', [$fileObject->getIdentifier()], self::WARNING);
                     $this->faultyUploadedFiles[] = $fileObject->getIdentifier();
                    continue;
                }
            }

            // changing file storage and hard delete the file from the current storage
            $this->log('Changing storage for file %s', [$fileObject->getIdentifier()]);
            $this->getFileMoveService()->changeStorage($fileObject, $this->targetStorage);
            $counter++;
        }
        $this->log(LF);
        $this->log('Number of files moved: %s', [$counter]);

        // Write possible log
        if ($this->missingFiles) {
            $this->writeLog('missing', $this->missingFiles);
        }
        if ($this->faultyUploadedFiles) {
            $this->writeLog('faulty-uploaded', $this->faultyUploadedFiles);
        }
        if ($this->skippedFiles) {
            $this->writeLog('skipped', $this->skippedFiles);
        }

        return 0;
    }

    /**
     * @param File $fileObject
     *
     * @return bool
     */
    protected function isFileSkipped(File $fileObject): bool
    {
        $isDisallowedPath = false;
        foreach ($this->getDisallowedPaths() as $disallowedPath) {
            $isDisallowedPath = strpos($fileObject->getIdentifier(), $disallowedPath) !== false;
            if ($isDisallowedPath) {
                break;
            }
        }

        $extension = PathUtility::pathinfo($fileObject->getIdentifier(), PATHINFO_EXTENSION);

        return in_array($extension, $this->getDisallowedExtensions(), true)
            || in_array($fileObject->getIdentifier(), $this->getDisallowedFileIdentifiers(), true)
            || $isDisallowedPath;
    }

    /**
     * @return array
     */
    protected function getDisallowedExtensions(): array
    {
        // Empty for now
        return [];
    }

    /**
     * @return array
     */
    protected function getDisallowedFileIdentifiers(): array
    {
        // Empty for now
        return [];
    }

    /**
     * @return array
     */
    protected function getDisallowedPaths(): array
    {
        return [
            'user_upload/_temp_/',
            '_temp_/',
            '_processed_/',
        ];
    }

    /**
     * @return object|FileMoveService
     */
    protected function getFileMoveService(): FileMoveService
    {
        return GeneralUtility::makeInstance(FileMoveService::class);
    }
}