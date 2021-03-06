# reklama_guru_test
Тестовое задание для reklama.guru

### Задача 1

_Вам поступила задача:_

_Необходимо создать общую ленту новостей для пользователей с возможностью оценки постов в ленте._

_Лента должна иметь фильтр по категориям. Любой пользователь может поставить "лайк" или отменить его. Необходимо
предусмотреть возможность просмотра списка всех оценивших пост пользователей. Ограничение на размер хранения контента
одного поста - 243 байта._

_Предложите структуру базы данных MySQL, позволяющую реализовать данную задачу. Напишите запросы для выборки и
обновления контента. Обоснуйте выбор индексов._

Результат лежит тут resources/task_1/task_1.sql

### Задача 2

_Имеется таблица пользователей:_

```SQL
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `gender` tinyint(2) NOT NULL,
  `email` varchar(1024) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB;
```

_В таблице более 100 млн записей, и она находится под нагрузкой в production
(идут запросы на добавление / изменение / удаление)._

_В поле email может быть от одного до нескольких перечисленных через запятую адресов. Может быть пусто._

_Напишите скрипт, который выведет список представленных в таблице почтовых доменов с количеством пользователей по
каждому домену._

В папке resources/task_1/ лежат DDL SQL запросы, реализовал скрипт test2.php для выборки доменов. Так как в процессе
обработки данных может что-нибудь пойти не так (например отвалится соединение с базой данных), поэтому скрипт
сохраняет свое текущее состояние в файл. Настройки соединения скрипта с базой данных лежат в
resources/task_1/config.json

Параметры запуска скрипта:
```bash
# если до этого скрипт запускался и нужно очистить результаты его работы
php test2.php clear

# запускает процесс агрегации доменов
php test2.php process

# с помощью флага --clear-previous-state можно запустить агрегацию заново, удалив перед этим старые данных
# с помощью флага --save-to-database можно заставить скрипт писать результаты в базу данных, а не в файл
# с помощью параметра --batch-size устанавливается размер выгребаемых из БД строк за один заход
# с помощью флага --aggregate-user-duplicates можно заставить скрипт считать вхождение одного и того же доменного имени
# в адрес электроннной почты у одного пользователя как несколько вхождений
```

По умолчанию результат работы скрипт складывает в файл состояния var/data/email-domain-processor-state.dat

С помощью лока pid файла процесса в папке var/run/email-domain-processor-state.pid предусмотрена возможность блока
параллельного запуска нескольких скриптов.

Скрипт после каждой итерации обработки строк обновляет результаты и сохраняет состояние в файл, поэтому, при повторном
запуске без флага --clear-previous-state скрипт не начнёт процесс с самого начала, а продолжит с места падения или
завершения.

Если передан флаг --aggregate-user-duplicates, то если у пользователя через запятую перечислены несколько адресов с
одним и тем же доменом и даже одинаковых адресов, каждый из имейлов увеличит счётчик у домена.

### Задача 3

_Дан текстовый файл размером 2ГБ. Напишите класс, реализующий интерфейс SeekableIterator, для чтения данного файла._

Класс итератора тут: src/Task3/FileByteIterator.php, позволяет осуществлять доступ к файлу как к коллекции байтов.
Тест кейс для класса тут: tests/Task3/FileByteIterator.php

Конструктор принимает первым параметром имя файла, во втором параметре можно указать, какие хотим получать из файла -
символы или коды этих символов:
```php
$iterator = new FileByteIterator('test.txt', FileByteIterator::MODE_CHAR);
foreach ($iterator as $char) {
    echo $char; // будет выводить последовательно содержимое файла на экран
}

$iterator = new FileByteIterator('test.txt', FileByteIterator::MODE_CODE);
foreach ($iterator as $char) {
    echo $char . ' '; // будет выводить последовательно коды символов на экран
}
```

Добавил ещё один класс итератора src/Task3/FileLineIterator.php, позволяющий пробегать по файлу не по байтово, а по
строчно. В качестве аргументов конструктор итератора принимает имя файла, разделитель строк и размер внутреннего буфера.
Из минусов - при работе с этим итератором файл будет читаться как минимум два раза, плюс при рандомном поиске при
вызове метода seek классу придётся считать весь файл до положения в которое перемещаемся, чтобы построить индекс
предыдущих строк.
