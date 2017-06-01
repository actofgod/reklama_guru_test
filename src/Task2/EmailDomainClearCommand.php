<?php
/**
 * Created by PhpStorm.
 * User: nyaah
 * Date: 01.06.17
 * Time: 14:28
 */

namespace ReklamaGuru\Task2;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EmailDomainClearCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('clear')
            ->setDescription('Очищает полученные до этого счётчики доменов')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $processor = $this->getProcessor($output);
        $processor->setSaveToDatabase(true)->clearPreviousState();
    }
}