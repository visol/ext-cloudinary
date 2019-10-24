<?php

namespace Sinso\Cloudinary\Domain\Repository;

/**
 * Class MediaRepository
 * @deprecated
 */
class MediaRepository {

	public function findOneByPublicId($publicId) {
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'tx_cloudinary_media', '`public_id_hash` = "' . sha1($publicId) . '"');

        return $row;
    }

    public function findByFilename($filename) {
        $rows = $this->getDatabaseConnection()->exec_SELECTgetRows('*', 'tx_cloudinary_media', '`filename_hash` = "' . sha1($filename) . '"');

        return $rows;
    }

    public function findOneByFilenameAndSha1($filename, $sha1) {
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'tx_cloudinary_media', '`filename_hash` = "' . sha1($filename) . '" AND `sha1` = "' . $sha1 . '"');

        return $row;
    }

    public function save($filename, $publicId, $sha1, $modification_date) {
        if ($this->findOneByPublicId($publicId)) {
	        $this->getDatabaseConnection()->exec_DELETEquery('tx_cloudinary_media', 'public_id_hash = "' . sha1($publicId) . '"');
	        $this->getDatabaseConnection()->exec_DELETEquery('tx_cloudinary_responsivebreakpoints', 'public_id_hash = "' . sha1($publicId) . '"');
        }

        $insert = [
            'filename' => $filename,
            'filename_hash' => sha1($filename),
            'public_id' => $publicId,
            'public_id_hash' => sha1($publicId),
            'sha1' => $sha1,
            'modification_date' => $modification_date,
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
