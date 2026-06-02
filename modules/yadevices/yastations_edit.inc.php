<?php
/*
* @version 0.1 (wizard)
*/
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$table_name = 'yastations';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

if ($this->mode == 'send_text') {
    $out['CLOUD']=gr('cloud','int');
    $out['TEXT']=gr('text');
    $out['ID']=$id;
	
	$out['SENDAS']=gr('sendas');
	
    if ($out['CLOUD'] == 1) {
		if($out['SENDAS'] == 1) {
			$command = 'text_action';
		} else {
			$command = 'phrase_action';
		}
		$result = $this->sendCommandToStationCloud($rec, $command, gr('text'));
    } else {
		if($out['SENDAS'] == 0) {
			$command = 'text';
		} else if($out['SENDAS'] == 1) {
			$command = 'command';
		} else if($out['SENDAS'] == 2) {
			$command = 'dialog';
		}
		$result = $this->sendCommandToStation($rec, $command, gr('text'));
    }
	
    $this->redirect("?view_mode=".$this->view_mode."&id=".$rec['ID']);
}

if ($this->mode == 'ask_alice') {
    $out['ASK_TEXT'] = gr('ask_text');
    $answer = $this->askStation($rec, $out['ASK_TEXT']);
    if (is_array($answer)) {
        $out['ASK_ANSWER'] = htmlspecialchars($answer['text'] ?? json_encode($answer, JSON_UNESCAPED_UNICODE));
        $out['OK_MSG'] = 'Ответ получен от локальной Алисы.';
    } else {
        $out['ERR'] = 1;
        $out['ERR_MSG'] = 'Не удалось получить ответ. Conversation работает только через локальный режим Glagol.';
    }
}

if ($this->mode == 'update_station_settings') {
    $settings = array(
        'dnd' => gr('dnd', 'int'),
        'beta' => gr('beta', 'int'),
        'locale' => gr('locale'),
        'led_brightness' => gr('led_brightness'),
        'visualization' => gr('visualization'),
    );
    if ($this->updateStationQuasarSettings($rec, $settings)) {
        $out['OK'] = 1;
        $out['OK_MSG'] = 'Настройки станции обновлены.';
    } else {
        $out['ERR'] = 1;
        $out['ERR_MSG'] = 'Не удалось обновить настройки станции. Возможно, устройство не поддерживает часть параметров.';
    }
}

if ($this->mode == 'create_alarm') {
    if ($this->createStationAlarm($rec, gr('alarm_date'), gr('alarm_time'))) {
        $out['OK'] = 1;
        $out['OK_MSG'] = 'Будильник создан.';
    } else {
        $out['ERR'] = 1;
        $out['ERR_MSG'] = 'Не удалось создать будильник.';
    }
}

if ($this->mode == 'cancel_alarm') {
    if ($this->cancelStationAlarm($rec, gr('alarm_id'))) {
        $out['OK'] = 1;
        $out['OK_MSG'] = 'Будильник удален.';
    } else {
        $out['ERR'] = 1;
        $out['ERR_MSG'] = 'Не удалось удалить будильник.';
    }
}

if ($this->mode == 'detect_ip') {
    $detectedIp = $this->detectStationIp($rec);
    if ($detectedIp) {
        $rec['IP'] = $detectedIp;
        SQLUpdate($table_name, $rec);
        setGlobal('cycle_yadevicesControl', 'restart');
        $out['OK'] = 1;
        $out['OK_MSG'] = 'IP обновлен: ' . htmlspecialchars($detectedIp);
    } else {
        $out['ERR'] = 1;
        $out['ERR_MSG'] = 'Не удалось определить IP автоматически. Укажите IP вручную или настройте mDNS/Zeroconf на стороне системы.';
    }
}

if ($this->mode == 'test_local') {
    if ($this->testLocalGlagolPort($rec['IP'] ?? '')) {
        $out['OK'] = 1;
        $out['OK_MSG'] = 'Локальный порт 1961 доступен.';
    } else {
        $out['ERR'] = 1;
        $out['ERR_MSG'] = 'Локальный порт 1961 недоступен. Проверьте IP, сеть и поддержку локального режима устройством.';
    }
}

