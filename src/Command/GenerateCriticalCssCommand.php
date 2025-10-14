<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\BaseCommand\Traits\EnvTrait;


#[AsCommand(name: 'GenerateCriticalCss')]
class GenerateCriticalCssCommand extends AbstractBaseCommand
{
    use EnvTrait;

    public function __construct(protected ProjectDir $projectDir, protected ParameterBagInterface $parameters)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this->fxTitle('Extracting critical CSS from live pages...');

        $scriptPath = $this->projectDir->getProjectDir('assets') . 'extract-critical-css.mjs';
        $this->fxInfo("Running ##$scriptPath##...");

        if( !file_exists($scriptPath) ) {
            $this->endWithError("##$scriptPath## not found...");
        }

        $process =
            new Process(
                ['node', $scriptPath, $this->parameters->get('siteUrl') ], $this->projectDir->getProjectDir(),
                $this->getEnv(), null, 300
            );

        try {
            $process->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            $this->fxOk('Critical CSS generated successfully!');

            $this->fxTitle('Generated files:');
            $criticalDir = $this->projectDir->getProjectDir('/public/build/critical');
            if (is_dir($criticalDir)) {
                $files = scandir($criticalDir);
                $cssFiles = array_filter($files, fn($f) => str_ends_with($f, '.css'));

                foreach ($cssFiles as $file) {
                    $size = filesize($criticalDir . '/' . $file);
                    $this->io->writeln(sprintf('  • %s (%s KB)', $file, number_format($size / 1024, 2)));
                }
            }

            return $this->endWithSuccess();

        } catch (ProcessFailedException $ex) {
            $this->endWithError('Critical CSS generation failed!' . PHP_EOL . $ex->getMessage() );
        }
    }
}
