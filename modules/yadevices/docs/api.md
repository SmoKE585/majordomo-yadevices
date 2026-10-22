---
title: API модуля
audience: user, agent
category: module-yadevices
tags: api, callapi, notify, tts, команды, громкость
description: Вызовы API Я.Устройства: станция, команда, текст, громкость, notify-группы.
order: 60
---

# API модуля

Endpoint: `/api/module/yadevices`.

## Отправка на станцию

```php
callAPI('/api/module/yadevices', 'GET', array(
    'station' => 3,
    'command' => 'text',
    'data' => 'Раз-два-три',
));
```

Параметры:

| Параметр | Значение |
| --- | --- |
| `station` | ID станции в таблице `yastations`. |
| `command` | `text`, `command`, `dialog`, `play`, `stop`, `prev`, `next`, `volumeUp`, `volumeDown`, `setVolume`, `audio`, `video`. |
| `data` | Текст, команда или ссылка. |
| `volume` | Громкость для `setVolume` (0.1–10). |
| `cloud` | `1` — принудительно через облако. |

## Обратная совместимость

Вместо `command` + `data` можно передать `say`:

```php
callAPI('/api/module/yadevices', 'GET', array(
    'station' => 3,
    'say' => 'Старое имя параметра',
));
```

## Notify-группы

Группы настраиваются на странице **Настройки**. Отправка:

```php
callAPI('/api/module/yadevices', 'GET', array(
    'group' => 'all',
    'command' => 'text',
    'data' => 'Текст для всех колонок группы',
));
```

Старый параметр `notify` тоже поддерживается:

```php
callAPI('/api/module/yadevices', 'GET', array(
    'notify' => 'all',
    'command' => 'text',
    'data' => 'Текст',
));
```

## Громкость

```php
callAPI('/api/module/yadevices', 'GET', array(
    'station' => 3,
    'command' => 'setVolume',
    'data' => 6,
));
```

Локальный режим принимает 0.1–10. Для ТВ-станций значение автоматически умножается на 10.

## Эффекты речи

```php
callAPI('/api/module/yadevices', 'GET', array(
    'station' => 3,
    'command' => 'text',
    'data' => '<speaker effect="megaphone">Ехал Грека через реку',
));
```

Поддерживаются эффекты, звуки, голоса и шёпот из документации Яндекс Диалогов.

## Сценарии

```php
callAPI('/api/module/yadevices', 'GET', array(
    'runscenario' => 'SCENARIO_ID',
));
```

## Служебные вызовы

| Параметр | Действие |
| --- | --- |
| `getonline=1` | Обновить онлайн-статус станций. |
| `refresh=1` | Обновить список устройств (только из админки). |

## Ответ

API не возвращает JSON-структуру: команда ставится в очередь или отправляется напрямую. Результат и ошибки пишутся в журнал модуля (`DebMes`).
