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
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryMetadataCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected string $tableName = 'sys_file';

    protected CloudinaryPathService $cloudinaryPathService;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($input->getArgument('storage'));

        $this->cloudinaryPathService = GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $this->storage,
        );
    }

    protected function configure(): void
    {
        $message = 'Set metadata on cloudinary resources such as file reference and file usage.';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addArgument('storage', InputArgument::REQUIRED, 'Storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:metadata [0-9]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! Storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        $q = $this->getQueryBuilder('sys_file');
        $items = $q->select('file.*', 'reference.*')
            ->from('sys_file', 'file')
            ->innerJoin(
                'file',
                'sys_file_reference',
                'reference',
                'file.uid = reference.uid_local'
            )
            ->where(
                $q->expr()->eq('file.storage', $this->storage->getUid()),
                $q->expr()->or(
                    // we could extend to more tables...
                    $q->expr()->eq('tablenames', $q->expr()->literal('tt_content')),
                    $q->expr()->eq('tablenames', $q->expr()->literal('pages'))
                )
            )
            ->execute()
            ->fetchAllAssociative();

        $site = $this->getFirstSite();

        $publicIdOptions = [];
        foreach ($items as $item) {
            $publicId = $this->cloudinaryPathService->computeCloudinaryPublicId($item['identifier']);
            $publicIdOptions[$publicId]['tags'][$item['pid']] = 't3-page-' . $item['pid'];
            $publicIdOptions[$publicId]['context']['t3-page-' . $item['pid']] = rtrim((string)$site->getBase(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '?id=' . $item['pid'];
        }

        // Initialize and configure the API
        $this->initializeApi();
        foreach ($publicIdOptions as $publicId => $options) {
            $this->log('Updating tags and metadata for public id ' . $publicId);
            \Cloudinary\Uploader::explicit(
                $publicId,
                [
                    'type' => 'upload',
                    'tags' => 't3,t3-page,' . implode(', ', $options['tags']),
                    'context' => $options['context']
                ]
            );
        }

        return Command::SUCCESS;
    }

    public function getFirstSite(): Site
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();
        return array_values($sites)[0];
    }

    protected function initializeApi(): void
    {
        CloudinaryApiUtility::initializeByConfiguration($this->storage->getConfiguration());
    }

}
