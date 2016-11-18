<?php

namespace Sinso\Cloudinary\Domain\Repository;

class MediaRepository {

	public function findByPublicId($publicId) {
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'tx_cloudinary_media', '`public_id` = "' . $publicId . '"');

        return $row;
    }


    public function findByFilename($filename) {
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'tx_cloudinary_media', '`filename` = "' . $filename . '"');

        return $row;
    }

    public function save($filename, $publicId) {
        $insert = [
            'filename' => $filename,
            'public_id' => $publicId,
        ];
        $this->getDatabaseConnection()->exec_INSERTquery('tx_cloudinary_media', $insert);
    }


    /**
     * Return DatabaseConnection
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection() {
        return $GLOBALS['TYPO3_DB'];
    }
}