<?php

/*
 * greetings to https://github.com/AlexxIT/YandexStation/ :)
 */

Define('YADEVICES_COOKIE_PATH', ROOT . "cms/yadevices/cookie.txt");
const GLAGOL_PORT = 1961;
const YADEVICES_X_TOKEN_CLIENT_ID = 'c0ebe342af7d48fbbbfcf2d2eedb8f9e';
const YADEVICES_X_TOKEN_CLIENT_SECRET = 'ad0a908f0aa341a182a37ecd75bc319e';
const YADEVICES_MUSIC_TOKEN_CLIENT_ID = '23cabbbdc6cd418abb4b39c32c41195d';
const YADEVICES_MUSIC_TOKEN_CLIENT_SECRET = '53bc75238f0c4d08a118e51fe9203300';
const YADEVICES_LOCAL_PLATFORMS = array(
    'yandexmini', 'yandexmicro', 'yandexstation', 'yandexstation_2', 'yandexmini_2',
    'jbl_link_portable', 'yandexmidi', 'cucumber', 'plum', 'bergamot', 'chiron',
    'pickle', 'yandexmodule', 'yandexmodule_2', 'yandex_tv', 'goya', 'magritte',
    'monet', 'orion'
);

spl_autoload_register(function ($class_name) {
    $path = DIR_MODULES . 'yadevices/' . $class_name . '.php';
    $path = str_replace('\\', '/', $path);
    @include_once $path;
});

use \WSSC\WebSocketClient;
use \WSSC\Components\ClientConfig;

require_once __DIR__ . '/lib/Catalog.php';
require_once __DIR__ . '/lib/Repository.php';
require_once __DIR__ . '/lib/Hints.php';

/**
 * YaDevices — Yandex Station and smart-home devices integration.
 *
 * The module keeps the legacy public API (`api()`, `refreshDevices()`,
 * `PropertySetHandle()`, the `yastations` / `yadevices` tables and the
 * `module/yadevices.html` player) while the admin UI is rendered with the
 * MajorDoMo design system (`md-*`).
 *
 * @package MajorDoMo\Modules\YaDevices
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 */
class yadevices extends module
{
    /** @var string */
    public $version = '2.0.0';

    /** @var string */
    private $lastAuthRefreshError = '';

    /** @var YaDevicesRepository */
    private $repository;

    /**
     * Module constructor.
     */
    public function __construct()
    {
        $this->name = 'yadevices';
        // Visual title only: the package, class, tables and API stay `yadevices`.
        $this->title = 'Я.Устройства';
        $this->module_category = '<#LANG_SECTION_DEVICES#>';
        $this->checkInstalled();
        $this->syncTitle();
        $this->repository = new YaDevicesRepository();
    }

