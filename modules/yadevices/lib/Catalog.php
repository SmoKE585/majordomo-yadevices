<?php
/**
 * YaDevices catalog.
 *
 * Static, side-effect free descriptions of the entities the module works with:
 * speaker platforms, Yandex device types, capabilities, TTS commands and auth
 * methods. The admin UI uses these labels instead of hard-coded Russian strings
 * in templates, and the legacy public API keeps the old method names.
 *
 * @package MajorDoMo\Modules\YaDevices
 */

final class YaDevicesCatalog
{
    public const VERSION = '2.0.0';

    /** @var array<string,string>|null */
    private static ?array $iconMap = null;

    /**
     * Platforms that can accept commands over the local Glagol WebSocket.
     *
     * @return array<int,string>
     */
    public static function localPlatforms(): array
    {
        return array(
            'yandexmini', 'yandexmicro', 'yandexstation', 'yandexstation_2', 'yandexmini_2',
            'jbl_link_portable', 'yandexmidi', 'cucumber', 'plum', 'bergamot', 'chiron',
            'pickle', 'yandexmodule', 'yandexmodule_2', 'yandex_tv', 'goya', 'magritte',
            'monet', 'orion',
        );
    }

    /**
     * Human readable platform titles.
     *
     * @return array<string,string>
     */
    public static function platformTitles(): array
    {
        return array(
            'yandexstation' => 'Яндекс Станция',
            'yandexstation_2' => 'Яндекс Станция 2',
            'yandexmini' => 'Яндекс Станция Мини',
            'yandexmini_2' => 'Яндекс Станция Мини 2',
            'yandexmicro' => 'Яндекс Станция Микро',
            'yandexmidi' => 'Яндекс Станция Миди',
            'yandexmodule' => 'Яндекс Модуль',
            'yandexmodule_2' => 'Яндекс Модуль 2',
            'yandex_tv' => 'Яндекс ТВ',
            'goya' => 'ТВ Станция',
            'magritte' => 'ТВ Станция Про',
            'monet' => 'ТВ Станция Макс',
            'jbl_link_portable' => 'JBL Link Portable',
            'cucumber' => 'Яндекс Станция Дуо',
            'plum' => 'Яндекс Станция Макс',
            'bergamot' => 'Яндекс Станция 2',
            'chiron' => 'Яндекс Станция Миди',
            'pickle' => 'Яндекс Станция Лайт',
            'orion' => 'Яндекс Станция',
        );
    }

    /**
     * @param string $platform
     * @return bool
     */
    public static function isLocalPlatform(string $platform): bool
    {
        return in_array(strtolower(trim($platform)), self::localPlatforms(), true);
    }

    /**
     * @param string $platform
     * @return string
     */
    public static function platformTitle(string $platform): string
    {
        $platform = strtolower(trim($platform));
        $titles = self::platformTitles();
        if (isset($titles[$platform])) {
            return $titles[$platform];
        }

        return $platform === '' ? 'Неизвестная платформа' : ucfirst($platform);
    }

    /**
     * Device type family (the part right after `devices.types.`).
     *
     * @param string $type
     * @return string
     */
    public static function deviceFamily(string $type): string
    {
        $type = strtolower(trim($type));
        if (strpos($type, 'devices.types.') === 0) {
            $type = substr($type, strlen('devices.types.'));
        }
        $parts = explode('.', $type);
        return $parts[0] ?? '';
    }

    /**
     * @param string $type
     * @return string
     */
    public static function deviceTypeTitle(string $type): string
    {
        $families = array(
            'smart_speaker' => 'Умная колонка',
            'light' => 'Свет',
            'socket' => 'Розетка',
            'switch' => 'Выключатель',
            'relay' => 'Реле',
            'sensor' => 'Датчик',
            'thermostat' => 'Термостат',
            'camera' => 'Камера',
            'openable' => 'Открывающее устройство',
            'curtain' => 'Шторы',
            'humidifier' => 'Увлажнитель',
            'purifier' => 'Очиститель воздуха',
            'vacuum' => 'Пылесос',
            'cooking' => 'Кухонная техника',
            'kettle' => 'Чайник',
            'coffee_maker' => 'Кофемашина',
            'multicooker' => 'Мультиварка',
            'dishwasher' => 'Посудомоечная машина',
            'washing_machine' => 'Стиральная машина',
            'iron' => 'Утюг',
            'media_device' => 'Медиаустройство',
            'tv' => 'Телевизор',
            'hub' => 'Хаб',
            'module' => 'Модуль',
            'water_leak' => 'Датчик протечки',
            'smoke_sensor' => 'Датчик дыма',
            'gas_sensor' => 'Датчик газа',
            'motion_sensor' => 'Датчик движения',
            'door_sensor' => 'Датчик двери',
            'window_sensor' => 'Датчик окна',
        );
        $family = self::deviceFamily($type);
        if (isset($families[$family])) {
            return $families[$family];
        }

        return $family === '' ? 'Устройство Яндекса' : ucfirst(str_replace('_', ' ', $family));
    }

