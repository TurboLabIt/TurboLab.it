<?php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


#[AsCommand(
    name: 'SitemapGenerator',
    description: 'Generate the XML Sitemap files',
    aliases: ['sitemap']
)]
class SitemapGeneratorCommand extends AbstractBaseCommand
{
    protected bool $allowDryRunOpt = true;


    public function __construct(protected EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        return $this->endWithSuccess();
    }
}
