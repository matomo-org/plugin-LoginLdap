<?php

namespace Piwik\Plugins\LoginLdap\tests\System\Output;

use Symfony\Component\Console\Output\NullOutput;

class TestOutput extends NullOutput
{
    public $output = [];

    public function writeln($messages, int $options = self::OUTPUT_NORMAL)
    {
        $this->output[] = $messages;
    }
}
