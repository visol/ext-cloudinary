<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Search\SearchApi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryStorageListCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected string $help = '
Usage: ./vendor/bin/typo3 cloudinary:storage:list

Examples:

# Fetch all storages and display their configuration
typo3 cloudinary:storage:list
    ';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

    }

    protected function configure(): void
    {
        $message = 'Display all available storages configured for cloudinary';
        $this->setDescription($message)
            ->setHelp($this->help);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $cloudinaryRecords = $this->getCloudinaryRecords();
        foreach ($cloudinaryRecords as $cloudinaryRecord) {

            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            $storage = $resourceFactory->getStorageObject($cloudinaryRecord['uid']);

            $this->log(chr(10) . '---');
            $this->log(sprintf('name: %s' , $storage->getName()));
            $this->log(sprintf('uid: %s', $storage->getUid()));
            $configuration = CloudinaryApiUtility::getArrayConfiguration($storage);
            $this->log(sprintf('%s', var_export($configuration, true)));
        }


        return Command::SUCCESS;
    }

    /**
     * We retrieve all cloudinary storages
     *
     * @return array<int,array<string,mixed>>
     */
    protected function getCloudinaryRecords(): array
    {
        $q = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

        $q->select('*')
            ->from('sys_file_storage')
            ->where(
                $q->expr()->eq('driver', $q->createNamedParameter(CloudinaryDriver::DRIVER_TYPE))
            );

        return $q->execute()->fetchAllAssociative();
    }

}
