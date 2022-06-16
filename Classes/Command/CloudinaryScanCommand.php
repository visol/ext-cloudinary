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
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryScanService;

/**
 * Class CloudinaryScanCommand
 */
class CloudinaryScanCommand extends AbstractCloudinaryCommand
{
    /**
     * @var ResourceStorage
     */
    protected ResourceStorage $storage;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($input->getArgument('storage'));
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $message = 'Scan and warm up a cloudinary storage.';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption(
                'empty',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Before scanning empty all resources for a given storage',
                false,
            )
            ->addArgument('storage', InputArgument::REQUIRED, 'Storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:scan [0-9]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! Storage is not of type "cloudinary"');
            return 1;
        }

        if ($input->getOption('empty') === null || $input->getOption('empty')) {
            $this->log('Emptying all mirrored resources for storage "%s"', [$this->storage->getUid()]);
            $this->log();
            $this->getCloudinaryScanService()->empty();
        }

        $this->log('Hint! Look at the log to get more insight:');
        $this->log('tail -f web/typo3temp/var/logs/cloudinary.log');
        $this->log();

        $result = $this->getCloudinaryScanService()->scan();

        $numberOfFiles = $result['created'] + $result['updated'] - $result['deleted'];
        if ($numberOfFiles !== $result['total']) {
            $this->error(
                'Something went wrong. There is a problem with the number of files counted. %s !== %s. It should be fixed in the next scan',
                [$numberOfFiles, $result['total']],
            );
        }

        $message = "Statistics for files: \n\n- created: %s\n- updated: %s\n- total: %s\n- deleted: %s";
        $message .= "\n\nStatistics for folders: \n\n- deleted: %s";
        $this->success($message, [
            $result['created'],
            $result['updated'],
            $result['total'],
            $result['deleted'],
            $result['folder_deleted'],
        ]);

        return 0;
    }

    protected function getCloudinaryScanService(): CloudinaryScanService
    {
        return GeneralUtility::makeInstance(CloudinaryScanService::class, $this->storage, $this->io);
    }
}
