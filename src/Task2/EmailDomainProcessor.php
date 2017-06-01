<?php

namespace ReklamaGuru\Task2;

use Symfony\Component\Console\Output\OutputInterface;

class EmailDomainProcessor
{
    /** @var \PDO Инстанс соединения с базой данных */
    private $pdo;

    /** @var string Имя файла, в который записывается информация о уже обработанных строках */
    private $stateFileName;

    /** @var string Имя файла блокировки, для того, чтоб нельзя было запустить два процесса одновременно */
    private $lockFileName;

    /** @var OutputInterface Поток для вывода логов */
    private $logOutput;

    /** @var \PDOStatement Подготовленный запрос для выборки данных */
    private $selectStatement;

    /** @var int Текущий максимальный айди пользователя, которого уже обработали */
    private $maxId;

    /** @var int Размер выборки данных при обработке */
    private $batchSize;

    /** @var bool Сохранять ли результат в базу данных */
    private $saveToDatabase;

    /** @var array Счетчики доменов, используются, если не сохраняем результат в БД */
    private $domainsCounters;

    /** @var bool Увеличивать ли счётчик, если у пользователя несколько адресов с одним доменом */
    private $aggregateDuplicates;

    public function __construct(\PDO $pdo, OutputInterface $output)
    {
        $this->maxId = -1;
        $this->stateFileName = 'var/data/email-domain-processor-state.dat';
        $this->lockFileName = 'var/run/email-domain-processor-state.pid';
        $this->batchSize = 1000;
        $this->saveToDatabase = false;
        $this->aggregateDuplicates = false;
        $this->domainsCounters = [];

        $this->pdo = $pdo;
        $this->logOutput = $output;
    }

    /**
     * Устанавливает количество выгребаемый строк из таблицы пользователей за раз
     * @param int $value Количество выбираемых пользователей за один заход
     * @return EmailDomainProcessor Инстанс текущего объекта
     */
    public function setBatchSize(int $value) : self
    {
        $this->batchSize = $value;
        return $this;
    }

    /**
     * Устанавливает флах сохранения результатов в базу данных
     * @param bool $value True если храним результат в базе данных, false если в файле состояния
     * @return EmailDomainProcessor Инстанс текущего объекта
     */
    public function setSaveToDatabase(bool $value) : self
    {
        $this->saveToDatabase = $value;
        return $this;
    }

    /**
     * Устанавливает флаг увеличения счётчика для домена, если у пользователя несколько адресов в одном домене
     * @param bool $value Увеличивать ли счётчик, если у пользователя несколько адресов с одним доменом
     * @return EmailDomainProcessor Инстанс текущего объекта
     */
    public function setAggregateDuplicates(bool $value) : self
    {
        $this->aggregateDuplicates = $value;
        return $this;
    }

