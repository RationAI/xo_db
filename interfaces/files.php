<?php

require_once XO_DB_ROOT . 'inc/io.php';

function xo_get_file_by_name($name) : array {
    global $db;
    return $db->read("SELECT * FROM files WHERE name LIKE ? LIMIT 1", [
        [$name, PDO::PARAM_STR]
    ]);
}

function xo_update_file_by_id($id, $status=null, $root=null, $biopsy=null) {
    _xo_update_file("id", $id, PDO::PARAM_INT, $status, $root, $biopsy);
}

function xo_update_file_by_name($name, $status=null, $root=null, $biopsy=null) {
    _xo_update_file("name", $name, PDO::PARAM_STR, $status, $root, $biopsy);
}


function _xo_update_file($by_col, $col_name, $col_type, $status=null, $root=null, $biopsy=null) {
    global $db;

    $qry = [];
    $params = [];
    if ($status) { $qry[]="status=?"; $params[]=[$status, PDO::PARAM_STR]; }
    if ($root) { $qry[]="root=?"; $params[]=[$root, PDO::PARAM_STR]; }
    if ($biopsy) { $qry[]="biopsy=?"; $params[]=[$biopsy, PDO::PARAM_STR]; }

    if (count($params) > 0) {
        $qry = implode(",", $qry);
        $params[]=[$col_name, $col_type];
        $db->run("UPDATE files SET $qry WHERE $by_col=?", $params);
    }
}

function xo_file_biopsy_get($biopsy) {
    global $db;
    return $db->read_all("SELECT * FROM files WHERE biopsy=?", [
        [$biopsy, PDO::PARAM_STR]
    ]);
}

function xo_file_biopsy_root_get_by_missing_event($biopsy, $root, $event_name, $cond_value=null) {
    return xo_file_get_by_missing_event("biopsy=? AND root=?",
        [[$biopsy, PDO::PARAM_STR], [$root, PDO::PARAM_STR]], $event_name, $cond_value);
}

function xo_file_name_get_by_missing_event($name, $event_name, $cond_value=null) {
    return xo_file_get_by_missing_event("name=?", [[$name, PDO::PARAM_STR]], $event_name, $cond_value);
}

function xo_file_name_list_get_by_missing_event($file_list, $event_name, $cond_value=null) : array {
    $qry = implode(",", array_map(fn($x) => '?', $file_list));
    return xo_file_get_by_missing_event("name IN ($qry)",
        array_map(fn($x) => [$x, PDO::PARAM_STR], $file_list), $event_name, $cond_value);
}

/**
 * @param $file_cond string SQL condition on files table
 * @param $params array array of param definitions for wildcards in the SQL statement
 * @param $event_name string event name to search for
 * @param $data_cond_val string|null optionally also require data is NOT equal to the cond value, can use wildcards
 * @return mixed array of file records that have no event name record, possibly with constraint of
 *   not having only event with data=$data_cond_val
 * @throws Exception
 */
function xo_file_get_by_missing_event(string $file_cond, array $params, string $event_name, string $data_cond_val=null) {
    global $db;

    $cond_qry = "";
    $params[]=[$event_name, PDO::PARAM_STR];
    if ($data_cond_val) {
        $cond_qry = " AND data NOT LIKE ?";
        $params[]=[$data_cond_val, PDO::PARAM_STR];
    }

    //file_events are never updated but created with changes, so valid event is always the latest timestamp
    return $db->read_all("SELECT * FROM files WHERE {$file_cond} AND id NOT IN 
        (SELECT file_id FROM file_events WHERE event=?{$cond_qry} ORDER BY tstamp DESC LIMIT 1)", $params);
}

function xo_file_name_get_latest_event($name, $event_name) {
    global $db;
    return $db->read("SELECT * FROM files f
                            INNER JOIN file_events e ON f.id = e.file_id
                            WHERE f.name=? AND e.event=?
                            ORDER BY e.tstamp DESC LIMIT 1", [
        [$name, PDO::PARAM_STR],
        [$event_name, PDO::PARAM_STR]
    ]);
}

function xo_files_by_id($id_list) : array {
    global $db;
    $qry = implode(",", array_map(fn($x) => '?', $id_list));
    return $db->read_all("SELECT * FROM files WHERE id IN ($qry)", [
        ... array_map(fn($x) => [$x, PDO::PARAM_INT], $id_list)
    ]);
}

function xo_file_name_event($name, $event, $data) {
    global $db;
    $db->run("INSERT INTO file_events(file_id, event, tstamp, data) VALUES ((SELECT id FROM files WHERE name=?), ?, NOW(), ?)", [
        [$name, PDO::PARAM_STR],
        [$event, PDO::PARAM_STR],
        [$data, PDO::PARAM_STR],
    ]);
}

function xo_file_id_event($file_id, $event, $data) {
    global $db;
    $db->run("INSERT INTO file_events(file_id, event, tstamp, data) VALUES (?, ?, NOW(), ?)", [
        [$file_id, PDO::PARAM_INT],
        [$event, PDO::PARAM_STR],
        [$data, PDO::PARAM_STR],
    ]);
}
function xo_file_id_get_events($file_id) {
    global $db;
    $db->run("SELECT * FROM file_events WHERE file_id=? ORDER BY tstamp DESC", [
        [$file_id, PDO::PARAM_INT]
    ]);
}

function xo_insert_or_get_file($name, $status, $root, $biopsy) : array {
    global $db;
    $file = xo_get_file_by_name($name);
    if (empty($file)) {
        $db->run("INSERT INTO files(name, created, status, root, biopsy) VALUES (?, NOW(), ?, ?, ?)", [
            [$name, PDO::PARAM_STR],
            [$status, PDO::PARAM_STR],
            [$root, PDO::PARAM_STR],
            [$biopsy, PDO::PARAM_STR],
        ]);
        $file = xo_get_file_by_name($name);
    }
    return $file;
}

function xo_insert_or_ignore_file($name, $status, $root, $biopsy): ?array {
    global $db;
    $file = xo_get_file_by_name($name);
    if (empty($file)) {
        $db->run("INSERT INTO files(name, created, status, root, biopsy) VALUES (?, NOW(), ?, ?, ?)", [
            [$name, PDO::PARAM_STR],
            [$status, PDO::PARAM_STR],
            [$root, PDO::PARAM_STR],
            [$biopsy, PDO::PARAM_STR],
        ]);
        return null;
    }
    return $file;
}

function xo_files_erase() {
    global $db;
    $db->run("DELETE FROM seen_files");
    $db->run("DELETE FROM file_events");
    $db->run("DELETE FROM xopat_session");
    $db->run("DELETE FROM files");
}
