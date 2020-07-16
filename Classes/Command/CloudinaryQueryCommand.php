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
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryScanService;

/**
 * Class CloudinaryQueryCommand
 */
class CloudinaryQueryCommand extends AbstractCloudinaryCommand
{

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');

        $this->storage = ResourceFactory::getInstance()->getStorageObject(
            $input->getArgument('storage')
        );
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $message = 'Scan and warm up a cloudinary storage.';
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
                'folder',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Before scanning empty all resources for a given storage',
                false
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_OPTIONAL,
                'Accept everything by default',
                false
            )
            ->addArgument(
                'storage',
                InputArgument::REQUIRED,
                'Storage identifier'
            )
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'Possible action "list"'
            )
            ->setHelp(
                'Usage: ./vendor/bin/typo3 cloudinary:scan [0-9]'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! Storage is not of type "cloudinary"');
            return 1;
        }


        $folder = $input->getOption('folder');
        if ($folder === null || $folder) {

            $folderIdentifier = '/'; // we can have a filter here

            $folders = $this->storage->getFoldersInFolder(
                $this->getFolder($folderIdentifier)
            );

            foreach ($folders as $folder) {
                $this->log($folder->getIdentifier());
            }
        } else {

            $folderIdentifier = '/'; // we can have a filter here

            $files = $this->storage->getFilesInFolder(
                $this->getFolder($folderIdentifier)
            );

            foreach ($files as $file) {
                $this->log($file->getIdentifier());
            }
        }

        return 0;
    }

    /**
     * @param string $folderIdentifier
     *
     * @return object|Folder
     */
    protected function getFolder($folderIdentifier): Folder
    {
        $folderIdentifier = $folderIdentifier === DIRECTORY_SEPARATOR
            ? $folderIdentifier
            : DIRECTORY_SEPARATOR . trim($folderIdentifier, '/') . DIRECTORY_SEPARATOR;

        return GeneralUtility::makeInstance(
            Folder::class,
            $this->storage,
            $folderIdentifier,
            $folderIdentifier === DIRECTORY_SEPARATOR
                ? ''
                : $folderIdentifier
        );
    }
}
