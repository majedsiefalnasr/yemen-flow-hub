<?php

namespace Tests\Unit\Configuration;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    public function test_artisan_boot_does_not_emit_deprecated_mysql_ssl_constant_warning(): void
    {
        $process = new Process([PHP_BINARY, 'artisan', 'about', '--only=environment'], base_path());
        $process->run();

        $this->assertStringNotContainsString(
            'PDO::MYSQL_ATTR_SSL_CA is deprecated',
            $process->getOutput().$process->getErrorOutput(),
        );
    }
}
