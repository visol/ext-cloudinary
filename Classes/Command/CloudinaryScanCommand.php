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
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryScanService;

class CloudinaryScanCommand extends AbstractCloudinaryCommand
{
    protected ResourceStorage $storage;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($input->getArgument('storage'));
    }

    protected function configure(): void
    {
        $message = 'Scan and warm up a cloudinary storage.';
        $this->setDescription($message)
            ->addOption('silent', 's', InputOption::VALUE_OPTIONAL, 'Mute output as much as possible', false)
            ->addOption(
                'expression',
                '',
                InputOption::VALUE_OPTIONAL,
                'Expression used by the cloudinary search api (e.g --expression="folder=fileadmin/* AND NOT folder=fileadmin/_processed_/*',
                false
            )
            ->addArgument('storage', InputArgument::REQUIRED, 'Storage identifier')
            ->setHelp('Usage: ./vendor/bin/typo3 cloudinary:scan [0-9]');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->checkDriverType($this->storage)) {
            $this->log('Look out! Storage is not of type "cloudinary"');
            return Command::INVALID;
        }

        $logFile = Environment::getVarPath() . '/log/cloudinary.log';
        $this->log('Hint! Look at the log to get more insight:');
        $this->log('tail -f ' . $logFile);
        $this->log();

        /** @var string $expression */
        $expression = $input->getOption('expression');

        $result = $this->getCloudinaryScanService()
            ->setAdditionalExpression($expression)
            ->scan();

        $numberOfFiles = $result['created'] + $result['updated'] - $result['deleted'];
        if ($numberOfFiles !== $result['total']) {
            $this->warning(
                'There is a problem with the number of files counted. %s !== %s. It should be fixed in the next scan',
                [$numberOfFiles, $result['total']],
            );
        }

        $message = "Statistics for files: \n\n- created: %s\n- updated: %s\n- total: %s\n- deleted: %s\n- failed: %s";
        $message .= "\n\nStatistics for folders: \n\n- deleted: %s";
        $this->success($message, [
            $result['created'],
            $result['updated'],
            $result['total'],
            $result['deleted'],
            $result['failed'],
            $result['folder_deleted'],
        ]);

        return Command::SUCCESS;
    }

    protected function getCloudinaryScanService(): CloudinaryScanService
    {
        return GeneralUtility::makeInstance(CloudinaryScanService::class, $this->storage, $this->io);
    }
}
