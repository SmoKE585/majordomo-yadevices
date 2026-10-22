# Я.Устройства для MajorDoMo

Модуль интеграции **Яндекс Станций** и устройств умного дома с MajorDoMo. Внутреннее имя пакета — `yadevices`, визуальное название в админке — **Я.Устройства**.

## Что умеет

- загружает станции и устройства из аккаунта Яндекса;
- управляет колонками локально через Glagol и через облако Яндекса;
- озвучивает события: локальный TTS и облачный Cloud TTS;
- создаёт и удаляет будильники, связывает их со свойствами объектов;
- задаёт вопросы Алисе и записывает ответ в свойство;
- связывает умения устройств с объектами, свойствами и методами MajorDoMo;
- отправляет текст и команды через API, включая notify-группы.

## Установка

1. Скопируйте содержимое репозитория в корень MajorDoMo, сохранив каталоги `modules/`, `templates/`, `public/assets/`.
2. Откройте **Устройства → Я.Устройства**. Модуль установится автоматически и создаст таблицы `yastations`, `yadevices`, `yadevices_capabilities`.
3. Перейдите в **Авторизация** и войдите в Яндекс.
4. Нажмите **Обновить** — станции и устройства загрузятся из аккаунта.

Подробно: [настройка](modules/yadevices/docs/setup.md), [авторизация](modules/yadevices/docs/auth.md).

## Разделы админки

| Раздел | Адрес |
| --- | --- |
| Станции | `/panel/yadevices.html` |
| Устройства | `/panel/yadevices/devices.html` |
| Сценарии | `/panel/yadevices/scenarios.html` |
| Настройки | `/panel/yadevices/settings.html` |
| Авторизация | `/panel/yadevices/auth.html` |
| О модуле | `/panel/yadevices/about.html` |

## Совместимость

- PHP 8.1+;
- старые таблицы, API `/api/module/yadevices` и плеер `/module/yadevices.html?station=ID` сохранены;
- название пакета, класса, таблиц и URL не менялись — только визуальная подпись;
- документация лежит в `modules/yadevices/docs/` и появляется в базе знаний, пока модуль установлен.

## Документация

- [Обзор](modules/yadevices/docs/README.md)
- [Настройка](modules/yadevices/docs/setup.md)
- [Авторизация](modules/yadevices/docs/auth.md)
- [Станции](modules/yadevices/docs/stations.md)
- [Устройства](modules/yadevices/docs/devices.md)
- [Сценарии](modules/yadevices/docs/scenarios.md)
- [API](modules/yadevices/docs/api.md)
- [Диагностика](modules/yadevices/docs/troubleshooting.md)

## Ссылки

- [Обсуждение на форуме](https://mjdm.ru/forum/viewtopic.php?f=5&t=7359)
- [Репозиторий](https://github.com/SmoKE585/majordomo-yadevices)
- [Документация Яндекса](https://yandex.ru/dev/dialogs/alice/doc/ru/)
