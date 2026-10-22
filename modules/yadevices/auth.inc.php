<?php
/**
 * Authentication actions for the YaDevices admin.
 *
 * The file only prepares data for templates and performs an action when the
 * request explicitly asks for it. Rendering the auth page never calls Yandex:
 * the status is taken from the saved module config, and the "check" action is
 * available explicitly.
 */

$type = (string)gr('type');
$out['TYPE'] = $type;
$out['AUTH_METHODS'] = array();
foreach (YaDevicesCatalog::authMethods() as $method) {
    $out['AUTH_METHODS'][] = array(
        'VALUE' => $this->e($method['value']),
        'LABEL' => $this->e($method['label']),
        'DESCRIPTION' => $this->e($method['description']),
        'ICON' => $this->e($method['icon']),
        'URL' => '?view_mode=auth&type=' . urlencode($method['value']),
    );
}
$out['AUTHORIZED_OK'] = !empty($this->config['AUTHORIZED']) ? 1 : 0;
$out['AUTH_HAS_COOKIE'] = file_exists(YADEVICES_COOKIE_PATH) ? 1 : 0;
$out['AUTH_X_TOKEN'] = trim((string)$this->config['X_TOKEN']) !== '' ? 1 : 0;
$out['AUTH_OAUTH_TOKEN'] = trim((string)$this->config['OAUTH_TOKEN']) !== '' ? 1 : 0;
$out['AUTH_COOKIE_PATH'] = $this->e(YADEVICES_COOKIE_PATH);
$out['AUTH_DEVICE_ID'] = $this->e($this->getOAuthDeviceId());
$out['AUTH_DEVICE_NAME'] = $this->e($this->getOAuthDeviceName());
$out['AUTH_POLL_URL'] = ROOTHTML . 'ajax/yadevices.html?check_qr_status=1';
$out['AUTH_DONE_URL'] = '?view_mode=auth&ok_msg=' . urlencode('Успешная авторизация.');
$out['AUTH_ERROR_URL'] = '?view_mode=auth&err_msg=' . urlencode('Ошибка авторизации.');

if (gr('err_msg')) {
    $out['ERR_MSG'] = $this->e((string)gr('err_msg'));
}
if (gr('ok_msg')) {
    $out['OK_MSG'] = $this->e((string)gr('ok_msg'));
}

if (gr('refresh_devices')) {
    if (!empty($this->config['AUTHORIZED'])) {
        $this->refreshDevices();
        $this->addScenarios();
    }
}

if ($type === 'reset') {
    $this->clearAuthFiles();
    $this->config['AUTHORIZED'] = 0;
    $this->config['X_TOKEN'] = '';
    $this->config['OAUTH_TOKEN'] = '';
    $this->saveConfig();
    $this->redirect('?view_mode=auth&ok_msg=' . urlencode('Данные авторизации удалены.'));
}

if ($type === 'check') {
    $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/devices');
    if (is_array($data) && isset($data['status']) && $data['status'] === 'ok') {
        if (empty($this->config['AUTHORIZED'])) {
            $this->completeAuthFromCookies();
        }
        $this->config['AUTHORIZED'] = 1;
        $this->saveConfig();
        $this->redirect('?view_mode=auth&ok_msg=' . urlencode('Авторизация активна.'));
    }
    $this->redirect('?view_mode=auth&err_msg=' . urlencode('Авторизация не подтверждена. Проверьте cookie или войдите заново.'));
}

if ($type === 'otp') {
    $useCookieFile = YADEVICES_COOKIE_PATH . '_otp';
    $trackId = (string)gr('track_id');
    if ($trackId !== '') {
        $otp = trim((string)gr('otp'));
        if ($otp !== '') {
            $post = array(
                'csrf_token' => gr('csrf_token'),
                'track_id' => $trackId,
                'password' => $otp,
                'retpath' => 'https://passport.yandex.ru/am/finish?status=ok&from=Login',
            );
            $postvars = http_build_query($post);
            $this->curl(
                'https://passport.yandex.ru/registration-validations/auth/multi_step/commit_password',
                $useCookieFile,
                '',
                $postvars,
                array(CURLOPT_COOKIEFILE => $useCookieFile, CURLOPT_COOKIEJAR => $useCookieFile)
            );
            $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
            if (!is_array($result) || ($result['status'] ?? '') !== 'ok') {
                @unlink($useCookieFile);
                $out['ERR_MSG'] = 'Ошибка авторизации. Попробуйте ещё раз.';
            } else {
                @rename($useCookieFile, YADEVICES_COOKIE_PATH);
                $this->completeAuthFromCookies();
                $this->redirect('?view_mode=auth&ok_msg=' . urlencode('Успешная авторизация.'));
            }
        }
        $out['TRACK_ID'] = $this->e($trackId);
    } else {
        $username = trim((string)gr('username'));
        if ($username !== '') {
            $csrfToken = $this->getCSRFToken($useCookieFile);
            if ($csrfToken !== '') {
                $out['CSRF_TOKEN'] = $this->e($csrfToken);
                $postvars = http_build_query(array('csrf_token' => $csrfToken, 'login' => $username));
                $result = $this->curl(
                    'https://passport.yandex.ru/registration-validations/auth/multi_step/start',
                    $useCookieFile,
                    '',
                    $postvars,
                    array(CURLOPT_COOKIEFILE => $useCookieFile, CURLOPT_COOKIEJAR => $useCookieFile)
                );
                $data = json_decode((string)$result, true);
                if (($data['status'] ?? '') === 'ok' && !empty($data['track_id'])) {
                    $out['TRACK_ID'] = $this->e((string)$data['track_id']);
                } else {
                    $out['ERR_MSG'] = 'Не удалось начать авторизацию. Проверьте логин.';
                }
            } else {
                $out['ERR_MSG'] = 'Не удалось получить CSRF-токен.';
            }
        }
    }
    $out['TYPE'] = 'otp';
}

