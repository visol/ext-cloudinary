<?php

namespace Visol\Cloudinary\Exceptions;

class InvalidResourceUrlException extends \Exception
{
    public function __construct(
        protected string $resourceUrl,
        protected string $cloudBaseUrl,
        int $code
    ) {
        $message = sprintf('Resource URL "%s" ist not in cloud base URL "%s"', $resourceUrl, $cloudBaseUrl);
        parent::__construct($message, $code);
    }
}