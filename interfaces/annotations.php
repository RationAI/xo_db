<?php

require_once XO_DB_ROOT . 'inc/io.php';

function xo_annotations_remove($id, $all=false) {
    global $db;
    $remaining = 1;
    if ($all) {
        $db->run("DELETE FROM xopat_annotation_data
                            WHERE annotation_id=?", [
            [$id, PDO::PARAM_INT]
        ]);
        $remaining = 0;
    } else {
        $stmt = $db->run("DELETE FROM xopat_annotation_data
                            WHERE id IN (SELECT id FROM xopat_annotation_data WHERE annotation_id=? ORDER BY tstamp DESC LIMIT 1)", [
            [$id, PDO::PARAM_INT]
        ]);
        if ($stmt->rowCount() > 0) {
            $result = $db->read("SELECT COUNT(*) FROM xopat_annotation_data
                            WHERE annotation_id=?", [[$id, PDO::PARAM_INT]], PDO::FETCH_NUM);
            $remaining = $result[0];
        }
    }
    if ($remaining < 1) {
        $db->run("DELETE FROM xopat_annotations
                            WHERE id=?", [
            [$id, PDO::PARAM_INT]
        ]);
    }
}

function xo_annotations_update($id, $data, $format, $version) {
    global $db;
    $db->run("INSERT INTO xopat_annotation_data(annotation_id, tstamp, data, format, version) 
        VALUES (?, NOW(), ? ,?, ?)", [
        [$id, PDO::PARAM_INT],
        [$data, PDO::PARAM_STR],
        [$format, PDO::PARAM_STR],
        [$version, PDO::PARAM_STR],
    ]);
}

function xo_annotations_read($id) {
    global $db;
    return $db->read("SELECT * FROM xopat_annotations a
          INNER JOIN xopat_annotation_data d ON d.annotation_id=a.id
          WHERE d.annotation_id=? AND a.id=? ORDER BY d.tstamp DESC LIMIT 1", [
        [$id, PDO::PARAM_INT],
        [$id, PDO::PARAM_INT]
    ]);
}

//todo metadata
function xo_annotations_create($tissue, $user_id, $metadata, $data, $format, $version) {
    global $db;
    $db->run("INSERT INTO xopat_annotations(author_user_id, file_id, metadata) 
        VALUES (?, (SELECT id FROM files WHERE name=? LIMIT 1), ?)", [
        [$user_id, PDO::PARAM_INT],
        [$tissue, PDO::PARAM_STR],
        [$metadata, PDO::PARAM_STR]
    ]);
    $id = $db->lastInsertId();
    xo_annotations_update($id, $data, $format, $version);
}

function xo_annotations_list_all($tissue) {
    global $db;
    return $db->read_all("SELECT u.id AS user_id, u.name, u.email,
        a.file_id, a.id, a.metadata
        FROM xopat_annotations a
        LEFT OUTER JOIN users u ON a.author_user_id=u.id
        WHERE a.file_id IN (SELECT id FROM files WHERE name=? LIMIT 1)", [
        [$tissue, PDO::PARAM_STR],
    ]);
}

function xo_annotations_list_similar_by_annotation_id($id) {
    global $db;
    return $db->read_all("SELECT u.id AS user_id, u.name, u.email,
        a.file_id, a.id, a.metadata
        FROM xopat_annotations a
        LEFT OUTER JOIN users u ON a.author_user_id=u.id
        WHERE a.file_id IN (SELECT file_id FROM xopat_annotations WHERE id=? LIMIT 1)", [
        [$id, PDO::PARAM_INT],
    ]);
}

function xo_annotations_get_history($id) {
    global $db;
    return $db->read_all("SELECT * FROM xopat_annotation_data
        WHERE annotation_id=? ORDER BY tstamp DESC", [
        [$id, PDO::PARAM_INT]
    ]);
}
