<?php

$type = gr('type');
$out['TYPE'] = $type;
if (gr('err_msg')) {
    $out['ERR_MSG'] = gr('err_msg');
}
if (gr('ok_msg')) {
    $out['OK_MSG'] = gr('ok_msg');
}

if (gr('refresh_devices')) {
    $this->refreshDevices();
	$this->addScenarios();
}

if ($type == 'reset') {
    $this->clearAuthFiles();
	$this->config['AUTHORIZED'] = 0;
	$this->config['X_TOKEN'] = '';
	$this->config['OAUTH_TOKEN'] = '';
	$this->saveConfig();
    $this->redirect("?view_mode=" . $this->view_mode);
}

if ($type == 'otp') {
    $use_cookie_file = YADEVICES_COOKIE_PATH.'_otp';
    $track_id = gr('track_id');
    if ($track_id) {
        $otp = gr('otp');
        if ($otp!='') {
            $post = array(
                'csrf_token' => gr('csrf_token'),
                'track_id' => $track_id,
                'password' => $otp,
                'retpath' => 'https://passport.yandex.ru/am/finish?status=ok&from=Login',
            );
            $postvars = '';
            foreach($post as $key=>$value) {
                $postvars .= $key . "=" . urlencode($value) . "&";
            }

			$result = $this->curl('https://passport.yandex.ru/registration-validations/auth/multi_step/commit_password', $use_cookie_file, '', $postvars, [CURLOPT_COOKIEFILE=>$use_cookie_file, CURLOPT_COOKIEJAR => $use_cookie_file]);
            $data = json_decode($result, true);
            if (($data['status'] ?? '') == 'ok' || (($data['errors'][0] ?? '') == 'account.auth_passed')) {
                rename($use_cookie_file, YADEVICES_COOKIE_PATH);
                $checkCookie = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
                if (!is_array($checkCookie) || ($checkCookie['status'] ?? '') != 'ok') {
                    @unlink(YADEVICES_COOKIE_PATH);
                    $out['ERR_MSG'] = 'Ошибка авторизации!';
                    return;
                } else {
					$this->completeAuthFromCookies();
                    $this->redirect("?view_mode=" . $this->view_mode . "&refresh_devices=1&ok_msg=" . urlencode("Успешная авторизация!"));
                }
            } else {
                $out['ERR_MSG'] = 'Авторизация не пройдена. Попробуйте ещё раз.';
            }
        }
        $out['TRACK_ID']=$track_id;
    } else {
        $username = gr('username');
        if ($username) {
            $csrf_token = $this->getCSRFToken($use_cookie_file);
            if ($csrf_token!='') {
                $out['CSRF_TOKEN'] = $csrf_token;
                $post = array(
                    'csrf_token' => $csrf_token,
                    'login' => $username,
                );
                $postvars = '';
                foreach($post as $key=>$value) {
                    $postvars .= $key . "=" . urlencode($value) . "&";
                }
				$result = $this->curl('https://passport.yandex.ru/registration-validations/auth/multi_step/start', $use_cookie_file, '', $postvars, [CURLOPT_COOKIEFILE=>$use_cookie_file, CURLOPT_COOKIEJAR => $use_cookie_file]);
                $data = json_decode($result, true);
                if (($data['status'] ?? '') == 'ok') {
                    $track_id = $data['track_id'];
                    $out['TRACK_ID']=$track_id;
                } else {
                    $out['ERR_MSG']='Ошибка авторизации. Попробуйте ещё раз.';
                }

            } else {
                $out['ERR_MSG'] = 'Ошибка получения CSRF-токена';
            }
        }

    }
}