if ($this->mode == 'update') {
    $ok = 1;

    $newTitle = trim(gr('title'));
    if ($newTitle != '') {
        $originalTitle = ($rec['ORIGINAL_TITLE'] ?? '') ?: $rec['TITLE'];
        $rec['TITLE'] = $newTitle;
        $rec['CUSTOM_TITLE'] = ($newTitle != $originalTitle) ? 1 : 0;
    }
    $rec['IP'] = gr('ip');
    $rec['TTS'] = gr('tts', 'int');
    $rec['MIN_LEVEL_TEXT'] = gr('min_level_text');

    $rec['ALLOW_ASK'] = gr('allow_ask', 'int');
    //$rec['DEVICE_TOKEN'] = gr('device_token');


    //UPDATING RECORD
    if ($ok) {
        if ($rec['ID']) {
            SQLUpdate($table_name, $rec); // update
        } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec); // adding new record
        }

        /*if ($rec['TTS']==1) {
            $token = $this->getDeviceToken($rec['STATION_ID'], $rec['PLATFORM'], true);
        }*/

		if(!empty($rec['IP'])){
			setGlobal('cycle_yadevicesControl', 'restart');
		}
        $out['OK'] = 1;
    } else {
        $out['ERR'] = 1;
    }
}

if (is_array($rec)) {
    $rec['LOCAL_CAPABLE'] = $this->isLocalCapablePlatform($rec['PLATFORM'] ?? '') ? 1 : 0;
    $rec['LOCAL_AVAILABLE'] = ($rec['LOCAL_CAPABLE'] && !empty($rec['IP']) && !empty($rec['DEVICE_TOKEN'])) ? 1 : 0;
    $rec['CLOUD_AVAILABLE'] = (!empty($this->config['AUTHORIZED']) && !empty($rec['IOT_ID'])) ? 1 : 0;
    $rec['CLOUD_SCENARIO_READY'] = !empty($rec['TTS_SCENARIO']) ? 1 : 0;
    $rec['IP_TEST_AVAILABLE'] = !empty($rec['IP']) ? 1 : 0;

    $stationConfig = $this->getStationQuasarConfig($rec);
    if ($stationConfig) {
        $config = $stationConfig['config'];
        $out['STATION_DND'] = !empty($config['dndMode']['enabled']) ? 1 : 0;
        $out['STATION_DND_SUPPORTED'] = isset($config['dndMode']) ? 1 : 0;
        $out['STATION_BETA'] = !empty($config['beta']) ? 1 : 0;
        $out['STATION_LOCALE'] = htmlspecialchars($config['locale'] ?? '');
        $out['STATION_LED_BRIGHTNESS'] = htmlspecialchars(isset($config['led']['brightness']['value']) ? (string)$config['led']['brightness']['value'] : '');
        $out['STATION_LED_AUTO'] = !empty($config['led']['brightness']['auto']) ? 1 : 0;
        $out['STATION_VISUALIZATION'] = !empty($config['led']['music_equalizer_visualization']['auto']) ? 'auto' : 'clock';
    } else {
        $out['STATION_CONFIG_UNAVAILABLE'] = 1;
    }

    $alarms = $this->getStationAlarms($rec);
    foreach ($alarms as $key => $alarm) {
        $alarms[$key]['ALARM_ID'] = htmlspecialchars($alarm['alarm_id'] ?? '');
        $alarms[$key]['TIME'] = htmlspecialchars($alarm['time'] ?? '');
        $alarms[$key]['DATE'] = htmlspecialchars($alarm['date'] ?? '');
        $alarms[$key]['ENABLED'] = !empty($alarm['enabled']) ? 'Да' : 'Нет';
        $alarms[$key]['RECURRING'] = htmlspecialchars(!empty($alarm['recurring']['days_of_week']) ? implode(', ', $alarm['recurring']['days_of_week']) : '');
    }
    $out['ALARMS'] = $alarms;
    $out['TODAY'] = date('Y-m-d');

    foreach ($rec as $k => $v) {
        if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
        }
    }
}

if (isset($rec['ID']) && isset($rec['MIN_LEVEL']) && !isset($rec['MIN_LEVEL_TEXT'])) {
    $rec['MIN_LEVEL_TEXT']=$rec['MIN_LEVEL'];
}
outHash($rec, $out);
