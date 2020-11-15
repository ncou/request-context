<?php

declare(strict_types=1);

namespace Chiron\RequestContext\Bag;

use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;

/**
 * Access to server parameters of request, every requested key will be normalized for better
 * usability.
 */
final class FileBag extends ParameterBag
{
    /**
     * Enforce the return type hint as UploadeFile object.
     */
    public function get(string $key, $default = null): ?UploadedFileInterface
    {
        if($default !== null && ! $default instanceof UploadedFileInterface) {
            throw new InvalidArgumentException('Default value should be null or a "UploadedFileInterface" instance.');
        }

        return parent::get($key, $default);
    }
}