    /**
     * Запускает процесс агрегации доменных имён
     */
    public function run()
    {
        if (file_exists($this->lockFileName)) {
            $lock = @fopen($this->lockFileName, 'r+');
        } else {
            $lock = @fopen($this->lockFileName, 'a+');
        }
        if (!$lock) {
            throw new \RuntimeException('Failed to open lock file "'.$this->lockFileName.'"');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Failed to lock file "'.$this->lockFileName.'"');
        }
        fwrite($lock, getmypid());
        fflush($lock);
        try {
            $this->loadCurrentState();
            $this->logOutput->writeln('Start fetching data from userId#' . $this->maxId);
            if ($this->saveToDatabase) {
                $this->prepareDatabase();
            }
            do {
                $this->logOutput->writeln('Fetch block from userId#' . $this->maxId, OutputInterface::VERBOSITY_VERBOSE);
                $stmt = $this->getSelectStatement($this->maxId);
                if ($stmt->execute()) {
                    $count = $stmt->rowCount();
                    $this->logOutput->writeln($count . ' records found', OutputInterface::VERBOSITY_VERBOSE);
                    if ($count > 0) {
                        $domains = $this->fetchDomainsFromResultSet($stmt);
                        $stmt->closeCursor();
                        $this->logOutput->writeln(count($domains) . ' domains found', OutputInterface::VERBOSITY_VERBOSE);
                        $this->updateEmailDomainsCounters($domains);
                    }
                } else {
                    throw new \RuntimeException('Failed to execute query '.$stmt->queryString.': '.$stmt->errorInfo()[2]);
                }
            } while ($count > 0);
            if (!$this->saveToDatabase) {
                $this->displayResult();
            }
        } catch (\Throwable $e) {
            $this->logOutput->writeln('Exception: ' . $e->getMessage(), OutputInterface::VERBOSITY_QUIET);
            $this->logOutput->writeln(
                'Exception trace: ' . PHP_EOL . $e->getTraceAsString(),
                OutputInterface::VERBOSITY_QUIET
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            unlink($this->lockFileName);
        }
    }

    /**
     * Выводит  в поток вывода список доменов со счётчиками
     */
    protected function displayResult()
    {
        foreach ($this->domainsCounters as $domain => $counter) {
            $this->logOutput->writeln($domain . ': ' . $counter);
        }
    }

    /**
     * Возвращает стейтмент SQL запроса для выборки данных из таблицы пользователей
     * @param int $maxId Максимальный айди пользователя, которого уже обрабатывали
     * @return \PDOStatement Стейтмент SQL запроса
     */
    protected function getSelectStatement(int $maxId) : \PDOStatement
    {
        if ($this->selectStatement === null) {
            $sql = 'SELECT `id`, `email` FROM `users` WHERE `id` > :maxId ORDER BY `id` ASC LIMIT :limit';
            $this->selectStatement = $this->pdo->prepare($sql);
        }
        $this->selectStatement->bindValue(':maxId', $maxId, \PDO::PARAM_INT);
        $this->selectStatement->bindValue(':limit', $this->batchSize, \PDO::PARAM_INT);
        return $this->selectStatement;
    }

    /**
     * Пробегается по результату выборки из таблицы пользователей и возвращает список доменов со счётчиками
     * @param \PDOStatement $stmt Стейтмент SQL запроса с выбранными пользователями
     * @return array Ассоциативный массив ключами которого являются домены, значениями количество вхождений
     */
    protected function fetchDomainsFromResultSet(\PDOStatement $stmt)
    {
        $domains = [];
        while (($row = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
            $this->maxId = (int) $row[0];
            if (empty($row[1])) {
                continue;
            }
            $emails = explode(',', trim($row[1]));
            $userDomains = [];
            foreach ($emails as $email) {
                $parts = explode('@', $email, 2);
                if (count($parts) != 2 || trim($parts[0]) == '') {
                    $this->logOutput->writeln(
                        'Invalid user email "'.$email.'", user id: ' . $row[0],
                        OutputInterface::VERBOSITY_NORMAL
                    );
                    continue;
                }
                $domain = trim($parts[1]);
                if (empty($domain)) {
                    $this->logOutput->writeln(
                        'Invalid user email "'.$email.'", user id: ' . $row[0],
                        OutputInterface::VERBOSITY_NORMAL
                    );
                    continue;
                }
                if ($this->aggregateDuplicates || !array_key_exists($domain, $userDomains)) {
                    if (!array_key_exists($domain, $domains)) {
                        $domains[$domain] = 1;
                    } else {
                        $domains[$domain] += 1;
                    }
                    $userDomains[$domain] = true;
                }
            }
        }
        return $domains;
    }

    /**
     * Обновляет информацию о количестве доменов
     * @param array $counters Ассоциативный массив ключами которого являются домены, значениями количество вхождений
     */
    protected function updateEmailDomainsCounters(array $counters)
    {
        if (!empty($counters)) {
            if ($this->saveToDatabase) {
                $updateRows = [];
                foreach ($counters as $domain => $counter) {
                    $updateRows[] = '(' . $this->pdo->quote($domain) . ',' . $counter . ')';
                }
                $insertSql = 'INSERT INTO `_tmp_email_domains_counters` (`domain`, `counter`) VALUES '
                    . implode(',', $updateRows) . '  ON DUPLICATE KEY UPDATE `counter` = `counter` + VALUES(`counter`)';
                $this->pdo->exec($insertSql);
            } else {
                foreach ($counters as $domain => $counter) {
                    if (array_key_exists($domain, $this->domainsCounters)) {
                        $this->domainsCounters[$domain] += $counter;
                    } else {
                        $this->domainsCounters[$domain] = $counter;
                    }
                }
            }
        }
        $this->saveCurrentState();
    }

    /**
     * Очищает предыдущее состояние скрипта
     * @return EmailDomainProcessor Стейтмент SQL запроса
     */
    public function clearPreviousState() : self
    {
        if (file_exists($this->stateFileName)) {
            unlink($this->stateFileName);
        }
        if ($this->saveToDatabase) {
            $this->pdo->exec('TRUNCATE TABLE `_tmp_email_domains_counters`');
        }
        return $this;
    }

    /**
     * Загружает состояние из файла
     * До этого скрипт мог упасть по какой-либо причине, состояние позволяет не начинать процесс поиска заново
     */
    protected function loadCurrentState()
    {
        if (!file_exists($this->stateFileName)) {
            return;
        }
        $fd = fopen($this->stateFileName, 'r');
        $data = fread($fd, 1024);
        fclose($fd);
        $tmp = json_decode($data, true);
        $this->maxId = $tmp['maxId'];
        if (!$this->saveToDatabase && !empty($tmp['counters']) && is_array($tmp['counters'])) {
            $this->domainsCounters = $tmp['counters'];
        }
    }

    /**
     * Сохраняет текущее состояние процесса агрегации доменных имен в файл
     */
    protected function saveCurrentState()
    {
        if (file_exists($this->stateFileName)) {
            $fd = fopen($this->stateFileName, 'a+');
        } else {
            $fd = fopen($this->stateFileName, 'w');
        }
        if (!$fd) {
            throw new \RuntimeException('Failed to open state file "'.$this->stateFileName.'"');
        }
        flock($fd, LOCK_EX);
        ftruncate($fd, 0);
        fseek($fd, 0, SEEK_SET);
        fwrite($fd, json_encode([
            'maxId'    => $this->maxId,
            'counters' => $this->domainsCounters,
        ]));
        fflush($fd);
        flock($fd, LOCK_UN);
        fclose($fd);
    }

    /**
     * Создаёт таблицу результатов в базе данных, если она ещё не создана
     */
    private function prepareDatabase()
    {
        $query = <<<SQL
CREATE TABLE IF NOT EXISTS `_tmp_email_domains_counters` (
  `domain`  VARCHAR(256) NOT NULL,
  `counter` INTEGER      NOT NULL,
  PRIMARY KEY (`domain`)
) ENGINE = Memory DEFAULT CHARACTER SET 'UTF8'
SQL;
        $this->pdo->exec($query);
    }
}