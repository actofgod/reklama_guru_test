<?php

namespace ReklamaGuru\Task3;

class FileLineIterator implements \SeekableIterator
{
    const DEFAULT_BUFFER_SIZE = 1024;
    const DEFAULT_DELIMITER = PHP_EOL;

    /** @var resource Файловый дескриптор */
    private $fileDescriptor;

    /** @var string Имя файла с которым работает итератор */
    private $sourceFileName;

    /** @var string|null Значение строки, на которую в данный момент указывает итератор */
    private $currentString;

    /** @var int Текущий индекс строки итератора */
    private $currentOffset;

    /** @var int Размер буфера чтения, используемого для обработки файла при построении индекса */
    private $bufferSize;

    /** @var int[] Индекс, массив со смещениями относительно начала файла */
    private $index;

    /** @var bool True если индекс уже был целиком построен, false если нет */
    private $isIndexComplete;

    /** @var string Строка, используемая в качестве разделителя строк */
    private $delimiter;

    /**
     * Контруктор, инициализирует итератор, открывает файл на чтение, блочит файл на запись
     * @param string $fileName Имя файла, с которым будем работать
     * @param string $delimiter Символ или строка, используемый в качестве разделителя строк
     * @param int $bufferSize Размер буфера чтения из файла для построения индекса, должен быть больше длины
     * разделителя минимум в два раза
     * @throws \InvalidArgumentException Генерируется если файл не удалось открыть
     * @throws \InvalidArgumentException Генерируется если длина буфера слишком мала
     */
    public function __construct(string $fileName, $delimiter = self::DEFAULT_DELIMITER, $bufferSize = self::DEFAULT_BUFFER_SIZE)
    {
        if ($bufferSize < strlen($delimiter) * 2) {
            throw new \InvalidArgumentException('Increase buffer size');
        }

        $this->openFileDescriptor($fileName);

        $this->sourceFileName = $fileName;
        $this->bufferSize = $bufferSize;
        $this->currentOffset = 0;
        $this->delimiter = $delimiter;
        $this->index = [0];
        $this->isIndexComplete = false;

        $this->readLine();
    }

