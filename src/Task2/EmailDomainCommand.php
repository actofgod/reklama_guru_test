<?php

namespace ReklamaGuru\Task2;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EmailDomainCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('process')
            ->setDescription(
                'Выгребает из таблицы пользователей список доменных имен, использующихся в адресах электронной почты'
            )
            ->setHelp(
                'Парсит таблицу пользователей и вытягивает доменные имена, использующиеся в адресах электронной почты'
            )
            ->addOption(
                'clear-previous-state', 'c', InputOption::VALUE_NONE, 'Очистить ли полученные до этого данные'
            )
            ->addOption(
                'save-to-database', 'd', InputOption::VALUE_NONE, 'Сохранять ли полученные данные в базу данных'
            )
            ->addOption(
                'batch-size', 's', InputOption::VALUE_REQUIRED, 'Количество строк, выгребаемых единовременно', 2000
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $processor = $this->getProcessor($output);

        $processor->setBatchSize($input->getOption('batch-size'));
        
        if ($input->getOption('save-to-database')) {
            $processor->setSaveToDatabase(true);
        }

        if ($input->getOption('clear-previous-state')) {
            $output->writeln('Clear previous state', OutputInterface::VERBOSITY_VERBOSE);
            $processor->clearPreviousState();
        }

        $processor->run();
    }
}