if ($type == 'qr' || $type == 'browser') {
	$use_cookie_file = YADEVICES_COOKIE_PATH.'_qr';
	$csrf_token = $this->getCSRFToken($use_cookie_file);
	if ($csrf_token) {
		$post = json_encode(['retpath' => 'https://passport.yandex.ru/']);
		$headers = ["X-CSRF-Token: ".$csrf_token];
		$auth = $this->curl('https://passport.yandex.ru/pwl-yandex/api/passport/auth/password/submit', $use_cookie_file, array_merge($headers, ["Content-Type: application/json"]), $post, [CURLOPT_COOKIEFILE=>$use_cookie_file, CURLOPT_COOKIEJAR=>$use_cookie_file, CURLOPT_USERAGENT => $this->getPassportAuthUserAgent()]);
		$auth_data = json_decode($auth, true);
		if (empty($auth_data['track_id'])) {
			$out['ERR_MSG'] = 'Ошибка получения QR-сессии авторизации.';
			return;
		}
		$post = http_build_query([
						"location_id"=> 0,
						"magic_track_id"=> $auth_data['track_id'],
						"track_id"=> "" ,
				]);
		$result = $this->curl('https://passport.yandex.ru/pwl-yandex/api/passport/auth/magic/code', $use_cookie_file, $headers, $post, [CURLOPT_COOKIEFILE=>$use_cookie_file, CURLOPT_COOKIEJAR=>$use_cookie_file, CURLOPT_USERAGENT => $this->getPassportAuthUserAgent()]);
		$data = json_decode($result, true);
		if (empty($data['link'])) {
			$out['ERR_MSG'] = 'Ошибка получения ссылки QR-кода.';
			return;
		}
		if ($type == 'qr') {
			include_once(ROOT . "modules/yadevices/phpqrcode/qrlib.php");
			$path = ROOT . "cms/cached/yaqrcode.png";
			QRcode::png($data['link'], $path, QR_ECLEVEL_L, 9, 2);
			$out['QR_URL'] = "cms/cached/yaqrcode.png";
		}
		$out['TRACK_ID'] = $auth_data['track_id'];
		$out['CSRF_TOKEN'] = $csrf_token;
		$out['AUTH'] = urlencode($auth);
		$out['AUTH_URL'] = $data['link'];	
	} else {
		$out['ERR_MSG'] = 'Ошибка получения CSRF-токена';
	}
}


if ($type == 'cookie') {
    global $file;
    if (is_file($file)) {
        move_uploaded_file($file, YADEVICES_COOKIE_PATH);
        $this->normalizeCookieFile(YADEVICES_COOKIE_PATH);
        $checkCookie = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
        if (!is_array($checkCookie) || ($checkCookie['status'] ?? '') != 'ok') {
            @unlink(YADEVICES_COOKIE_PATH);
            $out['ERR_MSG'] = 'Файл который вы загружаете не является Cookie файлом с сайта Яндекс или он устарел.';
            return;
        } else {
			$this->completeAuthFromCookies();
            $this->redirect("?view_mode=" . $this->view_mode . "&refresh_devices=1&ok_msg=" . urlencode("Успешная авторизация!"));
        }
    }
}

if ($type == 'token') {
    $xToken = trim(gr('x_token'));
    if ($xToken != '') {
        $this->getConfig();
        $this->normalizeConfig();
        if ($this->validateXToken($xToken)) {
            $this->config['X_TOKEN'] = $xToken;
            $this->getMusicToken($xToken, true);
            if (!$this->refreshCookies()) {
                $this->loginByXToken($xToken);
            }
            $this->config['AUTHORIZED'] = 1;
            $this->saveConfig();
            $this->redirect("?view_mode=" . $this->view_mode . "&refresh_devices=1&ok_msg=" . urlencode("Токен принят, авторизация обновлена."));
        } else {
            $out['ERR_MSG'] = 'x-token не прошел проверку.';
        }
    }
}

if (!$type) {
    if (!file_exists(YADEVICES_COOKIE_PATH) && !empty($this->config['X_TOKEN'])) {
        $this->refreshCookies();
    }
    $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/devices');
    if (is_array($data)) {
        $out['AUTHORIZED_OK'] = 1;
		$this->getConfig();
		if(empty($this->config['AUTHORIZED'])) $this->completeAuthFromCookies();
		$this->saveConfig();
    }
}