if ($type === 'qr' || $type === 'browser') {
    $useCookieFile = YADEVICES_COOKIE_PATH . '_qr';
    $csrfToken = $this->getCSRFToken($useCookieFile);
    if ($csrfToken !== '') {
        $auth = $this->curl(
            'https://passport.yandex.ru/pwl-yandex/api/passport/auth/password/submit',
            $useCookieFile,
            array('X-CSRF-Token: ' . $csrfToken, 'Content-Type: application/json'),
            json_encode(array('retpath' => 'https://passport.yandex.ru/')),
            array(
                CURLOPT_COOKIEFILE => $useCookieFile,
                CURLOPT_COOKIEJAR => $useCookieFile,
                CURLOPT_USERAGENT => $this->getPassportAuthUserAgent(),
            )
        );
        $authData = json_decode((string)$auth, true);
        if (empty($authData['track_id'])) {
            $out['ERR_MSG'] = 'Не удалось создать сессию авторизации.';
        } else {
            $postvars = http_build_query(array(
                'location_id' => 0,
                'magic_track_id' => $authData['track_id'],
                'track_id' => '',
            ));
            $result = $this->curl(
                'https://passport.yandex.ru/pwl-yandex/api/passport/auth/magic/code',
                $useCookieFile,
                array('X-CSRF-Token: ' . $csrfToken),
                $postvars,
                array(
                    CURLOPT_COOKIEFILE => $useCookieFile,
                    CURLOPT_COOKIEJAR => $useCookieFile,
                    CURLOPT_USERAGENT => $this->getPassportAuthUserAgent(),
                )
            );
            $data = json_decode((string)$result, true);
            if (empty($data['link'])) {
                $out['ERR_MSG'] = 'Не удалось получить ссылку для входа.';
            } else {
                if ($type === 'qr') {
                    include_once(ROOT . 'modules/yadevices/phpqrcode/qrlib.php');
                    $path = ROOT . 'cms/cached/yaqrcode.png';
                    QRcode::png($data['link'], $path, QR_ECLEVEL_L, 9, 2);
                    $out['QR_URL'] = ROOTHTML . 'cms/cached/yaqrcode.png';
                }
                $out['TRACK_ID'] = $this->e((string)$authData['track_id']);
                $out['CSRF_TOKEN'] = $this->e($csrfToken);
                $out['AUTH'] = $this->e(urlencode((string)$auth));
                $out['AUTH_URL'] = $this->e((string)$data['link']);
            }
        }
    } else {
        $out['ERR_MSG'] = 'Не удалось получить CSRF-токен. Попробуйте ещё раз.';
    }
    $out['TYPE'] = $type;
}

if ($type === 'cookie') {
    $file = $_FILES['file']['tmp_name'] ?? '';
    if ($file !== '' && is_uploaded_file($file)) {
        if (!is_dir(dirname(YADEVICES_COOKIE_PATH))) {
            @mkdir(dirname(YADEVICES_COOKIE_PATH), 0777, true);
        }
        move_uploaded_file($file, YADEVICES_COOKIE_PATH);
        $this->normalizeCookieFile(YADEVICES_COOKIE_PATH);
        $check = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
        if (!is_array($check) || ($check['status'] ?? '') !== 'ok') {
            @unlink(YADEVICES_COOKIE_PATH);
            $out['ERR_MSG'] = 'Файл не похож на актуальный cookie Яндекса.';
        } else {
            $this->completeAuthFromCookies();
            $this->redirect('?view_mode=auth&ok_msg=' . urlencode('Успешная авторизация.'));
        }
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $out['ERR_MSG'] = 'Выберите файл cookie.';
    }
    $out['TYPE'] = 'cookie';
}

if ($type === 'token') {
    $xToken = trim((string)gr('x_token'));
    if ($xToken !== '') {
        if ($this->validateXToken($xToken)) {
            $this->config['X_TOKEN'] = $xToken;
            $this->getMusicToken($xToken, true);
            if (!$this->refreshCookies()) {
                $this->loginByXToken($xToken);
            }
            $this->config['AUTHORIZED'] = 1;
            $this->saveConfig();
            $this->redirect('?view_mode=auth&ok_msg=' . urlencode('x-token принят.'));
        }
        $out['ERR_MSG'] = 'x-token не прошёл проверку.';
    }
    $out['TYPE'] = 'token';
}

if ($type === '') {
    $out['TYPE'] = '';
}