    /**
     * Tabler icon for a Yandex device type.
     *
     * @param string $type
     * @return string
     */
    public static function deviceIcon(string $type): string
    {
        $family = self::deviceFamily($type);
        $icons = array(
            'smart_speaker' => 'device-speaker',
            'light' => 'bulb',
            'socket' => 'plug',
            'switch' => 'toggle-right',
            'relay' => 'bolt',
            'sensor' => 'activity',
            'thermostat' => 'temperature',
            'camera' => 'camera',
            'openable' => 'door',
            'curtain' => 'blinds',
            'humidifier' => 'droplet',
            'purifier' => 'air-conditioning',
            'vacuum' => 'robot',
            'cooking' => 'chef-hat',
            'kettle' => 'cup',
            'coffee_maker' => 'coffee',
            'multicooker' => 'soup',
            'dishwasher' => 'dishwasher',
            'washing_machine' => 'washing-machine',
            'iron' => 'ironing',
            'media_device' => 'device-tv',
            'tv' => 'device-tv',
            'hub' => 'router',
            'module' => 'box',
        );

        return $icons[$family] ?? 'device-unknown';
    }

    /**
     * Legacy Yandex device icon URL. Kept for the public player and old links.
     *
     * @param string $type
     * @param bool $on
     * @return string
     */
    public static function deviceIconUrl(string $type, bool $on = false): string
    {
        $map = self::iconMap();
        $key = strtolower(trim($type)) . ($on ? '_on' : '');
        if (isset($map[$key])) {
            return $map[$key];
        }
        if ($on && isset($map[strtolower(trim($type))])) {
            return $map[strtolower(trim($type))];
        }

        return 'https://yastatic.net/s3/pudya/app/_/cf97acc6d0252b23.webp';
    }

    /**
     * @return array<string,string>
     */
    private static function iconMap(): array
    {
        if (self::$iconMap === null) {
            $devices_URL = array();
            include __DIR__ . '/../utils/devices_url.php';
            $map = array();
            foreach ($devices_URL as $key => $url) {
                $map[strtolower((string)$key)] = ltrim((string)$url, '(');
            }
            self::$iconMap = $map;
        }

        return self::$iconMap;
    }

    /**
     * Human readable capability label.
     *
     * @param string $title
     * @return string
     */
    public static function capabilityTitle(string $title): string
    {
        $known = array(
            'devices.capabilities.on_off' => 'Включение и выключение',
            'devices.capabilities.range' => 'Диапазон',
            'devices.capabilities.mode' => 'Режим',
            'devices.capabilities.toggle' => 'Переключатель',
            'devices.capabilities.color_setting' => 'Цвет',
            'devices.capabilities.video_stream' => 'Видеопоток',
            'devices.capabilities.quasar' => 'Облачный TTS',
            'devices.capabilities.quasar.server_action' => 'Облачная команда',
            'devices.properties.online' => 'Онлайн',
            'devices.properties.float' => 'Числовое значение',
            'devices.properties.event' => 'Событие',
            'local.artist' => 'Исполнитель',
            'local.track' => 'Название трека',
            'local.cover' => 'Обложка альбома',
            'local.text' => 'Алиса произнесёт текст',
            'local.command' => 'Алиса выполнит команду',
            'local.dialog' => 'Алиса ответит и продолжит диалог',
            'local.audio' => 'Аудио по ссылке',
            'local.video' => 'Видео по ссылке',
            'local.other' => 'Другие команды',
            'local.online' => 'Локальное подключение',
            'local.volume' => 'Громкость 1–10',
            'cloud.text' => 'Облачный TTS',
            'cloud.command' => 'Облачная команда',
            'cloud.aswr_scenario' => 'Ответ сценария',
            'cloud.online' => 'Станция онлайн',
        );
        if (isset($known[$title])) {
            return $known[$title];
        }

        $tail = $title;
        foreach (array('devices.capabilities.', 'devices.properties.', 'local.', 'cloud.') as $prefix) {
            if (strpos($tail, $prefix) === 0) {
                $tail = substr($tail, strlen($prefix));
                break;
            }
        }
        $tail = str_replace(array('.', '_'), ' ', $tail);

        return ucfirst(trim($tail));
    }

    /**
     * Short capability group used for badges.
     *
     * @param string $title
     * @return string
     */
    public static function capabilityGroup(string $title): string
    {
        if (strpos($title, 'local.') === 0) {
            return 'local';
        }
        if (strpos($title, 'cloud.') === 0) {
            return 'cloud';
        }
        if (strpos($title, 'devices.properties.') === 0) {
            return 'property';
        }
        if (strpos($title, 'devices.capabilities.') === 0) {
            return 'capability';
        }

        return 'other';
    }

    /**
     * @param string $command
     * @return string
     */
    public static function commandTitle(string $command): string
    {
        $commands = self::commands();
        return $commands[$command]['label'] ?? $command;
    }

