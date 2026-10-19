<?php

namespace Propel\Bundle\PropelBundle\Tests\Messenger\Transport;

final class TestMessage
{
    public function __construct(
        public string $content,
    ) {
    }
}
