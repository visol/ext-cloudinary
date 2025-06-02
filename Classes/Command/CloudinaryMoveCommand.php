<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\PathUtility;
use Visol\Cloudinary\Services\FileMoveService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CloudinaryMoveCommand extends AbstractCloudinaryCommand
{
    protected array $faultyUploadedFiles;

    protected array $skippedFiles;

    protected array $missingFiles = [];

    public function __construct(
        protected ResourceFactory $resourceFactory,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $message = 'Move bunch of images to a cloudinary storage. Consult the README.md for more info.';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption('yes', 'y', InputOption::VALUE_OPTIONAL, 'Accept everything by default', false)
            ->addOption('base-url', '', InputArgument::OPTIONAL, 'A base URL where to download missing files', '')
            ->addOption('filter', '', InputArgument::OPTIONAL, 'Filter pattern with possible wild cards, --filter="/foo/bar/%"', '')
            ->addOption('filter-file-type', '', InputArgument::OPTIONAL, 'Add a possible filter for file type as defined by FAL (e.g 1,2,3,4,5)', '')
            ->addOption('limit', '', InputArgument::OPTIONAL, 'Add a possible offset, limit to restrain the number of files. (eg. 0,100)', '')
            ->addOption('exclude', '', InputArgument::OPTIONAL, 'Exclude pattern, can contain comma separated values e.g. --exclude="/apps/%,/_temp/%"', '')
            ->addOption('used-only', '', InputArgument::OPTIONAL, 'Only move used files (with sys_file_reference)', false)
            ->addArgument('source', InputArgument::REQUIRED, 'Source storage identifier')
            ->addArgument('target', InputArgument::REQUIRED, 'Target storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:move 1 2');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceCombinedIdentifier = $input->getArgument('source');
        if (!is_string($sourceCombinedIdentifier)) {
            throw new \LogicException('source argument must be a string', 1749032224634);
        }
        $source = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($sourceCombinedIdentifier);
        $sourceStorage = $source->getStorage();
        $sourceStorageDriver = $this->getStorageDriver($sourceStorage);

        $targetCombinedIdentifier = $input->getArgument('target');
        if (!is_string($targetCombinedIdentifier)) {
            throw new \LogicException('target argument must be a string', 1749032230062);
        }
        $target = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($targetCombinedIdentifier);
        $targetIndexer = GeneralUtility::makeInstance(Indexer::class, $target->getStorage());

        $baseUrl = $input->getOption('base-url');
        if (!is_string($baseUrl)) {
            throw new \LogicException('Base URL must be a string');
        }

        if (!$sourceStorage->hasHierarchicalIdentifiers()) {
            $this->log('Source storage must use hierarchical identifiers');
            return Command::INVALID;
        }
        if (!$this->checkDriverType($target->getStorage())) {
            $this->log('Look out! target storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        $files = $this->getFiles($source, $input);
        if (count($files) === 0) {
            $this->log('No files found, no work for me!');
            return Command::SUCCESS;
        }

        $this->log('I will process %s files to be moved from storage "%s" (%s) to "%s" (%s)', [
            count($files),
            $source->getCombinedIdentifier(),
            $sourceStorage->getName(),
            $target->getCombinedIdentifier(),
            $target->getStorage()->getName(),
        ]);

        // A chance to the user to confirm the action
        if ($input->getOption('yes') === false) {
            $response = $this->io->confirm('Shall I continue?', true);

            if (!$response) {
                $this->log('Script aborted');
                return Command::SUCCESS;
            }
        }

        $counter = 0;
        foreach ($files as $file) {
            $this->log();
            $this->log('Starting migration with %s', [$file['identifier']]);

            /** @var File $fileObject */
            $fileObject = $this->resourceFactory->getFileObject($file['uid'], $file);
            $sourceFileExists = $fileObject->exists();

            if ($this->isFileSkipped($fileObject)) {
                $this->log('Skipping file ' . $fileObject->getIdentifier());
                // $this->skippedFiles[] = $fileObject->getIdentifier();
                continue;
            }

            if (! str_starts_with($fileObject->getIdentifier(), $source->getIdentifier())) {
                throw new \LogicException('file is not in source folder', 1748004982814);
            }

            $newIdentifier = str_replace($source->getIdentifier(), $target->getIdentifier(), $fileObject->getIdentifier());

            if ($this->getFileMoveService()->fileExists($target->getStorage(), $newIdentifier)) {
                $this->log('File has already been uploaded, good for us %s', [$fileObject->getIdentifier()]);
            } else {
                // Detect if the file is existing on storage "source" (1)
                if (!$sourceFileExists && empty($baseUrl)) {
                    $this->log('Missing file %s', [$fileObject->getIdentifier()], self::WARNING);
                    // We could log the missing files
                    $this->missingFiles[] = $fileObject->getIdentifier();
                    continue;
                }

                // Upload the file
                $this->log('Uploading file from %s%s', [$baseUrl, $fileObject->getIdentifier()]);

                try {
                    $start = microtime(true);
                    $this->getFileMoveService()->cloudinaryUploadFile($fileObject, $target, $newIdentifier, $baseUrl);
                    $timeElapsedSeconds = microtime(true) - $start;
                    $this->log('File uploaded, Elapsed time %.3f', [$timeElapsedSeconds]);
                } catch (Exception $e) {
                    $this->log(
                        'Mmm..., I could not upload file %s. Exception %s: %s',
                        [$fileObject->getIdentifier(), $e->getCode(), $e->getMessage()],
                        self::WARNING,
                    );
                    $this->faultyUploadedFiles[] = $fileObject->getIdentifier();
                    continue;
                }
            }

            // Update sys_file entry
            // See \TYPO3\CMS\Core\Resource\ResourceStorage::moveFile for reference
            $this->log('Changing storage for file %s', [$fileObject->getIdentifier()]);
            $oldIdentifier = $fileObject->getIdentifier();
            $oldFolder = $fileObject->getParentFolder();
            $fileObject->updateProperties(['storage' => $target->getStorage()->getUid(), 'identifier' => $newIdentifier]);
            $newFolder = $fileObject->getParentFolder();
            if (!$newFolder instanceof Folder) {
                throw new \LogicException('New folder must be a Folder', 1749032215642);
            }

            // clean up processed files
            $this->eventDispatcher->dispatch(new AfterFileAddedEvent($fileObject, $newFolder));
            $targetIndexer->updateIndexEntry($fileObject);

            // Delete the file from the source storage without deleting the sys_file record
            if ($sourceFileExists) {
                $sourceStorageDriver->deleteFile($oldIdentifier);
                $sourceFileExists = false;
            }

            $this->eventDispatcher->dispatch(new AfterFileMovedEvent($fileObject, $newFolder, $oldFolder));

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

        return Command::SUCCESS;
    }

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

        return in_array($extension, $this->getDisallowedExtensions(), true) ||
            in_array($fileObject->getIdentifier(), $this->getDisallowedFileIdentifiers(), true) ||
            $isDisallowedPath;
    }

    protected function getDisallowedExtensions(): array
    {
        // Empty for now
        return [];
    }

    protected function getDisallowedFileIdentifiers(): array
    {
        // Empty for now
        return [];
    }

    protected function getDisallowedPaths(): array
    {
        return ['user_upload/_temp_/', '_temp_/', '_processed_/'];
    }

    protected function getFileMoveService(): FileMoveService
    {
        return GeneralUtility::makeInstance(FileMoveService::class);
    }

    protected function getStorageDriver(ResourceStorage $storage): DriverInterface
    {
        $reflection = new \ReflectionClass($storage);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $driver = $property->getValue($storage);
        if (!$driver instanceof DriverInterface) {
            throw new \LogicException('Storage driver must implement DriverInterface', 1749032330406);
        }
        return $driver;
    }
}
