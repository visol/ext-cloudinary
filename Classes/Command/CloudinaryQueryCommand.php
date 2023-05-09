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
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Filters\RegularExpressionFilter;

class CloudinaryQueryCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $this->storage = $resourceFactory->getStorageObject($input->getArgument('storage'));
    }

    protected string $help = '
Usage: ./vendor/bin/typo3 cloudinary:query [0-9 - storage id]

Examples

# List of files withing a folder
typo3 cloudinary:query [0-9] --path=/foo/

# List of files withing a folder with recursive flag
typo3 cloudinary:query [0-9] --path=/foo/ --recursive

# List of files withing a folder with filter flag
typo3 cloudinary:query [0-9] --path=/foo/ --filter=\'[0-9,a-z]\.jpg\'

 # Count files / folder
typo3 cloudinary:query [0-9] --count

 # List of folders instead of files
typo3 cloudinary:query [0-9] --folder
    ' ;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $message = 'Query a given storage such a list, count files or folders';
        $this->setDescription($message)
            ->addOption('path', '', InputOption::VALUE_OPTIONAL, 'Give a folder identifier as a base path', '/')
            ->addOption('folder', '', InputOption::VALUE_NONE, 'Before scanning empty all resources for a given storage')
            ->addOption('yes', 'y', InputOption::VALUE_OPTIONAL, 'Accept everything by default', false)
            ->addOption('count', 'c', InputOption::VALUE_NONE, 'Count files')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Possible filter with regular expression')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Recursive lookup')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete found files / folders.')
            ->addArgument('storage', InputArgument::REQUIRED, 'Storage identifier')
            ->setHelp($this->help);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! Storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        // Get the chance to define a filter
        if ($input->getOption('filter')) {
            RegularExpressionFilter::setRegularExpression($input->getOption('filter'));
            $filters = $this->storage->getFileAndFolderNameFilters();
            $filters[] = [RegularExpressionFilter::class, 'filter'];
            $this->storage->setFileAndFolderNameFilters($filters);
        }

        if ($input->getOption('count')) {
            if ($input->getOption('folder')) {
                $this->countFoldersAction($input);
            } else {
                $this->countFilesAction($input);
            }
        } else {
            if ($input->getOption('folder')) {
                $folders = $this->listFoldersAction($input);

                $numberOfFolders = count($folders);
                if ($input->getOption('delete') && $numberOfFolders) {
                    $this->log();
                    $message = sprintf('You are about to recursively delete %s folder(s). Are you sure?', count($folders));
                    if ($this->io->confirm($message, false)) {
                        /** @var Folder $folder */
                        foreach ($folders as $folder) {
                            $this->log('Recursively deleting %s', [$folder->getIdentifier()]);
                            $folder->delete(true);
                        }
                    }
                }
            } else {
                $files = $this->listFilesAction($input);

                $numberOfFiles = count($files);
                if ($input->getOption('delete') && $numberOfFiles) {
                    $this->log();
                    $message = sprintf('You are about to delete %s files(s) from storage "%s". Are you sure?', $numberOfFiles, $this->storage->getName());
                    if ($this->io->confirm($message, false)) {
                        /** @var File $file */
                        foreach ($files as $file) {
                            $this->log('Deleting %s', [$file->getIdentifier()]);
                            $file->exists() ? $file->delete() : $this->error('Missing file %s', [$file->getIdentifier()]);
                        }
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function listFoldersAction(InputInterface $input): array
    {
        $folders = $this->storage->getFoldersInFolder($this->getFolder($input->getOption('path')), 0, 0, true, $input->getOption('recursive'));

        foreach ($folders as $folder) {
            $this->log($folder->getIdentifier());
        }

        return $folders;
    }

    protected function listFilesAction(InputInterface $input): array
    {
        $files = $this->storage->getFilesInFolder($this->getFolder($input->getOption('path')), 0, 0, true, $input->getOption('recursive'));

        foreach ($files as $file) {
            $this->log($file->getIdentifier());
        }
        return $files;
    }

    protected function countFoldersAction(InputInterface $input): void
    {
        $numberOfFolders = $this->storage->countFoldersInFolder($this->getFolder($input->getOption('path')), true, $input->getOption('recursive'));

        $this->log('I found %s folder(s)', [$numberOfFolders]);
    }

    protected function countFilesAction(InputInterface $input): void
    {
        $numberOfFiles = $this->storage->countFilesInFolder($this->getFolder($input->getOption('path')), true, $input->getOption('recursive'));

        $this->log('I found %s files(s)', [$numberOfFiles]);
    }

    protected function getFolder(string $folderIdentifier): Folder
    {
        $folderIdentifier =
            $folderIdentifier === DIRECTORY_SEPARATOR ? $folderIdentifier : DIRECTORY_SEPARATOR . trim($folderIdentifier, '/') . DIRECTORY_SEPARATOR;

        return GeneralUtility::makeInstance(
            Folder::class,
            $this->storage,
            $folderIdentifier,
            $folderIdentifier === DIRECTORY_SEPARATOR ? '' : $folderIdentifier,
        );
    }
}