    /**
     * Keep the menu label in sync with the visual module title.
     *
     * The module key (`yadevices`), class, tables and API are intentionally
     * unchanged; only the human-readable title is updated.
     *
     * @return void
     */
    private function syncTitle(): void
    {
        $row = SQLSelectOne("SELECT ID, TITLE FROM project_modules WHERE NAME='yadevices'");
        if (is_array($row) && !empty($row['ID']) && (string)$row['TITLE'] !== $this->title) {
            $row['TITLE'] = $this->title;
            SQLUpdate('project_modules', $row);
        }
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;

        global $station;
        global $update;
        global $zoom;
        global $bgcolor;
        global $textcolor;

        if (isset($station)) {
            $this->station = $station;
        }
        if (isset($update)) {
            $this->update = $update;
        }
        if (isset($zoom)) {
            $this->zoom = $zoom;
        }
        if (isset($bgcolor)) {
            $this->bgcolor = $bgcolor;
        }
        if (isset($textcolor)) {
            $this->textcolor = $textcolor;
        }

        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    public function run()
    {
        $out = array();
        $this->action = isset($this->action) ? (string)$this->action : '';
        $this->view_mode = isset($this->view_mode) ? (string)$this->view_mode : '';
        $this->mode = isset($this->mode) ? (string)$this->mode : '';
        $this->edit_mode = isset($this->edit_mode) ? (string)$this->edit_mode : '';
        $this->tab = isset($this->tab) ? (string)$this->tab : '';

        // Pretty URL / generic router: /panel/yadevices/<id>.html arrives as id only.
        $requestedId = isset($this->id) ? (int)$this->id : 0;
        if ($this->action === 'admin' && $this->view_mode === '' && $requestedId > 0) {
            $this->view_mode = 'edit_yastations';
        }

        if ($this->action === 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (isset($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (isset($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        $out['MODULE_VERSION'] = $this->version;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . '/' . $this->name . '.html', $this->data, $this);
        $this->result = $p->result;
    }

    function api($params)
    {
        $this->getConfig();
        $this->normalizeConfig();
        if (!empty($params['runscenario'])) {
            $this->runScenario($params['runscenario']);
        }
		if (!empty($params['getonline'])) {
            $this->onlineStations();
        }
        if (!empty($params['notify']) || !empty($params['group'])) {
            $command = $params['command'] ?? 'text';
            $data = $params['data'] ?? ($params['say'] ?? '');
            $groupName = $params['notify'] ?? $params['group'];
            $this->sendNotifyGroup($groupName, $command, $data);
            return;
        }
		
        //DebMes("API call: " . json_encode($params, JSON_UNESCAPED_UNICODE), 'yadevices');

        if (isset($params['station']) && (isset($params['command']) || isset($params['say']))) {
            if(!isset($params['command'])) $params['command'] = '';
            $station = SQLSelectOne("SELECT * FROM yastations WHERE ID=" . (int)$params['station']);
			
			//Облачная отправка
            $forceCloud = !empty($params['cloud']);
            if (($station['TTS'] == 2 && $station['IOT_ID'] != '') || $forceCloud) {
				if(!$this->ensureAuthorized()) return;
				if($params['command'] == 'setVolume') {
					$params['data'] = $params['volume'] ?? $params['data'];
					//У ТВСтанций от 1 до 100
					if($station['PLATFORM'] == "magritte" or $station['PLATFORM'] == "monet") $params['data'] *= 10;
				}
				else if (isset($params['say'])) { //для обратной совместимости
					$params['data'] = $params['say'];
					$params['command'] = 'phrase_action';
				}
				if(!isset($params['data'])) $params['data'] = '';
				$this->sendCommandToStationCloud($station, $params['command'], $params['data']);
			//Локальная отправка
            } else {
                $volumeBefore = null;
				if(isset($params['data']) and isset($params['volume'])){
					$volumeBefore = $params['volume'];
				}
                if ($params['command'] == 'setVolume') {
					if(isset($params['volume'])){
						(float)$params['data'] = is_float($params['volume']) ? $params['volume'] : $params['volume'] * 0.1;
					} else {
						(float)$params['data'] = $params['data'] * 0.1;
					}
				} else if($params['command'] == 'volumeUp' or $params['command'] == 'volumeDown'){
					$params['data'] = $params['command'];
				} else if (isset($params['say'])) { //для обратной совместимости
					$params['data'] = $params['say'];
					$params['command'] = 'text';
				} else if (!isset($params['data'])) {
                    $params['data'] = "";
                } else if ($params['data'] == 'volumeUp' or $params['data'] == 'volumeDown') {
					$params['command'] = $params['data'];
                }
                $sent = $this->sendCommandToStation($station, $params['command'], $params['data'], $volumeBefore);
                if (!$sent && $this->canFallbackToCloud($station, $params['command'])) {
                    $this->writeLog('Локальная отправка не выполнена, пробуем облако: ' . $station['TITLE']);
                    $this->sendCommandToStationCloud($station, $params['command'], $params['data']);
                }
            }
        }
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    /**
     * Admin controller.
     *
     * @param array<string,mixed> $out
     * @return void
     */
    public function admin(&$out)
    {
        $this->getConfig();
        $this->normalizeConfig();

        $authorized = !empty($this->config['AUTHORIZED']);
        $out['AUTHORIZED'] = $authorized ? 1 : 0;
        $out['HAS_COOKIE'] = file_exists(YADEVICES_COOKIE_PATH) ? 1 : 0;
        $out['X_TOKEN_SET'] = trim((string)$this->config['X_TOKEN']) !== '' ? 1 : 0;
        $out['OAUTH_TOKEN_SET'] = trim((string)$this->config['OAUTH_TOKEN']) !== '' ? 1 : 0;
        $out['API_USERNAME'] = $this->e($this->config['API_USERNAME']);
        $out['RELOAD_TIME'] = (int)$this->config['RELOAD_TIME'];
        $out['RELOAD_LABEL'] = $this->e(YaDevicesCatalog::reloadLabel((int)$this->config['RELOAD_TIME']));
        $out['FLASH_OK'] = $this->e((string)gr('ok_msg'));
        $out['FLASH_ERR'] = $this->e((string)gr('err_msg'));
        $out['FLASH_SAVED'] = (int)gr('saved');
        $out['FLASH_REFRESH'] = $this->e((string)gr('refresh'));
        $out['FLASH_LOGGED_OUT'] = (int)gr('logged_out');
        $out['FLASH_TOKEN'] = $this->e((string)gr('token'));
        $out['FLASH_SCENARIO'] = $this->e((string)gr('scenario'));
        $out['NAV_STATIONS'] = $this->repository->count('yastations');
        $out['NAV_DEVICES'] = $this->repository->count('yadevices');
        $id = isset($this->id) ? (int)$this->id : 0;

        if ($this->view_mode === 'logout') {
            $this->clearAuthFiles();
            $this->config['AUTHORIZED'] = 0;
            $this->config['X_TOKEN'] = '';
            $this->config['OAUTH_TOKEN'] = '';
            $this->saveConfig();
            $this->redirect('?logged_out=1');
        }

        if ($this->view_mode === 'update_settings' || $this->view_mode === 'update_settings_cycle') {
            $this->saveSettings();
        }

        if ($this->view_mode === 'refresh' || $this->mode === 'refresh') {
            if (!$authorized) {
                $this->redirect('?refresh=noauth');
            }
            $result = $this->refreshDevices();
            $this->addScenarios();
            $this->redirect('?refresh=' . ($result === false ? 'error' : 'ok'));
        }

        if ($this->view_mode === 'generate_dev_token') {
            $this->getDeviceTokenByHand($id);
        }

        if ($this->view_mode === 'delete_yastations') {
            $this->delete_yastations($id);
            $this->redirect('?ok_msg=' . urlencode('Станция удалена.'));
        }

        if ($this->view_mode === 'delete_yadevice') {
            $this->delete_yadevice($id);
            $this->redirect('?view_mode=search_yadevices&ok_msg=' . urlencode('Устройство удалено.'));
        }

        if ($this->view_mode === 'auth') {
            $this->auth($out);
            $out['HINT'] = YaDevicesHints::auth();
            return;
        }

        if ($this->view_mode === 'settings') {
            $this->settings($out);
            return;
        }

        if ($this->view_mode === 'about') {
            $this->about($out);
            return;
        }

        if ($this->view_mode === 'search_yadevices') {
            $this->searchYadevices($out);
            return;
        }

        if ($this->view_mode === 'search_scenarios') {
            $this->searchScenarios($out);
            return;
        }

        if ($this->view_mode === 'edit_yadevices') {
            $this->editYadevices($out, $id);
            return;
        }

        if ($this->view_mode === 'edit_yastations') {
            $this->editYastations($out, $id);
            return;
        }

        // Default route.
        $this->searchYastations($out);
    }

    function auth(&$out) {
        include_once(DIR_MODULES.'yadevices/auth.inc.php');
    }

	function receiveQuasar($data){
		if(($data['service'] ?? '') == 'alice-iot'){
			if(($data['operation'] ?? '') == 'update_states'){
				$message = json_decode($data['message'] ?? '', true);
				$devices = $message['updated_devices'] ?? array();
				foreach($devices as $device){
					//Получаем девайс из базы
					$rec_device = SQLSelectOne("SELECT * FROM yadevices WHERE IOT_ID = '" . dbSafe($device['id']) . "'");
					if(empty($rec_device['ID'])){
						//Если такого устройства нет, обновляем девайсы
						$this->refreshDevices();
						continue;
					}
					//добавим статус в массив для дальнейшей обработки
					if (isset($device['state']) && $device['state'] == 'online') {
						$currentStatus = 1;
					} else {
						$currentStatus = 0;
					}
					$onlineArray = [
						'type' => 'devices',
						'state' => [
							'value' => $currentStatus,
						],
						'parameters' => [
							'instance' => 'online',
						],
					];
					$device["properties"][] = $onlineArray;
					//Циклом пройдемся по всем умениям
					if (isset($device["capabilities"]) and is_array($device["capabilities"])) {
						foreach ($device["capabilities"] as $capabilitie) {
							if ($capabilitie['type'] == 'devices.capabilities.quasar.server_action') {
								$c_type = 'cloud.aswr_scenario';
							} else if ($capabilitie['type'] == 'devices.capabilities.on_off') {
								$c_type = $capabilitie['type'];
							} else {
								if (isset($capabilitie['state']['instance'])) {
									$c_type = $capabilitie['type'] . '.' . $capabilitie['state']['instance'];
								} else if (!empty($capabilitie['parameters']['instance'])) {
									$c_type = $capabilitie['type'] . '.' . $capabilitie['parameters']['instance'];
								} else {
									$c_type = $capabilitie['type'] . '.unknown';
								}
							}
							$req_skills = SQLSelectOne("SELECT * FROM yadevices_capabilities WHERE TITLE = '" . dbSafe($c_type) . "' AND YADEVICE_ID = '" . $rec_device['ID'] . "'");
							if(empty($req_skills['ID'])) {
                                $this->refreshDevices();
                                continue;
                            }
							//Основные умения, меняем значение
							$value = '?';
							if (isset($capabilitie['state']['value'])){
								if(is_bool($capabilitie['state']['value']) == true) {
									if ($capabilitie['state']['value'] == true) {
										$value = 1;
									} else {
										$value = 0;
									}
								} else if (isset($capabilitie['state']['instance'])){
									if($capabilitie['state']['instance']== 'color') {
										$value = $capabilitie['state']['value']['id'] ?? '';
									} else if ($capabilitie['state']['instance']== 'scene') {
										$value = $capabilitie['state']['value']['id'] ?? '';
									} else if ($capabilitie['state']['instance']== 'text_action') {
										$value = $capabilitie['state']['value'];
									} else {
										$value = $capabilitie['state']['value'];
									}
								} else {
									$value = $capabilitie['state']['value'];
								}  
							}
							//Ответы на сценарии обновляем всегда
							if ($c_type == 'cloud.aswr_scenario' or $value != ($req_skills['VALUE'] ?? '')) {
								$params['NEW_VALUE'] = $value;
								$params['OLD_VALUE'] = $req_skills['VALUE'] ?? '';
								$params['DEVICE_STATE'] = $currentStatus;
								$params['ALLOWPARAMS'] = $req_skills['ALLOWPARAMS'] ?? '';
								$params['UPDATED'] = date('Y-m-d H:i:s');
								$params['MODULE'] = $this->name;
								$this->setProperty($req_skills, $value, $params, $c_type);
								$req_skills['VALUE'] = $value;
								$req_skills['UPDATED'] = date('Y-m-d H:i:s');
								SQLUpdate('yadevices_capabilities', $req_skills);
							}
						}
					}
					//Значения датчиков
					if (isset($device["properties"]) && is_array($device["properties"])) {
						foreach ($device["properties"] as $propertie) {
							$p_type = $propertie['type'] . '.' . ($propertie['parameters']['instance'] ?? 'unknown');
							//Получаем по каждом свойству по отдельности
							$req_prop = SQLSelectOne("SELECT * FROM yadevices_capabilities WHERE TITLE = '" . dbSafe($p_type) . "' AND YADEVICE_ID = '" . $rec_device['ID'] . "'");
                            if (empty($req_prop['ID'])) {
                                $this->refreshDevices();
                                continue;
                            }
							//Основные датчики
							$value = $propertie['state']['value'] ?? '';
							if ($value != ($req_prop['VALUE'] ?? '')) {
								$params['NEW_VALUE'] = $value;
								$params['OLD_VALUE'] = $req_prop['VALUE'] ?? '';
								$params['DEVICE_STATE'] = $currentStatus;
								$params['ALLOWPARAMS'] = $req_prop['ALLOWPARAMS'] ?? '';
								$params['UPDATED'] = date('Y-m-d H:i:s');
								$params['MODULE'] = $this->name;
								$this->setProperty($req_prop, $value, $params, $p_type);
								$req_prop['VALUE'] = $value;
								$req_prop['UPDATED'] = date('Y-m-d H:i:s');
								SQLUpdate('yadevices_capabilities', $req_prop);
							}
						}
					}
				}
			} else {
				$this->writeLog($data);
				$this->writeLog('Не update-states');
			}
		} else {
			$this->writeLog($data);
			$this->writeLog('Не alice-iot');
		}
	}
	
    function refreshDevices()
    {
		$this->getConfig();
        $this->normalizeConfig();
		if(!$this->ensureAuthorized()) return false;
		$this->writeLog('Обновляем устройства.');
        $iot_ids = array();
        $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/v3/user/devices');
		if($data == 'Unauthorized') return false;
		if(!isset($data['status']) or $data['status'] != 'ok'){
			$this->writeLog('Ошибка получения списка устройсте', true);
			return false;
		}
		//Пройдемся по домам
		foreach(($data['households'] ?? array()) as $house){
			//Пройдёмся по всем устройствам в доме
			foreach(($house['all'] ?? array()) as $device){
				//Если это Станция
				if(preg_match('/^devices.types.smart_speaker/uis', $device['type'])) {
                    $this->loadQuasarInfo($device);
					$rec = SQLSelectOne("SELECT * FROM yastations WHERE IOT_ID='" . DBSafe($device['id']) . "'");
					$rec['OWNER'] = $this->config['API_USERNAME'];
                    $rec['ORIGINAL_TITLE'] = $device['name'];
                    if (empty($rec['CUSTOM_TITLE'])) {
                        $rec['TITLE'] = $device['name'];
                    }
					$rec['PLATFORM'] = $device['quasar_info']['platform'] ?? '';
					$rec['ICON_URL'] = $this->type2url($device['type']);
					$rec['STATION_ID'] = $device['quasar_info']['device_id'] ?? '';
					$rec['IS_ONLINE'] = ($device['state'] ?? '') == 'online' ? 1 : 0;
					foreach(($device['capabilities'] ?? array()) as $cap){
						if(isset($cap['state']['instance']) and $cap['state']['instance'] == 'volume') {
                            $rec['VOLUME'] = $cap['state']['value']['value'] ?? $cap['state']['value'] ?? 0;
                        }
					}
					$rec['UPDATED'] = date('Y-m-d H:i:s');
					if(empty($rec['ID'])) {
						$rec['IOT_ID'] = $device['id'];
						$rec['ID'] = SQLInsert('yastations', $rec);
					} else {
						SQLUpdate('yastations', $rec);
					}
					//Создадим Станцию
					$device_rec = SQLSelectOne("SELECT * FROM yadevices WHERE IOT_ID='" . $device['id'] . "'");
					if(empty($device_rec['ID'])) {
						$device_rec['TITLE'] = $device['name'];
                        $device_rec['ORIGINAL_TITLE'] = $device['name'];
                        $device_rec['CUSTOM_TITLE'] = 0;
						$device_rec['DEVICE_TYPE'] = str_replace('smart_speaker.yandex.', '', $device['type']);
                            $device_rec['HOUSE'] = $house['name'];
                            $device_rec['ROOM'] = $device['room_name'] ?? "";
                            $device_rec['SKILL_ID'] = 'local';
                            $device_rec['SKILL_NAME'] = 'Яндекс Станция';
                            $device_rec['UPDATED'] = date('Y-m-d H:i:s');
                            $device_rec['IOT_ID'] = $device['id'];
                            $device_rec['ID'] = SQLInsert('yadevices', $device_rec);
					} else{
						$update_station = false;
                        $device_rec['ORIGINAL_TITLE'] = $device['name'];
                        if (empty($device_rec['CUSTOM_TITLE']) && $device_rec['TITLE'] != $device['name']) {
                            $device_rec['TITLE'] = $device['name'];
                            $update_station = true;
                        }
						if($device_rec['HOUSE'] != $house['name']){
							$device_rec['HOUSE'] = $house['name'];
							$update_station = true;
						}
                        if($device_rec['SKILL_ID'] != 'local'){
                            $device_rec['SKILL_ID'] = 'local';
                            $update_station = true;
                        }
                        if(($device_rec['SKILL_NAME'] ?? '') != 'Яндекс Станция'){
                            $device_rec['SKILL_NAME'] = 'Яндекс Станция';
                            $update_station = true;
                        }
						if($device_rec['ROOM'] != ($device['room_name'] ?? "")){
							$device_rec['ROOM'] = $device['room_name'] ?? "";
							$update_station = true;
						}
						if($update_station){
							$device_rec['UPDATED'] = date('Y-m-d H:i:s');
							SQLUpdate('yadevices', $device_rec);
						}
					}
					
					//И умения и свойства Станции
					//Добавим локальные возможности
					$local = ['artist' => 'Исполнитель', 'track' => 'Название трека', 'cover' => 'Картинка альбома', 'text' => 'Алиса произнесёт текст', 'command' => 'Алиса выполнит команду', 'dialog' => 'Алиса произнесёт текст и будет ждать ответ', 'audio' => 'Ссылка на аудиофайл или поток', 'other' => 'Другие комады вида gif:URL', 'online' => 'Подключение к Станции установлено', "volume"=>'Громкость от 1 до 10'];
					foreach($local as $title => $desc){
						$c_rec = SQLSelectOne("SELECT * FROM yadevices_capabilities WHERE YADEVICE_ID=" . $device_rec['ID'] . " AND TITLE='" .'local.'. $title . "'");
						//Если нет такого умения
						if(empty($c_rec['ID'])) {
							$c_rec['YADEVICE_ID'] = $device_rec['ID'];
							$c_rec['TITLE'] = 'local.'.$title;
							$c_rec['ALLOWPARAMS'] = $desc;
							$c_rec['VALUE'] = '';
							if($title == 'artist' or $title == 'track' or $title == 'cover' or $title == 'online') $c_rec['READONLY'] = 1;
							else $c_rec['READONLY'] = 0;
							$c_rec['UPDATED'] = date('Y-m-d H:i:s');
							$c_rec['ID'] = SQLInsert('yadevices_capabilities', $c_rec);
						}
					}
					//Добавим облачные возможности
					$cloud = ['text' => 'Алиса произнесёт текст', 'command' => 'Алиса выполнит команду', 'aswr_scenario'=> 'Текст ответа на выполнение сценариев', 'online' => 'Станция онлайн'];
					foreach($cloud as $title => $desc){
						$c_rec = SQLSelectOne("SELECT * FROM yadevices_capabilities WHERE YADEVICE_ID=" . $device_rec['ID'] . " AND TITLE='" .'cloud.'. $title . "'");
						//Если нет такого умения
						if(empty($c_rec['ID'])) {
							$c_rec['YADEVICE_ID'] = $device_rec['ID'];
							$c_rec['TITLE'] = 'cloud.'.$title;
							$c_rec['ALLOWPARAMS'] = $desc;
							$c_rec['VALUE'] = '';
							if($title == 'artist' or $title == 'track' or $title == 'cover' or $title == 'aswr_scenario' or $title == 'online') $c_rec['READONLY'] = 1;
							else $c_rec['READONLY'] = 0;
							$c_rec['UPDATED'] = date('Y-m-d H:i:s');
							$c_rec['ID'] = SQLInsert('yadevices_capabilities', $c_rec);
						}
					}
					//Запоминаем, чтобы подтвердить актуальность устройства
					$iot_ids[] = $device['id'];
				//Если другое устройство
				} else {
					$device_rec = SQLSelectOne("SELECT * FROM yadevices WHERE IOT_ID='" . $device['id'] . "'");
                    $device_rec['ORIGINAL_TITLE'] = $device['name'];
                    if (empty($device_rec['CUSTOM_TITLE'])) {
                        $device_rec['TITLE'] = $device['name'];
                    }
					$device_rec['DEVICE_TYPE'] = $device['type'];
                    $device_rec['HOUSE'] = $house['name'];
                    $device_rec['ROOM'] = $device['room_name'] ?? "";
                    $device_rec['SKILL_ID'] = $device['skill_id'] ?? "";
                    $device_rec['SKILL_NAME'] = $device['skill_name'] ?? $device['skill']['name'] ?? $this->getSkillName($device_rec['SKILL_ID']);
                    $device_rec['UPDATED'] = date('Y-m-d H:i:s');
					if(empty($device_rec['ID'])) {
						$device_rec['IOT_ID'] = $device['id'];
                        $device_rec['CUSTOM_TITLE'] = 0;
						$device_rec['ID'] = SQLInsert('yadevices', $device_rec);
					} else {
						SQLUpdate('yadevices', $device_rec);
					}
				
					//Циклом пройдемся по всем умениям
					if(isset($device["capabilities"]) and is_array($device["capabilities"])) {
						foreach($device["capabilities"] as $capabilitie) {
							if($capabilitie['type'] == 'devices.capabilities.on_off') {
								$c_type = $capabilitie['type'];
							} else {
								if(!empty($capabilitie['state']['instance'])) {
									$c_type = $capabilitie['type'].'.'.$capabilitie['state']['instance'];
								} else if(!empty($capabilitie['parameters']['instance'])) {
									$c_type = $capabilitie['type'].'.'.$capabilitie['parameters']['instance'];
								} else {
									$c_type = $capabilitie['type'].'.unknown';
								}
							}
							
                            $value = '';
							if (isset($capabilitie['state']['value'])){
								if(is_bool($capabilitie['state']['value']) == true) {
									if ($capabilitie['state']['value'] == true) {
										$value = 1;
									} else {
										$value = 0;
									}
								} else if (isset($capabilitie['state']['instance'])){
								if($capabilitie['state']['instance']== 'color') {
									$value = $capabilitie['state']['value']['id'] ?? '';
								} else if ($capabilitie['state']['instance']== 'scene') {
									$value = $capabilitie['state']['value']['id'] ?? '';
									} else if ($capabilitie['state']['instance']== 'text_action') {
										$value = $capabilitie['state']['value'];
									} else {
										$value = $capabilitie['state']['value'];
									}
								} else {
									$value = $capabilitie['state']['value'];
								}  
							}
							if (is_null($value)) $value = 0;
				
							//Обработка для модов
							if(isset($capabilitie["parameters"]['modes']) and is_array($capabilitie["parameters"]['modes'])) {
								$allowparam = '';
								foreach($capabilitie["parameters"]['modes'] as $allowparams) {
									$allowparam .= ($allowparams['value'] ?? '').',';
								}
							} else if(isset($capabilitie["parameters"]['range']) and is_array($capabilitie["parameters"]['range'])) {
								$allowparam = 'От '.$capabilitie["parameters"]['range']['min'].' до '.$capabilitie["parameters"]['range']['max'].'. С шагом '.$capabilitie["parameters"]['range']['precision'].' ';
							} else if(isset($capabilitie["parameters"]['palette']) and is_array($capabilitie["parameters"]['palette'])) {
								$allowparam = '';
								foreach($capabilitie["parameters"]['palette'] as $allowparams) {
									$allowparam .= $allowparams['id'].', ';
								}
							} else {
								$allowparam = '';
							}
							
							//Запросим из БД текущие значения
							$c_rec = SQLSelectOne("SELECT * FROM yadevices_capabilities WHERE YADEVICE_ID=" . $device_rec['ID'] . " AND TITLE='" . $c_type . "'");
							
							if($allowparam) {
								$c_rec['ALLOWPARAMS'] = substr($allowparam,0,-1);
							}
							$c_rec['VALUE'] = $value;
							$c_rec['YADEVICE_ID'] = $device_rec['ID'];
							$c_rec['TITLE'] = $c_type;
							$c_rec['READONLY'] = 0;
							$c_rec['UPDATED'] = date('Y-m-d H:i:s');
							//Если нет такого умения
							if (empty($c_rec['ID'])) {
								$c_rec['ID'] = SQLInsert('yadevices_capabilities', $c_rec);
							} else {
								SQLUpdate('yadevices_capabilities', $c_rec);
							}
						}
					}
					//Значения датчиков
					if(isset($device["properties"]) and is_array($device["properties"])) {
						//Запихнем еще наш статус в массив
						$onlineArray = [
							'type' => 'devices.online',
							'state' => [
								'value' => ($device['state'] ?? '') == 'online' ? 1 : 0,
							],
						];
						$device["properties"][] = $onlineArray;
						foreach($device["properties"] as $propertie) {
							if($propertie['type'] == 'devices.online') {
								$p_type = $propertie['type'];
							} else {
								$p_type = $propertie['type'].'.'.($propertie['parameters']['instance'] ?? 'unknown');
							}
							$value = $propertie['state']['value'] ?? '';
				
							//Запросим из БД текущие значения
							$p_rec = SQLSelectOne("SELECT * FROM yadevices_capabilities WHERE YADEVICE_ID=" . $device_rec['ID'] . " AND TITLE='" . $p_type . "'");
							$p_rec['VALUE'] = $value;
							$p_rec['UPDATED'] = date('Y-m-d H:i:s');
							$p_rec['YADEVICE_ID'] = $device_rec['ID'];
							$p_rec['TITLE'] = $p_type;
							$p_rec['READONLY'] = 1;
							//Если нет такого умения
							if (empty($p_rec['ID'])) {
								$p_rec['ID'] = SQLInsert('yadevices_capabilities', $p_rec);
							} else {
								SQLUpdate('yadevices_capabilities', $p_rec);
							}
						}
					}
					$iot_ids[] = $device['id'];
				}
			}
		}
        $all_devices = SQLSelect("SELECT ID, IOT_ID, TITLE FROM yadevices WHERE IOT_ID!=''");
		//dprint($all_devices);
        $total = count($all_devices);
        for ($i = 0; $i < $total; $i++) {
            if (!in_array($all_devices[$i]['IOT_ID'], $iot_ids)) {
				$this->writeLog('Устройство'.$all_devices[$i]['TITLE'].' удалено.');
                $this->delete_yadevice($all_devices[$i]['ID']);
            }
        }
		$this->addScenarios();
		return $data['updates_url'] ?? '';
    }

    function loadQuasarInfo(&$device)
    {
        if (!empty($device['quasar_info']['device_id']) && !empty($device['quasar_info']['platform'])) {
            return true;
        }
        if (empty($device['id'])) {
            return false;
        }
        $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/devices/' . $device['id'] . '/configuration');
        if (is_array($data) && ($data['status'] ?? '') == 'ok' && !empty($data['quasar_info'])) {
            $device['quasar_info'] = $data['quasar_info'];
            return true;
        }
        return false;
    }

    function yandex_encode($in)
    {
        $in = strtolower($in);
        $MASK_EN = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', '-');
        $MASK_RU = array('о', 'е', 'а', 'и', 'н', 'т', 'с', 'р', 'в', 'л', 'к', 'м', 'д', 'п', 'у', 'я', 'ы');
        return 'мжд ' . str_replace($MASK_EN, $MASK_RU, $in);
    }

    function yandex_decode($in)
    {
        if (mb_substr($in, 0, 4) != 'мжд ') {
            return $in;
        }
        $in = mb_substr($in, 4);
        $MASK_EN = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', '-');
        $MASK_RU = array('о', 'е', 'а', 'и', 'н', 'т', 'с', 'р', 'в', 'л', 'к', 'м', 'д', 'п', 'у', 'я', 'ы');
        return str_replace($MASK_RU, $MASK_EN, $in);
    }

    function yandexScenarioTrigger($iot_id)
    {
        return mb_substr($this->yandex_encode($iot_id), 4);
    }

    function buildCloudScenarioPayload($iot_id, $phrase, $action = 'phrase_action')
    {
        $capability = array(
            'type' => 'devices.capabilities.quasar',
            'state' => array(
                'instance' => 'tts',
                'value' => array(
                    'text' => $phrase
                )
            )
        );

        if ($action == 'text_action') {
            $capability = array(
                'type' => 'devices.capabilities.quasar.server_action',
                'state' => array(
                    'instance' => 'text_action',
                    'value' => $phrase
                )
            );
        }

        $nameEncode = $this->yandex_encode($iot_id);
        return array(
            'name' => $nameEncode,
            'icon' => 'home',
            'triggers' => array(array(
                'trigger' => array(
                    'type' => 'scenario.trigger.voice',
                    'value' => $this->yandexScenarioTrigger($iot_id),
                )
            )),
            'steps' => array(array(
                'type' => 'scenarios.steps.actions.v2',
                'parameters' => array(
                    'items' => array(array(
                        'id' => $iot_id,
                        'type' => 'step.action.item.device',
                        'value' => array(
                            'id' => $iot_id,
                            'item_type' => 'device',
                            'capabilities' => array($capability)
                        )
                    ))
                )
            ))
        );
    }

    function ensureCloudScenario($station)
    {
        if (empty($station['IOT_ID'])) {
            return '';
        }
        if (!empty($station['TTS_SCENARIO'])) {
            return $station['TTS_SCENARIO'];
        }

        $iot_id = $station['IOT_ID'];
        $trigger = $this->yandexScenarioTrigger($iot_id);
        $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
        if (isset($data['scenarios']) && is_array($data['scenarios'])) {
            foreach ($data['scenarios'] as $scenario) {
                $scenarioTrigger = '';
                if (!empty($scenario['triggers'][0]['value'])) {
                    $scenarioTrigger = $scenario['triggers'][0]['value'];
                } elseif (!empty($scenario['triggers'][0]['trigger']['value'])) {
                    $scenarioTrigger = $scenario['triggers'][0]['trigger']['value'];
                }

                if ($this->yandex_decode($scenario['name'] ?? '') == strtolower($iot_id) || $scenarioTrigger == $trigger) {
                    $station['TTS_SCENARIO'] = $scenario['id'];
                    SQLUpdate('yastations', $station);
                    return $station['TTS_SCENARIO'];
                }
            }
        }

        $payload = $this->buildCloudScenarioPayload($iot_id, 'Сценарий для MajorDoMo. Не удалять.', 'phrase_action');
        $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/v4/user/scenarios', 'POST', $payload);
        if (is_array($result) && ($result['status'] ?? '') == 'ok') {
            $station['TTS_SCENARIO'] = $result['scenario_id'] ?? ($result['id'] ?? '');
            if (!empty($station['TTS_SCENARIO'])) {
                SQLUpdate('yastations', $station);
                return $station['TTS_SCENARIO'];
            }
        }

        $this->writeLog('Ошибка создания сценария Cloud TTS. Ответ Яндекса: ' . json_encode($result, JSON_UNESCAPED_UNICODE), true);
        return '';
    }

    function addScenarios($repeating = 0)
    {
        $some_added = 0;
        $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
        $scenarios = array();

        if (isset($data['scenarios']) && is_array($data['scenarios'])) {
            foreach ($data['scenarios'] as $scenario) {
                $scenarios[$this->yandex_decode($scenario['name'])] = $scenario;
            }
        }
        $stations = SQLSelect("SELECT * FROM yastations ORDER BY ID");
        foreach ($stations as $station) {
            $station_id = $station['IOT_ID'];
            if ($station_id == '') {
                continue;
            }
            if (!isset($scenarios[strtolower($station_id)])) {
                // add scenario
                $payload = $this->buildCloudScenarioPayload($station_id, 'Сценарий для MajorDoMo. Не удалять.', 'phrase_action');
                
                //dprint($payload, 0);
                $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/v4/user/scenarios', 'POST', $payload);
                //dprint($result, 0);
                if (isset($result['status']) && $result['status'] == 'ok') {
                    $some_added = 1;
                }
            } else {
                $station['TTS_SCENARIO'] = $scenarios[strtolower($station_id)]['id'];
                SQLUpdate('yastations', $station);
            }
        }
        if ($some_added && !$repeating) {
            $this->addScenarios(1);
        }
    }


    function runScenario($scenario_id)
    {
        $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios/' . $scenario_id . '/actions', 'POST', array());

        if (!is_array($result) || ($result["status"] ?? '') == 'error') {
            $this->writeLog('Ошибка запуска сценария. Ответ от Яндекс: ' . (($result["message"] ?? '') ?: json_encode($result, JSON_UNESCAPED_UNICODE)), true);
        } else {
            $this->writeLog('Запрошено выполнение сценария: ' . ($result["request_id"] ?? ''));
        }

        return $result;
    }

    function delScenario($scenario_id)
    {
        $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios/' . $scenario_id, 'DELETE');

        if (!is_array($result) || ($result["status"] ?? '') == 'error') {
            $this->writeLog('Ошибка удаления сценария. Ответ от Яндекс: ' . (($result["message"] ?? '') ?: json_encode($result, JSON_UNESCAPED_UNICODE)), true);
        } else {
            $this->writeLog('Запрошено удаление сценария: ' . ($result["request_id"] ?? ''));
        }

        return $result;
    }

    function sendCloudTTS($iot_id, $phrase, $action = 'phrase_action')
    {
        $station_rec = SQLSelectOne("SELECT * FROM yastations WHERE IOT_ID='" . DBSafe($iot_id) . "'");
		$phrase = preg_replace('/\^.*/u', '', $phrase);
        $phrase = preg_replace('/\s+/u', ' ', $phrase);
		$phrase = trim($phrase);

        if (mb_strlen($phrase, 'UTF-8') >= 100) {
            $phrase = mb_substr($phrase, 0, 99, 'UTF-8');
        }
        $this->writeLog("Отправка в облако '$action: $phrase' на " . $station_rec['TITLE']);


        //$action = 'phrase';
        //phrase_action - просто сказать и не ждать
        //text_action - выполнит команду

        $scenario_id = $this->ensureCloudScenario($station_rec);
        if (!$scenario_id) return false;

        $payload = $this->buildCloudScenarioPayload($iot_id, $phrase, $action);
        $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/v4/user/scenarios/' . $scenario_id, 'PUT', $payload);
        if ((!is_array($result) || ($result['status'] ?? '') != 'ok') && !empty($station_rec['TTS_SCENARIO'])) {
            $this->writeLog('Старый Cloud TTS сценарий не обновился, пробуем пересоздать: ' . json_encode($result, JSON_UNESCAPED_UNICODE), true);
            $oldScenarioId = $station_rec['TTS_SCENARIO'];
            $station_rec['TTS_SCENARIO'] = '';
            SQLUpdate('yastations', $station_rec);
            $scenario_id = $this->ensureCloudScenario($station_rec);
            if ($scenario_id && $scenario_id != $oldScenarioId) {
                $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/v4/user/scenarios/' . $scenario_id, 'PUT', $payload);
            }
        }

        if (is_array($result) && ($result['status'] ?? '') == 'ok') {
            $payload = array();
            $result = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios/' . $scenario_id . '/actions', 'POST', $payload);
            if (is_array($result) && ($result['status'] ?? '') == 'ok') {
                return true;
            } else {
                $this->writeLog("Ошибка вызова сценария для запуска Cloud TTS. Ошибка: " . json_encode($result, JSON_UNESCAPED_UNICODE), true);
            }
        } else {
            $this->writeLog("Ошибка обновления сценария для запуска Cloud TTS. Ошибка: " . json_encode($result, JSON_UNESCAPED_UNICODE), true);
        }
        return false;
    }

    function onlineStations()
    {
        $data = $this->apiRequest('https://quasar.yandex.ru/devices_online_stats');
        if (isset($data['items']) and is_array($data['items'])) {
            $items = $data['items'];
            foreach ($items as $item) {
				//Исключаем приложения на телефоне и ТВ
				if(($item['platform'] ?? '') == 'alice_app_ios' or ($item['platform'] ?? '') == 'iot_app_ios' or ($item['platform'] ?? '') == 'iot_app_android' or ($item['platform'] ?? '') == 'yandex_tv_mt6681_cv') continue;
                $rec = SQLSelectOne("SELECT * FROM yastations WHERE STATION_ID='" . DBSafe($item['id'] ?? '') . "'");
                if (empty($rec['ID'])) {
                    continue;
                }
                $rec['UPDATED'] = date('Y-m-d H:i:s');
                /*$rec['OWNER'] = $this->config['API_USERNAME'];
                $rec['TITLE'] = $item['name'];
                $rec['ICON_URL'] = $item['icon'];
                $rec['PLATFORM'] = $item['platform'];
				*/
				if($rec['IS_ONLINE'] != (int)($item['online'] ?? 0)){
					$rec['IS_ONLINE'] = (int)($item['online'] ?? 0);
					SQLUpdate('yastations', $rec);
					$params['NEW_VALUE'] = (int)($item['online'] ?? 0);
					$property = SQLSelectOne("SELECT yadevices_capabilities.* FROM yadevices_capabilities LEFT JOIN yadevices ON yadevices_capabilities.YADEVICE_ID=yadevices.ID WHERE yadevices.IOT_ID LIKE '" . $rec['IOT_ID'] . "' AND yadevices_capabilities.TITLE LIKE 'cloud.online'");
                    if (!empty($property['ID'])) {
                        $this->setProperty($property, (int)($item['online'] ?? 0), $params);
                        $property['VALUE'] = (int)($item['online'] ?? 0);
                        $property['UPDATED'] = date('Y-m-d H:i:s');
                        SQLUpdate('yadevices_capabilities', $property);
                    }
					//Отправляем плееру в ws, если станция пропала
					postToWebSocket('YADEVICES_ONLINE_'.$rec['ID'], ['online'=>(int)($item['online'] ?? 0)], 'PostEvent');
				}
            }
        }
    }

	 //Запись в привязанное свойство/метод
	function setProperty($device, $value, $params = [], $type = ''){
		if (!empty($device['LINKED_OBJECT']) && !empty($device['LINKED_PROPERTY'])) {
			setGlobal($device['LINKED_OBJECT'] . '.' . $device['LINKED_PROPERTY'], $value, array($this->name=>1), $this->name . '.' . $type);
		}
		if (!empty($device['LINKED_OBJECT']) && !empty($device['LINKED_METHOD'])) {
			$params['VALUE'] = $value;
			callMethodSafe($device['LINKED_OBJECT'] . '.' . $device['LINKED_METHOD'], $params);
		}
	}
	
    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        //$this->admin($out);
		//dprint($out, 0);
		//авторизация через QR
		$check_qr_status = gr('check_qr_status');
		if($check_qr_status){
			http_response_code(200);
			header('Content-Type: application/json; charset=utf-8');
			$use_cookie_file = YADEVICES_COOKIE_PATH.'_qr';
			$csrf_token = gr('csrf_token');
			$auth = urldecode(gr('auth'));
			$headers = ["X-CSRF-Token: ".$csrf_token];
            $result = $this->curl('https://passport.yandex.ru/pwl-yandex/api/passport/auth/magic/code/status', $use_cookie_file, array_merge($headers, ["Content-Type: application/json"]), $auth, [CURLOPT_COOKIEFILE=>$use_cookie_file, CURLOPT_COOKIEJAR=>$use_cookie_file, CURLOPT_USERAGENT => $this->getPassportAuthUserAgent()]);
            $data = json_decode($result, true);
			if(($data["state"] ?? '') != "otp_auth_finished"){
				echo '{"state": "waiting"}';
				exit;
			}
			$post = http_build_query(["track_id" => $data['trackId'] ?? '']);
			$result = $this->curl('https://passport.yandex.ru/pwl-yandex/api/passport/sessions/get_session', $use_cookie_file, $headers, $post, [CURLOPT_COOKIEFILE=>$use_cookie_file, CURLOPT_COOKIEJAR=>$use_cookie_file, CURLOPT_USERAGENT => $this->getPassportAuthUserAgent()]);
            $data = json_decode($result, true);
			//dprint($result,0);
			rename($use_cookie_file, YADEVICES_COOKIE_PATH);
			$checkCookie = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
            if (!is_array($checkCookie) || ($checkCookie['status'] ?? '') != 'ok') {
                @unlink(YADEVICES_COOKIE_PATH);
                echo '{"state": "auth_error"}';
            } else {
				$this->completeAuthFromCookies();
                echo '{"state": "otp_auth_finished"}';
            }
			exit;
		}
        //Функции плеера
		$station = (int)gr('station');
		if ($station <= 0) {
            $station = (int)($this->id ?? $this->station ?? 0);
        }
		$out['STATION_ID'] = $station;

		//$bgcolor = gr('bgcolor');
        $bgcolor = $this->bgcolor ?? '0,0,0';
        $out['BGCOLOR'] = $bgcolor;


        $textcolor = $this->textcolor ?? 'white';
        $out['TEXTCOLOR'] = $textcolor;

        $zoom = $this->zoom ?? '';
        $out['ZOOM_PLAYER'] = $zoom;

        $ajax = gr('ajax');

        $rec = SQLSelectOne("SELECT * FROM yastations WHERE ID = " . $station);
		if (!$rec) {
            http_response_code(404);
            die();
        }
		$out['TITLE'] = $this->e((string)$rec['TITLE']);
        $out['VOLUME'] = (int)($rec['VOLUME'] ?? 0);
		
        if ($ajax && $station > 0) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            $command = (string)gr('control');
            if ($command !== '' && preg_match('/^[a-zA-Z]+$/', $command)) {
                $sent = $this->sendCommandToStation($rec, $command);
                echo json_encode(array('status' => $sent ? 'ok' : 'error'));
            } else {
				usleep(200000);
				$this->sendCommandToStation($rec, 'playerState');
                echo json_encode(array('status' => 'ok'));
            }
            exit;
        }
    }

    /**
     * Stations list.
     *
     * @param array<string,mixed> $out
     * @return void
     */
    private function searchYastations(array &$out): void
    {
        $rows = $this->repository->stations();
        $online = 0;
        $localReady = 0;
        $cloudReady = 0;
        $ttsEnabled = 0;
        $platforms = array();

        foreach ($rows as $index => $row) {
            $id = (int)$row['ID'];
            $platform = (string)($row['PLATFORM'] ?? '');
            $isOnline = (int)($row['IS_ONLINE'] ?? 0) === 1;
            $localCapable = YaDevicesCatalog::isLocalPlatform($platform);
            $localAvailable = $localCapable
                && trim((string)($row['IP'] ?? '')) !== ''
                && trim((string)($row['DEVICE_TOKEN'] ?? '')) !== '';
            $cloudAvailable = !empty($this->config['AUTHORIZED']) && trim((string)($row['IOT_ID'] ?? '')) !== '';
            $tts = (int)($row['TTS'] ?? 0);

            if ($isOnline) {
                $online++;
            }
            if ($localAvailable) {
                $localReady++;
            }
            if ($cloudAvailable) {
                $cloudReady++;
            }
            if ($tts > 0) {
                $ttsEnabled++;
            }
            if ($platform !== '') {
                $platforms[$platform] = YaDevicesCatalog::platformTitle($platform);
            }

            $rows[$index]['TITLE'] = $this->e((string)($row['TITLE'] ?: $row['ORIGINAL_TITLE'] ?: ('Станция #' . $id)));
            $rows[$index]['ORIGINAL_TITLE'] = $this->e((string)($row['ORIGINAL_TITLE'] ?? ''));
            $rows[$index]['PLATFORM_TITLE'] = $this->e(YaDevicesCatalog::platformTitle($platform));
            $rows[$index]['PLATFORM'] = $this->e($platform);
            $rows[$index]['ICON_TI'] = YaDevicesCatalog::deviceIcon('devices.types.smart_speaker.' . $platform);
            $rows[$index]['ICON_URL'] = $this->e(YaDevicesCatalog::deviceIconUrl('devices.types.smart_speaker.' . $platform, false));
            $rows[$index]['ONLINE_LABEL'] = $isOnline ? 'online' : 'offline';
            $rows[$index]['ONLINE_TONE'] = $isOnline ? 'success' : 'offline';
            $rows[$index]['LOCAL_CAPABLE'] = $localCapable ? 1 : 0;
            $rows[$index]['LOCAL_READY'] = $localAvailable ? 1 : 0;
            $rows[$index]['CLOUD_READY'] = $cloudAvailable ? 1 : 0;
            $rows[$index]['TTS'] = $tts;
            $rows[$index]['TTS_LABEL'] = $tts === 2 ? 'cloud TTS' : ($tts === 1 ? 'local TTS' : 'TTS off');
            $rows[$index]['TTS_TONE'] = $tts === 2 ? 'info' : ($tts === 1 ? 'success' : 'neutral');
            $rows[$index]['VOLUME'] = max(0, min(100, (int)($row['VOLUME'] ?? 0)));
            $rows[$index]['PLAYING'] = (int)($row['PLAYING'] ?? 0) === 1 ? 1 : 0;
            $rows[$index]['TRACK'] = $this->e((string)($row['TRACK'] ?? ''));
            $rows[$index]['ARTIST'] = $this->e((string)($row['ARTIST'] ?? ''));
            $rows[$index]['COVER'] = $this->e((string)($row['COVER'] ?? ''));
            $rows[$index]['STATION_ID'] = $this->e((string)($row['STATION_ID'] ?? ''));
            $rows[$index]['IOT_ID'] = $this->e((string)($row['IOT_ID'] ?? ''));
            $rows[$index]['UPDATED'] = $this->e((string)($row['UPDATED'] ?? ''));
            $rows[$index]['PLAYER_URL'] = ROOTHTML . 'module/yadevices.html?station=' . $id;
            $rows[$index]['EDIT_URL'] = '?view_mode=edit_yastations&id=' . $id;
            $rows[$index]['SEARCH'] = $this->e(mb_strtolower(
                implode(' ', array(
                    (string)($row['TITLE'] ?? ''),
                    (string)($row['ORIGINAL_TITLE'] ?? ''),
                    $platform,
                    (string)($row['STATION_ID'] ?? ''),
                    (string)($row['IP'] ?? ''),
                    (string)($row['TRACK'] ?? ''),
                    (string)($row['ARTIST'] ?? ''),
                )),
                'UTF-8'
            ));
        }

        $out['RESULT'] = $rows;
        $out['STATIONS_TOTAL'] = count($rows);
        $out['STATIONS_ONLINE'] = $online;
        $out['STATIONS_LOCAL_READY'] = $localReady;
        $out['STATIONS_CLOUD_READY'] = $cloudReady;
        $out['STATIONS_TTS'] = $ttsEnabled;
        $out['DEVICES_TOTAL'] = $this->repository->count('yadevices');
        $out['CAPABILITIES_TOTAL'] = $this->repository->count('yadevices_capabilities');
        $out['PLATFORM_OPTIONS'] = $this->platformOptions($platforms);
        $out['HINT'] = YaDevicesHints::main(!empty($this->config['AUTHORIZED']), file_exists(YADEVICES_COOKIE_PATH));
        $out['ACTIVE_TAB'] = 'stations';
    }

    /**
     * @param array<string,string> $platforms
     * @return array<int,array{TITLE:string,VALUE:string}>
     */
    private function platformOptions(array $platforms): array
    {
        $options = array();
        foreach ($platforms as $value => $title) {
            $options[] = array('VALUE' => $this->e($value), 'TITLE' => $this->e($title));
        }
        usort($options, static function (array $left, array $right): int {
            return strcasecmp($left['TITLE'], $right['TITLE']);
        });

        return $options;
    }

    /**
     * Devices list.
     *
     * @param array<string,mixed> $out
     * @return void
     */
    private function searchYadevices(array &$out): void
    {
        if ($this->mode === 'switch') {
            $iotId = trim((string)gr('iot_id'));
            $current = (int)gr('value');
            $value = $current === 1 ? 0 : 1;
            if ($iotId !== '') {
                $this->sendValueToYandex($iotId, 'devices.capabilities.on_off', $value);
                usleep(500000);
            }
            $this->redirect('?view_mode=search_yadevices');
        }

        $rows = $this->repository->devices();
        $capabilities = $this->repository->capabilities();
        $byDevice = array();
        $linkedByDevice = array();
        $onByDevice = array();
        $toggleByDevice = array();
        $stateByDevice = array();
        foreach ($capabilities as $capability) {
            $deviceId = (int)($capability['YADEVICE_ID'] ?? 0);
            if ($deviceId <= 0) {
                continue;
            }
            $byDevice[$deviceId][] = $capability;
            if (trim((string)($capability['LINKED_OBJECT'] ?? '')) !== ''
                && trim((string)($capability['LINKED_PROPERTY'] ?? '')) !== '') {
                $linkedByDevice[$deviceId] = ($linkedByDevice[$deviceId] ?? 0) + 1;
            }
            $title = (string)($capability['TITLE'] ?? '');
            if ($title === 'devices.capabilities.on_off') {
                $toggleByDevice[$deviceId] = true;
                $onByDevice[$deviceId] = (int)($capability['VALUE'] ?? 0) === 1 ? 1 : 0;
            } elseif ($title === 'local.online' && !isset($onByDevice[$deviceId])) {
                $onByDevice[$deviceId] = (int)($capability['VALUE'] ?? 0) === 1 ? 1 : 0;
            }
            if (!isset($stateByDevice[$deviceId]) && strpos($title, 'devices.properties.') === 0
                && trim((string)($capability['VALUE'] ?? '')) !== '') {
                $stateByDevice[$deviceId] = (string)$capability['VALUE'];
            }
        }

        $skillsMap = array();
        foreach ($rows as $row) {
            $skillId = trim((string)($row['SKILL_ID'] ?? ''));
            $key = $skillId !== '' ? $skillId : '__empty__';
            if (!isset($skillsMap[$key])) {
                $skillsMap[$key] = array(
                    'ID' => $key,
                    'TITLE' => $this->skillLabel($skillId, (string)($row['SKILL_NAME'] ?? '')),
                    'COUNT' => 0,
                );
            }
            $skillsMap[$key]['COUNT']++;
        }

        $hidden = json_decode((string)($this->config['HIDDEN_SKILLS'] ?? '[]'), true);
        $hidden = is_array($hidden) ? array_map('strval', $hidden) : array();

        if ($this->mode === 'save_skill_filter') {
            $visible = isset($_POST['visible_skills']) && is_array($_POST['visible_skills'])
                ? array_map('strval', $_POST['visible_skills'])
                : array();
            $newHidden = array();
            foreach ($skillsMap as $key => $skill) {
                if (!in_array($key, $visible, true)) {
                    $newHidden[] = $key;
                }
            }
            $this->config['HIDDEN_SKILLS'] = json_encode(array_values($newHidden));
            $this->saveConfig();
            $this->redirect('?view_mode=search_yadevices&saved=1');
        }

        $visibleRows = array();
        $onCount = 0;
        $linkedDevices = 0;
        $visibleSkills = array();
        foreach ($rows as $index => $row) {
            $deviceId = (int)$row['ID'];
            $skillId = trim((string)($row['SKILL_ID'] ?? ''));
            $key = $skillId !== '' ? $skillId : '__empty__';
            $visibleSkills[$key] = true;
            if (in_array($key, $hidden, true)) {
                continue;
            }

            $deviceCaps = $byDevice[$deviceId] ?? array();
            $linked = (int)($linkedByDevice[$deviceId] ?? 0);
            $isOn = (int)($onByDevice[$deviceId] ?? 0);
            if ($isOn === 1) {
                $onCount++;
            }
            if ($linked > 0) {
                $linkedDevices++;
            }

            $deviceType = (string)($row['DEVICE_TYPE'] ?? '');
            $visibleRows[] = array(
                'ID' => $deviceId,
                'TITLE' => $this->e((string)($row['TITLE'] ?: $row['ORIGINAL_TITLE'] ?: ('Устройство #' . $deviceId))),
                'ORIGINAL_TITLE' => $this->e((string)($row['ORIGINAL_TITLE'] ?? '')),
                'SKILL_NAME' => $this->e($this->skillLabel($skillId, (string)($row['SKILL_NAME'] ?? ''))),
                'SKILL_ID' => $this->e($skillId),
                'DEVICE_TYPE' => $this->e($deviceType),
                'TYPE_TITLE' => $this->e(YaDevicesCatalog::deviceTypeTitle($deviceType)),
                'ICON_TI' => YaDevicesCatalog::deviceIcon($deviceType),
                'ICON_URL' => $this->e(YaDevicesCatalog::deviceIconUrl($deviceType, $isOn === 1)),
                'ROOM' => $this->e((string)($row['ROOM'] ?? '')),
                'HOUSE' => $this->e((string)($row['HOUSE'] ?? '')),
                'IOT_ID' => $this->e((string)($row['IOT_ID'] ?? '')),
                'UPDATED' => $this->e((string)($row['UPDATED'] ?? '')),
                'VALUE' => $isOn,
                'CAN_TOGGLE' => !empty($toggleByDevice[$deviceId]) ? 1 : 0,
                'STATE_TEXT' => $this->e((string)($stateByDevice[$deviceId] ?? '')),
                'CAPS_COUNT' => count($deviceCaps),
                'LINKED_COUNT' => $linked,
                'HAS_LINKS' => $linked > 0 ? 1 : 0,
                'IS_STATION' => stripos($deviceType, 'devices.types.smart_speaker') !== false ? 1 : 0,
                'SEARCH' => $this->e(mb_strtolower(
                    implode(' ', array(
                        (string)($row['TITLE'] ?? ''),
                        (string)($row['ORIGINAL_TITLE'] ?? ''),
                        $deviceType,
                        (string)($row['ROOM'] ?? ''),
                        (string)($row['HOUSE'] ?? ''),
                        (string)($row['SKILL_NAME'] ?? ''),
                        (string)($row['IOT_ID'] ?? ''),
                    )),
                    'UTF-8'
                )),
                'EDIT_URL' => '?view_mode=edit_yadevices&id=' . $deviceId,
            );
        }

        $skills = array();
        foreach ($skillsMap as $key => $skill) {
            $skills[] = array(
                'ID' => $this->e($key),
                'TITLE' => $this->e($skill['TITLE']),
                'COUNT' => (int)$skill['COUNT'],
                'CHECKED' => in_array($key, $hidden, true) ? 0 : 1,
            );
        }
        usort($skills, static function (array $left, array $right): int {
            return strcasecmp($left['TITLE'], $right['TITLE']);
        });

        $out['RESULT'] = $visibleRows;
        $out['DEVICES_TOTAL'] = count($visibleRows);
        $out['DEVICES_ALL'] = count($rows);
        $out['DEVICES_ON'] = $onCount;
        $out['DEVICES_LINKED'] = $linkedDevices;
        $out['SKILLS'] = $skills;
        $out['SKILLS_TOTAL'] = count($skills);
        $out['HIDDEN_SKILL_COUNT'] = count($hidden);
        $out['HINT'] = YaDevicesHints::devices();
        $out['ACTIVE_TAB'] = 'devices';
    }

    /**
     * @param string $skillId
     * @param string $stored
     * @return string
     */
    private function skillLabel(string $skillId, string $stored = ''): string
    {
        if ($stored !== '') {
            return $stored;
        }
        if ($skillId === 'local') {
            return 'Яндекс Станция';
        }
        if ($skillId === '') {
            return 'Без навыка';
        }

        return $skillId;
    }

    function getSkillName($skill_id)
    {
        static $cache = array();
        if ($skill_id == '') {
            return 'Без навыка';
        }
        if ($skill_id == 'local') {
            return 'Яндекс Станция';
        }
        if (isset($cache[$skill_id])) {
            return $cache[$skill_id];
        }

        $skill = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/skills/' . $skill_id);
        $cache[$skill_id] = $skill['name'] ?? $skill_id;
        return $cache[$skill_id];
    }

    /**
     * Cloud scenarios list.
     *
     * @param array<string,mixed> $out
     * @return void
     */
    private function searchScenarios(array &$out): void
    {
        $scenarioId = isset($this->id) ? trim((string)$this->id) : '';
        if ($this->mode === 'runScenario' && $scenarioId !== '') {
            $this->runScenario($scenarioId);
            $this->redirect('?view_mode=search_scenarios&scenario=run');
        }
        if ($this->mode === 'delScenario' && $scenarioId !== '') {
            $this->delScenario($scenarioId);
            $this->redirect('?view_mode=search_scenarios&scenario=deleted');
        }

        $out['RESULT'] = array();
        $out['RESULT_TIMER'] = array();
        $out['SCENARIO_ERROR'] = '';
        $out['SCENARIOS_TOTAL'] = 0;
        $out['SCENARIOS_ACTIVE'] = 0;
        $out['SCENARIOS_CLOUD'] = 0;
        $out['HINT'] = YaDevicesHints::scenarios();
        $out['ACTIVE_TAB'] = 'scenarios';

        if (empty($this->config['AUTHORIZED'])) {
            $out['SCENARIO_ERROR'] = 'auth';
            return;
        }

        $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/user/scenarios');
        if (!is_array($data)) {
            $out['SCENARIO_ERROR'] = 'api';
            return;
        }

        $scenarios = isset($data['scenarios']) && is_array($data['scenarios']) ? $data['scenarios'] : array();
        $result = array();
        foreach ($scenarios as $scenario) {
            $name = (string)($scenario['name'] ?? '');
            $isCloud = strpos($name, 'мжд ') !== false;
            $isActive = !empty($scenario['is_active']);
            $devices = isset($scenario['devices']) && is_array($scenario['devices']) ? $scenario['devices'] : array();
            $result[] = array(
                'ID' => $this->e((string)($scenario['id'] ?? '')),
                'NAME' => $this->e($name),
                'IS_ACTIVE' => $isActive ? 'Активен' : 'Отключён',
                'ACTIVE_TONE' => $isActive ? 'success' : 'neutral',
                'CLOUDSCENARIO' => $isCloud ? 1 : 0,
                'ICON_TI' => $isCloud ? 'cloud' : 'wand',
                'ALLOWDEVICES' => $this->e(implode(', ', array_map('strval', $devices))),
                'DEVICES_COUNT' => count($devices),
            );
            if ($isActive) {
                $out['SCENARIOS_ACTIVE']++;
            }
            if ($isCloud) {
                $out['SCENARIOS_CLOUD']++;
            }
        }

        $timers = array();
        if (isset($data['onetime_scenarios']) && is_array($data['onetime_scenarios'])) {
            foreach ($data['onetime_scenarios'] as $scenario) {
                $timers[] = array(
                    'NAME' => $this->e((string)($scenario['name'] ?? '')),
                    'SCHEDULED_TIME_HUMAN' => !empty($scenario['current_timer_value'])
                        ? date('d.m.Y H:i', time() + (int)$scenario['current_timer_value'])
                        : '—',
                );
            }
        }

        $out['RESULT'] = $result;
        $out['RESULT_TIMER'] = $timers;
        $out['SCENARIOS_TOTAL'] = count($result);
        $out['HINT'] = YaDevicesHints::scenarios();
    }

    /**
     * Legacy public entry point.
     *
     * @param array<string,mixed> $out
     * @param int|string $id
     * @return void
     */
    public function edit_yastations(&$out, $id)
    {
        $this->editYastations($out, (int)$id);
    }

    /**
     * Station card.
     *
     * @param array<string,mixed> $out
     * @param int $id
     * @return void
     */
    private function editYastations(array &$out, int $id): void
    {
        $station = $this->repository->station($id);
        if ($station === null) {
            $out['NOT_FOUND'] = 1;
            $out['HINT'] = YaDevicesHints::main(false, false);
            return;
        }

        $authorized = !empty($this->config['AUTHORIZED']);
        $old = $station;

        if ($this->mode === 'update_links') {
            $station['ALARM_LINKED_OBJECT'] = trim((string)gr('alarm_linked_object'));
            $station['ALARM_LINKED_PROPERTY'] = trim((string)gr('alarm_linked_property'));
            $station['ASK_QUESTION_LINKED_OBJECT'] = trim((string)gr('ask_question_linked_object'));
            $station['ASK_QUESTION_LINKED_PROPERTY'] = trim((string)gr('ask_question_linked_property'));
            $station['ASK_ANSWER_LINKED_OBJECT'] = trim((string)gr('ask_answer_linked_object'));
            $station['ASK_ANSWER_LINKED_PROPERTY'] = trim((string)gr('ask_answer_linked_property'));

            $this->syncStationLinkedProperty($old['ALARM_LINKED_OBJECT'] ?? '', $old['ALARM_LINKED_PROPERTY'] ?? '', $station['ALARM_LINKED_OBJECT'], $station['ALARM_LINKED_PROPERTY']);
            $this->syncStationLinkedProperty($old['ASK_QUESTION_LINKED_OBJECT'] ?? '', $old['ASK_QUESTION_LINKED_PROPERTY'] ?? '', $station['ASK_QUESTION_LINKED_OBJECT'], $station['ASK_QUESTION_LINKED_PROPERTY']);
            $this->syncStationLinkedProperty($old['ASK_ANSWER_LINKED_OBJECT'] ?? '', $old['ASK_ANSWER_LINKED_PROPERTY'] ?? '', $station['ASK_ANSWER_LINKED_OBJECT'], $station['ASK_ANSWER_LINKED_PROPERTY']);

            $this->repository->saveStation($station);
            if ($station['ALARM_LINKED_OBJECT'] !== '' && $station['ALARM_LINKED_PROPERTY'] !== '') {
                $currentAlarmValue = gg($station['ALARM_LINKED_OBJECT'] . '.' . $station['ALARM_LINKED_PROPERTY']);
                if (trim((string)$currentAlarmValue) !== '') {
                    $this->handleStationAlarmProperty($station, $currentAlarmValue);
                }
            }
            $out['OK_MSG'] = 'Привязки обновлены.';
        }

        if ($this->mode === 'update_station_settings') {
            $settings = array(
                'dnd' => gr('dnd', 'int'),
                'beta' => gr('beta', 'int'),
                'locale' => gr('locale'),
                'led_brightness' => gr('led_brightness'),
                'visualization' => gr('visualization'),
            );
            if ($this->updateStationQuasarSettings($station, $settings)) {
                $out['OK_MSG'] = 'Настройки станции обновлены.';
            } else {
                $out['ERR_MSG'] = 'Не удалось обновить настройки станции. Возможно, устройство не поддерживает часть параметров.';
            }
        }

        if ($this->mode === 'create_alarm') {
            if ($this->createStationAlarm($station, gr('alarm_date'), gr('alarm_time'))) {
                $out['OK_MSG'] = 'Будильник создан.';
            } else {
                $out['ERR_MSG'] = 'Не удалось создать будильник.';
            }
        }

        if ($this->mode === 'cancel_alarm') {
            $alarmId = (string)gr('alarm_id');
            if ($this->cancelStationAlarm($station, $alarmId)) {
                if ((string)($station['ALARM_LINKED_ID'] ?? '') === $alarmId) {
                    $station['ALARM_LINKED_ID'] = '';
                    $station['ALARM_LINKED_VALUE'] = '';
                    $this->repository->saveStation($station);
                }
                $out['OK_MSG'] = 'Будильник удалён.';
            } else {
                $out['ERR_MSG'] = 'Не удалось удалить будильник.';
            }
        }

        if ($this->mode === 'detect_ip') {
            $detectedIp = $this->detectStationIp($station);
            if ($detectedIp) {
                $station['IP'] = $detectedIp;
                $this->repository->saveStation($station);
                setGlobal('cycle_yadevicesControl', 'restart');
                $out['OK_MSG'] = 'IP обновлён: ' . htmlspecialchars($detectedIp);
            } else {
                $out['ERR_MSG'] = 'Не удалось определить IP автоматически. Укажите IP вручную.';
            }
        }

        if ($this->mode === 'test_local') {
            if ($this->testLocalGlagolPort((string)($station['IP'] ?? ''))) {
                $out['OK_MSG'] = 'Локальный порт 1961 доступен.';
            } else {
                $out['ERR_MSG'] = 'Локальный порт 1961 недоступен. Проверьте IP, сеть и поддержку локального режима.';
            }
        }

        if ($this->mode === 'update') {
            $newTitle = trim((string)gr('title'));
            if ($newTitle !== '') {
                $originalTitle = ($station['ORIGINAL_TITLE'] ?? '') ?: $station['TITLE'];
                $station['TITLE'] = $newTitle;
                $station['CUSTOM_TITLE'] = $newTitle !== $originalTitle ? 1 : 0;
            }
            $station['IP'] = trim((string)gr('ip'));
            $station['TTS'] = (int)gr('tts', 'int');
            $station['MIN_LEVEL_TEXT'] = trim((string)gr('min_level_text'));
            $station['ALLOW_ASK'] = (int)gr('allow_ask', 'int');
            $this->repository->saveStation($station);
            if ($station['IP'] !== '') {
                setGlobal('cycle_yadevicesControl', 'restart');
            }
            $out['OK_MSG'] = 'Станция сохранена.';
        }

        if ($this->mode === 'send_text') {
            $text = trim((string)gr('text'));
            $cloud = (int)gr('cloud');
            $sendAs = (string)gr('sendas');
            if ($text === '') {
                $out['ERR_MSG'] = 'Введите текст или команду.';
            } elseif ($cloud === 1) {
                if (!$authorized) {
                    $out['ERR_MSG'] = 'Для облачной отправки нужна авторизация Яндекса.';
                } else {
                    $command = $sendAs === '1' ? 'text_action' : 'phrase_action';
                    $ok = $this->sendCommandToStationCloud($station, $command, $text);
                    $ok ? $out['OK_MSG'] = 'Команда отправлена в облако.' : $out['ERR_MSG'] = 'Не удалось отправить команду в облако.';
                }
            } else {
                $command = $sendAs === '2' ? 'dialog' : ($sendAs === '1' ? 'command' : 'text');
                $ok = $this->sendCommandToStation($station, $command, $text);
                $ok ? $out['OK_MSG'] = 'Команда отправлена на станцию.' : $out['ERR_MSG'] = 'Не удалось отправить команду. Проверьте IP, токен и цикл.';
            }
            $out['TEXT'] = $this->e($text);
            $out['SENDAS'] = $this->e($sendAs);
        }

        if ($this->mode === 'ask_alice') {
            $askText = trim((string)gr('ask_text'));
            $out['ASK_TEXT'] = $this->e($askText);
            $answer = $this->askStation($station, $askText);
            if (is_array($answer)) {
                $answerText = $answer['text'] ?? json_encode($answer, JSON_UNESCAPED_UNICODE);
                $this->setLinkedStationAnswer($station, $answerText);
                $out['ASK_ANSWER'] = $this->e((string)$answerText);
                $out['OK_MSG'] = 'Ответ получен от локальной Алисы.';
            } else {
                $out['ERR_MSG'] = 'Не удалось получить ответ. Диалог работает только через локальный Glagol.';
            }
        }

        $station = $this->repository->station($id) ?: $station;
        $localCapable = YaDevicesCatalog::isLocalPlatform((string)($station['PLATFORM'] ?? ''));
        $localAvailable = $localCapable
            && trim((string)($station['IP'] ?? '')) !== ''
            && trim((string)($station['DEVICE_TOKEN'] ?? '')) !== '';

        $out['ID'] = (int)$station['ID'];
        $out['TITLE'] = $this->e((string)($station['TITLE'] ?: $station['ORIGINAL_TITLE'] ?: ('Станция #' . $id)));
        $out['ORIGINAL_TITLE'] = $this->e((string)($station['ORIGINAL_TITLE'] ?? ''));
        $out['PLATFORM'] = $this->e((string)($station['PLATFORM'] ?? ''));
        $out['PLATFORM_TITLE'] = $this->e(YaDevicesCatalog::platformTitle((string)($station['PLATFORM'] ?? '')));
        $out['ICON_TI'] = YaDevicesCatalog::deviceIcon('devices.types.smart_speaker.' . (string)($station['PLATFORM'] ?? ''));
        $out['ICON_URL'] = $this->e(YaDevicesCatalog::deviceIconUrl('devices.types.smart_speaker.' . (string)($station['PLATFORM'] ?? '')));
        $out['STATION_ID'] = $this->e((string)($station['STATION_ID'] ?? ''));
        $out['IOT_ID'] = $this->e((string)($station['IOT_ID'] ?? ''));
        $out['IP'] = $this->e((string)($station['IP'] ?? ''));
        $out['DEVICE_TOKEN'] = $this->e((string)($station['DEVICE_TOKEN'] ?? ''));
        $out['TTS'] = (int)($station['TTS'] ?? 0);
        $out['TTS_MODES'] = $this->ttsModeOptions((int)($station['TTS'] ?? 0), $localCapable, $localAvailable, $authorized);
        $out['MIN_LEVEL_TEXT'] = $this->e((string)($station['MIN_LEVEL_TEXT'] ?? ''));
        $out['ALLOW_ASK'] = (int)($station['ALLOW_ASK'] ?? 0);
        $out['LOCAL_CAPABLE'] = $localCapable ? 1 : 0;
        $out['LOCAL_AVAILABLE'] = $localAvailable ? 1 : 0;
        $out['LOCAL_READY'] = $localAvailable ? 1 : 0;
        $out['CLOUD_AVAILABLE'] = $authorized && trim((string)($station['IOT_ID'] ?? '')) !== '' ? 1 : 0;
        $out['CLOUD_READY'] = $out['CLOUD_AVAILABLE'];
        $out['CLOUD_SCENARIO_READY'] = trim((string)($station['TTS_SCENARIO'] ?? '')) !== '' ? 1 : 0;
        $out['IP_TEST_AVAILABLE'] = trim((string)($station['IP'] ?? '')) !== '' ? 1 : 0;
        $out['ALARM_LINKED_OBJECT'] = $this->e((string)($station['ALARM_LINKED_OBJECT'] ?? ''));
        $out['ALARM_LINKED_PROPERTY'] = $this->e((string)($station['ALARM_LINKED_PROPERTY'] ?? ''));
        $out['ALARM_LINKED_ID'] = $this->e((string)($station['ALARM_LINKED_ID'] ?? ''));
        $out['ALARM_LINKED_VALUE'] = $this->e((string)($station['ALARM_LINKED_VALUE'] ?? ''));
        $out['ASK_QUESTION_LINKED_OBJECT'] = $this->e((string)($station['ASK_QUESTION_LINKED_OBJECT'] ?? ''));
        $out['ASK_QUESTION_LINKED_PROPERTY'] = $this->e((string)($station['ASK_QUESTION_LINKED_PROPERTY'] ?? ''));
        $out['ASK_ANSWER_LINKED_OBJECT'] = $this->e((string)($station['ASK_ANSWER_LINKED_OBJECT'] ?? ''));
        $out['ASK_ANSWER_LINKED_PROPERTY'] = $this->e((string)($station['ASK_ANSWER_LINKED_PROPERTY'] ?? ''));
        $out['UPDATED'] = $this->e((string)($station['UPDATED'] ?? ''));
        $out['HINT'] = YaDevicesHints::station($station, $authorized);
        $out['ACTIVE_TAB'] = 'stations';
        $out['PLAYER_URL'] = ROOTHTML . 'module/yadevices.html?station=' . $id;
        $out['API_URL'] = ROOTHTML . 'api/module/yadevices';

        $out['STATION_CONFIG_UNAVAILABLE'] = 1;
        $out['STATION_DND'] = 0;
        $out['STATION_DND_SUPPORTED'] = 0;
        $out['STATION_BETA'] = 0;
        $out['STATION_LOCALE'] = 'ru-RU';
        $out['STATION_LED_BRIGHTNESS'] = '';
        $out['STATION_VISUALIZATION'] = 'clock';
        if ($authorized) {
            $stationConfig = $this->getStationQuasarConfig($station);
            if (is_array($stationConfig) && isset($stationConfig['config']) && is_array($stationConfig['config'])) {
                $config = $stationConfig['config'];
                $out['STATION_CONFIG_UNAVAILABLE'] = 0;
                $out['STATION_DND'] = !empty($config['dndMode']['enabled']) ? 1 : 0;
                $out['STATION_DND_SUPPORTED'] = isset($config['dndMode']) ? 1 : 0;
                $out['STATION_BETA'] = !empty($config['beta']) ? 1 : 0;
                $out['STATION_LOCALE'] = $this->e((string)($config['locale'] ?? 'ru-RU'));
                $out['STATION_LED_BRIGHTNESS'] = $this->e((string)($config['led']['brightness']['value'] ?? ''));
                $out['STATION_VISUALIZATION'] = !empty($config['led']['music_equalizer_visualization']['auto']) ? 'auto' : 'clock';
            }
        }
        $out['LOCALE_OPTIONS'] = $this->localeOptions((string)$out['STATION_LOCALE']);

        $alarms = array();
        if ($authorized) {
            $rawAlarms = $this->getStationAlarms($station);
            if (is_array($rawAlarms)) {
                foreach ($rawAlarms as $alarm) {
                    $alarms[] = array(
                        'ALARM_ID' => $this->e((string)($alarm['alarm_id'] ?? '')),
                        'DATE' => $this->e((string)($alarm['date'] ?? '')),
                        'TIME' => $this->e((string)($alarm['time'] ?? '')),
                        'ENABLED' => !empty($alarm['enabled']) ? 'Да' : 'Нет',
                        'RECURRING' => $this->e(!empty($alarm['recurring']['days_of_week']) ? implode(', ', $alarm['recurring']['days_of_week']) : ''),
                    );
                }
            }
        }
        $out['ALARMS'] = $alarms;
        $out['TODAY'] = date('Y-m-d');
        $out['TEXT'] = $out['TEXT'] ?? '';
        $out['SENDAS'] = $out['SENDAS'] ?? '0';
        $out['ASK_TEXT'] = $out['ASK_TEXT'] ?? '';
        $out['ASK_ANSWER'] = $out['ASK_ANSWER'] ?? '';
    }

    /**
     * @param int $selected
     * @param bool $localCapable
     * @param bool $localAvailable
     * @param bool $cloudAvailable
     * @return array<int,array<string,mixed>>
     */
    private function ttsModeOptions(int $selected, bool $localCapable, bool $localAvailable, bool $cloudAvailable): array
    {
        $result = array();
        foreach (YaDevicesCatalog::ttsModes() as $mode) {
            $value = (int)$mode['value'];
            $disabled = false;
            $description = $mode['description'];
            if ($value === 1 && (!$localCapable || !$localAvailable)) {
                $disabled = true;
                $description = !$localCapable ? 'Платформа не поддерживает локальный режим.' : 'Сначала укажите IP и сформируйте токен.';
            }
            if ($value === 2 && !$cloudAvailable) {
                $disabled = true;
                $description = 'Нужна авторизация Яндекса.';
            }
            $result[] = array(
                'VALUE' => (string)$value,
                'LABEL' => $this->e($mode['label']),
                'DESCRIPTION' => $this->e($description),
                'CHECKED' => $selected === $value ? 1 : 0,
                'DISABLED' => $disabled ? 1 : 0,
            );
        }

        return $result;
    }

    /**
     * @param string $selected
     * @return array<int,array<string,mixed>>
     */
    private function localeOptions(string $selected): array
    {
        $result = array();
        foreach (YaDevicesCatalog::locales() as $value => $label) {
            $result[] = array(
                'VALUE' => $this->e($value),
                'LABEL' => $this->e($label),
                'SELECTED' => $selected === $value ? 1 : 0,
            );
        }

        return $result;
    }

    /**
     * Legacy public entry point.
     *
     * @param array<string,mixed> $out
     * @param int|string $id
     * @return void
     */
    public function edit_yadevices(&$out, $id)
    {
        $this->editYadevices($out, (int)$id);
    }

    /**
     * Device card.
     *
     * @param array<string,mixed> $out
     * @param int $id
     * @return void
     */
    private function editYadevices(array &$out, int $id): void
    {
        $device = $this->repository->device($id);
        if ($device === null) {
            $out['NOT_FOUND'] = 1;
            $out['HINT'] = YaDevicesHints::devices();
            return;
        }

        $authorized = !empty($this->config['AUTHORIZED']);

        if ($this->mode === 'switch') {
            $current = (int)gr('value');
            $value = $current === 1 ? 0 : 1;
            $this->sendValueToYandex((string)$device['IOT_ID'], 'devices.capabilities.on_off', $value);
            usleep(500000);
            $this->redirect('?view_mode=edit_yadevices&id=' . $id);
        }

        if ($this->mode === 'rename') {
            $newTitle = trim((string)gr('title'));
            if ($newTitle !== '') {
                $originalTitle = ($device['ORIGINAL_TITLE'] ?? '') ?: $device['TITLE'];
                $device['TITLE'] = $newTitle;
                $device['CUSTOM_TITLE'] = $newTitle !== $originalTitle ? 1 : 0;
                $this->repository->saveDevice($device);
                if (!empty($device['yastationID'])) {
                    $station = $this->repository->station((int)$device['yastationID']);
                    if ($station !== null) {
                        $stationOriginalTitle = ($station['ORIGINAL_TITLE'] ?? '') ?: $station['TITLE'];
                        $station['TITLE'] = $newTitle;
                        $station['CUSTOM_TITLE'] = $newTitle !== $stationOriginalTitle ? 1 : 0;
                        $this->repository->saveStation($station);
                    }
                }
                $out['OK_MSG'] = 'Название сохранено.';
                $device = $this->repository->device($id) ?: $device;
            }
        }

        $capabilities = $this->repository->deviceCapabilities($id);
        if ($this->mode === 'update') {
            $postedObjects = isset($_POST['linked_object']) && is_array($_POST['linked_object']) ? $_POST['linked_object'] : array();
            $postedProperties = isset($_POST['linked_property']) && is_array($_POST['linked_property']) ? $_POST['linked_property'] : array();
            $postedMethods = isset($_POST['linked_method']) && is_array($_POST['linked_method']) ? $_POST['linked_method'] : array();
            foreach ($capabilities as $index => $capability) {
                $capId = (int)$capability['ID'];
                $oldObject = (string)($capability['LINKED_OBJECT'] ?? '');
                $oldProperty = (string)($capability['LINKED_PROPERTY'] ?? '');
                $capability['LINKED_OBJECT'] = trim((string)($postedObjects[$capId] ?? ''));
                $capability['LINKED_PROPERTY'] = trim((string)($postedProperties[$capId] ?? ''));
                $capability['LINKED_METHOD'] = trim((string)($postedMethods[$capId] ?? ''));
                if ($capability['LINKED_OBJECT'] !== '' && $capability['LINKED_PROPERTY'] === '' && $capability['LINKED_METHOD'] === '') {
                    $capability['LINKED_OBJECT'] = '';
                }
                $this->repository->saveCapability($capability);
                if ($oldObject !== '' && ($oldObject !== $capability['LINKED_OBJECT'] || $oldProperty !== $capability['LINKED_PROPERTY'])) {
                    removeLinkedProperty($oldObject, $oldProperty, $this->name);
                }
                if ($capability['LINKED_OBJECT'] !== '' && $capability['LINKED_PROPERTY'] !== '') {
                    addLinkedProperty($capability['LINKED_OBJECT'], $capability['LINKED_PROPERTY'], $this->name);
                    $this->setProperty($capability, $capability['VALUE'], $capability, (string)$capability['TITLE']);
                }
                $capabilities[$index] = $capability;
            }
            $out['OK_MSG'] = 'Связи устройства сохранены.';
        }

        $linked = 0;
        $deviceType = (string)($device['DEVICE_TYPE'] ?? '');
        $onState = 0;
        foreach ($capabilities as $index => $capability) {
            $title = (string)($capability['TITLE'] ?? '');
            $group = YaDevicesCatalog::capabilityGroup($title);
            $isLinked = trim((string)($capability['LINKED_OBJECT'] ?? '')) !== ''
                && trim((string)($capability['LINKED_PROPERTY'] ?? '')) !== '';
            if ($isLinked) {
                $linked++;
            }
            if ($title === 'devices.capabilities.on_off' && (int)($capability['VALUE'] ?? 0) === 1) {
                $onState = 1;
            }
            $hide = false;
            if (stripos($deviceType, 'devices.types.smart_speaker') !== false
                && $group === 'local'
                && trim((string)($device['IP'] ?? '')) === '') {
                $hide = true;
            }
            $capabilities[$index]['TITLE_RAW'] = $this->e($title);
            $capabilities[$index]['LABEL'] = $this->e(YaDevicesCatalog::capabilityTitle($title));
            $capabilities[$index]['GROUP'] = $group;
            $capabilities[$index]['VALUE'] = $this->e((string)($capability['VALUE'] ?? ''));
            $capabilities[$index]['READONLY'] = (int)($capability['READONLY'] ?? 0);
            $capabilities[$index]['ALLOWPARAMS'] = $this->e((string)($capability['ALLOWPARAMS'] ?? ''));
            $capabilities[$index]['LINKED_OBJECT'] = $this->e((string)($capability['LINKED_OBJECT'] ?? ''));
            $capabilities[$index]['LINKED_PROPERTY'] = $this->e((string)($capability['LINKED_PROPERTY'] ?? ''));
            $capabilities[$index]['LINKED_METHOD'] = $this->e((string)($capability['LINKED_METHOD'] ?? ''));
            $capabilities[$index]['UPDATED'] = $this->e((string)($capability['UPDATED'] ?? ''));
            $capabilities[$index]['LINKED'] = $isLinked ? 1 : 0;
            $capabilities[$index]['HIDE'] = $hide ? 1 : 0;
            $capabilities[$index]['CAN_LINK'] = 1;
        }

        $device['ICON_TI'] = YaDevicesCatalog::deviceIcon($deviceType);
        $device['ICON_URL'] = YaDevicesCatalog::deviceIconUrl($deviceType, $onState === 1);
        $out['ID'] = $id;
        $out['TITLE'] = $this->e((string)($device['TITLE'] ?: $device['ORIGINAL_TITLE'] ?: ('Устройство #' . $id)));
        $out['ORIGINAL_TITLE'] = $this->e((string)($device['ORIGINAL_TITLE'] ?? ''));
        $out['DEVICE_TYPE'] = $this->e($deviceType);
        $out['TYPE_TITLE'] = $this->e(YaDevicesCatalog::deviceTypeTitle($deviceType));
        $out['ICON_TI'] = $device['ICON_TI'];
        $out['ICON_URL'] = $this->e($device['ICON_URL']);
        $out['IOT_ID'] = $this->e((string)($device['IOT_ID'] ?? ''));
        $out['STATION_ID'] = $this->e((string)($device['yastationID'] ?? ''));
        $out['SKILL_ID'] = $this->e((string)($device['SKILL_ID'] ?? ''));
        $out['SKILL_NAME'] = $this->e($this->skillLabel((string)($device['SKILL_ID'] ?? ''), (string)($device['SKILL_NAME'] ?? '')));
        $out['ROOM'] = $this->e((string)($device['ROOM'] ?? ''));
        $out['HOUSE'] = $this->e((string)($device['HOUSE'] ?? ''));
        $out['UPDATED'] = $this->e((string)($device['UPDATED'] ?? ''));
        $out['VALUE'] = $onState;
        $out['PROPERTIES'] = $capabilities;
        $out['PROPERTIES_COUNT'] = count($capabilities);
        $out['LINKED_COUNT'] = $linked;
        $out['HINT'] = YaDevicesHints::device(array('LINKED_COUNT' => $linked));
        $out['ACTIVE_TAB'] = 'devices';
        $out['IS_STATION'] = stripos($deviceType, 'devices.types.smart_speaker') !== false ? 1 : 0;
        $out['AUTHORIZED'] = $authorized ? 1 : 0;
    }

    /**
     * Settings page.
     *
     * @param array<string,mixed> $out
     * @return void
     */
    private function settings(array &$out): void
    {
        $out['API_USERNAME'] = $this->e((string)$this->config['API_USERNAME']);
        $out['RELOAD_TIME'] = (int)$this->config['RELOAD_TIME'];
        $out['RELOAD_OPTIONS'] = $this->reloadOptions((int)$this->config['RELOAD_TIME']);
        $out['ERRORMONITOR'] = !empty($this->config['ERRORMONITOR']) ? 1 : 0;
        $out['ERRORMONITORTYPE'] = (int)$this->config['ERRORMONITORTYPE'];
        $out['NOTIFY_GROUPS'] = $this->prepareNotifyGroupsForTemplate();
        $out['NOTIFY_STATIONS'] = $this->prepareNotifyStationsForTemplate();
        $out['NOTIFY_GROUPS_TEXT'] = $this->e($this->notifyGroupsToText());
        $lastRun = (int)getGlobal('cycle_yadevicesRun');
        $out['CYCLE_LAST_RUN'] = $lastRun > 0 ? date('d.m.Y H:i:s', $lastRun) : 'ещё не запускался';
        $out['CYCLE_STATE'] = $lastRun > 0 && time() - $lastRun < 120 ? 'running' : 'idle';
        $out['CYCLE_TONE'] = $out['CYCLE_STATE'] === 'running' ? 'success' : 'warning';
        $out['CYCLE_ICON'] = $out['CYCLE_STATE'] === 'running' ? 'activity' : 'clock-pause';
        $out['CYCLE_LABEL'] = $out['CYCLE_STATE'] === 'running' ? 'работает' : 'не запущен';
        $out['STATIONS_TOTAL'] = $this->repository->count('yastations');
        $out['DEVICES_TOTAL'] = $this->repository->count('yadevices');
        $out['CAPABILITIES_TOTAL'] = $this->repository->count('yadevices_capabilities');
        $out['HINT'] = YaDevicesHints::settings();
        $out['ACTIVE_TAB'] = 'settings';
        $out['API_URL'] = ROOTHTML . 'api/module/yadevices';
    }

    /**
     * @param int $selected
     * @return array<int,array<string,mixed>>
     */
    private function reloadOptions(int $selected): array
    {
        $result = array();
        foreach (YaDevicesCatalog::reloadOptions() as $seconds) {
            $result[] = array(
                'VALUE' => (string)$seconds,
                'LABEL' => $this->e(YaDevicesCatalog::reloadLabel($seconds)),
                'SELECTED' => $selected === $seconds ? 1 : 0,
            );
        }

        return $result;
    }

    /**
     * About page.
     *
     * @param array<string,mixed> $out
     * @return void
     */
    private function about(array &$out): void
    {
        $out['MODULE_VERSION'] = $this->version;
        $out['STATIONS_TOTAL'] = $this->repository->count('yastations');
        $out['DEVICES_TOTAL'] = $this->repository->count('yadevices');
        $out['CAPABILITIES_TOTAL'] = $this->repository->count('yadevices_capabilities');
        $out['AUTHORIZED'] = !empty($this->config['AUTHORIZED']) ? 1 : 0;
        $out['KB_README'] = ROOTHTML . 'panel/knowledge_base/module/yadevices/README.html';
        $out['KB_SETUP'] = ROOTHTML . 'panel/knowledge_base/module/yadevices/setup.html';
        $out['KB_API'] = ROOTHTML . 'panel/knowledge_base/module/yadevices/api.html';
        $out['FORUM_URL'] = 'https://mjdm.ru/forum/viewtopic.php?f=5&t=7359';
        $out['GITHUB_URL'] = 'https://github.com/SmoKE585/majordomo-yadevices';
        $out['YANDEX_DOCS_URL'] = 'https://yandex.ru/dev/dialogs/alice/doc/ru/';
        $out['ACTIVE_TAB'] = 'about';
    }

    /**
     * Persist settings and restart the polling cycle.
     *
     * @return void
     */
    private function saveSettings(): void
    {
        $reload = (int)gr('cycleIsOnTime');
        if (!in_array($reload, YaDevicesCatalog::reloadOptions(), true)) {
            $reload = 10;
        }
        $errorMonitor = gr('errorMonitor');
        $enabled = in_array((string)$errorMonitor, array('on', '1'), true);
        $this->config['RELOAD_TIME'] = $reload;
        $this->config['ERRORMONITOR'] = $enabled ? 1 : 0;
        $this->config['ERRORMONITORTYPE'] = $enabled ? (int)gr('errorMonitorType', 'int') : 0;
        $this->config['NOTIFY_GROUPS'] = $this->normalizeNotifyGroupsPost($_POST);
        $this->saveConfig();
        setGlobal('cycle_yadevicesControl', 'restart');
        $this->redirect('?view_mode=settings&saved=1');
    }

    /**
     * HTML-escape a value for templates.
     *
     * @param mixed $value
     * @return string
     */
    private function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * yastations delete record
     *
     * @access public
     */
    function clearAll()
    {
        SQLExec("DELETE FROM yastations");
        //Отвяжемся от свойств
        $req = SQLSelect("SELECT * FROM yadevices_capabilities WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");

        foreach ($req as $prop) {
            removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
        }
        SQLExec("DELETE FROM yadevices_capabilities");
    }

    function delete_yastations($id)
    {
        $rec = SQLSelectOne("SELECT * FROM yastations WHERE ID='$id'");
        $this->removeStationLinkedProperties($rec);
		$device = SQLSelectOne("SELECT ID FROM yadevices WHERE IOT_ID='".$rec['IOT_ID']."'");
		$this->delete_yadevice($device['ID']);
        // some action for related tables
        SQLExec("DELETE FROM yastations WHERE ID='" . $rec['ID'] . "'");
    }

    function delete_yadevice($id)
    {
        //Отвяжемся от свойств
        $req = SQLSelect("SELECT * FROM yadevices_capabilities WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != '' AND YADEVICE_ID = '" . (int)$id . "'");

        foreach ($req as $prop) {
            removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
        }

        SQLExec("DELETE FROM yadevices_capabilities WHERE YADEVICE_ID=" . (int)$id);
        SQLExec("DELETE FROM yadevices WHERE ID=" . (int)$id);
    }

    function getStatus($token, $ip)
    {
        $clientConfig = new ClientConfig();
        $clientConfig->setHeaders([
            'X-Origin' => 'http://yandex.ru/',
        ]);
        $clientConfig->setContextOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $msg = array(
            'conversationToken' => $token,
            'payload' => array(
                'command' => '',
            )
        );

        $client = new WebSocketClient('wss://' . $ip . ':' . GLAGOL_PORT . '/', $clientConfig);
        $client->send(json_encode($msg));
        $result = $client->receive();
        $client->close();
        $result_data = json_decode($result, true);
		//$this->writeLog($result);
        if (is_array($result_data)) {
            return $result_data;
        }
        return false;

    }


    function sendCommandToStationCloud($station, $command, $data = '')
    {
        if (!$command) return false;
		switch($command){
			case 'text':
				$command = 'phrase_action';
				break;
			case 'command':
			case 'dialog':
				$command = 'text_action';
				break;
			case 'setVolume':
				$command = 'text_action';
				$data = 'громкость на ' . $data;
				break;
			case 'volumeUp':
				$command = 'text_action';
				$data = 'громче';
				break;
			case 'volumeDown':
				$command = 'text_action';
				$data = 'тише';
				break;
			case 'play':
				$command = 'text_action';
				$data = 'продолжи воспроизведение';
				break;
			case 'stop':
				$command = 'text_action';
				$data = 'стоп';
				break;
			case 'next':
				$command = 'text_action';
				$data = 'следующий трек';
				break;
			case 'prev':
				$command = 'text_action';
				$data = 'предыдущий трек';
				break;
		}
        return $this->sendCloudTTS($station['IOT_ID'], $data, $command);
    }

    function sendToStationAuto($station, $command, $data = '')
    {
        if (!is_array($station)) {
            $station = SQLSelectOne("SELECT * FROM yastations WHERE ID=" . (int)$station);
        }
        if (empty($station['ID'])) {
            return false;
        }
        if (($station['TTS'] ?? 0) == 2 || !$this->isLocalCapablePlatform($station['PLATFORM'] ?? '')) {
            return $this->sendCommandToStationCloud($station, $command, $data);
        }
        $sent = $this->sendCommandToStation($station, $command, $data);
        if (!$sent && $this->canFallbackToCloud($station, $command)) {
            return $this->sendCommandToStationCloud($station, $command, $data);
        }
        return $sent;
    }

    function parseNotifyGroups()
    {
        $groups = json_decode($this->config['NOTIFY_GROUPS'] ?? '{}', true);
        return is_array($groups) ? $groups : array();
    }

    function notifyGroupsToText()
    {
        $this->getConfig();
        $this->normalizeConfig();
        $groups = $this->parseNotifyGroups();
        $lines = array();
        foreach ($groups as $name => $ids) {
            $lines[] = $name . '=' . implode(',', array_map('intval', (array)$ids));
        }
        return implode("\n", $lines);
    }

    function normalizeNotifyGroupsText($text)
    {
        $groups = array();
        foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
            $line = trim($line);
            if ($line == '' || strpos($line, '=') === false) {
                continue;
            }
            list($name, $idsText) = explode('=', $line, 2);
            $name = trim($name);
            $ids = array();
            foreach (explode(',', $idsText) as $id) {
                $id = (int)trim($id);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            if ($name != '' && !empty($ids)) {
                $groups[$name] = array_values(array_unique($ids));
            }
        }
        return json_encode($groups, JSON_UNESCAPED_UNICODE);
    }

    function normalizeNotifyGroupsPost($post)
    {
        $names = isset($post['notify_group_names']) && is_array($post['notify_group_names']) ? $post['notify_group_names'] : array();
        $stations = isset($post['notify_group_stations']) && is_array($post['notify_group_stations']) ? $post['notify_group_stations'] : array();
        $groups = array();

        foreach ($names as $index => $name) {
            $name = trim((string)$name);
            if ($name == '') {
                continue;
            }
            $ids = array();
            if (isset($stations[$index]) && is_array($stations[$index])) {
                foreach ($stations[$index] as $stationId) {
                    $stationId = (int)$stationId;
                    if ($stationId > 0) {
                        $ids[] = $stationId;
                    }
                }
            }
            if (!empty($ids)) {
                $groups[$name] = array_values(array_unique($ids));
            }
        }

        if (empty($groups) && isset($post['notifyGroups'])) {
            return $this->normalizeNotifyGroupsText($post['notifyGroups']);
        }

        return json_encode($groups, JSON_UNESCAPED_UNICODE);
    }

    function prepareNotifyStationsForTemplate()
    {
        $stations = SQLSelect("SELECT ID, TITLE, ORIGINAL_TITLE FROM yastations ORDER BY TITLE");
        foreach ($stations as $key => $station) {
            $title = $station['TITLE'] ?: ($station['ORIGINAL_TITLE'] ?: ('Station #' . $station['ID']));
            $stations[$key]['ID'] = (int)$station['ID'];
            $stations[$key]['TITLE'] = htmlspecialchars($title);
        }
        return $stations;
    }

    function prepareNotifyGroupsForTemplate()
    {
        $groups = $this->parseNotifyGroups();
        $stations = $this->prepareNotifyStationsForTemplate();
        $result = array();
        $index = 0;
        foreach ($groups as $name => $ids) {
            $items = array();
            foreach ($stations as $station) {
                $items[] = array(
                    'GROUP_INDEX' => $index,
                    'ID' => $station['ID'],
                    'TITLE' => $station['TITLE'],
                    'CHECKED' => in_array((int)$station['ID'], array_map('intval', (array)$ids), true) ? 1 : 0,
                );
            }
            $result[] = array(
                'INDEX' => $index,
                'TITLE' => htmlspecialchars($name),
                'STATIONS' => $items,
            );
            $index++;
        }

        if (empty($result)) {
            $items = array();
            foreach ($stations as $station) {
                $items[] = array(
                    'GROUP_INDEX' => 0,
                    'ID' => $station['ID'],
                    'TITLE' => $station['TITLE'],
                    'CHECKED' => 0,
                );
            }
            $result[] = array(
                'INDEX' => 0,
                'TITLE' => 'all',
                'STATIONS' => $items,
            );
        }

        return $result;
    }

    function sendNotifyGroup($groupName, $command = 'text', $data = '')
    {
        $this->getConfig();
        $this->normalizeConfig();
        $groups = $this->parseNotifyGroups();
        if (empty($groups[$groupName])) {
            $this->writeLog('Notify-группа не найдена: ' . $groupName, true);
            return false;
        }
        $ok = false;
        foreach ($groups[$groupName] as $stationId) {
            $station = SQLSelectOne("SELECT * FROM yastations WHERE ID=" . (int)$stationId);
            if (!empty($station['ID'])) {
                $ok = $this->sendToStationAuto($station, $command, $data) || $ok;
            }
        }
        return $ok;
    }

    function sendCommandToStation($station, $command, $data = '', $volumeBefore = null)
    {
        if (empty($command)) return false;
		if(!is_array($station)){
			$station = SQLSelectOne("SELECT * FROM yastations WHERE IOT_ID='" . DBSafe($station) . "'");
		}
        if (empty($station['ID']) || empty($station['IP'])) return false;
        if (empty($station['DEVICE_TOKEN']) && !empty($station['STATION_ID']) && !empty($station['PLATFORM'])) {
            $station['DEVICE_TOKEN'] = $this->getDeviceToken($station['STATION_ID'], $station['PLATFORM']);
        }
        if (!empty($station['DEVICE_TOKEN'])) {
			$updatedCycle = gg('cycle_yadevicesRun');
			if ((time() - (int)$updatedCycle < 16)) {
				addToOperationsQueue('yadevices', $station['IOT_ID'], $this->encodeQueuedCommand($command, $data, $volumeBefore));
				return true;
			} else {
                if ($volumeBefore !== null) {
                    $this->sendGlagol('setVolume', ((float)$volumeBefore) * 0.1, $station['DEVICE_TOKEN'], $station['IP']);
                }
				$result = $this->sendGlagol($command, $data, $station['DEVICE_TOKEN'], $station['IP']);
				if (is_array($result)) {
					return true;
				}
			}
        } else {
            $this->writeLog('sendCommandToStation() -> Перед тем, как отправлять команды на станцию - сформируйте токен доступа!', true);
        }
        return false;
    }

    function canFallbackToCloud($station, $command)
    {
        return $this->ensureAuthorized()
            && !empty($station['IOT_ID'])
            && in_array($command, array('text', 'command', 'dialog', 'setVolume', 'volumeUp', 'volumeDown', 'play', 'stop', 'next', 'prev'), true);
    }

    function getStationQuasarConfig($station)
    {
        if (empty($station['IOT_ID'])) {
            return false;
        }
        $data = $this->apiRequest('https://iot.quasar.yandex.ru/m/v2/user/devices/' . $station['IOT_ID'] . '/configuration');
        if (is_array($data) && ($data['status'] ?? '') == 'ok') {
            return array(
                'config' => $data['quasar_config'] ?? array(),
                'version' => $data['quasar_config_version'] ?? '',
            );
        }
        return false;
    }

    function setStationQuasarConfig($station, $config, $version)
    {
        if (empty($station['IOT_ID']) || $version == '') {
            return false;
        }
        $result = $this->apiRequest(
            'https://iot.quasar.yandex.ru/m/v3/user/devices/' . $station['IOT_ID'] . '/configuration/quasar',
            'POST',
            array('config' => $config, 'version' => $version)
        );
        return is_array($result) && ($result['status'] ?? '') == 'ok';
    }

    function updateStationQuasarSettings($station, $settings)
    {
        $data = $this->getStationQuasarConfig($station);
        if (!$data) {
            return false;
        }
        $config = $data['config'];

        if (isset($settings['dnd']) && isset($config['dndMode'])) {
            $config['dndMode']['enabled'] = (bool)$settings['dnd'];
        }
        if (isset($settings['beta'])) {
            $config['beta'] = (bool)$settings['beta'];
        }
        if (!empty($settings['locale']) && in_array($settings['locale'], array('ru-RU', 'en-US', 'ar-SA', 'kk-KZ', 'tr-TR'), true)) {
            $config['locale'] = $settings['locale'];
        }
        if (isset($settings['led_brightness']) && $settings['led_brightness'] !== '') {
            $brightness = (float)$settings['led_brightness'];
            $led = $config['led'] ?? array();
            $led['brightness'] = ($brightness >= 0 && $brightness <= 1)
                ? array('auto' => false, 'value' => $brightness)
                : array('auto' => true, 'value' => 0.5);
            $config['led'] = $led;
        }
        if (!empty($settings['visualization'])) {
            $led = $config['led'] ?? array();
            $led['music_equalizer_visualization'] = array(
                'style' => 'showClock',
                'auto' => $settings['visualization'] != 'clock',
            );
            $config['led'] = $led;
        }

        return $this->setStationQuasarConfig($station, $config, $data['version']);
    }

    function alarmHeaders()
    {
        return array(
            'Accept: application/json',
            'Origin: https://yandex.ru',
            'x-ya-app-type: iot-app',
            'x-ya-application: {"app_id":"unknown","uuid":"unknown","lang":"ru"}',
        );
    }

    function getStationAlarms($station)
    {
        if (empty($station['STATION_ID'])) {
            return array();
        }
        $result = $this->apiRequest(
            'https://rpc.alice.yandex.ru/gproxy/get_alarms',
            'POST',
            array('device_ids' => array($station['STATION_ID'])),
            0,
            $this->alarmHeaders()
        );
        return is_array($result) && isset($result['alarms']) && is_array($result['alarms']) ? $result['alarms'] : array();
    }

    function createStationAlarm($station, $date, $time)
    {
        if (empty($station['STATION_ID']) || $date == '' || $time == '') {
            return false;
        }
        $device = SQLSelectOne("SELECT DEVICE_TYPE FROM yadevices WHERE IOT_ID='" . DBSafe($station['IOT_ID']) . "'");
        $deviceType = !empty($device['DEVICE_TYPE']) ? 'devices.types.smart_speaker.yandex.' . $device['DEVICE_TYPE'] : 'devices.types.smart_speaker';
        $alarm = array(
            'alarm_id' => '',
            'enabled' => true,
            'date' => $date,
            'time' => $time,
            'device_id' => $station['STATION_ID'],
        );
        $result = $this->apiRequest(
            'https://rpc.alice.yandex.ru/gproxy/create_alarm',
            'POST',
            array('alarm' => $alarm, 'device_type' => $deviceType),
            0,
            $this->alarmHeaders()
        );
        if (!is_array($result) || (($result['status'] ?? 'ok') == 'error')) {
            return false;
        }
        if (!empty($result['alarm']['alarm_id'])) {
            return $result['alarm']['alarm_id'];
        }
        if (!empty($result['alarm_id'])) {
            return $result['alarm_id'];
        }
        $alarms = $this->getStationAlarms($station);
        foreach ($alarms as $alarm) {
            if (($alarm['date'] ?? '') == $date && ($alarm['time'] ?? '') == $time) {
                return $alarm['alarm_id'] ?? true;
            }
        }
        return true;
    }

    function cancelStationAlarm($station, $alarmId)
    {
        if (empty($station['STATION_ID']) || $alarmId == '') {
            return false;
        }
        $result = $this->apiRequest(
            'https://rpc.alice.yandex.ru/gproxy/cancel_alarms',
            'POST',
            array('device_alarm_ids' => array(array('alarm_id' => $alarmId, 'device_id' => $station['STATION_ID']))),
            0,
            $this->alarmHeaders()
        );
        return is_array($result) && (($result['status'] ?? 'ok') != 'error');
    }

    function askStation($station, $text)
    {
        if (empty($station['IP']) || empty($station['DEVICE_TOKEN']) || $text == '') {
            return false;
        }
        return $this->sendGlagol('command', $text, $station['DEVICE_TOKEN'], $station['IP']);
    }

    function parseAlarmDateTime($value)
    {
        $value = trim((string)$value);
        if ($value == '') {
            return false;
        }
        $formats = array('Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'd.m.Y H:i', 'd.m.Y H:i:s');
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime && $dt->format($format) == $value) {
                return array($dt->format('Y-m-d'), $dt->format('H:i'));
            }
        }
        return false;
    }

    function setLinkedStationAnswer($station, $answer)
    {
        if (!empty($station['ASK_ANSWER_LINKED_OBJECT']) && !empty($station['ASK_ANSWER_LINKED_PROPERTY'])) {
            setGlobal(
                $station['ASK_ANSWER_LINKED_OBJECT'] . '.' . $station['ASK_ANSWER_LINKED_PROPERTY'],
                $answer,
                array($this->name => 1),
                $this->name . '.ask_answer'
            );
        }
    }

    function handleStationAlarmProperty($station, $value)
    {
        $value = trim((string)$value);
        if ($value == '') {
            if (!empty($station['ALARM_LINKED_ID'])) {
                $this->cancelStationAlarm($station, $station['ALARM_LINKED_ID']);
            } elseif (!empty($station['ALARM_LINKED_VALUE'])) {
                $parsed = $this->parseAlarmDateTime($station['ALARM_LINKED_VALUE']);
                if ($parsed) {
                    $alarms = $this->getStationAlarms($station);
                    foreach ($alarms as $alarm) {
                        if (($alarm['date'] ?? '') == $parsed[0] && ($alarm['time'] ?? '') == $parsed[1] && !empty($alarm['alarm_id'])) {
                            $this->cancelStationAlarm($station, $alarm['alarm_id']);
                        }
                    }
                }
            }
            if (!empty($station['ALARM_LINKED_ID']) || !empty($station['ALARM_LINKED_VALUE'])) {
                $station['ALARM_LINKED_ID'] = '';
                $station['ALARM_LINKED_VALUE'] = '';
                SQLUpdate('yastations', $station);
            }
            return true;
        }

        $parsed = $this->parseAlarmDateTime($value);
        if (!$parsed) {
            $this->writeLog('Некорректная дата/время будильника для ' . $station['TITLE'] . ': ' . $value, true);
            return false;
        }

        if (!empty($station['ALARM_LINKED_ID'])) {
            $this->cancelStationAlarm($station, $station['ALARM_LINKED_ID']);
            $station['ALARM_LINKED_ID'] = '';
        }
        $alarmId = $this->createStationAlarm($station, $parsed[0], $parsed[1]);
        if ($alarmId) {
            $station['ALARM_LINKED_ID'] = is_string($alarmId) ? $alarmId : '';
            $station['ALARM_LINKED_VALUE'] = $parsed[0] . ' ' . $parsed[1];
            SQLUpdate('yastations', $station);
            return true;
        }
        return false;
    }

    function handleStationAskProperty($station, $value)
    {
        $value = trim((string)$value);
        if ($value == '') {
            return false;
        }
        $answer = $this->askStation($station, $value);
        if (is_array($answer)) {
            $text = $answer['text'] ?? json_encode($answer, JSON_UNESCAPED_UNICODE);
            $this->setLinkedStationAnswer($station, $text);
            return true;
        }
        return false;
    }

    function encodeQueuedCommand($command, $data = '', $volumeBefore = null)
    {
        return 'json:' . base64_encode(json_encode(array('command' => $command, 'data' => $data, 'volume_before' => $volumeBefore), JSON_UNESCAPED_UNICODE));
    }
	
	function sendGlagol($command, $data, $token, $ip)
    {
        $this->writeLog("Отправляем команду '$data' на устройство $ip");

        $clientConfig = new ClientConfig();
        $clientConfig->setHeaders([
            'X-Origin' => 'http://yandex.ru/',
        ]);
        $clientConfig->setContextOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $msg = array(
            'conversationToken' => $token,
            'id' => uniqid(''),
            'sentTime' => time() * 1000,
        );
        try {
            $client = new WebSocketClient('wss://' . $ip . ':' . GLAGOL_PORT . '/', $clientConfig);
            $message = $this->message($command, $data, $token);
            if (!$message) {
                return false;
            }
            $client->send($message);
            $result = $client->receive();
        } catch (Throwable $e) {
            $this->writeLog('Ошибка локальной отправки на ' . $ip . ': ' . $e->getMessage(), true);
            return false;
        }

        $result_data = json_decode($result, true);

        if (is_array($result_data)) {
            $client->close();
            return $result_data;
        }
        if (isset($client)) {
            $client->close();
        }
        return false;
    }

    function processSubscription($event, $details = '')
    {
        $this->getConfig();

        if ($event == 'SAY' || $event == 'ASK') {
            $this->writeLog("$event: " . json_encode($details, JSON_UNESCAPED_UNICODE));
        }

        if ($event == 'ASK') {
            $message = $details['message'] ?? '';
            $message = preg_replace('/\?$/', '', $message);
            $qry = "ALLOW_ASK=1";
            if (!empty($details['destination'])) {
                $qry .= " AND yastations.TITLE LIKE '%" . DBSafe($details['destination']) . "%'";
            }
            $stations = SQLSelect("SELECT * FROM yastations WHERE " . $qry);
            foreach ($stations as $station) {
                callAPI('/api/module/yadevices', 'GET', array('station' => $station['ID'], 'command' => 'command', 'data' => 'попроси навык дом мажордом задать вопрос "' . $message . '"'));
            }
        }

        if ($event == 'SAY' || $event == 'SAYTO') {
            $level = (int)($details['level'] ?? 0);
            $message = $details['message'] ?? '';

            // TTS LOCAL & CLOUD
            $qry = "(TTS=1 OR (TTS=2 AND IOT_ID!=''))";
            if (isset($details['destination'])) {
                $qry .= " AND yastations.TITLE LIKE '%" . DBSafe($details['destination']) . "%'";
            }
            $stations = SQLSelect("SELECT * FROM yastations WHERE " . $qry);
            foreach ($stations as $station) {
                $min_level = 0;
                if ($station['MIN_LEVEL_TEXT'] != '') {
                    $min_level = processTitle($station['MIN_LEVEL_TEXT']);
                }
                if ($level >= $min_level) {
                    //$this->sendCloudTTS($station['IOT_ID'],$message);
					//$this->sendCommandToStation($station, 'text', $message);
                    callAPI('/api/module/yadevices', 'GET', array('station' => $station['ID'], 'command' => 'text', 'data' => $message));
                }
            }
        }
    }

    function sendValueToYandex($iot_id, $command_type, $value)
    {
        $command_type = explode('.', $command_type);
        if (count($command_type) < 3) {
            $this->writeLog('sendValueToYandex() -> Неверный тип команды: ' . implode('.', $command_type), true);
            return false;
        }

        $url = "https://iot.quasar.yandex.ru/m/user/devices/" . $iot_id . "/actions";
        if ($command_type[0] . '.' . $command_type[1] . '.' . $command_type[2] == 'devices.capabilities.on_off') {
            if ($value) {
                $value = true;
            } else {
                $value = false;
            }
            $data = array('actions' => array(
                array('state' => array('instance' => 'on', 'value' => $value),
                    'type' => $command_type[0] . '.' . $command_type[1] . '.' . $command_type[2]
                )));

            //print_r(json_encode($data));

            $result = $this->apiRequest($url, 'POST', $data);
            return $result;
        } else if ($command_type[0] . '.' . $command_type[1] . '.' . $command_type[2] == 'devices.capabilities.mode') {
            //Мод, например work_speed
            $mode = $command_type[3] ?? '';
            if ($mode == '') return false;

            $data = array('actions' => array(
                array('state' => array('instance' => $mode, 'value' => $value),
                    'type' => $command_type[0] . '.' . $command_type[1] . '.' . $command_type[2]
                )));

            //debMes(json_encode($data));

            $result = $this->apiRequest($url, 'POST', $data);
            return $result;
        } else if ($command_type[0] . '.' . $command_type[1] . '.' . $command_type[2] == 'devices.capabilities.toggle') {
            //Мод, например work_speed
            $toggle = $command_type[3] ?? '';
            if ($toggle == '') return false;

            if ($value == 1) {
                $value = true;
            } else {
                $value = false;
            }

            $data = array('actions' => array(
                array('state' => array('instance' => $toggle, 'value' => $value),
                    'type' => $command_type[0] . '.' . $command_type[1] . '.' . $command_type[2]
                )));

            //debMes(json_encode($data));

            $result = $this->apiRequest($url, 'POST', $data);
            return $result;
        } else if ($command_type[0] . '.' . $command_type[1] . '.' . $command_type[2] == 'devices.capabilities.range') {
            //Мод, например work_speed
            $range = $command_type[3] ?? '';
            if ($range == '') return false;

            $data = array('actions' => array(
                array('type' => $command_type[0] . '.' . $command_type[1] . '.' . $command_type[2], 'state' => array('instance' => $range, 'value' => (int)$value),)));


            //debMes(json_encode($data));
            $result = $this->apiRequest($url, 'POST', $data);
            //debMes(json_encode($result));
            return $result;
        } else if ($command_type[0] . '.' . $command_type[1] . '.' . $command_type[2] == 'devices.capabilities.color_setting') { //xor2016: для Я.Лампочки
            $mode = $command_type[3] ?? '';
            if ($mode == '') return false;

            $data = array('actions' => array(
                array('state' => array('instance' => $mode, 'value' => $value), 'type' => $command_type[0] . '.' . $command_type[1] . '.' . $command_type[2])));

            $result = $this->apiRequest($url, 'POST', $data);
            return $result;
        }

    }

    function propertySetHandle($object, $property, $value)
    {
        $this->ensureRenameColumns();
        $stations = SQLSelect("SELECT * FROM yastations WHERE (ALARM_LINKED_OBJECT='" . DBSafe($object) . "' AND ALARM_LINKED_PROPERTY='" . DBSafe($property) . "') OR (ASK_QUESTION_LINKED_OBJECT='" . DBSafe($object) . "' AND ASK_QUESTION_LINKED_PROPERTY='" . DBSafe($property) . "')");
        foreach ($stations as $station) {
            if ($station['ALARM_LINKED_OBJECT'] == $object && $station['ALARM_LINKED_PROPERTY'] == $property) {
                $this->handleStationAlarmProperty($station, $value);
            }
            if ($station['ASK_QUESTION_LINKED_OBJECT'] == $object && $station['ASK_QUESTION_LINKED_PROPERTY'] == $property) {
                $this->handleStationAskProperty($station, $value);
            }
        }

        $properties = SQLSelect("SELECT yadevices_capabilities.*, yadevices.IOT_ID FROM yadevices_capabilities LEFT JOIN yadevices ON yadevices_capabilities.YADEVICE_ID=yadevices.ID WHERE yadevices_capabilities.LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND yadevices_capabilities.LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        $total = count($properties);
        for ($i = 0; $i < $total; $i++) {
            if ($properties[$i]['READONLY'] == 0) {
				//если имя начинается с local
				if(stripos($properties[$i]['TITLE'], 'local') !== false){
					    // Добавление в очередь, которая обрабатывается в цикле
						$command = str_replace('local.', '', $properties[$i]['TITLE']);
						if($command == 'other'){
							$command = trim(stristr($value, ':', true));
							$value = trim(stristr($value, ':'), ' \n\r\t\v\x00\:');
						} else if($command == 'volume'){
							if($value == 'volumeUp' or $value == 'volumeDown') $command = $value;
							else{
								$command = 'setVolume';
								$value *= 0.1;
							}
						}
						$this->sendCommandToStation($properties[$i]['IOT_ID'], $command, $value);
						//addToOperationsQueue('yadevices', $properties[$i]['IOT_ID'], $command . '^' . $value);
				} else if(stripos($properties[$i]['TITLE'], 'cloud') !== false){
					    // Отправляем в облако
						$command = str_replace('cloud.', '', $properties[$i]['TITLE']);
						if($command == 'command') $command = 'text_action';
						else if($command == 'text') $command = 'phrase_action';
						$this->sendCloudTTS($properties[$i]['IOT_ID'], $value, $command);
				} else {
					$sendCMD = $this->sendValueToYandex($properties[$i]['IOT_ID'], $properties[$i]['TITLE'], $value);
					$sendCMD = json_encode($sendCMD);
	
					if ($sendCMD->status == 'ok') {
						$this->writeLog('sendValueToYandex() -> Успешно! ' . $object . '.' . $property . ' = ' . $value);
					} else {
						$this->writeLog('sendValueToYandex() -> Неверная команда: ' . $object . '.' . $property);
					}					
				}
            } else {
                 $this->writeLog('sendValueToYandex() -> Свойство ' . $properties[$i]['TITLE'] . ' доступно только для чтения!', true);
            }
        }
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        subscribeToEvent($this->name, 'SAY');
        subscribeToEvent($this->name, 'SAYTO');
        subscribeToEvent($this->name, 'ASK');

        $cookie_dir = dirname(YADEVICES_COOKIE_PATH);
        if (!is_dir($cookie_dir)) {
            umask(0);
            mkdir($cookie_dir, 0777);
        }

        $old_cookie_file = ROOT . 'cms/cached/yadevices/new_yandex_coockie.txt';
        if (file_exists($old_cookie_file)) {
            copy($old_cookie_file, YADEVICES_COOKIE_PATH);
            unlink($old_cookie_file);
            chmod(YADEVICES_COOKIE_PATH, 0666);
        }

        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        $stations = SQLSelect("SELECT * FROM yastations");
        foreach ($stations as $station) {
            $this->removeStationLinkedProperties($station);
        }

        //Отвяжемся от свойств
        $req = SQLSelect("SELECT * FROM yadevices_capabilities WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");

        foreach ($req as $prop) {
            removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
        }

        SQLExec('DROP TABLE IF EXISTS yastations');
        SQLExec('DROP TABLE IF EXISTS yadevices');
        SQLExec('DROP TABLE IF EXISTS yadevices_capabilities');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
		//Получим список существующих столбцов
		$query = mysqli_fetch_all(SQLExec("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'yastations'"), MYSQLI_NUM);
		$rename = 0;
		//Пройдёмся по именам циклом
		foreach($query as $name) {
			if($name[0] == 'TTS_EFFECT') $rename = 1;
		}
		//Переименовываем, если необходимо
		if($rename){
			SQLExec("ALTER TABLE `yastations` CHANGE COLUMN `TTS_EFFECT` `ARTIST` VARCHAR(255) NOT NULL DEFAULT ''");
			SQLExec("ALTER TABLE `yastations` CHANGE COLUMN `TTS_ANNOUNCE` `TRACK` VARCHAR(255) NOT NULL DEFAULT ''");
			SQLExec("ALTER TABLE `yastations` CHANGE COLUMN `SCREEN_CAPABLE` `COVER` VARCHAR(255) NOT NULL DEFAULT ''");
			SQLExec("ALTER TABLE `yastations` CHANGE COLUMN `SCREEN_PRESENT` `PLAYING` INT(3) NOT NULL DEFAULT '0'");;
		}
        $this->ensureRenameColumns();
        /*
        yastations -
        */
        $data = <<<EOD
 yastations: ID int(10) unsigned NOT NULL auto_increment
 yastations: TITLE varchar(255) NOT NULL DEFAULT ''
 yastations: ORIGINAL_TITLE varchar(255) NOT NULL DEFAULT ''
 yastations: CUSTOM_TITLE tinyint(1) NOT NULL DEFAULT 0
 yastations: OWNER varchar(255) NOT NULL DEFAULT ''
 yastations: STATION_ID varchar(100) NOT NULL DEFAULT ''
 yastations: IP varchar(100) NOT NULL DEFAULT ''
 yastations: MIN_LEVEL_TEXT varchar(255) NOT NULL DEFAULT ''
 yastations: TTS int(3) NOT NULL DEFAULT '0'
 yastations: ALLOW_ASK int(3) NOT NULL DEFAULT '0'
 yastations: IOT_ID varchar(255) NOT NULL DEFAULT ''
 yastations: PLATFORM varchar(100) NOT NULL DEFAULT ''
 yastations: ICON_URL varchar(255) NOT NULL DEFAULT ''
 yastations: DEVICE_TOKEN varchar(255) NOT NULL DEFAULT ''
 yastations: TTS_SCENARIO varchar(255) NOT NULL DEFAULT ''
 yastations: ALARM_LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 yastations: ALARM_LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 yastations: ALARM_LINKED_ID varchar(255) NOT NULL DEFAULT ''
 yastations: ALARM_LINKED_VALUE varchar(255) NOT NULL DEFAULT ''
 yastations: ASK_QUESTION_LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 yastations: ASK_QUESTION_LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 yastations: ASK_ANSWER_LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 yastations: ASK_ANSWER_LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 yastations: ARTIST varchar(255) NOT NULL DEFAULT ''
 yastations: TRACK varchar(255) NOT NULL DEFAULT ''
 yastations: COVER varchar(255) NOT NULL DEFAULT ''
 yastations: PLAYING int(3) NOT NULL DEFAULT '0'
 yastations: VOLUME int(3) NOT NULL DEFAULT '0'
 yastations: IS_ONLINE int(3) NOT NULL DEFAULT '0'
 yastations: UPDATED datetime

 yadevices: ID int(10) unsigned NOT NULL auto_increment
 yadevices: TITLE varchar(255) NOT NULL DEFAULT ''
 yadevices: ORIGINAL_TITLE varchar(255) NOT NULL DEFAULT ''
 yadevices: CUSTOM_TITLE tinyint(1) NOT NULL DEFAULT 0
 yadevices: IOT_ID varchar(255) NOT NULL DEFAULT ''
 yadevices: DEVICE_TYPE varchar(100) NOT NULL DEFAULT ''
 yadevices: HOUSE varchar(100) NOT NULL DEFAULT ''
 yadevices: ROOM varchar(100) NOT NULL DEFAULT ''
 yadevices: SKILL_ID varchar(100) NOT NULL DEFAULT ''
 yadevices: SKILL_NAME varchar(255) NOT NULL DEFAULT ''
 yadevices: UPDATED datetime

 yadevices_capabilities: ID int(10) unsigned NOT NULL auto_increment
 yadevices_capabilities: YADEVICE_ID int(10) NOT NULL DEFAULT '0'
 yadevices_capabilities: TITLE varchar(255) NOT NULL DEFAULT ''
 yadevices_capabilities: VALUE varchar(100) NOT NULL DEFAULT ''
 yadevices_capabilities: READONLY tinyint(1) NOT NULL DEFAULT 0
 yadevices_capabilities: ALLOWPARAMS varchar(255) NOT NULL DEFAULT ''
 yadevices_capabilities: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 yadevices_capabilities: LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 yadevices_capabilities: LINKED_METHOD varchar(255) NOT NULL DEFAULT ''
 yadevices_capabilities: UPDATED datetime

EOD;
        parent::dbInstall($data);
    }

    function ensureRenameColumns()
    {
        $tables = array('yastations', 'yadevices');
        foreach ($tables as $table) {
            $exists = SQLSelectOne("SHOW TABLES LIKE '" . DBSafe($table) . "'");
            if (empty($exists)) {
                continue;
            }
            $columns = SQLSelect("SHOW COLUMNS FROM `" . DBSafe($table) . "`");
            $known = array();
            foreach ($columns as $column) {
                $known[$column['Field']] = 1;
            }
            if (empty($known['ORIGINAL_TITLE'])) {
                SQLExec("ALTER TABLE `" . DBSafe($table) . "` ADD `ORIGINAL_TITLE` varchar(255) NOT NULL DEFAULT '' AFTER `TITLE`");
                SQLExec("UPDATE `" . DBSafe($table) . "` SET ORIGINAL_TITLE=TITLE WHERE ORIGINAL_TITLE=''");
            }
            if (empty($known['CUSTOM_TITLE'])) {
                SQLExec("ALTER TABLE `" . DBSafe($table) . "` ADD `CUSTOM_TITLE` tinyint(1) NOT NULL DEFAULT 0 AFTER `ORIGINAL_TITLE`");
            }
            if ($table == 'yadevices' && empty($known['SKILL_NAME'])) {
                SQLExec("ALTER TABLE `" . DBSafe($table) . "` ADD `SKILL_NAME` varchar(255) NOT NULL DEFAULT '' AFTER `SKILL_ID`");
            }
            if ($table == 'yastations') {
                $stationColumns = array(
                    'ALARM_LINKED_OBJECT' => "varchar(255) NOT NULL DEFAULT ''",
                    'ALARM_LINKED_PROPERTY' => "varchar(255) NOT NULL DEFAULT ''",
                    'ALARM_LINKED_ID' => "varchar(255) NOT NULL DEFAULT ''",
                    'ALARM_LINKED_VALUE' => "varchar(255) NOT NULL DEFAULT ''",
                    'ASK_QUESTION_LINKED_OBJECT' => "varchar(255) NOT NULL DEFAULT ''",
                    'ASK_QUESTION_LINKED_PROPERTY' => "varchar(255) NOT NULL DEFAULT ''",
                    'ASK_ANSWER_LINKED_OBJECT' => "varchar(255) NOT NULL DEFAULT ''",
                    'ASK_ANSWER_LINKED_PROPERTY' => "varchar(255) NOT NULL DEFAULT ''",
                );
                foreach ($stationColumns as $columnName => $definition) {
                    if (empty($known[$columnName])) {
                        SQLExec("ALTER TABLE `" . DBSafe($table) . "` ADD `" . DBSafe($columnName) . "` " . $definition);
                    }
                }
            }
        }
    }

    function removeStationLinkedProperties($station)
    {
        $pairs = array(
            array('ALARM_LINKED_OBJECT', 'ALARM_LINKED_PROPERTY'),
            array('ASK_QUESTION_LINKED_OBJECT', 'ASK_QUESTION_LINKED_PROPERTY'),
            array('ASK_ANSWER_LINKED_OBJECT', 'ASK_ANSWER_LINKED_PROPERTY'),
        );
        foreach ($pairs as $pair) {
            if (!empty($station[$pair[0]]) && !empty($station[$pair[1]])) {
                removeLinkedProperty($station[$pair[0]], $station[$pair[1]], $this->name);
            }
        }
    }

    function syncStationLinkedProperty($oldObject, $oldProperty, $newObject, $newProperty)
    {
        if ($oldObject && $oldProperty && ($oldObject != $newObject || $oldProperty != $newProperty)) {
            removeLinkedProperty($oldObject, $oldProperty, $this->name);
        }
        if ($newObject && $newProperty) {
            addLinkedProperty($newObject, $newProperty, $this->name);
        }
    }
	
///////////////////////////////////////////////Утилиты////////////////////////////////////////////////////////
    function apiRequest($url, $method = 'GET', $params = 0, $repeating = 0, $extraHeaders = array())
    {
        $debug = 0;

        if ($method != 'GET' && !isset($this->csrf_token)) {
            $this->getToken();
        }

        $YaCurl = curl_init();
        curl_setopt($YaCurl, CURLOPT_URL, $url);
		curl_setopt($YaCurl, CURLOPT_TIMEOUT, 5);
        curl_setopt($YaCurl, CURLOPT_COOKIEFILE, YADEVICES_COOKIE_PATH);
        curl_setopt($YaCurl, CURLOPT_COOKIEJAR, YADEVICES_COOKIE_PATH);
        curl_setopt($YaCurl, CURLOPT_ENCODING, '');
        curl_setopt($YaCurl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($YaCurl, CURLOPT_USERAGENT, $this->getUserAgent());
        if ($method == 'GET') {
            curl_setopt($YaCurl, CURLOPT_POST, false);
        } else {
            $headers = array(
                'Content-type: application/json',
                'x-csrf-token: ' . $this->csrf_token
            );
            if (is_array($extraHeaders) && !empty($extraHeaders)) {
                $headers = array_merge($headers, $extraHeaders);
            }
            curl_setopt($YaCurl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($YaCurl, CURLOPT_POST, true);
            if ($method != 'POST') {
                curl_setopt($YaCurl, CURLOPT_CUSTOMREQUEST, $method);
            }
            if (is_array($params)) {
                curl_setopt($YaCurl, CURLOPT_POSTFIELDS, json_encode($params)); 
			}
        }
        curl_setopt($YaCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($YaCurl, CURLINFO_HEADER_OUT, true);
        curl_setopt($YaCurl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($YaCurl, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($YaCurl);
        $info = curl_getinfo($YaCurl);
        $curlError = curl_error($YaCurl);
        curl_close($YaCurl);
        if ($result === false) {
            $this->writeLog("Ошибка CURL при запросе $url: " . $curlError, true);
            return array('status' => 'error', 'error' => $curlError);
        }
		if($info['http_code'] == 401){
			$this->getConfig();
            $this->normalizeConfig();
			$canRefreshAuth = !empty($this->config['X_TOKEN']);
			if($this->config['AUTHORIZED'] == 1 || $canRefreshAuth){
                if (!$repeating && $this->refreshCookies()) {
                    return $this->apiRequest($url, $method, $params, 1, $extraHeaders);
                }
				if(!$repeating && $this->restoreBackupCookies()){
					return $this->apiRequest($url, $method, $params, 1, $extraHeaders);
				}
				if (!in_array($this->lastAuthRefreshError ?? '', array('missing_x_token', 'invalid_x_token'), true)) {
					$this->writeLog('Авторизация временно недоступна, но x-token сохранен. Повторим восстановление позже.', true);
					return 'Unauthorized';
				}
				if(method_exists($this, 'sendnotification')) {
					$this->sendnotification('Авторизация отсутствует', 'warning ');
				}
			}
			$this->config['AUTHORIZED'] = 0;
			$this->saveConfig();
			return 'Unauthorized';
		}
        $request_headers = $info['request_header'] ?? '';
        if ($debug) {
            dprint("REQUEST HEADERS:",false);
            dprint($request_headers,false);
        }
        $result_code = $info['http_code'];
        $data = json_decode($result, true);

        if (!is_array($data) && $debug) {
            dprint($method." ".$url.'<br/>'.$result,false);
        }
        if (!$repeating &&
            (!isset($data['code']) || $data['code']!= 'BAD_REQUEST') &&   
            ($result_code==403 || (isset($data['status']) && $data['status'] == 'error'))
        ) {
            if ($debug) {
                dprint("REPEATING: ".$method." ".$url,false);
            }
            $this->csrf_token = '';
            $data = $this->apiRequest($url, $method, $params, 1, $extraHeaders);
        }
        return $data;
    }


	function curl($url, $cookie = '', $headers = '', $post = '', $options = ''){
		$YaCurl = curl_init();
		curl_setopt($YaCurl, CURLOPT_URL, $url);
		curl_setopt($YaCurl, CURLOPT_TIMEOUT, 3);
        curl_setopt($YaCurl, CURLOPT_USERAGENT, $this->getUserAgent());
        curl_setopt($YaCurl, CURLOPT_ENCODING, '');
		if($headers != '') curl_setopt($YaCurl, CURLOPT_HTTPHEADER, $headers);
		if($post != ''){
			curl_setopt($YaCurl, CURLOPT_POST, true);
			curl_setopt($YaCurl, CURLOPT_POSTFIELDS, $post);
		} else {
			curl_setopt($YaCurl, CURLOPT_POST, false);
		}
		curl_setopt($YaCurl, CURLOPT_RETURNTRANSFER, true);
		if($cookie != '') {
            curl_setopt($YaCurl, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($YaCurl, CURLOPT_COOKIEJAR, $cookie);
        }
        curl_setopt($YaCurl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($YaCurl, CURLOPT_SSL_VERIFYHOST, false);
		
		//Подключим дополнительные опции
		if(is_array($options)){
			foreach($options as $option => $value){
				curl_setopt($YaCurl, $option, $value);
			}
		}		
		$result = curl_exec($YaCurl);
		//if($url == 'https://passport.yandex.ru/pwl-yandex/api/passport/auth/password/submit') dprint($result);
		if(curl_error($YaCurl)) {
			$result = json_encode(array('error' => curl_error($YaCurl)), JSON_UNESCAPED_UNICODE);
		}
        curl_close($YaCurl);
		return $result;
	} 

	// V.A.S.t
	function message($command, $data, $token, $id = ''){
		//Если есть дополнительные параметры в комаде, через запятую
		//Для gif можно отправлять endless - для бесконечного воспроизведения или till_end_of_speech - анимация будет проигрываться, пока Алиса говорит (сперва запускаем разговор, потом анимацию)(Не работает)
		$param = '';
		if($id == '') $id = uniqid('');
		if(strpos($command, ',')){
			$arg = explode(',', $command);
			$command = $arg[0];
			$param = ',"'. $arg[1] . '":true'; 
		}
		$msg = array(
			'conversationToken' => $token,
			'id' => $id,
			'sentTime' => time() * 1000,
		);
		switch($command){
			case 'setVolume':
				$msg['payload'] = ["command" => "setVolume", "volume" => (float)$data];
				break;
			case 'volumeUp':
				$msg['payload'] = $this->external_command('sound_louder');
				break;
			case 'volumeDown':
				$msg['payload'] = $this->external_command('sound_quiter');
				break;
			case 'text':
				$msg['payload'] = $this->update_form('personal_assistant.scenarios.quasar.iot.repeat_phrase', ['phrase_to_repeat' => $data]);
				break;
			case 'command':
				$msg['payload'] = ["command" => "sendText", "text" => $data];
				break;
			case 'dialog':
				$msg['payload'] = $this->update_form('personal_assistant.scenarios.repeat_after_me', ['request' => $this->fix_dialog_text($data)]);
				break;
			case 'gif':
				$msg['payload'] = $this->external_command('draw_led_screen', '{"animation_sequence":[{"frontal_led_image":"'.$data.'"'.$param.'}]}');
				break;
			case 'rewind':
				$msg['payload'] = ["command" => "rewind", "position" => (int)$data];
				break;
			case 'ping':
			case 'softwareVersion':
			case 'play':
			case 'stop':
			case 'prev':
			case 'next':
				$msg['payload'] = ["command" => $command];
				break;
			case 'repeat': //All, One, None
				$msg['payload'] = ["command" => "repeat", "mode" => $data];
				break;
			case 'shuffle': //bool
				$msg['payload'] = ["command" => "shuffle", "enable" => $data];
				break;
			case 'turnOn':
				$msg['payload'] = $this->update_form('personal_assistant.scenarios.player_continue');
				break;
			case 'turnOff':
				$msg['payload'] = $this->update_form('personal_assistant.scenarios.quasar.go_home');
				break;
			case 'playerState':
				$msg['payload'] = ["command" => "ping"];
				break;
			case 'audio':
				$url = $this->extractMediaId($data);
				if($url != false){
					if($url['type'] == 'music_item'){
						$msg['payload'] = ["command" => "playMusic", "type" => $url['type_item'], 'id' => $url['id']];
					} else if($url['type'] == 'music_playlist'){
						$data = json_decode($this->curl("https://api.music.yandex.net/users/{$url['user']}/playlists/{$url['playlist_id']}"), true);
						if(isset($data['result']['owner']['uid'])) $user_id = $data['result']['owner']['uid'];
						else return false;
						$msg['payload'] = ["command" => "playMusic", "type" => "playlist", 'id' => $user_id . ":" . $url['playlist_id']];
					} else if($url['type'] == 'bookmate'){
						$data = json_decode($this->curl("https://api-gateway-rest.bookmate.yandex.net/audiobook/album", '', array(
		'Content-Type: application/json; charset=UTF-8'), json_encode(["audiobook_uuid" => $url['id']])), true);
						if(isset($data['album_id'])) $album_id = $data['album_id'];
						else return false;
						$msg['payload'] = ["command" => "playMusic", "type" => "album", 'id' => $album_id];
					}
				} else {
					$msg['payload'] = $this->external_command('radio_play', '{"streamUrl":"'.$data.'"'.$param.'}');
				}
				break;
			case 'video':
				$url = $this->extractMediaId($data);
				if($url != false){
					if($url['type'] == 'youtube' or $url['type'] == 'kinopoisk' or $url['type'] == 'strm' or $url['type'] == 'yavideo'){
						$msg['payload'] = $this->play_video_by_descriptor($url['type'], $url['id']);
					} else if($url['type'] == 'vk'){
						$msg['payload'] = $this->play_video_by_descriptor('yavideo', 'https://vk.com/' . $url['id']);
					} else if($url['type'] == 'kinopoisk_id'){
						$data = json_decode($this->curl("https://ott-widget.kinopoisk.ru/ott/api/kp-film-status/?kpFilmId=".$url['id']), true);
						if(isset($data['uuid'])) $uuid = $data['uuid'];
						else return false;
						$msg['payload'] = $this->play_video_by_descriptor('kinopoisk', $url['id']);
					}
				}
				break;
			 default:
				$msg['payload'] = $this->update_form('personal_assistant.scenarios.quasar.iot.repeat_phrase', ['phrase_to_repeat' => $command]);
		}
		//print_r($msg);
		//print json_encode($msg, JSON_NUMERIC_CHECK );
		return json_encode($msg, JSON_UNESCAPED_UNICODE);
	}
	
	function extractMediaId(string $url)
	{
		$patterns = [
			'youtube' => '/https:\/\/(?:youtu\.be\/|www\.youtube\.com\/.+?v=)([0-9A-Za-z_-]{11})/',
			'kinopoisk' => '/https:\/\/hd\.kinopoisk\.ru\/.*([0-9a-z]{32})/',
			'strm' => '/https:\/\/yandex\.ru\/efir\?.*stream_id=([^&]+)/',
			'music_playlist' => '/https:\/\/music\.yandex\.[a-z]+\/users\/(.+?)\/playlists\/(\d+)/',
			'music_item' => '/https:\/\/music\.yandex\.[a-z]+\/.*(artist|track|album)\/(\d+)/',
			'kinopoisk_id' => '/https?:\/\/www\.kinopoisk\.ru\/film\/(\d+)\//',
			'yavideo' => '/(https?:\/\/ok\.ru\/video\/\d+|https?:\/\/vk\.com\/video-?[0-9_]+|https?:\/\/vkvideo\.ru\/video-?[0-9_]+)/',
			'vk' => '/https:\/\/vk\.com\/.*(video-?[0-9_]+)/',
			'bookmate' => '/https:\/\/books\.yandex\.ru\/audiobooks\/(\w+)/'
		];
	
		foreach ($patterns as $type => $pattern) {
			if (preg_match($pattern, $url, $matches)) {
				$result = [
					'type' => $type,
					'id' => $matches[count($matches) - 1]
				];
	
				// Для плейлистов дополнительно возвращаем user_id
				if ($type === 'music_playlist') {
					$result['user'] = $matches[1];
					$result['playlist_id'] = $matches[2];
				}
	
				// Для музыкальных треков дополнительно возвращаем тип
				if ($type === 'music_item') {
					$result['type_item'] = $matches[1];
					$result['id'] = $matches[2];
				}
	
				return $result;
			}
		}
	
		return false;
	}

	function play_video_by_descriptor($provider, $id)
	{
		return ['command' => 'serverAction',
				'serverActionEventPayload' => [
					'type' => 'server_action',
					'name' => 'bass_action',
					'payload' => [
						'data' => [
							'video_descriptor' => [
								'provider_item_id' => $id,
								'provider_name' => $provider
							]
						],
						'name' => 'quasar.play_video_by_descriptor',
					]
				]
			];
	}
	
	function update_form(string $name, array $kwargs = []){
		$response = [
			"command" => "serverAction",
			"serverActionEventPayload" => [
				"type" => "server_action",
				"name" => "update_form",
				"payload" => [
					"form_update" => [
						"name" => $name,
						"slots" => array_map(function($key, $value) {
							return [
								"type" => "string",
								"name" => $key,
								"value" => $value
							];
						}, array_keys($kwargs), array_values($kwargs))
					],
					"resubmit" => true
				]
			]
		];
		
		return $response;
	}

/**
 * Функция для исправления текста диалога
 * 
 * Известные проблемные слова: запа, таблетк, трусы
 * 
 * @param string $text Исходный текст для обработки
 * @return string Текст с преобразованными словами в верхний регистр
 */
function fix_dialog_text(string $text): string 
{
    // Используем правильное регулярное выражение для кириллицы
    return preg_replace_callback(
        '/[а-яё]+/iu', // i - регистронезависимый поиск, u - поддержка юникода
        function($matches) {
            // Преобразуем найденное слово в верхний регистр
            return mb_strtoupper($matches[0], 'UTF-8');
        },
        $text
    );
}

function external_command(string $name, $payload = null): array {
    $data = [1 => $name];
    
    if ($payload !== null) {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }
        $data[2] = $payload;
    }
    
    require_once 'utils/protobuf.php';
    $protobuf = new Protobuf();
    
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $protobuf->setString($key, $value);
        } else {
            $protobuf->setInt32($key, $value);
        }
    }
    
    $encoded_data = base64_encode($protobuf->serialize());
    
    return [
        "command" => "externalCommandBypass",
        "data" => $encoded_data
    ];
}

function writeLog($message, $is_error = false){
	$this->getConfig();
	if ($is_error && $this->config['ERRORMONITOR'] == 1 && $this->config['ERRORMONITORTYPE'] == 1) {
		$trace = debug_backtrace();
		$caller = $trace[1];
		registerError("YaDevice -> {$caller['function']}", $message);
	} else if ($this->config['ERRORMONITOR'] == 1 && $this->config['ERRORMONITORTYPE'] == 2) {
		debmes($message, 'yadevices');
	}
}

function parseUserName(){
	$this->getConfig();
    $this->completeAuthFromCookies();
}

function extractCookies($string) {
    $cookies = array();
    
    $lines = explode("\n", $string);

    // iterate over lines
    foreach ($lines as $line) {

        // we only care for valid cookie def lines
        if (isset($line[0]) && substr_count($line, "\t") == 6) {

            // get tokens in an array
            $tokens = explode("\t", $line);

            // trim the tokens
            $tokens = array_map('trim', $tokens);

            $cookie = array();

            // Extract the data
            $cookie['domain'] = $tokens[0];
            $cookie['flag'] = $tokens[1];
            $cookie['path'] = $tokens[2];
            $cookie['secure'] = $tokens[3];

            // Convert date to a readable format
            $cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);

            $cookie['name'] = $tokens[5];
            $cookie['value'] = $tokens[6];

            // Record the cookie.
            $cookies[] = $cookie;
        }
    }
    
    return $cookies;
}

function type2url($type){
	include "utils/devices_url.php";
	$type_arr = explode('.', $type);
	$station = end($type_arr);
	foreach($devices_URL as $key=>$url){
		if(strpos($key, $station . '_on') !== false){
			return $url;
		}
	}
	return $devices_URL['devices.types.image_icon'];
}

function isLocalCapablePlatform($platform)
{
    return in_array((string)$platform, YADEVICES_LOCAL_PLATFORMS, true);
}

function testLocalGlagolPort($ip, $timeout = 1.0)
{
    $ip = trim((string)$ip);
    if ($ip == '') {
        return false;
    }
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($ip, GLAGOL_PORT, $errno, $errstr, $timeout);
    if (!$fp) {
        return false;
    }
    fclose($fp);
    return true;
}

function detectStationIp($station)
{
    if (empty($station['STATION_ID'])) {
        return false;
    }

    $data = $this->apiRequest('https://quasar.yandex.ru/devices_online_stats');
    if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
        return false;
    }

    foreach ($data['items'] as $item) {
        if (($item['id'] ?? '') != $station['STATION_ID']) {
            continue;
        }
        foreach (array('host', 'ip', 'address', 'local_ip', 'localAddress') as $key) {
            if (!empty($item[$key]) && filter_var($item[$key], FILTER_VALIDATE_IP)) {
                return $item[$key];
            }
        }
    }

    return false;
}


//////////////////////////////Авторизация и токены//////////////////////////////////////////

    function normalizeConfig()
    {
        $defaults = array(
            'API_USERNAME' => '',
            'AUTHORIZED' => 0,
            'OAUTH_TOKEN' => '',
            'X_TOKEN' => '',
            'RELOAD_TIME' => 10,
            'ERRORMONITOR' => 0,
            'ERRORMONITORTYPE' => 2,
            'HIDDEN_SKILLS' => '[]',
            'NOTIFY_GROUPS' => '{}',
        );
        foreach ($defaults as $key => $value) {
            if (!isset($this->config[$key])) {
                $this->config[$key] = $value;
            }
        }
    }

    function getUserAgent()
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36';
    }

    function getPassportAuthUserAgent()
    {
        return $this->getUserAgent() . ' MajorDoMo-YaDevices/1.0';
    }

    function getOAuthDeviceId()
    {
        $this->getConfig();
        $this->normalizeConfig();
        if (empty($this->config['OAUTH_DEVICE_ID'])) {
            $seed = ROOT . '|' . DIR_MODULES . '|' . php_uname('n');
            $this->config['OAUTH_DEVICE_ID'] = 'majordomo-yadevices-' . substr(md5($seed), 0, 16);
            $this->saveConfig();
        }
        return $this->config['OAUTH_DEVICE_ID'];
    }

    function getOAuthDeviceName()
    {
        return 'MajorDoMo YaDevices Module';
    }

    function ensureCookieDir()
    {
        $cookie_dir = dirname(YADEVICES_COOKIE_PATH);
        if (!is_dir($cookie_dir)) {
            umask(0);
            mkdir($cookie_dir, 0777, true);
        }
    }

    function clearAuthFiles()
    {
        @unlink(YADEVICES_COOKIE_PATH);
        @unlink(YADEVICES_COOKIE_PATH . '_back');
        @unlink(YADEVICES_COOKIE_PATH . '_otp');
        @unlink(YADEVICES_COOKIE_PATH . '_qr');
    }

    function cookiesToHeader($cookieFile = YADEVICES_COOKIE_PATH, &$host = 'passport.yandex.ru')
    {
        if (!file_exists($cookieFile)) {
            return '';
        }
        $cookie_data = LoadFile($cookieFile);
        $new_cookies = array();
        $lines = explode("\n", $cookie_data);
        foreach ($lines as $line) {
            if (!preg_match('/^(.*?)\.yandex\./', $line)) {
                continue;
            }
            $values = explode("\t", $line);
            if (count($values) < 7) {
                continue;
            }
            if ($host == 'passport.yandex.ru') {
                $host = ltrim($values[0], '.');
            }
            $cookie_title = $values[5];
            $cookie_value = trim($values[6]);
            if ($cookie_title == '' || $cookie_title == 'yaexpflags') {
                continue;
            }
            $new_cookies[] = $cookie_title . '=' . $cookie_value;
        }
        return implode("; ", $new_cookies);
    }

    function normalizeCookieFile($filePath)
    {
        if (!is_file($filePath)) {
            return false;
        }
        $raw = trim(LoadFile($filePath));
        if ($raw == '') {
            return false;
        }

        if ($raw[0] == '[') {
            $cookies = json_decode($raw, true);
            if (!is_array($cookies)) {
                return false;
            }
            $lines = array("# Netscape HTTP Cookie File");
            foreach ($cookies as $cookie) {
                if (empty($cookie['name']) || !isset($cookie['value'])) {
                    continue;
                }
                $domain = $cookie['domain'] ?? '.yandex.ru';
                $path = $cookie['path'] ?? '/';
                $secure = !empty($cookie['secure']) ? 'TRUE' : 'FALSE';
                $expires = (int)($cookie['expirationDate'] ?? $cookie['expires'] ?? (time() + 31536000));
                $flag = strpos($domain, '.') === 0 ? 'TRUE' : 'FALSE';
                $lines[] = implode("\t", array($domain, $flag, $path, $secure, $expires, $cookie['name'], $cookie['value']));
            }
            SaveFile($filePath, implode("\n", $lines) . "\n");
            return true;
        }

        if (strpos($raw, "\t") === false && strpos($raw, '=') !== false) {
            $lines = array("# Netscape HTTP Cookie File");
            foreach (explode(';', $raw) as $part) {
                $part = trim($part);
                if ($part == '' || strpos($part, '=') === false) {
                    continue;
                }
                list($name, $value) = explode('=', $part, 2);
                $lines[] = implode("\t", array('.yandex.ru', 'TRUE', '/', 'TRUE', time() + 31536000, trim($name), trim($value)));
            }
            SaveFile($filePath, implode("\n", $lines) . "\n");
            return true;
        }

        return true;
    }

    function getXTokenFromCookies($cookieFile = YADEVICES_COOKIE_PATH)
    {
        $host = 'passport.yandex.ru';
        $cookies_line = $this->cookiesToHeader($cookieFile, $host);
        if ($cookies_line == '') {
            $this->writeLog('Cookie-файл пустой или не содержит cookie Яндекса.');
            return false;
        }

        $post = array(
            'client_secret' => YADEVICES_X_TOKEN_CLIENT_SECRET,
            'client_id' => YADEVICES_X_TOKEN_CLIENT_ID,
            'device_id' => $this->getOAuthDeviceId(),
            'device_name' => $this->getOAuthDeviceName(),
        );
        $headers = array(
            'Ya-Client-Host: ' . $host,
            'Ya-Client-Cookie: ' . $cookies_line,
        );

        $result = $this->curl('https://mobileproxy.passport.yandex.net/1/bundle/oauth/token_by_sessionid', $cookieFile, $headers, http_build_query($post));
        $data = json_decode($result, true);
        if (isset($data['error'])) {
            $this->writeLog("Ошибка подключения для получения x-token: " . $data['error']);
            return false;
        }
        if (empty($data['access_token'])) {
            $this->writeLog("Failed to get x-token from cookies:\n" . $result);
            return false;
        }

        $this->config['X_TOKEN'] = $data['access_token'];
        $this->saveConfig();
        return $data['access_token'];
    }

    function validateXToken($xToken)
    {
        if ($xToken == '') {
            return false;
        }
        $headers = array('Authorization: OAuth ' . $xToken);
        $result = $this->curl('https://mobileproxy.passport.yandex.net/1/bundle/account/short_info/?avatar_size=islands-300', '', $headers);
        $data = json_decode($result, true);
        if (!is_array($data) || ($data['status'] ?? '') != 'ok') {
            $this->writeLog("Ошибка проверки x-token:\n" . $result);
            return false;
        }
        if (!empty($data['display_login'])) {
            $this->config['API_USERNAME'] = $data['display_login'];
        }
        return true;
    }

    function loginByXToken($xToken, $cookieFile = YADEVICES_COOKIE_PATH)
    {
        $this->ensureCookieDir();
        $payload = http_build_query(array('type' => 'x-token', 'retpath' => 'https://www.yandex.ru'));
        $headers = array('Ya-Consumer-Authorization: OAuth ' . $xToken);
        $result = $this->curl(
            'https://mobileproxy.passport.yandex.net/1/bundle/auth/x_token/',
            $cookieFile,
            $headers,
            $payload,
            array(CURLOPT_COOKIEFILE => $cookieFile, CURLOPT_COOKIEJAR => $cookieFile)
        );
        $data = json_decode($result, true);
        if (!is_array($data) || ($data['status'] ?? '') != 'ok') {
            $this->writeLog("Ошибка входа по x-token:\n" . $result);
            return false;
        }

        $host = $data['passport_host'] ?? 'https://passport.yandex.ru';
        $trackId = $data['track_id'] ?? '';
        if ($trackId == '') {
            return false;
        }
        $sessionResult = $this->curl($host . '/auth/session/?' . http_build_query(array('track_id' => $trackId)), $cookieFile, '', '', array(
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ));
        if (stripos($sessionResult, '/auth/finish') === false && stripos($sessionResult, 'Set-Cookie:') === false) {
            $this->writeLog("Ошибка создания cookie-сессии по x-token:\n" . $sessionResult);
            return false;
        }

        return true;
    }

    function ensureAuthorized($forceRefresh = false)
    {
        $this->getConfig();
        $this->normalizeConfig();

        if (!$forceRefresh && !empty($this->config['AUTHORIZED']) && file_exists(YADEVICES_COOKIE_PATH)) {
            return true;
        }

        if (empty($this->config['X_TOKEN'])) {
            return !empty($this->config['AUTHORIZED']) && file_exists(YADEVICES_COOKIE_PATH);
        }

        if ($this->refreshCookies()) {
            return true;
        }

        if ($this->restoreBackupCookies()) {
            return true;
        }

        return !empty($this->config['AUTHORIZED']) && file_exists(YADEVICES_COOKIE_PATH);
    }

    function restoreBackupCookies()
    {
        if (!file_exists(YADEVICES_COOKIE_PATH . '_back')) {
            return false;
        }

        copy(YADEVICES_COOKIE_PATH . '_back', YADEVICES_COOKIE_PATH);
        if ($this->checkCookiesAuthorized(YADEVICES_COOKIE_PATH)) {
            $this->completeAuthFromCookies(YADEVICES_COOKIE_PATH);
            $this->writeLog('Автоматическая авторизация из бэкапа успешна!', true);
            return true;
        }

        @unlink(YADEVICES_COOKIE_PATH);
        @unlink(YADEVICES_COOKIE_PATH . '_back');
        $this->writeLog('Бэкап cookie устарел или не подходит для автоматической авторизации.', true);
        return false;
    }

    function checkCookiesAuthorized($cookieFile = YADEVICES_COOKIE_PATH)
    {
        $checkResult = $this->curl('https://yandex.ru/quasar?storage=1', $cookieFile);
        $check = json_decode($checkResult, true);
        if (is_array($check) && !empty($check['storage']['user']['uid'])) {
            return true;
        }

        $checkResult = $this->curl('https://iot.quasar.yandex.ru/m/user/scenarios', $cookieFile);
        $check = json_decode($checkResult, true);
        return is_array($check) && ($check['status'] ?? '') == 'ok';
    }

    function refreshCookies()
    {
        $this->getConfig();
        $this->normalizeConfig();
        $this->lastAuthRefreshError = '';
        if ($this->config['X_TOKEN'] == '') {
            $this->lastAuthRefreshError = 'missing_x_token';
            return false;
        }
        if (!$this->validateXToken($this->config['X_TOKEN'])) {
            $this->lastAuthRefreshError = 'invalid_x_token';
            return false;
        }
        if (!$this->loginByXToken($this->config['X_TOKEN'])) {
            $this->lastAuthRefreshError = 'login_failed';
            return false;
        }
        $this->csrf_token = null;
        if ($this->checkCookiesAuthorized(YADEVICES_COOKIE_PATH)) {
            copy(YADEVICES_COOKIE_PATH, YADEVICES_COOKIE_PATH . '_back');
            $this->config['AUTHORIZED'] = 1;
            $this->saveConfig();
            return true;
        }
        $this->lastAuthRefreshError = 'cookie_check_failed';
        return false;
    }

    function completeAuthFromCookies($cookieFile = YADEVICES_COOKIE_PATH)
    {
        $this->getConfig();
        $this->normalizeConfig();
        $this->normalizeCookieFile($cookieFile);

        if ($cookieFile != YADEVICES_COOKIE_PATH) {
            copy($cookieFile, YADEVICES_COOKIE_PATH);
        }

        $xToken = $this->getXTokenFromCookies(YADEVICES_COOKIE_PATH);
        if ($xToken && $this->validateXToken($xToken)) {
            $this->config['X_TOKEN'] = $xToken;
            $this->getMusicToken($xToken, true);
        } else {
            $cookies = $this->extractCookies(file_get_contents(YADEVICES_COOKIE_PATH));
            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'yandex_login') {
                    $this->config['API_USERNAME'] = $cookie['value'];
                    break;
                }
            }
        }

        $this->config['AUTHORIZED'] = 1;
        copy(YADEVICES_COOKIE_PATH, YADEVICES_COOKIE_PATH . '_back');
        setGlobal('cycle_yadevicesControl', 'restart');
        $this->saveConfig();
        return true;
    }

    function getCSRFToken($cookie_file = YADEVICES_COOKIE_PATH) {
        $this->ensureCookieDir();
		$result = $this->curl('https://passport.yandex.ru/pwl-yandex', $cookie_file, '', '', [CURLOPT_HEADER=>true,CURLOPT_VERBOSE=>false,CURLOPT_FOLLOWLOCATION=>true]);
		$data = json_decode($result, true);
		if(isset($data['error'])){
			$this->writeLog("Ошибка подключении для получения CSRF токена: " . $data['error']);
			return false;
		}
        if (preg_match('/__CSRF__ = "([^"]+)/', $result, $m)) {
            $token = $m[1];
            return $token;
        } else {
            $this->writeLog("Ошибка запроса CSRF токена. Токен по шаблону не найден.\n");
            return false;
        }
    }

    function getToken($url = 'https://yandex.ru/quasar/iot')
    {
        //Получение токенов для отправки запросов в Яндекс
		$result = $this->curl($url, YADEVICES_COOKIE_PATH, '', '', [CURLOPT_HEADER=>true,CURLOPT_VERBOSE=>false,CURLOPT_ENCODING=>'gzip',CURLOPT_FOLLOWLOCATION=>true,CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]);
		$data = json_decode($result, true);
		if(isset($data['error'])){
			$this->writeLog("Ошибка подключении для получения csrfToken2 токена: " . $data['error']);
			return false;
		}
        if (preg_match('/"csrfToken2":"(.+?)"/', $result, $m)) {
            $token = $m[1];
            $this->csrf_token = $token;
            return $token;
        } else {
            $this->writeLog("Ошибка получения csrfToken2 токена:\n" . $result);
            return false;
        }
    }

    function getOAuthToken($force = false)
    {
        $this->getConfig();
        $this->normalizeConfig();
        if ($force) $oauth_token = '';
        else $oauth_token = $this->config['OAUTH_TOKEN'];

        if ($oauth_token != '') return $oauth_token;

        $xToken = $this->config['X_TOKEN'];
        if ($xToken == '') {
            $xToken = $this->getXTokenFromCookies();
        }
        if (!$xToken) {
            return false;
        }

        return $this->getMusicToken($xToken, true);
    }

    function getMusicToken($xToken, $save = true)
    {
        $post = array(
            'client_secret' => YADEVICES_MUSIC_TOKEN_CLIENT_SECRET,
            'client_id' => YADEVICES_MUSIC_TOKEN_CLIENT_ID,
            'grant_type' => 'x-token',
            'access_token' => $xToken,
        );
        $postvars = http_build_query($post);
		
		$result = $this->curl('https://oauth.mobile.yandex.net/1/token', YADEVICES_COOKIE_PATH, '', $postvars);
        $data = json_decode($result,true);
		if(isset($data['error'])){
			$this->writeLog("Ошибка при подключении для получения x-token токена: " . $data['error']);
			return false;
		}
        if (empty($data['access_token'])) {
            $this->writeLog("Failed to get x-token token:\n" . $result);
            return false;
        }
        $oauth_token = $data['access_token'];
        if ($save) {
            $this->config['OAUTH_TOKEN'] = $oauth_token;
            $this->saveConfig();
        }
        return $oauth_token;
    }
	
	function getDeviceTokenByHand($id)
    {
        $req = SQLSelectOne("SELECT STATION_ID, PLATFORM FROM yastations WHERE ID='" . dbSafe($id) . "'");
        $this->getDeviceToken($req['STATION_ID'], $req['PLATFORM']);
        $this->redirect("?id=" . $id . "&view_mode=edit_yastations");
    }

    function getDeviceToken($device_id, $platform, $force = false)
    {
        $oauth_token = $this->getOAuthToken();
		//print $oauth_token.PHP_EOL;
        if (!$oauth_token) return false;
        $url = "https://quasar.yandex.net/glagol/token?device_id=" . $device_id . "&platform=" . $platform;

		$header = array('Content-type: application/json',
						'Authorization: Oauth ' . $oauth_token);
		
		$result = $this->curl($url, YADEVICES_COOKIE_PATH, $header);
		if(!$result){
			$this->writeLog("Ошибка подключения при получении локального токена.");
            return false;
		} else if (is_array($result)){
			$this->writeLog("Неожиданный ответ при получении локального токена: ".$result);
            return false;
		}

        $data = json_decode($result, true);
		if(isset($data['error'])){
			$this->writeLog("Ошибка подключения для получения локального токена: " . $data['error']);
			return false;
		}
        if (isset($data['status']) && $data['status'] == 'ok' && isset($data['token'])) {
            //Запишем токен
            SQLExec("UPDATE yastations SET DEVICE_TOKEN = '" . dbSafe($data['token']) . "' WHERE STATION_ID = '" . dbSafe($device_id) . "'");
            return $data['token'];
        } else {
            $this->writeLog("Ошибка получения локального токена:\n" . $result);
            return false;
        }
    }
	
	////////////////////////////////////////////////////////////////////////////////////////////////

// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRGVjIDMxLCAyMDE5IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
