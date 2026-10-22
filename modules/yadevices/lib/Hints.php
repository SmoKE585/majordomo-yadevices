<?php
/**
 * Contextual hints for the YaDevices admin pages.
 *
 * Hints are educational, not status messages: they explain the next step and
 * stay close to the controls they describe.
 *
 * @package MajorDoMo\Modules\YaDevices
 */

final class YaDevicesHints
{
    /**
     * @param bool $authorized
     * @param bool $hasCookie
     * @return string
     */
    public static function main(bool $authorized, bool $hasCookie): string
    {
        if (!$authorized) {
            return self::hint(
                'warning',
                'Нужна авторизация',
                'Без входа в Яндекс модуль не увидит станции и устройства. Авторизация хранится локально в файле cookie.',
                array(
                    'Откройте «Авторизация» и выберите QR-код или ссылку на вход.',
                    'После входа нажмите «Обновить», чтобы загрузить станции.',
                ),
                'auth',
                'Перейти к авторизации',
                'shield-lock'
            );
        }

        if (!$hasCookie) {
            return self::hint(
                'info',
                'Авторизация есть, cookie не найден',
                'Модуль может работать через сохранённый x-token, но для локального режима и обновления списка нужен cookie-файл. Откройте страницу авторизации и проверьте состояние.',
                array(),
                'auth',
                'Проверить авторизацию',
                'key'
            );
        }

        return self::hint(
            'tip',
            'Локально или через облако',
            'Локальный режим быстрее и не зависит от облака, но требует IP станции и токен устройства. Облачный режим работает через сценарий Cloud TTS и авторизацию Яндекса.',
            array(
                'Откройте карточку станции, укажите IP и сформируйте токен.',
                'Для облачного TTS включите режим «Облако Яндекса» в карточке станции.',
            ),
            'stations',
            'Открыть станции',
            'speakerphone'
        );
    }

    /**
     * @return string
     */
    public static function devices(): string
    {
        return self::hint(
            'info',
            'Как связать умение со свойством',
            'У каждого умения есть объект, свойство и необязательный метод. Модуль читает состояние из свойства и записывает команды обратно. Для методов доступна передача параметров.',
            array(
                'Откройте карточку устройства и нажмите на поле «Объект» — появится список объектов MajorDoMo.',
                'Выберите свойство в том же объекте. Для методов укажите метод, если хотите обрабатывать команды кодом.',
                'Сохраните карточку: связи сразу появятся в свойствах объектов.',
            ),
            null,
            null,
            'link'
        );
    }

    /**
     * @return string
     */
    public static function scenarios(): string
    {
        return self::hint(
            'tip',
            'Системные сценарии Cloud TTS',
            'Модуль создаёт по одному служебному сценарию на станцию. Не удаляйте сценарии с префиксом «мжд »: через них работает облачное озвучивание.',
            array(),
            null,
            null,
            'cloud'
        );
    }

    /**
     * @param array<string,mixed> $station
     * @param bool $authorized
     * @return string
     */
    public static function station(array $station, bool $authorized): string
    {
        $local = !empty($station['LOCAL_CAPABLE']);
        $ip = trim((string)($station['IP'] ?? ''));
        $token = trim((string)($station['DEVICE_TOKEN'] ?? ''));

        if ($local && ($ip === '' || $token === '')) {
            return self::hint(
                'warning',
                'Локальный режим пока недоступен',
                'Для локального управления нужны IP станции в локальной сети и токен устройства. Без них останется только облако.',
                array(
                    'Нажмите «Определить» рядом с IP или введите адрес вручную.',
                    'Нажмите «Сформировать токен» и дождитесь ответа Яндекса.',
                    'Проверьте порт 1961 кнопкой «Проверить».',
                ),
                null,
                null,
                'wifi-off'
            );
        }

        if (!$authorized) {
            return self::hint(
                'warning',
                'Облачные функции недоступны',
                'Настройки станции, будильники и Cloud TTS требуют авторизации Яндекса.',
                array(),
                '?view_mode=auth',
                'Авторизоваться',
                'shield-lock'
            );
        }

        return self::hint(
            'tip',
            'Что можно сделать в карточке',
            'Переименуйте станцию, выберите режим озвучивания и привяжите будильник, вопрос и ответ Алисы к свойствам объектов.',
            array(
                'Привязки работают в обе стороны: свойство с датой создаёт будильник, ответ Алисы записывается в свойство.',
                'Тестовый блок помогает проверить локальную и облачную отправку до настройки сценариев.',
            ),
            null,
            null,
            'adjustments'
        );
    }

