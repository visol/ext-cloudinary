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
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryScanService;

/**
 * Class CloudinaryScanCommand
 */
class CloudinaryScanCommand extends AbstractCloudinaryCommand
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
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');

        $this->targetStorage = ResourceFactory::getInstance()->getStorageObject(
            $input->getArgument('target')
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
                'empty',
                'e',
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
                'target',
                InputArgument::REQUIRED,
                'Storage identifier'
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
        if (!$this->checkDriverType()) {
            $this->log('Look out! target storage is not of type "cloudinary"');
            return 1;
        }

        if ($input->getOption('empty') === null || $input->getOption('empty')) {
            $this->log('Emptying all resources for storage %s"', [$this->targetStorage->getUid()]);
            $this->getCloudinaryScanService()->empty();
        }

        $result = $this->getCloudinaryScanService()->scan();


        $numberOfFiles = $result['created'] + $result['updated'] + $result['deleted'];
        if ($numberOfFiles !== $result['total']) {
            $this->error(
                'Something went wrong. There is a problem with the number of files counted. %s !== %s',
                [
                    $numberOfFiles,
                    $result['total']
                ]
            );
        }

        $numberOfFolders = $result['folder_created'] + $result['folder_updated'] + $result['folder_deleted'];
        if ($numberOfFolders !== $result['folder_total']) {
            $this->error(
                'Something went wrong. There is a problem with the number of folders counted. %s !== %s',
                [
                    $numberOfFolders,
                    $result['folder_total']
                ]
            );
        }

        $message = "Statistics for files: \n\n- created: %s\n- updated: %s\n- deleted: %s\n- total: %s";
        $message .= "\n\nStatistics for folders: \n\n- created: %s\n- updated: %s\n- deleted: %s\n- total: %s";
        $this->success(
            $message,
            [
                $result['created'],
                $result['updated'],
                $result['deleted'],
                $result['total'],
                $result['folder_created'],
                $result['folder_updated'],
                $result['folder_deleted'],
                $result['folder_total'],
            ]
        );

        return 0;
    }

    /**
     * @return object|CloudinaryScanService
     */
    protected function getCloudinaryScanService(): CloudinaryScanService
    {
        return GeneralUtility::makeInstance(CloudinaryScanService::class, $this->targetStorage);
    }
}
