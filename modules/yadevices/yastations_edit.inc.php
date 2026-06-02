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
