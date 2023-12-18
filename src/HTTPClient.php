<?php

namespace NikolaDev\ImagineCode;

class HTTPClient {
    /**
     * HTTP response status; will contain associative array representing
     * the HTTP version, status code, and reason phrase.
     */
    private $responseStatus = null;

    /**
     * HTTP response header; will contain associative array of header
     * attributes returned from the cURL request.
     */
    private $responseHeader = null;

    /**
     * HTTP response body; will contain a string representing the body
     * of the response returned from the cURL request.
     */
    private $responseBody = null;

    function get($url, $params = array(), $header = null) {
        return $this->makeRequest('GET', Format::url($url, $params), null, $header);
    }
    function post($url, $body = null, $header = null) {
        return $this->makeRequest('POST', $url, $body, $header);
    }
    function download($url, $dest_path, $headers = null, $timeout = 60) {
        $error = new Error();
        set_time_limit(0);
        $filename = basename($dest_path);
        if(!file_exists(dirname($dest_path)))
            mkdir(dirname($dest_path), 0755, true);

        if(!file_exists(dirname($dest_path)))
            return $error->addData($dest_path)->add('path', 'Directory cannot be created.');

        $temp = dirname($dest_path) . '/' . $filename . '.tmp';
        $fp = fopen ($temp, 'w+');
        if(!$fp)
            return $error->addData($dest_path)->add('path', 'Temporary file cannot be created.');

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if ($headers !== null)
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
        }
        catch(\Exception $ex) {
            fclose($fp);
            @unlink($temp);
            return $error->addData($dest_path)->add('curl', $ex->getMessage());
        }
        fclose($fp);
        if(filesize($temp) > 0) {
            if(file_exists($dest_path)) {
                if(!unlink($dest_path)) {
                    unlink($temp);
                    return $error->addData($dest_path)->add('path', 'Old file cannot be overwritten.');
                }
            }

            rename($temp, $dest_path);
            return true;
        }
        unlink($temp);
        return $error->addData($dest_path)->add('path', 'File size is zero.');
    }
    /**
     * Make an HTTP request.  Defaults to a simple GET request if only
     * the $url parameter is specified.  Returns the complete response
     * header and body in a PHP-friendly data structure.
     *
     * @param string $method The HTTP request method to use for this request.
     * @param string $url A complete URL including URL parameters.
     * @param string $body The string literal containing request body data (eg. POST params go here).
     * @param array $headers
     * @return array Associative array containing response header and body as 'header' and 'body' keys.
     */
    function makeRequest($method, $url, $body = null, $headers = null) {
        // Reinitialize response header and body.
        $this->responseHeader = null;
        $this->responseBody = null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($headers !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'handleResponseHeader'));
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'handleResponseBody'));

        // Additional options need to be set for PUT and POST requests.
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else if ($method == 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Execute and close the request and close the connection
        // handler as quickly as possible.
        curl_exec($ch);
        curl_close($ch);

        return array(
            'status' => $this->responseStatus,
            'header' => $this->responseHeader,
            'body' => $this->responseBody,
        );
    }

    /**
     * Process an incoming response header following a cURL request and
     * store the header in $this->responseHeader.
     *
     * @param Object $ch The cURL handler instance.
     * @param String $headerData The header to handle; expects header to come in one line at a time.
     * @return int The length of the input data.
     */
    private function handleResponseHeader($ch, $headerData) {
        // If we haven't found the HTTP status yet, then try to match it.
        if ($this->responseStatus == null) {
            $regex = '/^\s*HTTP\s*\/\s*(?P<protocolVersion>\d*\.\d*)\s*(?P<statusCode>\d*)\s(?P<reasonPhrase>.*)\r\n/';
            preg_match($regex , $headerData, $matches);

            foreach (array('protocolVersion', 'statusCode', 'reasonPhrase') as $part) {
                if (isset($matches[$part])) {
                    $this->responseStatus[$part] = $matches[$part];
                }
            }
        }

        // Digest HTTP header attributes.
        if (!isset($responseStatusMatches) || empty($responseStatusMatches)) {
            $regex = '/^\s*(?P<attributeName>[a-zA-Z0-9-]*):\s*(?P<attributeValue>.*)\r\n/';
            preg_match($regex, $headerData, $matches);

            if (isset($matches['attributeName'])) {
                $this->responseHeader[$matches['attributeName']] = isset($matches['attributeValue']) ? $matches['attributeValue'] : null;
            }
        }

        return strlen($headerData);
    }

    /**
     * Process an incoming response body following a cURL request
     * and store the body in $this->responseBody.
     *
     * @param Object $ch The cURL handler instance.
     * @param String $bodyData The body data to handle.
     * @param int The length of the input data.
     */
    private function handleResponseBody($ch, $bodyData) {
        $this->responseBody .= $bodyData;

        return strlen($bodyData);
    }
}
