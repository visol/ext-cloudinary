<?php

namespace Sinso\Cloudinary\Domain\Repository;

class ResponsiveBreakpointsRepository {

    public function findByPublicIdAndOptions($publicId, $options) {
        $optionsHash = $this->calculateHashFromOptions($options);
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'tx_cloudinary_responsivebreakpoints', '`public_id` = "' . $publicId . '" AND `options_hash` = "' . $optionsHash . '"');

        return $row;
    }

    public function save($publicId, $options, $breakpoints) {
        $optionsHash = $this->calculateHashFromOptions($options);
        $insert = [
            'public_id' => $publicId,
            'options_hash' => $optionsHash,
            'breakpoints' => $breakpoints,
        ];

        $this->getDatabaseConnection()->exec_INSERTquery('tx_cloudinary_responsivebreakpoints', $insert);
    }


    protected function calculateHashFromOptions($options) {
        return sha1(json_encode($options));
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