<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

class MimeTypeUtility
{

    static public function guessMimeType(string $fileExtension): string
    {

        $mimeTypes = [

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'csv' => 'text/comma-separated-values',
            'ics' => 'text/calendar',
            'log' => 'text/x-log',
            'zsh' => 'text/x-scriptzsh',
            'rtx' => 'text/richtext',
            'srt' => 'text/srt',
            'vcf' => 'text/x-vcard',
            'vtt' => 'text/vtt',
            'xsl' => 'text/xsl',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'json' => 'text/json',
            'cdr' => 'image/cdr',

            // audio
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'aac' => 'audio/x-acc',
            'ac3' => 'audio/ac3',
            'aif' => 'audio/aiff',
            'au' => 'audio/x-au',
            'flac' => 'audio/x-flac',
            'm4a' => 'audio/x-m4a',
            'mid' => 'audio/midi',
            'ra' => 'audio/x-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'wma' => 'audio/x-ms-wma',

            // video
            'youtube' => 'video/youtube',
            'vimeo' => 'video/vimeo',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'ogg' => 'video/ogg',
            'rv' => 'video/vnd.rn-realvideo',
            'webm' => 'video/webm',
            'wmv' => 'video/x-ms-wmv',
            '3g2' => 'video/3gpp2',
            '3gp' => 'video/3gp',
            'avi' => 'video/avi',
            'f4v' => 'video/x-f4v',
            'flv' => 'video/x-flv',
            'jp2' => 'video/mj2',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',

            // ms office
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // other
            '7zip' => 'application/x-compressed',
            'cpt' => 'application/mac-compactpro',
            'dcr' => 'application/x-director',
            'dvi' => 'application/x-dvi',
            'gpg' => 'application/gpg-keys',
            'gtar' => 'application/x-gtar',
            'gzip' => 'application/x-gzip',
            'kml' => 'application/vnd.google-earth.kml+xml',
            'kmz' => 'application/vnd.google-earth.kmz',
            'm4u' => 'application/vnd.mpegurl',
            'mif' => 'application/vnd.mif',
            'p10' => 'application/pkcs10',
            'p12' => 'application/x-pkcs12',
            'p7a' => 'application/x-pkcs7-signature',
            'p7c' => 'application/pkcs7-mime',
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/pkcs7-signature',
            'pem' => 'application/x-pem-file',
            'pgp' => 'application/pgp',
            'sit' => 'application/x-stuffit',
            'smil' => 'application/smil',
            'tar' => 'application/x-tar',
            'tgz' => 'application/x-gzip-compressed',
            'vlc' => 'application/videolan',
            'wbxml' => 'application/wbxml',
            'wmlc' => 'application/wmlc',
            'xhtml' => 'application/xhtml+xml',
            'xl' => 'application/excel',
            'xspf' => 'application/xspf+xml',
            'z' => 'application/x-compress',

        ];

        return array_key_exists($fileExtension, $mimeTypes)
            ? $mimeTypes[$fileExtension]
            : 'application/octet-stream';
    }
}