    /**
     * @param array<string,mixed> $device
     * @return string
     */
    public static function device(array $device): string
    {
        if (empty($device['LINKED_COUNT'])) {
            return self::hint(
                'info',
                'Устройство ещё не привязано',
                'Пока ни одно умение не связано со свойствами MajorDoMo. Выберите объект и свойство хотя бы для основного умения — например, включения.',
                array(
                    'Для розетки и света обычно достаточно «Включение и выключение».',
                    'Датчики только читаются: привяжите свойство, чтобы сохранять значения.',
                ),
                null,
                null,
                'unlink'
            );
        }

        return self::hint(
            'tip',
            'Значения и команды',
            'Колонка «Значение» показывает последнее состояние из Яндекса. Привязанное свойство обновляется автоматически, а изменение свойства отправляет команду в устройство.',
            array(),
            null,
            null,
            'link'
        );
    }

    /**
     * @return string
     */
    public static function settings(): string
    {
        return self::hint(
            'tip',
            'Опрос и уведомления',
            'Модуль обновляет состояние станций фоновым циклом. Слишком частый опрос повышает риск ограничений со стороны Яндекса.',
            array(
                '10–30 секунд — для отладки, 1–5 минут — для повседневной работы.',
                'Notify-группы позволяют отправить текст сразу на несколько колонок через API.',
            ),
            null,
            null,
            'settings'
        );
    }

    /**
     * @return string
     */
    public static function auth(): string
    {
        return self::hint(
            'tip',
            'Что выбрать',
            'QR-код — самый быстрый способ. Ссылка на вход удобна, если Яндекс уже открыт в браузере. Cookie и x-token нужны для переноса авторизации с другой системы.',
            array(
                'После успешного входа модуль сам загрузит станции и создаст системные сценарии.',
                'Не используйте пароль администратора MajorDoMo: это отдельная авторизация Яндекса.',
            ),
            null,
            null,
            'shield-lock'
        );
    }

    /**
     * @param string $tone
     * @param string $title
     * @param string $text
     * @param array<int,string> $points
     * @param string|null $link
     * @param string|null $linkLabel
     * @param string $icon
     * @return string
     */
    private static function hint(
        string $tone,
        string $title,
        string $text,
        array $points = array(),
        ?string $link = null,
        ?string $linkLabel = null,
        string $icon = 'bulb'
    ): string {
        $tone = in_array($tone, array('tip', 'info', 'warning', 'danger', 'success'), true) ? $tone : 'tip';
        $html = '<aside class="md-hint md-hint--' . $tone . '" role="note">';
        $html .= '<span class="md-hint__icon"><i class="ti ti-' . self::escape($icon) . '" aria-hidden="true"></i></span>';
        $html .= '<div class="md-hint__body">';
        $html .= '<div class="md-hint__title">' . self::escape($title) . '</div>';
        $html .= '<p class="md-hint__text">' . self::escape($text) . '</p>';
        if ($points !== array()) {
            $html .= '<ul class="md-hint__points">';
            foreach ($points as $point) {
                $html .= '<li>' . self::escape($point) . '</li>';
            }
            $html .= '</ul>';
        }
        if ($link !== null && $linkLabel !== null) {
            $html .= '<div class="md-hint__actions"><a class="md-btn md-btn--ghost md-btn--sm" href="' . self::escape($link) . '">'
                . self::escape($linkLabel) . '<i class="ti ti-arrow-right" aria-hidden="true"></i></a></div>';
        }
        $html .= '</div></aside>';

        return $html;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
