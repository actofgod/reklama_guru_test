<?php

namespace ReklamaGuru\Task2;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    /** @var EmailDomainProcessor */
    private $processor;

    protected function getProcessor(OutputInterface $output) : EmailDomainProcessor
    {
        if ($this->processor === null) {
            $configFileName = __DIR__ . '/../../resources/task_2/config.json';
            if (!file_exists($configFileName)) {
                throw new \RuntimeException('Config file "'.$configFileName.'" not exists');
            }
            $config = json_decode(file_get_contents($configFileName), true);
            if (empty($config)) {
                throw new \RuntimeException('Invalid config file "'.$configFileName.'": '.json_last_error_msg());
            }
            $user = $config['pdo']['username'];
            unset($config['pdo']['username']);
            $pass = $config['pdo']['password'];
            unset($config['pdo']['password']);
            $dsnParts = [];
            foreach ($config['pdo'] as $key => $value) {
                $dsnParts[] = $key . '=' . $value;
            }
            $pdo = new \PDO('mysql:' . implode(';', $dsnParts), $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $this->processor = new EmailDomainProcessor($pdo, $output);
        }
        return $this->processor;
    }
}