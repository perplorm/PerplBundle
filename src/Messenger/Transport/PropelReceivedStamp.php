<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Messenger\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Marks an envelope received from the Propel transport with the id of its row.
 */
final class PropelReceivedStamp implements NonSendableStampInterface
{
    /**
     * @param int $id
     */
    public function __construct(public readonly int $id)
    {
    }
}
