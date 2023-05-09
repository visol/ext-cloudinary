<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Tests\Acceptance\FolderTestSuite;
use Visol\Cloudinary\Tests\Acceptance\FileTestSuite;

// Quick autoloader for now...
spl_autoload_register(
    function ($className) {
        if (strpos($className, 'Visol\Cloudinary') === 0) {
            $fileNameAndPath = str_replace(
                [
                    'Visol\Cloudinary\Tests\Acceptance\\',
                    '\\',
                ],
                [
                    '',
                    '/',
                ],
                $className
            );

            $fileNameAndPath = __DIR__ . '/../../Tests/Acceptance/' . $fileNameAndPath . '.php';
            require_once $fileNameAndPath;
        }
    }
);

class CloudinaryAcceptanceTestCommand extends AbstractCloudinaryCommand
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $message = 'Run a suite of Acceptance Tests';
        $this
            ->setDescription(
                $message
            )
            ->addArgument(
                'api-configuration',
                InputArgument::REQUIRED,
                'The API configuration'
            )
            ->setHelp(
                'Usage: ./vendor/bin/typo3 cloudinary:tests'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // We should dynamically inject the configuration. For now use an existing driver
        [$couldName, $apiKey, $apiSecret] = GeneralUtility::trimExplode(':', $input->getArgument('api-configuration'));
        if (!$couldName || !$apiKey || !$apiSecret) {
            // Everything must be defined!
            $message = 'API configuration is incomplete. Format should be "cld-name:1234:abcd".' . LF . LF;
            $message .= '"cld-name" is the name of the cloudinary bucket' . LF;
            $message .= '"12345" is the API key' . LF;
            $message .= '"abcd" is the API secret' . LF . LF;
            $message .= 'https://cloudinary.com/console' . LF;
            $message .= 'Strong advice! Take a free account to run the test suite';
            $this->error($message);
            return Command::INVALID;
        }

        $logFile = Environment::getVarPath() . '/log/cloudinary.log';
        $this->log('Hint! Look at the log to get more insight:');
        $this->log('tail -f ' . $logFile);
        $this->log();

        // Create a testing storage
        $storageId = $this->setUp($couldName, $apiKey, $apiSecret);
        if (!$storageId) {
            $this->error('Something went wrong. I could not create a testing storage');
            return Command::FAILURE;
        }

        // Test case for video file
        $testSuite = new FileTestSuite($storageId, $this->io);
        $testSuite->runTests();

        $testSuite = new FolderTestSuite($storageId, $this->io);
        $testSuite->runTests();

        $this->tearDown($storageId);

        return Command::SUCCESS;
    }

    protected function setUp(string $cloudName, string $apiKey, string $apiSecret): int
    {
        $values = [
            'name' => 'cloudinary-acceptance-tests',
            'driver' => CloudinaryDriver::DRIVER_TYPE,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 1,
            'is_online' => 1,
            'configuration' => sprintf(
                '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="cloudName">
                    <value index="vDEF">%s</value>
                </field>
                <field index="apiKey">
                    <value index="vDEF">%s</value>
                </field>
                <field index="apiSecret">
                    <value index="vDEF">%s</value>
                </field>
                <field index="basePath">
                    <value index="vDEF">acceptance-tests-1590756100</value>
                </field>
                <field index="timeout">
                    <value index="vDEF">60</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>',
                $cloudName,
                $apiKey,
                $apiSecret
            ),
        ];

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $db = $connectionPool->getConnectionForTable('sys_file_storage');
        $db->insert(
            'sys_file_storage',
            $values
        );
        return (int)$db->lastInsertId();
    }

    protected function tearDown(int $storageId)
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        // Remove the testing resource storage
        $db = $connectionPool->getConnectionForTable('sys_file_storage');
        $db->delete(
            'sys_file_storage',
            [
                'uid' => $storageId
            ]
        );

        // Remove all file records
        $db = $connectionPool->getConnectionForTable('sys_file');
        $db->delete(
            'sys_file',
            [
                'storage' => $storageId
            ]
        );

        // Remove all cache
        $db->truncate('cf_cloudinary');
        $db->truncate('cf_cloudinary_tags');
    }
}
