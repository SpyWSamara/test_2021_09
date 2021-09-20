# Задача 1

```
написать скрипт, который будет периодически дергаться кроном (периодичность указать) и искать заказы, которые висят в начальном статусе больше двух дней.
Если такие заказы есть, отправить e-mail администратору сайта, в письме указать номера найденных заказов
```

Для удобства (что бы была возможность запуска как агента) код был организован в класс `Local\StuckOrderForAdmin`. Через
агент можно настроить врем и периодичность запуска через административную панель Битрикс.

## Автозагрузка

Пример добавления в автозагрузку средствами Битрикс. Файлы расположен по пути `/local/src/Local/StuckOrderForAdmin.php`.
Тогда в `init.php` указываем:

```injectablephp
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'Local\\StuckOrderForAdmin' => 'StuckOrderForAdmin'
]);
```

В более новых версиях можно подключить namespace:

```injectablephp
\Bitrix\Main\Loader::registerNamespace(
    'Local', 
    \Bitrix\Main\Loader::getDocumentRoot().'/local/src/Local'
);
```

Или через стороннюю систему автозагрузки (например: `composer`).

## Почтовое событие

Почтовое событие можно создать по
интсрукции https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=41&LESSON_ID=3534.

В почтовом событии будут доступны следующие дополнительные поля:

- `ORDER_ID_LIST` - список ID заказов
- `ORDER_NUMBER_LIST` - список номеров заказов
- `ORDER_COUNT` - общее количество заказов

Для описания события:

```
#ORDER_ID_LIST# - список ID заказов
#ORDER_NUMBER_LIST# - список номеров заказов
#ORDER_COUNT# - общее количество заказов
```

## Запуск

### Классический скрипт

Для запуска как классического скрипта, через крон потребуется создать "бутстрап" скрипт:

```injectablephp
<?php

// Заменить на реальный путь к корню сайта!
$_SERVER["DOCUMENT_ROOT"] = '/path/to/your/bitrix/document/root/folder/';
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define("BX_CRONTAB", true);
define('BX_WITH_ON_AFTER_EPILOG', true);
define('BX_NO_ACCELERATOR_RESET', true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

\Local\StuckOrderForAdmin::exec('STUCK_ORDERS');
```

И добавить его в `crontab`, выполнив в консоли пользователя:

```shell
crontab -e
```

Откроется редактор по умолчанию с содержимом `crontab`. Вписать нужный период (`man crontab`):

```shell
# 1. Minute [0,59]
# 2. Hour [0,23]
# 3. Day of the month [1,31]
# 4. Month of the year [1,12]
# 5. Day of the week ([0,6] with 0=Sunday)

# В примере скрипт будет запускаться каждый день с понедельника по пятницу в 9 утра
0 9 * * 1-5 php -f /path/to/your/bootstrap/file.php
```

### Через агенты Битрикс

Нужно добавить новый агент по инструкции https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2290.

Либо выполнить код в php-консоли Битрикс (`/bitrix/admin/php_command_line.php`), который добавит агента с отправкой
собятия `STUCK_ORDERS` с 9 утра завтра каждый день:

```injectablephp
\Local\StuckOrderForAdmin::registerAgent('STUCK_ORDERS', 24 * 3600, new \DateTime('tomorrow 9am'));
```
