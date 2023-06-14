<?php
    //Kamailio AUTH API
    mb_internal_encoding("UTF-8");
    require_once "utils/error.php";
    require_once "utils/loader.php";
    require_once "utils/checkint.php";
    require_once "utils/db_ext.php";
    require_once "backends/backend.php";

    header('Content-Type: application/json');

    // Check config
    try {
        $config = @json_decode(file_get_contents(__DIR__ . "/config/config.json"), true);
    } catch (Exception $e) {
        $config = false;
    }

    if (!$config) {
        try {
            $config = @json_decode(json_encode(yaml_parse_file(__DIR__ . "/config/config.yml")), true);
        } catch (Exception $e) {
            $config = false;
        }
    }

    if (!$config) {
        echo "config is empty\n";
        exit(1);
    }

    if (@!$config["backends"]) {
        echo "no backends defined\n";
        exit(1);
    }

    try {
        $db = new PDO_EXT(@$config["db"]["dsn"], @$config["db"]["username"], @$config["db"]["password"], @$config["db"]["options"]);
    } catch (Exception $e) {
        echo "can't open database " . $config["db"]["dsn"] . "\n";
        echo $e->getMessage() . "\n";
        exit(1);
    }

    // Check Kamailio config
    $kamailio_config = false;
    foreach ($config['backends']['sip']['servers'] as $server){
          if ($server['type'] === 'kamailio') {
              $kamailio_config = $server;
              break;
          }
    }
    if (@!$kamailio_config){
        echo "No Kamailio config";
        exit(1);
    }

    $path = $_SERVER["REQUEST_URI"];
    $server = parse_url($config["api"]["kamailio"]);

    if ($server && $server['path']) {
        $path = substr($path, strlen($server['path']));
    }

    if ($path && $path[0] == '/') {
        $path = substr($path, 1);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $path == 'subscribers') {
        $postdata = json_decode(file_get_contents("php://input"), associative:  true);

        //TODO: verification endpoint and payload, check domain
        $from_uri = $postdata['from_uri'];
        [$subscriber, $sip_domain] = explode('@', substr($from_uri, 4));

        if ( strlen((int)$subscriber) !== 10 ) {
            http_response_code(400);
            echo json_encode(['status' => 'Bad Request', 'message' => 'Invalid username']);
            exit(1);
        }

        //Get subscriber and validate
        $flat_id = (int)substr( $subscriber, 1);
        $households = loadBackend("households");

        $flat = $households->getFlat($flat_id);

        if ($flat && $flat['sipEnabled']) {
            $sip_passwd = $flat['sipPassword'];

            //md5(username:realm:password)
            $ha1 = md5($subscriber .':'. $kamailio_config['domain'] .':'. $sip_passwd );
            $str = $subscriber .':'. $kamailio_config['domain'] .':'. $sip_passwd;

            echo json_encode( [ 'ha1' => $ha1]);
            exit(1);
        }

        //sip disabled
        http_response_code(403);
        echo json_encode(['status' => 'Forbidden', 'message' => 'SIP not enabled']);
        exit(1);
    }

    //TODO: make response
    http_response_code(400);
    echo json_encode(['status' => 'Bad Request', 'message' => null]);