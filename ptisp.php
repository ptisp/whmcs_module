<?php

//v2.1.2

require_once("RestRequest.inc.php");

function ptisp_getConfigArray() {
    $configarray = array(
        "Username" => array("Type" => "text", "Size" => "20", "Description" => "Enter your username here",),
        "Hash" => array("Type" => "password", "Size" => "100", "Description" => "Enter your access hash here",),
        "Vatcustom" => array("Type" => "text", "Size" => "100", "Description" => "VAT customer's customfield name",),
    );
    return $configarray;
}

function ptisp_GetContactDetails($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/contacts/info', 'GET');
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute();
    $result = json_decode($request->getResponseBody(), true);

    if (strpos($tld, "pt") !== false) {
        $values["Tech"]["Nic"] = $result["data"]["nic"];
        $values["Tech"]["Name"] = $result["data"]["name"];
        $values["Tech"]["Street"] = $result["data"]["street"];
        $values["Tech"]["City"] = $result["data"]["city"];
        $values["Tech"]["Postal"] = $result["data"]["postal"];
        $values["Tech"]["Country"] = $result["data"]["country"];
        $values["Tech"]["Email"] = $result["data"]["email"];
        $values["Tech"]["Phone"] = $result["data"]["phone"];
        $values["Tech"]["Id"] = $result["data"]["id"];
    }

    return $values;
}

function ptisp_SaveContactDetails($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    if (strpos($tld, "pt") !== false) {
        if (empty($params["contactdetails"]["Tech"]["Nic"])) {
            $request = new RestRequest('https://api.ptisp.pt/domains/contacts/create', 'POST');
            $request->setUsername($username);
            $request->setPassword($password);
            $par = array("name" => utf8ToUnicode($params["contactdetails"]["Tech"]["Name"]), "vat" => $params["contactdetails"]["Tech"]["Id"], "postalcode" => $params["contactdetails"]["Tech"]["Postal"], "country" => $params["contactdetails"]["Tech"]["Country"], "address" => utf8ToUnicode($params["contactdetails"]["Tech"]["Street"]), "phone" => $params["contactdetails"]["Tech"]["Phone"], "mail" => utf8ToUnicode($params["contactdetails"]["Tech"]["Email"]), "city" => utf8ToUnicode($params["contactdetails"]["Tech"]["City"]));
            $request->execute($par);
            $result = json_decode($request->getResponseBody(), true);
            $nichandle = $result["nichandle"];
        } else {
            $nichandle = $params["contactdetails"]["Tech"]["Nic"];
        }

        if (!empty($nichandle)) {
            $contact = $nichandle;
            $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/contacts/update/' . $contact, 'POST');
            $request->setUsername($username);
            $request->setPassword($password);
            $request->execute(array());
            $result = json_decode($request->getResponseBody(), true);
            
            error_log(print_r($result, true));
            $values["error"] = $result["error"];
        } else {
            $values["error"] = $result["error"];
        }
    } else {
        $values["error"] = "tld not supported";
    }

    return $values;
}

function ptisp_GetNameservers($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/info', 'GET');
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute();

    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        if(empty($result['error'])) {
            $values["error"] = "unknown";   
        } else {
            $values["error"] = $result['error'];   
        }
    } else {
        $values["ns1"] = $result['data']['ns'][0];
        $values["ns2"] = $result['data']['ns'][1];
        $values["ns3"] = $result['data']['ns'][2];
        $values["ns4"] = $result['data']['ns'][3];
    }

    return $values;
}

function ptisp_SaveNameservers($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $nameserver1 = $params["ns1"];
    $nameserver2 = $params["ns2"];
    $nameserver3 = $params["ns3"];
    $nameserver4 = $params["ns4"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/update/ns/' . $nameserver1 . '/' . $nameserver2 . '/' . $nameserver3 . '/' . $nameserver4, 'GET');
    $request->setUsername($username);
    $request->setPassword($password);
    $request->execute();

    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        if(empty($result['error'])) {
            $values["error"] = "unknown";   
        } else {
            $values["error"] = $result['error'];   
        }
    }

    return $values;
}

function ptisp_RenewDomain($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $regperiod = $params["regperiod"];

    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/renew/' . $regperiod, 'POST');
    $request->setUsername($username);
    $request->setPassword($password);

    $request->execute(array());
    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        $values["error"] = $result['error'];
    }

    return $values;
}

function ptisp_RegisterDomain($params) {
    $username = $params["Username"];
    $password = $params["Hash"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $regperiod = $params["regperiod"];


    $request = new RestRequest('https://api.ptisp.pt/domains/' . $sld . "." . $tld . '/register/' . $regperiod, 'POST');
    $request->setUsername($username);
    $request->setPassword($password);


    if (empty($params["additionalfields"]["Nichandle"])) {
        $contact = $params["additionalfields"]["Nichandle"];
    } else if (!empty($params[$params["Vatcustom"]])) {
        $request = new RestRequest('https://api.ptisp.pt/domains/contacts/create', 'POST');
        $request->setUsername($username);
        $request->setPassword($password);
        $par = array("name" => $params["firstname"], "vat" => $params[$params["Vatcustom"]], "postalcode" => $params["postcode"], "country" => $params["country"], "address" => $params["address1"], "phone" => $params["phonenumber"], "mail" => $params["email"], "city" => $params["city"]);
        $request->execute($par);
        $result = json_decode($request->getResponseBody(), true);
        if ($result["result"] === "ok") {
            $contact = $result["nichandle"];
        }
    }

    if (empty($contact)) {
        $par = array("ns" => $params["ns1"]);
    } else {
        $par = array("ns" => $params["ns1"], "contact" => $contact);
    }

    $request->execute($par);

    $result = json_decode($request->getResponseBody(), true);

    if ($result['result'] != "ok") {
        if(empty($result['error'])) {
            $values["error"] = "unknown";   
        } else {
            $values["error"] = $result['error'];   
        }
    }

    return $values;
}

function utf8ToUnicode($str) {
    return preg_replace_callback('/./u', function ($m) {
                $ord = ord($m[0]);
                if ($ord <= 127) {
                    return $m[0];
                } else {
                    return trim(json_encode($m[0]), '"');
                }
            }, $str);
}

?>