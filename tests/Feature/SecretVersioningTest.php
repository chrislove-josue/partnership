<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecretVersioningTest extends TestCase
{
    private function envExample(): string
    {
        return (string) file_get_contents(base_path('.env.example'));
    }

    public function test_environment_example_disables_debug_and_does_not_contain_secrets(): void
    {
        $example = $this->envExample();

        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $example);
        $this->assertDoesNotMatchRegularExpression('/^APP_DEBUG=true$/m', $example);
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $example);
        $this->assertMatchesRegularExpression('/^MAIL_USERNAME=$/m', $example);
        $this->assertMatchesRegularExpression('/^MAIL_PASSWORD=$/m', $example);
        $this->assertMatchesRegularExpression('/^STAFF_DEFAULT_PASSWORD=$/m', $example);
        $this->assertDoesNotMatchRegularExpression('/^APP_KEY=base64:/m', $example);
    }

    public function test_env_copy_is_ignored_and_no_longer_tracked(): void
    {
        $this->assertStringContainsString(
            '.env copy',
            (string) file_get_contents(base_path('.gitignore')),
        );

        $process = new Process(['git', 'ls-files'], base_path());
        $process->mustRun();

        $tracked = array_values(array_filter(
            explode("\n", str_replace("\r", '', $process->getOutput())),
        ));

        $this->assertNotContains('.env copy', $tracked);
    }
}
