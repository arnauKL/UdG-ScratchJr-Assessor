<?php
define("ROOT", $_SERVER["DOCUMENT_ROOT"]);
session_start();
const BLOCKS = [
    "yellow_start" => ["onflag", "onclick", "ontouch", "onmessage", "message"],
    "blue_motion" => [
        "forward",
        "back",
        "up",
        "down",
        "right",
        "left",
        "hop",
        "home",
    ],
    "purple_looks" => [
        "say",
        "space",
        "grow",
        "shrink",
        "same",
        "space",
        "hide",
        "show",
    ],
    "green_sound" => ["playusersnd"],
    "orange_control" => [
        "wait",
        "stopmine",
        "setspeed",
        "repeat",
        "caretstart",
        "caretend",
        "caretrepeat",
        "caretcmd",
    ],
    "red_end" => ["endstack", "forever", "gotopage"],
];

const DEFAULTSVALUES = [
    "onflag" => null,
    "onmessage" => "Orange",
    "onclick" => null,
    "ontouch" => null,
    "message" => "Orange",
    "repeat" => 4,
    "forward" => 1,
    "back" => 1,
    "up" => 1,
    "down" => 1,
    "right" => 1,
    "left" => 1,
    "home" => null,
    "hop" => 2,
    "wait" => 10,
    "setspeed" => 1,
    "stopmine" => null,
    "say" => [
        "hi",
        "hola",
        "Bonjour",
        "ciao",
        "hoi",
        "はい",
        "olá",
        "hej",
        "สวัสดี",
        "嗨",
    ],
    "show" => null,
    "hide" => null,
    "grow" => 2,
    "shrink" => 2,
    "same" => null,
    "playsnd" => "pop.mp3",
    "playusersnd" => "1",
    "endstack" => null,
    "forever" => null,
    "gotopage" => "2",
    "caretstart" => null,
    "caretend" => null,
    "caretrepeat" => null,
    "caretcmd" => null,
];

require_once ROOT . "/controllers/HomeController.php";
require_once ROOT . "/controllers/AnalysisController.php";
require_once ROOT . "/controllers/SVGController.php";

$home_controller = new HomeController();
$analysis_controller = new AnalysisController();
$svg_controller = new SVGController();

$action = $_SERVER["REQUEST_URI"];
$path = array_slice(explode("/", $action), 1);
if ($path[sizeof($path) - 1] === "") {
    unset($path[sizeof($path) - 1]);
}

if (sizeof($path) !== 0 && (isset($path[0]) && $path[0][0] !== "?")) {
    if (isset(${$path[0] . "_controller"})) {
        if (isset($path[1]) && $path[1] !== "") {
            $parameters = array_slice($path, 1);
            ${$path[0] . "_controller"}->rerouting(...$parameters);
        } else {
            ${$path[0] . "_controller"}->main();
        }
    } else {
        // This does not exist?
        $error_controller->notfound();
    }
} else {
    $home_controller->main();
}
