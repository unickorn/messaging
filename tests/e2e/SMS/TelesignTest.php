<?php

namespace Tests\E2E;

use Utopia\Messaging\Adapters\SMS\Telesign;
use Utopia\Messaging\Messages\SMS;

class TelesignTest extends Base
{
    /**
     * @throws \Exception
     */
    public function testSendSMS()
    {
        $this->markTestSkipped('Telesign requires support/sales call in order to enable bulk SMS');
    }
}
