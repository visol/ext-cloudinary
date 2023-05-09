<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CloudinaryCopyCommand extends AbstractCloudinaryCommand
{
    protected array $missingFiles = [];

    protected ResourceStorage $sourceStorage;

    protected ResourceStorage $targetStorage;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->sourceStorage = $resourceFactory->getStorageObject($input->getArgument('source'));
        $this->targetStorage = $resourceFactory->getStorageObject($input->getArgument('target'));
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Copy bunch of images from a local storage to a cloudinary storage')
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption('yes', 'y', InputOption::VALUE_OPTIONAL, 'Accept everything by default', false)
            ->addOption('base-url', '', InputArgument::OPTIONAL, 'A base URL where to download missing files', '')
            ->addOption('filter', '', InputArgument::OPTIONAL, 'Filter pattern with possible wild cards, --filter="/foo/bar/%"', '')
            ->addOption('filter-file-type', '', InputArgument::OPTIONAL, 'Add a possible filter for file type as defined by FAL (e.g 1,2,3,4,5)', '')
            ->addOption('limit', '', InputArgument::OPTIONAL, 'Add a possible offset, limit to restrain the number of files. (eg. 0,100)', '')
            ->addOption('exclude', '', InputArgument::OPTIONAL, 'Exclude pattern, can contain comma separated values e.g. --exclude="/apps/%,/_temp/%"', '')
            ->addArgument('source', InputArgument::REQUIRED, 'Source storage identifier')
            ->addArgument('target', InputArgument::REQUIRED, 'Target storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:copy 1 2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->targetStorage)) {
            $this->log('Look out! target storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        $files = $this->getFiles($this->sourceStorage, $input);

        if (count($files) === 0) {
            $this->log('No files found, no work for me!');
            return Command::SUCCESS;
        }

        $this->log('Copying %s files from storage "%s" (%s) to "%s" (%s)', [
            count($files),
            $this->sourceStorage->getName(),
            $this->sourceStorage->getUid(),
            $this->targetStorage->getName(),
            $this->targetStorage->getUid(),
        ]);

        // A chance to the user to confirm the action
        if ($input->getOption('yes') === false) {
            $response = $this->io->confirm('Shall I continue?', true);

            if (!$response) {
                $this->log('Script aborted');
                return Command::SUCCESS;
            }
        }

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $counter = 0;
        foreach ($files as $file) {
            $fileObject = $resourceFactory->getFileObjectByStorageAndIdentifier($this->sourceStorage->getUid(), $file['identifier']);

            // Get the chance to download it
            if (!$fileObject->exists() && $input->getOption('base-url')) {
                $url = rtrim($input->getOption('base-url'), DIRECTORY_SEPARATOR) . $fileObject->getIdentifier();
                $this->log('Missing file, try downloading it from %s%s', [$url]);
                $this->download($fileObject, $url);
            }
            if ($fileObject->exists()) {
                $this->log('Copying %s', [$fileObject->getIdentifier()]);
                $this->targetStorage->addFile(
                    $fileObject->getForLocalProcessing(),
                    $fileObject->getParentFolder(),
                    $fileObject->getName(),
                    DuplicationBehavior::REPLACE,
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

        return Command::SUCCESS;
    }

    public function download(File $fileObject, string $url): bool
    {
        $this->ensureDirectoryExistence($fileObject);

        $contents = file_get_contents($url);
        return $contents ? (bool)file_put_contents($this->getAbsolutePath($fileObject), $contents) : false;
    }

    protected function getAbsolutePath(File $fileObject): string
    {
        // Compute the absolute file name of the file to move
        $configuration = $fileObject->getStorage()->getConfiguration();
        $fileRelativePath = rtrim($configuration['basePath'], '/') . $fileObject->getIdentifier();
        return GeneralUtility::getFileAbsFileName($fileRelativePath);
    }

    protected function ensureDirectoryExistence(File $fileObject)
    {
        // Make sure the directory exists
        $directory = dirname($this->getAbsolutePath($fileObject));
        if (!is_dir($directory)) {
            GeneralUtility::mkdir_deep($directory);
        }
    }
}
