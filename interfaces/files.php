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
    if ($biopsy) { $qry[]="biopsy=?"; $params[]=[$biopsy, PDO::PARAM_INT]; }

    if (count($params) > 0) {
        $qry = implode(",", $qry);
        $params[]=[$col_name, $col_type];
        $db->run("UPDATE files SET $qry WHERE $by_col=?", $params);
    }
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

function xo_insert_or_get_file($name, $request_id, $status, $root, $biopsy) : array {
    global $db;
    $file = xo_get_file_by_name($name);
    if (empty($file)) {
        $db->run("INSERT INTO files(name, created, status, root, biopsy) VALUES (?, NOW(), ?, ?, ?)", [
            [$name, PDO::PARAM_STR],
            [$status, PDO::PARAM_STR],
            [$root, PDO::PARAM_STR],
            [$biopsy, PDO::PARAM_INT],
        ]);
        $file = xo_get_file_by_name($name);
        xo_file_id_event($file["id"], "upload", $request_id);
    }
    return $file;
}
