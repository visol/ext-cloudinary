<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryApiCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected string $help = '
Usage: ./vendor/bin/typo3 cloudinary:api [0-9 storage id]

Examples

# Query by public id
typo3 cloudinary:api [0-9] --publicId=\'foo-bar\'

# Query with an expression
# @see https://cloudinary.com/documentation/search_api
typo3 cloudinary:api [0-9] --expression=\'public_id:foo-bar\'
typo3 cloudinary:api [0-9] --expression=\'resource_type:image AND tags=kitten AND uploaded_at>1d\'
    ' ;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($input->getArgument('storage'));
    }

    protected function configure()
    {
        $message = 'Interact with cloudinary API';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption('publicId', '', InputOption::VALUE_OPTIONAL, 'Cloudinary public id', '')
            ->addOption('expression', '', InputOption::VALUE_OPTIONAL, 'Cloudinary search expression', '')
            ->addArgument('storage', InputArgument::REQUIRED, 'Storage identifier')
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

        $this->initializeApi();
        try {

            if ($publicId) {
                $resource = $this->getApi()->resource($publicId);
                $this->log(var_export((array)$resource, true));
            } elseif ($expression) {
                $search = new \Cloudinary\Search();
                $search->expression($expression);
                $response = $search->execute();
                $this->log(var_export((array)$response, true));
            } else {
                $this->log('Nothing to do...');
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

        return Command::SUCCESS;
    }

    protected function getApi()
    {
        // create a new instance upon each API call to avoid driver confusion
        return new Api();
    }

    protected function initializeApi(): void
    {
        CloudinaryApiUtility::initializeByConfiguration($this->storage->getConfiguration());
    }
}
