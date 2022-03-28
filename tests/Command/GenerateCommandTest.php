<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Command\GenerateCommand;

class GenerateCommandTest extends KernelTestCase
{
    public function testExecute()
    {
        $_ENV['ROOT_DIR'] = __DIR__ . '/../../';
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->add(new GenerateCommand);

        /** @var GenerateCommand $command */
        $command = $application->find('generate');
        $commandTester = new CommandTester($command);

        $inputInclud = 'included';
        $inputCountries = ['BY'];
        $commandTester->setInputs(['yes', 'yes', implode(',', $inputCountries), $inputInclud]);

        $commandTester->execute(['command' => $command->getName()]);
        $commandTester->assertCommandIsSuccessful();

        $file = $command->getOutputFile($inputInclud, $inputCountries);
        $this->assertFileExists($file);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[BY] - Belarus', $output);
        $this->assertMatchesRegularExpression('/\[([^0]|[1-9][0-9]+)\] lines/', $output);
        $this->assertStringContainsString($file, $output);
        $this->assertStringContainsString('Duration', $output);
    }
}
