<?php
defined('XO_DB_ROOT') || define('XO_DB_ROOT', '');
require_once XO_DB_ROOT . 'inc/config.php';

//The code
$protocol = $command = $id = $tissue = $data = $metadata = null;

//Some fallback code for support of old style links
try {
    if (isset($_GET["Annotation"])) {
        $protocol = "Annotation";
        $parsed = explode('/', $_GET["Annotation"]);
        $command = trim(array_shift($parsed));
        //depends on the command, they do not use both params
        $id = $tissue = trim(implode('/', $parsed));
        $user_id = -1;
    } else {
        $in = $_POST;
        if (!isset($_POST["protocol"])) {
            $in = (array)json_decode(file_get_contents("php://input"), true);
        }
        $protocol = $in["protocol"];
        $command = $in["command"];
        $id = $in["id"];
        $tissue = $in["tissuePath"];
        $data = $in["data"];
        $metadata = $in["metadata"];

        require_presence($metadata, "array", "metadata");
        $user_id = (int)$metadata["user"];
        if ($user_id < 1) {
            send(403, "Access denied for unregistered users!");
        }
    }
    $tissue = $tissue ? basename($tissue) : null;

} catch (Exception $e) {
    echo $e;
    die;
}

set_exception_handler(function (Throwable $exception) {
    send(500, $exception->getMessage());
});

function require_presence($var, $type, $missing) {
    if (!isset($var) || gettype($var) !== $type) {
        send(400, "Invalid request: missing or invalid '$missing'!");
    }
}

function send_list_of_annotations_meta($param,
                                       $param_type="string",
                                       $err="tissue unique id",
                                       $getter='xo_annotations_list_all') {
    require_presence($param, $param_type, $err);
    $data = array_map(function ($x) {
        $x["metadata"] = json_decode($x["metadata"]);
        return $x;
    }, call_user_func($getter, $param));
    send_as_json(200, $data);
}

function send_as_json($code, $data) {
    send($code, json_encode($data));
}

function send($code, $data){
    echo $data;
    http_response_code($code);
    exit;
}

require_presence($protocol, "string", "protocol");

try {
    require_once XO_DB_ROOT . 'interfaces/annotations.php';

    switch ($command) {
        case "remove":
            require_presence($id, "string", "id");
            xo_annotations_remove($id, true);
            send_list_of_annotations_meta($tissue);
            break;

        case "update":
            //todo if someone already uploaded, what now? compare metadata!
            require_presence($id, "string", "id");
            require_presence($data, "string", "data");
            require_presence($metadata, "array", "metadata");
            $format = $metadata["annotations-format"];
            require_presence($format, "string", "format type");
            xo_annotations_update($id, $data, $format, "");

            //read annotations to given file by id
            send_list_of_annotations_meta($id,
                "string", "", 'xo_annotations_list_similar_by_annotation_id');
            break;

        case "load":
            require_presence($id, "string", "id");
            send_as_json(200, xo_annotations_read($id));
            break;

        case "list":
            send_list_of_annotations_meta($tissue);
            break;

        case "history":
            //todo untested
            require_presence($id, "string", "id");
            $data = xo_annotations_get_history($id);
            send_as_json(200, $data);
            break;

        case "save":
            require_presence($tissue, "string", "tissue unique id");
            require_presence($data, "string", "data");
            $format = $metadata["annotations-format"];
            require_presence($user_id, "integer", "user id");
            require_presence($format, "string", "format type");
            xo_annotations_create($tissue, $user_id, json_encode($metadata), $data, $format, "");

            send_list_of_annotations_meta($tissue);
            break;

        default:
            require_presence(null, "--fail--", "command");

    }
} catch (Exception $e) {
    send_as_json(500, $e);
}
