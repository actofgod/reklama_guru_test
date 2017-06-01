<?php

namespace Tests\ReklamaGuru\Task3;

use PHPUnit\Framework\TestCase;
use ReklamaGuru\Task3\FileByteIterator;

class FileByteIteratorTest extends TestCase
{
    /** @var string Временная папка, в которой создаются файлы для тестирования */
    const TMP_FILE_DIRECTORY = __DIR__ . '/../../var/tmp';

    /**
     * @param string $sourceString
     * @param bool $codeMode
     * @return FileByteIterator
     */
    protected function getTestInstance(string $sourceString, bool $codeMode = false) : FileByteIterator
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
        return new FileByteIterator($fileName, $codeMode ? FileByteIterator::MODE_CODE : FileByteIterator::MODE_CHAR);
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testCurrent(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        for ($i = 0; $i < strlen($sourceString); $i++) {
            $char = substr($sourceString, $i, 1);
            if ($codeMode) {
                $char = ord($char);
            }
            self::assertEquals($char, $instance->current());
            self::assertEquals($char, $instance->current());
            $instance->next();
        }
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testNextAndKey(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        for ($i = 0; $i < strlen($sourceString); $i++) {
            self::assertEquals($i, $instance->key());
            self::assertEquals($i, $instance->key());
            $instance->next();
        }
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testValid(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        for ($i = 0; $i < strlen($sourceString); $i++) {
            self::assertTrue($instance->valid());
            $instance->next();
        }
        self::assertFalse($instance->valid());

        $instance->rewind();
        self::assertTrue($instance->valid());
        $instance->seek(strlen($sourceString) - 1);
        self::assertTrue($instance->valid());
        $instance->next();
        self::assertFalse($instance->valid());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testRewind(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        self::assertEquals(0, $instance->key());
        $expected = substr($sourceString, 0, 1);
        if ($codeMode) {
            $expected = ord($expected);
        }
        self::assertEquals($expected, $instance->current());

        $instance->rewind();
        self::assertEquals(0, $instance->key());
        $expected = substr($sourceString, 0, 1);
        if ($codeMode) {
            $expected = ord($expected);
        }
        self::assertEquals($expected, $instance->current());

        $instance->next();
        self::assertNotEquals(0, $instance->key());
        $expected = substr($sourceString, 1, 1);
        if ($codeMode) {
            $expected = ord($expected);
        }
        self::assertEquals($expected, $instance->current());

        $instance->rewind();
        self::assertEquals(0, $instance->key());
        $expected = substr($sourceString, 0, 1);
        if ($codeMode) {
            $expected = ord($expected);
        }
        self::assertEquals($expected, $instance->current());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testSeek(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        $indexes = range(0, strlen($sourceString) - 1);
        shuffle($indexes);
        foreach ($indexes as $index) {
            $instance->seek($index);
            self::assertEquals($index, $instance->key());
            $expected = substr($sourceString, $index, 1);
            if ($codeMode) {
                $expected = ord($expected);
            }
            self::assertEquals($expected, $instance->current());
        }
        $instance->seek(strlen($sourceString));
        self::assertFalse($instance->valid());
        try {
            $instance->seek(-1);
        } catch (\InvalidArgumentException $e) {
            try {
                $instance->seek('test');
            } catch (\InvalidArgumentException $e) {
                try {
                    $instance->seek(strlen($sourceString) + 1);
                } catch (\InvalidArgumentException $e) {
                    return;
                }
                self::fail('Exception not thrown with large index');
            }
            self::fail('Exception not thrown with invalid index');
        }
        self::fail('Exception not thrown with negative index');
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testIterate(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        $result = '';
        foreach ($instance as $char) {
            if ($codeMode) {
                $result .= chr($char);
            } else {
                $result .= $char;
            }
        }
        self::assertEquals($sourceString, $result);

        $instance->rewind();

        $result = '';
        foreach ($instance as $char) {
            if ($codeMode) {
                $result .= chr($char);
            } else {
                $result .= $char;
            }
        }
        self::assertEquals($sourceString, $result);
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testIsClosed(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);
        self::assertFalse($instance->isClosed());
        $instance->close();
        self::assertTrue($instance->isClosed());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testClose(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        $instance->close();
        self::assertTrue($instance->isClosed());
        try {
            $instance->valid();
        } catch (\BadMethodCallException $e) {
            return;
        }
        self::fail('Exception not thrown after closing');
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testLength(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);
        self::assertEquals(strlen($sourceString), $instance->length());
    }

    public function testEmptyFile()
    {
        $instance = $this->getTestInstance('', true);

        self::assertNull($instance->current());
        self::assertFalse($instance->valid());
        self::assertNull($instance->key());

        $instance->next();
        self::assertNull($instance->current());
        self::assertFalse($instance->valid());
        self::assertNull($instance->key());

        $instance->rewind();
        self::assertNull($instance->current());
        self::assertFalse($instance->valid());
        self::assertNull($instance->key());

        $instance->seek(0);
        self::assertNull($instance->current());
        self::assertFalse($instance->valid());
        self::assertNull($instance->key());
    }

    /**
     * @dataProvider dataProvider
     * @param string $sourceString
     * @param bool $codeMode
     */
    public function testClone(string $sourceString, bool $codeMode)
    {
        $instance = $this->getTestInstance($sourceString, $codeMode);

        $another = clone $instance;
        self::assertNotSame($instance, $another);
        self::assertEquals($instance->current(), $another->current());
        self::assertEquals($instance->key(), $another->key());

        $instance->close();
        self::assertTrue($instance->isClosed());
        self::assertFalse($another->isClosed());
        self::assertTrue($another->valid());
        $pos = strlen($sourceString) - 1;
        $another->seek($pos);

        $instance = clone $another;
        self::assertFalse($instance->isClosed());
        self::assertEquals($instance->current(), $another->current());
        self::assertEquals($instance->key(), $another->key());
    }

    /**
     * Тестируем использование памяти
     */
    public function testLargeFile()
    {
        $fileSize = 2; // 2MB
        // файл размером 2 гигабайта создаётся довольно долго (больше 5 минут)
        // $fileSize = 1024 * 2; // 2GB

        $fileName = $this->createLargeFile($fileSize);
        $instance = new FileByteIterator($fileName);

        for ($i = 0; $i < 10; $i++) {
            $expected = random_int(0, $fileSize * 1024 * 1024 / 4);
            $index = $expected * 4;
            $instance->seek($index);
            $tmp = $instance->current(); $instance->next();
            $tmp .= $instance->current(); $instance->next();
            $tmp .= $instance->current(); $instance->next();
            $tmp .= $instance->current(); $instance->next();
            $res = unpack('Lvalue', $tmp);
            self::assertArrayHasKey('value', $res);
            self::assertEquals($expected, $res['value']);
        }
    }

    /**
     * @return array
     */
    public function dataProvider() : array
    {
        $records = [
            ["0123456789", true],
            ["0123456789", false],
        ];
        $byteString = '';
        for ($i = 0; $i < 32; $i++) {
            $byteString .= chr($i);
        }
        $records[] = [$byteString, true];
        $records[] = [$byteString, false];
        return $records;
    }

    /**
     * Создаёт файл с указанным размером в мегабайтах, заполненный последовательно целыми четырёх байтовыми значениями
     * Если файл уже имеется, просто возращает имя файла
     * @param int $fileSizeInMb Размер файла в мегабайтах
     * @return string Имя созданного файла
     */
    private function createLargeFile(int $fileSizeInMb) : string
    {
        if ($fileSizeInMb > 1024 * 4) {
            throw new \InvalidArgumentException('Maximum file size is ' . (1024 * 4));
        }
        $fileName = self::TMP_FILE_DIRECTORY . DIRECTORY_SEPARATOR . $fileSizeInMb . '.bin';
        if (file_exists($fileName)) {
            return $fileName;
        }
        $fd = fopen($fileName, 'w');

        $block = '';
        $dWords = 1024 * 1024 * $fileSizeInMb / 4;
        for ($i = 0; $i < $dWords; $i++) {
            $block .= pack('L', $i);
            if (strlen($block) > 1024 * 1024) {
                fwrite($fd, $block);
                $block = '';
            }
        }
        if (strlen($block) > 0) {
            fwrite($fd, $block);
        }
        fclose($fd);
        return $fileName;
    }
}