    /**
     * Закрывает файл
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Возвращает текущий элемент
     * @return string|int|null Текущий символ или его код
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function current()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        if (!$this->valid()) {
            return null;
        }
        return $this->currentString;
    }

    /**
     * Переводит смещение внутри итератора на одину строку дальше от начала файла
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function next()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        $this->currentOffset++;
        $this->readLine();
    }

    /**
     * Возвращает текущий номер строки
     * @return int|null Номер строки или null если достигнут конец файла
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function key()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        return $this->valid() ? $this->currentOffset : null;
    }

    /**
     * Проверяет, достигнут ли итератором конец файла
     * @return bool True если до конца файла ещё не дошли, false если конец файла достигнут
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function valid()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        return $this->currentOffset < count($this->index) - 1;
    }

    /**
     * Перемещает указатель итератора на начало файла
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function rewind()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        $this->currentOffset = 0;
        $this->readLine();
    }

    /**
     * Перемещает указатель итератора на указанную позицию относительно начала файла
     * @param int $position Номер строки, которую хотим получить
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     * @throws \InvalidArgumentException Генерируется если было передано не число, либо если позиция отрицательная
     */
    public function seek($position)
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        if (!is_numeric($position) || $position < 0) {
            throw new \InvalidArgumentException('Invalid position value: ' . $position);
        }
        $this->currentOffset = $position;
        $this->readLine();
    }

    /**
     * Закрывает файловый декскриптор текущего итератора, делает итератор невалидным
     */
    public function close()
    {
        if ($this->fileDescriptor) {
            flock($this->fileDescriptor, LOCK_UN);
            fclose($this->fileDescriptor);
            $this->fileDescriptor = null;
            $this->index = [0];
            $this->currentOffset = 0;
            $this->currentString = null;
        }
    }

    /**
     * Проверяет, закрыт ли файловый дескриптор итератора
     * @return bool True если файл закрыт и с итератором работать нельзя, false если файл открыт
     */
    public function isClosed() : bool
    {
        return $this->fileDescriptor === null;
    }

    /**
     * При клонировании итератора в копии заново открывает файловый дескриптор
     */
    public function __clone()
    {
        if ($this->fileDescriptor !== null) {
            $this->openFileDescriptor($this->sourceFileName);
        }
    }

    /**
     * При сериализации не сохраняем файловый дескриптор
     * @return array Список полей для сериализации
     */
    public function __sleep()
    {
        return [
            'sourceFileName',
            'currentOffset',
            'bufferSize',
            'index',
            'isIndexComplete',
            'delimiter',
        ];
    }

    /**
     * При десериализации открываем новый файловый дескриптор и получаем строку по текущему смещению
     */
    public function __wakeup()
    {
        $this->openFileDescriptor($this->sourceFileName);
        $this->readLine();
    }

    /**
     * Открывает файловый дескриптор итератора
     * @param string $fileName Имя открываемого файла
     * @throws \InvalidArgumentException Генерируется если открыть файл не удалось
     * @throws \RuntimeException Генерируется если при установке лока на файл произошла ошибка
     */
    private function openFileDescriptor(string $fileName)
    {
        $fd = @fopen($fileName, 'rb');
        if (!$fd) {
            throw new \InvalidArgumentException('Failed to open file "'.$fileName.'"');
        }
        if (!flock($fd, LOCK_SH)) {
            throw new \RuntimeException('Failed to lock file "'.$fileName.'"');
        }
        $this->fileDescriptor = $fd;
    }

    /**
     * Читает из файла струку в текущей позиции итератора
     * @throws \RuntimeException Генерируется если до конча файла ещё не добрались, но при чтении произошла ошибка
     */
    private function readLine()
    {
        if (!$this->isIndexComplete || $this->currentOffset < count($this->index) - 1) {
            while ($this->currentOffset >= count($this->index) - 2) {
                $this->addIndexEntries();
                if ($this->isIndexComplete) {
                    break;
                }
            }
            if ($this->currentOffset < count($this->index) - 2) {
                $offset = $this->index[$this->currentOffset];
                $length = $this->index[$this->currentOffset + 1] - $offset - strlen($this->delimiter);
                if ($length > 0) {
                    fseek($this->fileDescriptor, $offset);
                    $this->currentString = fread($this->fileDescriptor, $length);
                } else {
                    $this->currentString = '';
                }
            } elseif ($this->currentOffset == count($this->index) - 2) {
                $offset = $this->index[$this->currentOffset];
                $length = $this->index[$this->currentOffset + 1] - $offset;
                if ($length > 0) {
                    fseek($this->fileDescriptor, $offset);
                    $this->currentString = fread($this->fileDescriptor, $length);
                } else {
                    $this->currentString = '';
                }
            } else {
                $this->currentString = null;
            }
        }
    }

    /**
     * Считывает из файла буфер начиная от последней проиндексированной строки, добавляет в индекс найденные строки.
     * Добавляет как минимум ещё одно смещение в индекс, либо, если достигнут конец файла, помечает, что индекс уже
     * построен
     */
    private function addIndexEntries()
    {
        if ($this->isIndexComplete) {
            return;
        }
        $pos = false;
        $last = end($this->index);
        fseek($this->fileDescriptor, $last, SEEK_SET);
        while ($pos === false && !$this->isIndexComplete) {
            $buffer = fread($this->fileDescriptor, $this->bufferSize);
            if ($buffer === false) {
                fseek($this->fileDescriptor, 0, SEEK_END);
                $this->index[] = ftell($this->fileDescriptor);
                $this->isIndexComplete = true;
            } else {
                $pos = strpos($buffer, $this->delimiter);
                if ($pos !== false) {
                    while ($pos !== false) {
                        $this->index[] = $last + $pos + strlen($this->delimiter);
                        $pos = strpos($buffer, $this->delimiter, $pos + strlen($this->delimiter));
                    }
                    $pos = true;
                } elseif (strlen($this->delimiter) > 1) {
                    fseek($this->fileDescriptor, -strlen($this->delimiter), SEEK_CUR);
                }
                if (strlen($buffer) < $this->bufferSize) {
                    fseek($this->fileDescriptor, 0, SEEK_END);
                    $this->index[] = ftell($this->fileDescriptor);
                    $this->isIndexComplete = true;
                }
            }
        }
    }
}
