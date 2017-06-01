<?php

namespace ReklamaGuru\Task3;

/**
 * Класс итератора по содержимому файла как по коллекции однобайтовых символов
 *
 * @package ReklamaGuru\Task3
 */
class FileByteIterator implements \SeekableIterator
{
    const MODE_CHAR = 0;
    const MODE_CODE = 1;

    /** @var resource Файловый дескриптор */
    private $fileDescriptor;

    /** @var string Имя файла с которым работает итератор */
    private $sourceFileName;

    /** @var string|null Значение символа, на который в данный момент указывает итератор */
    private $currentCharacter;

    /** @var int Текущее смещение относительно начала файла */
    private $currentOffset;

    /** @var int Размер файла в байтах */
    private $fileSize;

    /** @var int Определяет что возвращать в качестве значения */
    private $mode;

    /**
     * Контруктор, инициализирует итератор, открывает файл на чтение, блочит файл на запись
     * @param string $fileName Имя файла, с которым будем работать
     * @param int $mode Что возвращать при итерировании в качестве значения. Если передана константа
     * FileByteIterator::MODE_CHAR то возвращаться будет символ в текущей позиции курсора, если же
     * FileByteIterator::MODE_CODE будет возвращаться код символа
     * @throws \InvalidArgumentException Генерируется, если файл не удалось открыть
     */
    public function __construct(string $fileName, int $mode = self::MODE_CHAR)
    {
        $this->openFileDescriptor($fileName);

        $this->sourceFileName = $fileName;
        $this->fileSize = filesize($fileName);
        $this->currentOffset = 0;
        $this->mode = $mode;

        $this->readByte();
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
        return $this->mode === self::MODE_CODE ? ord($this->currentCharacter) : $this->currentCharacter;
    }

    /**
     * Переводит смещение внутри итератора на один байт дальше от начала файла
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function next()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        $this->currentOffset++;
        $this->readByte();
    }

    /**
     * Возвращает текущее смещение итератора относительно начала файла
     * @return int|null Смещение относительно начала файла или null если достигнут конец файла
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     */
    public function key()
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        return $this->currentOffset < $this->fileSize ? $this->currentOffset : null;
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
        return $this->currentOffset < $this->fileSize;
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
        fseek($this->fileDescriptor, 0, SEEK_SET);
        $this->currentOffset = 0;
        $this->readByte();
    }

    /**
     * Перемещает указатель итератора на указанную позицию относительно начала файла
     * @param int $position Позиция относительно начала файла
     * @throws \BadMethodCallException Генерируется если файл не был открыт, или в процессе работы был закрыт
     * @throws \InvalidArgumentException Генерируется если было передано не число, либо если позиция выходит за пределы
     * файла (исключая конец файла, переход на позицию filesize разрешён)
     */
    public function seek($position)
    {
        if ($this->isClosed()) {
            throw new \BadMethodCallException('File descriptor error');
        }
        if (!is_numeric($position) || $position < 0 || $position > $this->fileSize) {
            throw new \InvalidArgumentException('Invalid position value: ' . $position);
        }
        fseek($this->fileDescriptor, (int)$position, SEEK_SET);
        $this->currentOffset = $position;
        $this->readByte();
    }

    /**
     * При клонировании итератора в копии заново открывает файловый дескриптор
     */
    public function __clone()
    {
        if ($this->fileDescriptor !== null) {
            $this->openFileDescriptor($this->sourceFileName);
            fseek($this->fileDescriptor, $this->currentOffset + 1, SEEK_SET);
        }
    }

    /**
     * При сериализации запоминаем только имя файла, смещение и тип итератора
     * @return array Список полей для сериализации
     */
    public function __sleep()
    {
        return [
            'sourceFileName',
            'currentOffset',
            'mode'
        ];
    }

    /**
     * При десериализации открываем новый файловый дескриптор, вычисляем размер файла и получаем символ в текущей
     * позиции
     */
    public function __wakeup()
    {
        $this->openFileDescriptor($this->sourceFileName);
        $this->fileSize = filesize($this->sourceFileName);
        fseek($this->fileDescriptor, $this->currentOffset, SEEK_SET);
        $this->readByte();
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
            $this->fileSize = 0;
            $this->currentOffset = 0;
            $this->currentCharacter = null;
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
     * Возвращает размер файла в байтах, если файл закрыт, возвращает ноль
     * @return int Размер файла в байтах
     */
    public function length() : int
    {
        return $this->fileSize;
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
     * Читает байт из файла в текущей позиции итератора
     * @throws \RuntimeException Генерируется если до конча файла ещё не добрались, но при чтении произошла ошибка
     */
    private function readByte()
    {
        if ($this->currentOffset < $this->fileSize) {
            $this->currentCharacter = fgetc($this->fileDescriptor);
            if ($this->currentCharacter === false) {
                $this->currentCharacter = null;
                $this->fileSize = 0;
                throw new \RuntimeException('Failed to read character from file "'.$this->sourceFileName.'"');
            }
        }
    }
}
