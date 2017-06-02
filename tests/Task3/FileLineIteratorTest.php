<?php

namespace Tests\ReklamaGuru\Task3;

use PHPUnit\Framework\TestCase;
use ReklamaGuru\Task3\FileLineIterator;

class FileLineIteratorTest extends TestCase
{
    /** @var string Временная папка, в которой создаются файлы для тестирования */
    const TMP_FILE_DIRECTORY = __DIR__ . '/../../var/tmp';

    /**
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     * @return FileLineIterator
     */
    protected function getTestInstance(string $sourceString, string $delimiter, int $bufferSize) : FileLineIterator
    {
        if (!file_exists(self::TMP_FILE_DIRECTORY)) {
            if (!mkdir(self::TMP_FILE_DIRECTORY)) {
                throw new \RuntimeException('Failed to create temp directory "'.self::TMP_FILE_DIRECTORY.'"');
            }
        }
        $fileName = self::TMP_FILE_DIRECTORY . DIRECTORY_SEPARATOR . md5($sourceString) . '.txt';
        if (!file_exists($fileName)) {
            file_put_contents($fileName, $sourceString);
        }
        return new FileLineIterator($fileName, $delimiter, $bufferSize);
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testCurrent(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $lines = explode($delimiter, $sourceString);
        for ($i = 0; $i < count($lines); $i++) {
            self::assertEquals($lines[$i], $instance->current());
            self::assertEquals($lines[$i], $instance->current());
            $instance->next();
        }
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testNextAndKey(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $lines = explode($delimiter, $sourceString);
        for ($i = 0; $i < count($lines); $i++) {
            self::assertEquals($i, $instance->key());
            self::assertEquals($i, $instance->key());
            $instance->next();
        }
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testValid(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $lines = explode($delimiter, $sourceString);
        for ($i = 0; $i < count($lines); $i++) {
            self::assertTrue($instance->valid());
            $instance->next();
        }
        self::assertFalse($instance->valid());

        $instance->rewind();
        self::assertTrue($instance->valid());
        $instance->seek(count($lines) - 1);
        self::assertTrue($instance->valid());
        $instance->next();
        self::assertFalse($instance->valid());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testRewind(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $lines = explode($delimiter, $sourceString);

        self::assertEquals(0, $instance->key());
        self::assertEquals($lines[0], $instance->current());

        $instance->rewind();
        self::assertEquals(0, $instance->key());
        self::assertEquals($lines[0], $instance->current());

        $instance->next();
        self::assertNotEquals(0, $instance->key());
        self::assertEquals($lines[1], $instance->current());

        $instance->rewind();
        self::assertEquals(0, $instance->key());
        self::assertEquals($lines[0], $instance->current());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testSeek(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $lines = explode($delimiter, $sourceString);
        $indexes = range(0, count($lines) - 1);
        shuffle($indexes);
        foreach ($indexes as $index) {
            $instance->seek($index);
            self::assertEquals($index, $instance->key());
            self::assertEquals($lines[$index], $instance->current());
        }
        $instance->seek(count($lines));
        self::assertFalse($instance->valid());
        try {
            $instance->seek(-1);
        } catch (\InvalidArgumentException $e) {
            try {
                $instance->seek('test');
            } catch (\InvalidArgumentException $e) {
                $instance->seek(count($lines) + 1);
                self::assertFalse($instance->valid());
                return;
            }
            self::fail('Exception not thrown with invalid index');
        }
        self::fail('Exception not thrown with negative index');
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testIterate(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $result = [];
        foreach ($instance as $line) {
            $result[] = $line;
        }
        self::assertEquals($sourceString, implode($delimiter, $result));

        $instance->rewind();

        $result = [];
        foreach ($instance as $line) {
            $result[] = $line;
        }
        self::assertEquals($sourceString, implode($delimiter, $result));
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testIsClosed(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);
        self::assertFalse($instance->isClosed());
        $instance->close();
        self::assertTrue($instance->isClosed());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testClose(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $instance->close();
        self::assertTrue($instance->isClosed());
        try {
            $instance->valid();
        } catch (\BadMethodCallException $e) {
            return;
        }
        self::fail('Exception not thrown after closing');
    }

    public function testEmptyFile()
    {
        $instance = $this->getTestInstance('', PHP_EOL, 1024);

        self::assertEquals('', $instance->current());
        self::assertTrue($instance->valid());
        self::assertEquals(0, $instance->key());

        $instance->next();
        self::assertNull($instance->current());
        self::assertFalse($instance->valid());
        self::assertNull($instance->key());

        $instance->rewind();
        self::assertEquals('', $instance->current());
        self::assertTrue($instance->valid());
        self::assertEquals(0, $instance->key());

        $instance->seek(0);
        self::assertEquals('', $instance->current());
        self::assertTrue($instance->valid());
        self::assertEquals(0, $instance->key());

        $instance->seek(1);
        self::assertNull($instance->current());
        self::assertFalse($instance->valid());
        self::assertNull($instance->key());
    }

    /**
     * Тестируем использование памяти
     */
    public function testLargeFile()
    {
        $fileSize = 10; // ~10MB
        $linesCount = 0;
        $fileName = $this->createLargeFile($fileSize, $linesCount);
        $instance = new FileLineIterator($fileName, PHP_EOL);
        for ($i = 0; $i < 10; $i++) {
            $expected = random_int(0, $linesCount);
            $instance->seek($expected);
            self::assertEquals($expected, $instance->current());
        }
        $instance->seek($linesCount);
        self::assertEquals($linesCount, $instance->current());

        $instance->seek(0);
        self::assertEquals('0', $instance->current());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testClone(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $another = clone $instance;
        self::assertNotSame($instance, $another);
        self::assertEquals($instance->current(), $another->current());
        self::assertEquals($instance->key(), $another->key());

        $instance->close();
        self::assertTrue($instance->isClosed());
        self::assertFalse($another->isClosed());
        self::assertTrue($another->valid());

        $lines = explode($delimiter, $sourceString);
        $pos = count($lines) - 1;
        $another->seek($pos);

        $instance = clone $another;
        self::assertFalse($instance->isClosed());
        self::assertEquals($instance->current(), $another->current());
        self::assertEquals($instance->key(), $another->key());

        $instance->next();
        self::assertNotEquals($instance->current(), $another->current());
        self::assertNotEquals($instance->key(), $another->key());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param string $delimiter
     * @param int $bufferSize
     */
    public function testSerializeUnserialize(string $sourceString, string $delimiter, int $bufferSize)
    {
        $instance = $this->getTestInstance($sourceString, $delimiter, $bufferSize);

        $instance->seek(4);
        $tmp = serialize($instance);
        $another = unserialize($tmp);

        for (;$instance->valid(); $instance->next()) {
            self::assertEquals($instance->key(), $another->key());
            self::assertEquals($instance->current(), $another->current());
            $another->next();
        }
        $instance->close();

        self::assertFalse($another->isClosed());
        $lines = explode($delimiter, $sourceString);
        foreach ($another as $key => $value) {
            self::assertEquals($lines[$key], $value);
        }
    }

    /**
     * @return array
     */
    public function dataProvider() : array
    {
        $records = [
            ["0,1,2,3,4,5,6,7,8,9", ',', 1024],
            ["0,1,2,3,4,5,6,7,8,9", ',', 5],
            ["0,\n1,\n2,\n3,\n4,\n5,\n6,\n7,\n8,\n9", ",\n", 1024],
            ["0,\n1,\n2,\n3,\n4,\n5,\n6,\n7,\n8,\n9", ",\n", 5],
        ];
        return $records;
    }

    /**
     * Создаёт файл с указанным размером в мегабайтах, заполненный последовательно целыми четырёх байтовыми значениями
     * Если файл уже имеется, просто возращает имя файла
     * @param int $fileSizeInMb Размер файла в мегабайтах
     * @param int $linesCount Количество строк, добавленных в файл
     * @return string Имя созданного файла
     */
    private function createLargeFile(int $fileSizeInMb, &$linesCount) : string
    {
        if ($fileSizeInMb > 1024 * 4) {
            throw new \InvalidArgumentException('Maximum file size is ' . (1024 * 4));
        }
        $fileName = self::TMP_FILE_DIRECTORY . DIRECTORY_SEPARATOR . $fileSizeInMb . '.numbers';
        if (file_exists($fileName)) {
            return $fileName;
        }
        $fd = fopen($fileName, 'w');

        $block = '';
        for ($i = 0, $j = 0; $i < $fileSizeInMb; $j++) {
            $block .= $j . PHP_EOL;
            if (strlen($block) > 1024 * 1024) {
                fwrite($fd, $block);
                $block = '';
                $i++;
                $linesCount = $j;
            }
        }
        fclose($fd);
        return $fileName;
    }
}