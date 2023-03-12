<?php
require_once XO_DB_ROOT . 'inc/io.php';

/**
 * Add User Record Without Access Restriction
 * @throws Exception
 */
function xo_add_user(string $name, string $auth, string $auth_id, string $secret, string $email=null): array {
    global $db;
    $user = xo_get_user_by("name", $name);
    if (empty($user)) $user = xo_get_user_by("email", $email);
    if (empty($user)) {
        $db->run("INSERT INTO users(name, email, created) VALUES (?, ?, NOW())", [
            [$name, PDO::PARAM_STR],
            $email ? [$email, PDO::PARAM_STR] : [null, PDO::PARAM_NULL]
        ]);
        $user = xo_get_user_by("name", $name);
    }

    $db->run("INSERT INTO auth(user_id, type, type_id, secret) VALUES (?, ?, ?, ?)", [
        [$user["id"], PDO::PARAM_INT],
        [$auth, PDO::PARAM_STR],
        [$auth_id, PDO::PARAM_STR],
        [$secret, PDO::PARAM_STR],
    ]);

    //todo extend user with auth data?
    return $user;
}

/**
 * Set User Access Restriction (relative root path)
 * @param $name
 * @param $path
 * @return void
 */
function set_user_root($name, $path) {
    //todo
    throw new Exception("not implemented");
}

/**
 * Get User Data
 * @param $name string email or name (unsafe to SQL injection)
 * @param $value string value to search by
 * @return array
 * @throws Exception
 */
function xo_get_user_by(string $name, string $value): array {
    global $db;
    //todo possibly missing data, return all...
    return $db->read("SELECT * FROM users u LEFT OUTER JOIN access a ON u.id=a.user_id WHERE u.$name=?", [
        [$value, PDO::PARAM_STR]
    ]);
}

/**
 * Get User Auth by Auth ID
 * @param $name string email or name (unsafe to SQL injection)
 * @param $value string value to search by
 * @return array
 * @throws Exception
 */
function xo_get_user_by_with_auth(string $name, string $value, string $auth_id): array {
    global $db;
    return $db->read("SELECT * FROM users u LEFT OUTER JOIN auth a ON u.id=a.user_id WHERE u.$name=? AND a.type_id=? LIMIT 1", [
        [$value, PDO::PARAM_STR],
        [$auth_id, PDO::PARAM_STR]
    ]);
}

/**
 * @throws Exception
 */
function xo_file_list_not_seen_by_user($file_list, $user_id) : array {
    global $db;
    $qry = implode(",", array_map(fn($x) => '?', $file_list));
    return $db->read("SELECT * FROM files f 
        LEFT OUTER JOIN (SELECT * FROM seen_files WHERE user_id=?) t ON f.id=t.file_id 
        WHERE f.name IN ($qry) AND t.file_id IS NULL", [
        [$user_id, PDO::PARAM_INT],
        ... array_map(fn($x) => [$x, PDO::PARAM_STR], $file_list)
    ]);
}

function xo_file_seen_by($filename, $user_id) {
    global $db;
    $db->run("INSERT INTO seen_files (user_id,file_id) VALUES (?, (SELECT id FROM files WHERE name LIKE ?)) ON CONFLICT DO NOTHING", [
        [$user_id, PDO::PARAM_INT],
        [$filename, PDO::PARAM_STR]
    ]);
}
