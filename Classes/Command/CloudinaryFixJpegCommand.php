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

/**
 * Class CloudinaryFixJpegCommand
 */
class CloudinaryFixJpegCommand extends AbstractCloudinaryCommand
{
    /**
     * @var ResourceStorage
     */
    protected $targetStorage;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->targetStorage = $resourceFactory->getStorageObject($input->getArgument('target'));
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $message = 'After "moving" files you should fix the jpeg extension. Consult README.md for more info.';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption('yes', 'y', InputOption::VALUE_OPTIONAL, 'Accept everything by default', false)
            ->addArgument('target', InputArgument::REQUIRED, 'Target storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:fix [0-9]');
    }

    /**
     * Move file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->targetStorage)) {
            $this->log('Look out! target storage is not of type "cloudinary"');
            return 1;
        }

        $files = $this->getJpegFiles();

        if (count($files) === 0) {
            $this->log('No files found, no work for me!');
            return 0;
        }

        $this->log('I will update %s files by replacing "jpeg" to "jpg" in various fields in storage "%s" (%s)', [
            count($files),
            $this->targetStorage->getUid(),
            $this->targetStorage->getName(),
        ]);

        // A chance to the user to confirm the action
        if ($input->getOption('yes') === false) {
            $response = $this->io->confirm('Shall I continue?', true);

            if (!$response) {
                $this->log('Script aborted');
                return 0;
            }
        }

        // Handle extension case
        $connection = $this->getConnection();
        $query =
            "
UPDATE sys_file 
SET extension = REPLACE(extension, 'jpeg', 'jpg'), 
    identifier = REPLACE(identifier, '.jpeg', '.jpg'),  
    name = REPLACE(name, '.jpeg', '.jpg') 
WHERE storage = " . $this->targetStorage->getUid();

        $connection->query($query)->execute();

        return 0;
    }

    /**
     * @return array
     */
    protected function getJpegFiles(): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where($query->expr()->eq('storage', $this->targetStorage->getUid()), $query->expr()->eq('extension', $query->expr()->literal('jpeg')));

        return $query->execute()->fetchAllAssociative();
    }
}
