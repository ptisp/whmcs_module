<?php

namespace Ptisp;

/**
 * HTTP client for the PTisp REST API.
 *
 * Wraps RestRequest so that registrar functions never touch
 * RestRequest directly; all endpoint knowledge lives here.
 */
class PtispApiClient {

    const BASE_URL = 'https://api.ptisp.pt';

    private $username;
    private $password;

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    // -------------------------------------------------------------------------
    // Private transport helpers
    // -------------------------------------------------------------------------

    private function get($path) {
        $req = new RestRequest(self::BASE_URL . $path, 'GET');
        $req->setUsername($this->username);
        $req->setPassword($this->password);
        $req->execute();
        $response = json_decode($req->getResponseBody(), true) ?? [];
        return $response;
    }

    private function post($path, array $data = []) {
        $req = new RestRequest(self::BASE_URL . $path, 'POST');
        $req->setUsername($this->username);
        $req->setPassword($this->password);
        $req->execute($data);
        $response = json_decode($req->getResponseBody(), true) ?? [];
        return $response;
    }

    // -------------------------------------------------------------------------
    // Domain endpoints
    // -------------------------------------------------------------------------

    public function getDomainInfo($domain) {
        return $this->get("/domains/{$domain}/info");
    }

    public function registerDomain($domain, $period, array $par) {
        return $this->post("/domains/{$domain}/register/{$period}", $par);
    }

    public function renewDomain($domain, $period) {
        return $this->post("/domains/{$domain}/renew/{$period}", []);
    }

    public function transferDomain($domain, $authcode) {
        return $this->post("/domains/{$domain}/transfer/", ['authcode' => $authcode]);
    }

    public function getDomainContacts($domain) {
        return $this->get("/domains/{$domain}/contacts/info");
    }

    public function createContact($domain, array $par) {
        return $this->post("/domains/{$domain}/contacts/create", $par);
    }

    public function updateContact($domain, $contact) {
        return $this->post("/domains/{$domain}/contacts/update/{$contact}", []);
    }

    /**
     * Save nameservers. Pass all four slots (empty strings are filtered out).
     * The API endpoint expects them as path segments: /update/ns/ns1/ns2/...
     */
    public function saveNameservers($domain, array $nameservers) {
        $nsPart = implode('/', array_filter($nameservers));
        return $this->get("/domains/{$domain}/update/ns/{$nsPart}");
    }

    // -------------------------------------------------------------------------
    // Static string utility
    // -------------------------------------------------------------------------

    /**
     * Converts non-ASCII UTF-8 characters to their Unicode escape sequences
     * as required by the PTisp API for contact fields.
     */
    public static function utf8ToUnicode($str) {
        return preg_replace_callback("/./u", function ($m) {
            $ord = ord($m[0]);
            if ($ord <= 127) {
                return $m[0];
            }
            return trim(json_encode($m[0]), '"');
        }, $str);
    }
}
