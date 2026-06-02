<?php

if ($this->mode == 'switch') {
	$iot_id = gr('iot_id');
	if(gr('value') == 1) $value = 0;
	else $value = 1;
	$sendCMD = $this->sendValueToYandex($iot_id, 'devices.capabilities.on_off', $value);
	usleep(500000);
    $this->redirect("?view_mode=".$this->view_mode);
}

$this->getConfig();
$this->normalizeConfig();
$hiddenSkills = json_decode($this->config['HIDDEN_SKILLS'] ?? '[]', true);
if (!is_array($hiddenSkills)) {
    $hiddenSkills = array();
}

//Добавим иконки из БД
include_once "utils/devices_url.php";
$this->ensureRenameColumns();
$devices = SQLSelect("SELECT * FROM yadevices ORDER BY SKILL_NAME, TITLE");

$skillsMap = array();
foreach ($devices as $key => $device) {
    $skillId = ($device['SKILL_ID'] ?? '') ?: '__empty__';
    $skillName = ($device['SKILL_NAME'] ?? '') ?: $this->getSkillName($device['SKILL_ID'] ?? '');
    if (($device['SKILL_NAME'] ?? '') == '' && !empty($device['ID'])) {
        $devices[$key]['SKILL_NAME'] = $skillName;
        SQLExec("UPDATE yadevices SET SKILL_NAME='" . DBSafe($skillName) . "' WHERE ID=" . (int)$device['ID']);
    }
    $skillsMap[$skillId] = $skillName;
}

if ($this->mode == 'save_skill_filter') {
    $visibleSkills = isset($_POST['visible_skills']) && is_array($_POST['visible_skills']) ? $_POST['visible_skills'] : array();
    $hiddenSkills = array();
    foreach ($skillsMap as $skillId => $skillName) {
        if (!in_array($skillId, $visibleSkills, true)) {
            $hiddenSkills[] = $skillId;
        }
    }
    $this->config['HIDDEN_SKILLS'] = json_encode(array_values($hiddenSkills));
    $this->saveConfig();
    $this->redirect("?view_mode=" . $this->view_mode);
}

$skills = array();
foreach ($skillsMap as $skillId => $skillName) {
    $skills[] = array(
        'ID' => htmlspecialchars($skillId),
        'TITLE' => htmlspecialchars($skillName),
        'CHECKED' => in_array($skillId, $hiddenSkills, true) ? 0 : 1,
    );
}
usort($skills, function($a, $b) {
    return strcasecmp($a['TITLE'], $b['TITLE']);
});
$out['SKILLS'] = $skills;

//$devices = SQLSelect("SELECT yadevices.*, yadevices_capabilities.VALUE FROM yadevices LEFT JOIN yadevices_capabilities ON yadevices.ID=yadevices_capabilities.YADEVICE_ID WHERE yadevices_capabilities.TITLE = 'devices.capabilities.on_off' ORDER BY TITLE");
$properties_temp = SQLSelect("SELECT * FROM yadevices_capabilities");
//Если есть статус включения, добавим его значение в отдельный массив
foreach($properties_temp as $prop){
	if($prop['TITLE'] == 'devices.capabilities.on_off' or $prop['TITLE']=='local.online' and $prop['VALUE'] == 1){
		$properties[$prop['YADEVICE_ID']] = $prop['VALUE'];
	}
}
unset($properties_temp);
foreach($devices as $key=>$device){
    $skillId = $device['SKILL_ID'] ?: '__empty__';
    if (in_array($skillId, $hiddenSkills, true)) {
        unset($devices[$key]);
        continue;
    }
	$on = '';
	if(isset($properties[$device['ID']])){
		$devices[$key]["VALUE"] = 0;
		if($properties[$device['ID']] == 1){
			$on = '_on';
			$devices[$key]["VALUE"] = 1;
		}
	}
	if(stripos($device['DEVICE_TYPE'], 'devices.types.station') !== false) unset($devices[$key]["VALUE"]);
	$devices[$key]["ICON"] = $devices_URL[$device['DEVICE_TYPE'].$on] ?? 'https://yastatic.net/s3/pudya/app/_/cf97acc6d0252b23.webp';
    $devices[$key]["SKILL_NAME"] = htmlspecialchars(($device['SKILL_NAME'] ?? '') ?: ($skillsMap[$skillId] ?? 'Без навыка'));
    $devices[$key]["TITLE"] = htmlspecialchars($device['TITLE']);
    $devices[$key]["DISPLAY_TITLE"] = $devices[$key]["SKILL_NAME"] . ' -> ' . $devices[$key]["TITLE"];
    $devices[$key]["SEARCH_TEXT"] = htmlspecialchars(mb_strtolower($devices[$key]["DISPLAY_TITLE"] . ' ' . ($device['ORIGINAL_TITLE'] ?? '') . ' ' . $device['ROOM'] . ' ' . $device['HOUSE'], 'UTF-8'));
    $devices[$key]["DEVICE_TYPE"] = htmlspecialchars($device['DEVICE_TYPE']);
    $devices[$key]["ROOM"] = htmlspecialchars($device['ROOM']);
    $devices[$key]["HOUSE"] = htmlspecialchars($device['HOUSE']);
    $devices[$key]["IOT_ID"] = htmlspecialchars($device['IOT_ID']);
}
$devices = array_values($devices);
$out['DEVICE_COUNT'] = count($devices);
$out['HIDDEN_SKILL_COUNT'] = count($hiddenSkills);
if (!empty($devices[0]['ID'])) {
    $out['RESULT'] = $devices;
}
