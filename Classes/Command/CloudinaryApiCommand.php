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
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryApiCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected string $help = '
Usage: ./vendor/bin/typo3 cloudinary:api [storage-uid]

Examples:

# Query by public id
typo3 cloudinary:api [0-9] --publicId="foo-bar"
typo3 cloudinary:api [0-9] --publicId="foo-bar" --type="video"

# Query by file uid (will retrieve the public id from the file)
typo3 cloudinary:api --fileUid="[0-9]"

# Query with an expression
# @see https://cloudinary.com/documentation/search_api
typo3 cloudinary:api [0-9] --expression="public_id:foo-bar"
typo3 cloudinary:api [0-9] --expression="resource_type:image AND tags=kitten AND uploaded_at>1d"

# List the resources instead of the whole resource
typo3 cloudinary:api [0-9] --expression="folder=fileadmin/_processed_/*" --list

# Delete the resources according to the expression
typo3 cloudinary:api [0-9] --expression="folder=fileadmin/_processed_/*" --delete
    ';

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($input->getArgument('storage'));
    }

    protected function configure(): void
    {
        $message = 'Interact with cloudinary API';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption('fileUid', '', InputOption::VALUE_OPTIONAL, 'File uid', '')
            ->addOption('publicId', '', InputOption::VALUE_OPTIONAL, 'Cloudinary public id', '')
            ->addOption('type', '', InputOption::VALUE_OPTIONAL, 'In combination with publicId, overrides the type. Possible value iamge, video or raw', 'image')
            ->addOption('expression', '', InputOption::VALUE_OPTIONAL, 'Cloudinary search expression e.g --expression="folder=fileadmin/*"', '')
            ->addOption('list', '', InputOption::VALUE_OPTIONAL, 'List instead of the whole resource --expression="folder=fileadmin/_processed_/*" --list', false)
            ->addOption('delete', '', InputOption::VALUE_OPTIONAL, 'Delete the resources --expression="folder=fileadmin/*" --delete', false)
            ->addArgument('storage', InputArgument::OPTIONAL, 'Storage identifier')
            ->setHelp($this->help);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! Storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        $publicId = $input->getOption('publicId');
        $expression = $input->getOption('expression');
        $list = $input->getOption('list') === null;
        $delete = $input->getOption('delete') === null;

        if ($delete) {
            // ask the user whether it should continue
            $continue = $this->io->confirm('Are you sure you want to delete the resources?');
            if (!$continue) {
                $this->log('Aborting...');
                return Command::SUCCESS;
            }
        }

        /** @var int $fileUid */
        $fileUid = $input->getOption('fileUid');
        if ($fileUid) {
            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            $file = $resourceFactory->getFileObject($fileUid);

            $this->storage = $file->getStorage(); // just to be sure
            $publicId = $this->getPublicIdFromFile($file);
        }

        try {
            if ($publicId) {
                $resource = $this->getAdminApi()->asset($publicId, ['resource_type' => $input->getOption('type')]);
                $this->log(var_export((array)$resource, true));
            } elseif ($expression) {

                $counter = 0;
                do {
                    $nextCursor = isset($response)
                        ? $response['next_cursor']
                        : '';

                    $response = $this->getSearchApi()
                        ->expression($expression)
                        ->sortBy('public_id', 'asc')
                        ->maxResults(100)
                        ->nextCursor($nextCursor)
                        ->execute();

                    if (is_array($response['resources'])) {
                        $_resources = [];
                        foreach ($response['resources'] as $resource) {
                            if ($list || $delete) {
                                $this->log($resource['public_id']);
                            } else {
                                $this->log(var_export((array)$resource, true));
                            }

                            // collect resources in case of deletion.
                            $_resources[] = $resource['public_id'];
                        }
                        // delete the resource if told
                        if ($delete) {
                            $counter++;
                            $this->log("\nDeleting batch #$counter...\n");
                            $this->getAdminApi()->deleteAssets($_resources);
                        }
                    }
                } while (!empty($response) && isset($response['next_cursor']));

            } else {
                $this->log('Nothing to do...');
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

        return Command::SUCCESS;
    }

    protected function getPublicIdFromFile(File $file): string
    {
        /** @var CloudinaryPathService $cloudinaryPathService */
        $cloudinaryPathService = GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $file->getStorage(),
        );
        return $cloudinaryPathService->computeCloudinaryPublicId($file->getIdentifier());
    }

    protected function getSearchApi(): SearchApi
    {
        return CloudinaryApiUtility::getCloudinary($this->storage)->searchApi();
    }

    protected function getAdminApi(): AdminApi
    {
        return CloudinaryApiUtility::getCloudinary($this->storage)->adminApi();
    }

}
