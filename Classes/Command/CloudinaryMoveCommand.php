<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Doctrine\DBAL\Driver\Connection;
use Visol\Cloudinary\Utility\CloudinaryPathUtility;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class CloudinaryMoveCommand
 */
class CloudinaryMoveCommand extends Command
{

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var ResourceStorage
     */
    protected $sourceStorage;
    /**
     * @var ResourceStorage
     */
    protected $targetStorage;

    /**
     * @var string
     */
    protected $tableName = 'sys_file';

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $message = 'Move bunch of images from a local storage to a cloudinary storage.';
        $message .= ' CAUTIOUS!';
        $message .= ' 1. Moving means: we are "manually" uploading a file';
        $message .= ' to the Cloudinary storage and "manually" deleting the one from the local storage';
        $message .= ' Finally we are changing the `sys_file.storage value` to the cloudinary storage.';
        $message .= ' Consequently, the file uid will be kept.';
        $message .= ' 2. The FE might break. Migrate your code that use VH `<f:image />` to `<c:cloudinaryImage />`';
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
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source storage identifier'
            )->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target storage identifier'
            )
            ->setHelp(
                'Usage: ./vendor/bin/typo3 cloudinary:move 1 2'
            );
    }

    /**
     * Initializes the command after the input has been bound and before the input
     * is validated.
     *
     * @see InputInterface::bind()
     * @see InputInterface::validate()
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->options = $input->getOptions();

        $this->sourceStorage = ResourceFactory::getInstance()->getStorageObject(
            $input->getArgument('source')
        );
        $this->targetStorage = ResourceFactory::getInstance()->getStorageObject(
            $input->getArgument('target')
        );

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();
    }

    /**
     * Move file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $files = $this->getSourceFiles();

        $this->log(
            'Moving %s files from storage "%s" (%s) to "%s" (%s)',
            [
                count($files),
                $this->sourceStorage->getName(),
                $this->sourceStorage->getUid(),
                $this->targetStorage->getName(),
                $this->targetStorage->getUid(),
            ],
            'info'
        );

        $counter = 0;
        foreach ($files as $file) {
            $fileObject = ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(
                $this->sourceStorage->getUid(),
                $file['identifier']
            );

            $fileNameAndAbsolutePath = $this->getAbsolutePath($fileObject);
            if (file_exists($fileNameAndAbsolutePath)) {
                $this->log('Moving %s', [$file['identifier']]);

                // Upload the file storage
                $isUploaded = $this->cloudinaryUploadFile($fileObject);

                if ($isUploaded) {

                    // Update the storage uid
                    $isUpdated = $this->updateFile($fileObject,
                        [
                            'storage' => $this->targetStorage->getUid()
                        ]
                    );

                    if ($isUpdated) {
                        // Delete the file form the local storage
                        unlink($fileNameAndAbsolutePath);
                    }
                }

                $counter++;
            }
        }
        $this->log(LF);
        $this->log('Number of files moved: %s', [$counter]);
    }

    /**
     * @param File $fileObject
     * @return string
     */
    protected function getAbsolutePath(File $fileObject): string
    {
        // Compute the absolute file name of the file to move
        $configuration = $this->sourceStorage->getConfiguration();
        $fileRelativePath = rtrim($configuration['basePath'], '/') . $fileObject->getIdentifier();
        return GeneralUtility::getFileAbsFileName($fileRelativePath);
    }

    /**
     * @param File $fileObject
     * @return bool
     */
    protected function cloudinaryUploadFile(File $fileObject): bool
    {

        $publicId = PathUtility::basename(
            CloudinaryPathUtility::computeCloudinaryPublicId($fileObject->getName())
        );

        $options = [
            'public_id' => $publicId,
            'folder' => CloudinaryPathUtility::computeCloudinaryPath(
                $fileObject->getParentFolder()->getIdentifier()
            ),
            'overwrite' => true,
        ];

        // Upload the file
        $resource = \Cloudinary\Uploader::upload(
            $this->getAbsolutePath($fileObject),
            $options
        );
        return !empty($resource);
    }

    /**
     * @return void
     */
    protected function initializeApi()
    {
        // Compute the absolute file name of the file to move
        $configuration = $this->targetStorage->getConfiguration();
        \Cloudinary::config(
            [
                'cloud_name' => $configuration['cloudName'],
                'api_key' => $configuration['apiKey'],
                'api_secret' => $configuration['apiSecret'],
                'timeout' => $configuration['timeout'],
                'secure' => true
            ]
        );
    }
    /**
     * @return object|QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable($this->tableName);
    }

    /**
     * @return object|Connection
     */
    protected function getConnection(): Connection
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getConnectionForTable($this->tableName);
    }

    /**
     * @return array
     */
    protected function getSourceFiles(): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->sourceStorage->getUid()),
                $query->expr()->eq('missing', 0),
                $query->expr()->eq('type', File::FILETYPE_IMAGE)
            );

        return $query->execute()->fetchAll();
    }

    /**
     * @param File $fileObject
     * @param array $values
     * @return int
     */
    protected function updateFile(File $fileObject, array $values): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->tableName,
            $values,
            [
                'uid' => $fileObject->getUid(),
            ]
        );
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param string $severity
     */
    protected function log(string $message, array $arguments = [], $severity = '')
    {
        if (!$this->isSilent()) {
            if ($severity) {
                $message = '<' . $severity . '>' . $message . '</' . $severity . '>';
            }
            $this->io->writeln(
                vsprintf($message, $arguments)
            );
        }
    }

    /**
     * @return bool
     */
    protected function isSilent(): bool
    {
        return $this->options['silent'] !== false;
    }
}
