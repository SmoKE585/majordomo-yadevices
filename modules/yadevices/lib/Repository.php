<?php
/**
 * YaDevices database access.
 *
 * Thin typed wrapper around the legacy SQL helpers. Keeping SQL here makes the
 * admin controller easier to read and gives one place to escape identifiers.
 *
 * @package MajorDoMo\Modules\YaDevices
 */

final class YaDevicesRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function stations(): array
    {
        $rows = SQLSelect('SELECT * FROM yastations ORDER BY IS_ONLINE DESC, TITLE');
        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function station(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = SQLSelectOne('SELECT * FROM yastations WHERE ID=' . $id);
        return is_array($row) && !empty($row['ID']) ? $row : null;
    }

    /**
     * @param string $iotId
     * @return array<string,mixed>|null
     */
    public function stationByIotId(string $iotId): ?array
    {
        if ($iotId === '') {
            return null;
        }
        $row = SQLSelectOne("SELECT * FROM yastations WHERE IOT_ID='" . DBSafe($iotId) . "'");
        return is_array($row) && !empty($row['ID']) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function devices(): array
    {
        $rows = SQLSelect('SELECT * FROM yadevices ORDER BY SKILL_NAME, TITLE');
        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function device(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = SQLSelectOne(
            'SELECT yadevices.*, yastations.IP, yastations.ID AS yastationID'
            . ' FROM yadevices'
            . ' LEFT JOIN yastations ON yadevices.IOT_ID=yastations.IOT_ID'
            . ' WHERE yadevices.ID=' . $id
        );
        return is_array($row) && !empty($row['ID']) ? $row : null;
    }

    /**
     * @param string $iotId
     * @return array<string,mixed>|null
     */
    public function deviceByIotId(string $iotId): ?array
    {
        if ($iotId === '') {
            return null;
        }
        $row = SQLSelectOne("SELECT * FROM yadevices WHERE IOT_ID='" . DBSafe($iotId) . "'");
        return is_array($row) && !empty($row['ID']) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function capabilities(): array
    {
        $rows = SQLSelect('SELECT * FROM yadevices_capabilities ORDER BY TITLE');
        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $deviceId
     * @return array<int,array<string,mixed>>
     */
    public function deviceCapabilities(int $deviceId): array
    {
        if ($deviceId <= 0) {
            return array();
        }
        $rows = SQLSelect('SELECT * FROM yadevices_capabilities WHERE YADEVICE_ID=' . $deviceId . ' ORDER BY TITLE');
        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function capability(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = SQLSelectOne('SELECT * FROM yadevices_capabilities WHERE ID=' . $id);
        return is_array($row) && !empty($row['ID']) ? $row : null;
    }

    /**
     * @param array<string,mixed> $record
     * @return int
     */
    public function saveStation(array $record): int
    {
        if (!empty($record['ID'])) {
            SQLUpdate('yastations', $record);
            return (int)$record['ID'];
        }
        return (int)SQLInsert('yastations', $record);
    }

    /**
     * @param array<string,mixed> $record
     * @return int
     */
    public function saveDevice(array $record): int
    {
        if (!empty($record['ID'])) {
            SQLUpdate('yadevices', $record);
            return (int)$record['ID'];
        }
        return (int)SQLInsert('yadevices', $record);
    }

    /**
     * @param array<string,mixed> $record
     * @return int
     */
    public function saveCapability(array $record): int
    {
        if (!empty($record['ID'])) {
            SQLUpdate('yadevices_capabilities', $record);
            return (int)$record['ID'];
        }
        return (int)SQLInsert('yadevices_capabilities', $record);
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteStation(int $id): void
    {
        if ($id > 0) {
            SQLExec('DELETE FROM yastations WHERE ID=' . $id);
        }
    }

    /**
     * @param int $id
     * @return void
     */
    public function deleteDevice(int $id): void
    {
        if ($id > 0) {
            SQLExec('DELETE FROM yadevices_capabilities WHERE YADEVICE_ID=' . $id);
            SQLExec('DELETE FROM yadevices WHERE ID=' . $id);
        }
    }

    /**
     * @param string $table
     * @return int
     */
    public function count(string $table): int
    {
        $allowed = array('yastations', 'yadevices', 'yadevices_capabilities');
        if (!in_array($table, $allowed, true)) {
            return 0;
        }
        $row = SQLSelectOne('SELECT COUNT(*) AS CNT FROM ' . $table);
        return isset($row['CNT']) ? (int)$row['CNT'] : 0;
    }
}
