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
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryResourceService;

class CloudinaryFixImageDimensionCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected string $tableName = 'sys_file';

    protected CloudinaryResourceService $cloudinaryResourceService;

    protected CloudinaryPathService $cloudinaryPathService;

    protected MetaDataRepository $metadataRepository;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($input->getArgument('target'));

        $this->cloudinaryResourceService = GeneralUtility::makeInstance(
            CloudinaryResourceService::class,
            $this->storage,
        );

        $this->cloudinaryPathService = GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $this->storage,
        );

        $this->metadataRepository = GeneralUtility::makeInstance(MetaDataRepository::class);
    }

    protected function configure(): void
    {
        $this->setDescription('Fix missing width and height of image.')
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption('yes', 'y', InputOption::VALUE_OPTIONAL, 'Accept everything by default', false)
            ->addArgument('target', InputArgument::REQUIRED, 'Target storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:fix:dimension [0-9]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! target storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        $files = $this->getProblematicImages();

        foreach ($files as $file) {
            $fileObject = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($file['uid']);
            if ($fileObject->exists()) {
                $this->log('Fixing %s (%s)', [$fileObject->getIdentifier(), $fileObject->getUid()]);

                // get the corresponding cloudinary resource
                $publicId = $this->cloudinaryPathService->computeCloudinaryPublicId($fileObject->getIdentifier());

                $cloudinaryResource = $this->cloudinaryResourceService->getResource($publicId);

                // we update the metadata
                $this->metadataRepository->update(
                    $fileObject->getUid(),
                    [
                        'width' => $cloudinaryResource['width'],
                        'height' => $cloudinaryResource['height'],
                    ]
                );

            }
        }

        return Command::SUCCESS;
    }

    protected function getProblematicImages(): array
    {
        $query = $this->getQueryBuilder($this->tableName);
        $query
            ->select('sys_file.uid')
            ->from($this->tableName)
            ->join(
                $this->tableName,
                'sys_file_metadata',
                'metadata',
                $query->expr()->eq(
                    'metadata.file',
                    $this->tableName . '.uid'
                )
            )
            ->where(
                $query->expr()->eq(
                    'storage',
                    $this->storage->getUid()
                ),
                $query->expr()->eq(
                    'type',
                    File::FILETYPE_IMAGE
                ),
                $query->expr()->eq(
                    'missing',
                    0
                ),
                $query->expr()->orX(
                    $query->expr()->eq(
                        'metadata.width',
                        0
                    ),
                    $query->expr()->eq(
                        'metadata.height',
                        0
                    )
                )
            );

        return $query->execute()->fetchAllAssociative();
    }
}