    /**
     * Commands accepted by the API and the test panel.
     *
     * @return array<string,array{label:string,description:string,icon:string}>
     */
    public static function commands(): array
    {
        return array(
            'text' => array('label' => 'Произнести текст', 'description' => 'Алиса прочитает фразу и не будет ждать ответа.', 'icon' => 'message'),
            'command' => array('label' => 'Выполнить команду', 'description' => 'Фраза воспринимается как голосовая команда.', 'icon' => 'microphone'),
            'dialog' => array('label' => 'Диалог', 'description' => 'Алиса ответит и продолжит разговор.', 'icon' => 'messages'),
            'play' => array('label' => 'Играть', 'description' => 'Продолжить воспроизведение.', 'icon' => 'player-play'),
            'stop' => array('label' => 'Пауза', 'description' => 'Остановить воспроизведение.', 'icon' => 'player-pause'),
            'prev' => array('label' => 'Предыдущий трек', 'description' => 'Переключить на предыдущий трек.', 'icon' => 'player-track-prev'),
            'next' => array('label' => 'Следующий трек', 'description' => 'Переключить на следующий трек.', 'icon' => 'player-track-next'),
            'volumeUp' => array('label' => 'Громче', 'description' => 'Увеличить громкость.', 'icon' => 'volume-2'),
            'volumeDown' => array('label' => 'Тише', 'description' => 'Уменьшить громкость.', 'icon' => 'volume-1'),
            'setVolume' => array('label' => 'Установить громкость', 'description' => 'Громкость от 0.1 до 10.', 'icon' => 'volume'),
            'audio' => array('label' => 'Аудио по ссылке', 'description' => 'Ссылка на трек, альбом или поток.', 'icon' => 'music'),
            'video' => array('label' => 'Видео по ссылке', 'description' => 'YouTube или другое видео на станции с экраном.', 'icon' => 'video'),
        );
    }

    /**
     * @return array<int,array{value:string,label:string,description:string}>
     */
    public static function ttsModes(): array
    {
        return array(
            array('value' => '0', 'label' => 'Выключено', 'description' => 'Не озвучивать события.'),
            array('value' => '1', 'label' => 'Локально', 'description' => 'Через Glagol, без облака Яндекса.'),
            array('value' => '2', 'label' => 'Облако Яндекса', 'description' => 'Через системный сценарий Cloud TTS.'),
        );
    }

    /**
     * @return array<string,string>
     */
    public static function locales(): array
    {
        return array(
            'ru-RU' => 'Русский (ru-RU)',
            'en-US' => 'English (en-US)',
            'kk-KZ' => 'Қазақша (kk-KZ)',
            'tr-TR' => 'Türkçe (tr-TR)',
            'ar-SA' => 'العربية (ar-SA)',
        );
    }

    /**
     * @return array<int,string>
     */
    public static function reloadOptions(): array
    {
        return array(10, 15, 30, 60, 120, 180, 300, 600);
    }

    /**
     * @param int $seconds
     * @return string
     */
    public static function reloadLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' секунд';
        }
        $minutes = (int)round($seconds / 60);
        return $minutes . ' ' . self::plural($minutes, 'минута', 'минуты', 'минут');
    }

    /**
     * @param int $number
     * @param string $one
     * @param string $few
     * @param string $many
     * @return string
     */
    public static function plural(int $number, string $one, string $few, string $many): string
    {
        $number = abs($number) % 100;
        $last = $number % 10;
        if ($number > 10 && $number < 20) {
            return $many;
        }
        if ($last === 1) {
            return $one;
        }
        if ($last >= 2 && $last <= 4) {
            return $few;
        }

        return $many;
    }

    /**
     * @return array<int,array{value:string,label:string,description:string,icon:string}>
     */
    public static function authMethods(): array
    {
        return array(
            array(
                'value' => 'qr',
                'label' => 'QR-код',
                'description' => 'Самый быстрый путь: отсканируйте код в приложении Яндекс.',
                'icon' => 'qrcode',
            ),
            array(
                'value' => 'browser',
                'label' => 'Ссылка на вход',
                'description' => 'Откройте ссылку в браузере, где уже выполнен вход в Яндекс.',
                'icon' => 'external-link',
            ),
            array(
                'value' => 'cookie',
                'label' => 'Cookie-файл',
                'description' => 'Загрузите Netscape cookie или JSON из браузера.',
                'icon' => 'file-upload',
            ),
            array(
                'value' => 'token',
                'label' => 'x-token',
                'description' => 'Перенесите готовый account x-token из Home Assistant.',
                'icon' => 'key',
            ),
            array(
                'value' => 'otp',
                'label' => 'Пароль Я.Ключа',
                'description' => 'Одноразовый пароль. Оставлено для совместимости.',
                'icon' => 'shield-lock',
            ),
        );
    }

    /**
     * @param string $type
     * @return string
     */
    public static function typePrefix(string $type): string
    {
        return strpos($type, 'devices.types.') === 0 ? $type : 'devices.types.' . $type;
    }

    /**
     * Normalize a Yandex `devices.capabilities.*` title to a stable key.
     *
     * @param string $title
     * @return string
     */
    public static function normalizeCapability(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/\.unknown$/u', '', $title) ?? $title;
        return $title;
    }
}
