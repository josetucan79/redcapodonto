<?php
/**
 * AzureBlob Class
 * Contains methods used with regard to create, get, delete, copy files to Azure Blob Storage using REST API
 */
class AzureBlob
{
    /**
     * Create/Upload file in Microsoft Azure Blob Storage using REST API method
     * @param string $container_name Azure container name
     * @param string $stored_name File stored name
     * @param string $file_contents File contents
     * @return boolean File uploaded status true|false
     */
    public function createBlockBlob($container_name, $stored_name, $file_contents)
    {
        $azure_app_name = $GLOBALS['azure_app_name'];
        $secret_key = $GLOBALS['azure_app_secret'];
        $azure_environment = $GLOBALS['azure_environment'];

        // Put file contents in temp file then delete this file
        $filepath = APP_PATH_TEMP . "blob_" . $stored_name;
        file_put_contents($filepath, $file_contents);

        $request_url = "https://$azure_app_name.$azure_environment/$container_name/$stored_name";
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $handle = fopen($filepath, "r");
        $file_len = filesize($filepath);
        unlink($filepath);

        $header_resource = "x-ms-blob-cache-control:max-age=3600\nx-ms-blob-type:BlockBlob\nx-ms-date:$date\nx-ms-version:2023-11-03";
        $url_resource = "/$azure_app_name/$container_name/$stored_name";

        $mime_type = Files::mime_content_type($stored_name);

        $sign_arr = array();
        $sign_arr[] = 'PUT';               /*HTTP Verb*/
        $sign_arr[] = '';                  /*Content-Encoding*/
        $sign_arr[] = '';                  /*Content-Language*/
        $sign_arr[] = $file_len;           /*Content-Length (include value when zero)*/
        $sign_arr[] = '';                  /*Content-MD5*/
        $sign_arr[] = $mime_type;          /*Content-Type*/
        $sign_arr[] = '';                  /*Date*/
        $sign_arr[] = '';                  /*If-Modified-Since */
        $sign_arr[] = '';                  /*If-Match*/
        $sign_arr[] = '';                  /*If-None-Match*/
        $sign_arr[] = '';                  /*If-Unmodified-Since*/
        $sign_arr[] = '';                  /*Range*/
        $sign_arr[] = $header_resource;    /*CanonicalizedHeaders*/
        $sign_arr[] = $url_resource;       /*CanonicalizedResource*/

        $str_to_sign = implode("\n", $sign_arr);

        $sign = base64_encode(hash_hmac('sha256', urldecode(utf8_encode($str_to_sign)), base64_decode($secret_key), true));
        $authHeader = "SharedKey $azure_app_name:$sign";

        $headers = [
            'Authorization: ' . $authHeader,
            'x-ms-blob-cache-control: max-age=3600',
            'x-ms-blob-type: BlockBlob',
            'x-ms-date: ' . $date,
            'x-ms-version: 2023-11-03',
            'Content-Type: ' . $mime_type,
            'Content-Length: ' . $file_len
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_INFILE, $handle);
        curl_setopt($ch, CURLOPT_INFILESIZE, $file_len);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        $result = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if (!empty($error)) {
            throw new \Exception("CURL Error: $error");
        }

        $output = false;
        if ($httpCode == 201) {
            $output = true;
        } else {
            throw new \Exception("HTTP error code $httpCode received.");
        }
        return $output;
    }

    /**
     * Delete file from Microsoft Azure Blob Storage using REST API method
     * @param string $file_name File stored name
     * @return boolean File deleted status true|false
     */
    public static function deleteBlob($file_name)
    {
        $azure_app_name = $GLOBALS['azure_app_name'];
        $secret_key = $GLOBALS['azure_app_secret'];
        $container_name = $GLOBALS['azure_container'];
        $azure_environment = $GLOBALS['azure_environment'];

        $signed_resource = "b";
        $signed_version = "2020-04-08";
        $resource_name = "$container_name/$file_name";
        $signed_permissions = "rd";
        $signed_expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 day'));
        $signed_start = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 day'));;
        $signed_ip = "";
        $signed_protocol = "";
        $signed_identifier = "";
        $cache_control = "";
        $content_disposition = "";
        $content_encoding = "";
        $content_language = "";
        $content_type = "";
        $canonicalized_resource = sprintf('/%s/%s/%s', "blob", $azure_app_name, $resource_name);

        $parameters = array();
        $parameters[] = $signed_permissions;
        $parameters[] = $signed_start;
        $parameters[] = $signed_expiry;
        $parameters[] = $canonicalized_resource;
        $parameters[] = $signed_identifier;
        $parameters[] = $signed_ip;
        $parameters[] = $signed_protocol;
        $parameters[] = $signed_version;
        $parameters[] = $signed_resource;
        $parameters[] = "";                 // Signed Snapshot Time
        $parameters[] = $cache_control;
        $parameters[] = $content_disposition;
        $parameters[] = $content_encoding;
        $parameters[] = $content_language;
        $parameters[] = $content_type;
        $string_to_sign = implode("\n", $parameters);

        // decode the account key from base64
        $decoded_account_key = base64_decode($secret_key);
        // create the signature with hmac sha256
        $signature = hash_hmac("sha256", $string_to_sign, $decoded_account_key, true);
        // encode the signature as base64
        $sig = urlencode(base64_encode($signature));

        //adding all the components for account SAS together.
        $sas = 'sv=' . $signed_version;
        $sas .= '&sr=' . $signed_resource;
        $sas .= self::buildOptQueryStr($cache_control, '&rscc=');
        $sas .= self::buildOptQueryStr($content_disposition, '&rscd=');
        $sas .= self::buildOptQueryStr($content_encoding, '&rsce=');
        $sas .= self::buildOptQueryStr($content_language, '&rscl=');
        $sas .= self::buildOptQueryStr($content_type, '&rsct=');

        $sas .= self::buildOptQueryStr($signed_start, '&st=');
        $sas .= '&se=' . $signed_expiry;
        $sas .= '&sp=' . $signed_permissions;
        $sas .= self::buildOptQueryStr($signed_ip, '&sip=');
        $sas .= self::buildOptQueryStr($signed_protocol, '&spr=');
        $sas .= self::buildOptQueryStr($signed_identifier, '&si=');
        $sas .= '&sig=' . $sig;

        $url = 'https://' . $azure_app_name . '.' . $azure_environment . '/' . $resource_name . '?' . $sas;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if (!empty($error)) {
            throw new \Exception("CURL Error: $error");
        }
        $output = false;
        if ($httpCode == 202) {
            $output = true;
        } else {
            throw new \Exception("HTTP error code $httpCode received.");
        }
        return $output;
    }

    /**
     * Build Query string
     * @param string $string
     * @param string $abrv
     * @return string
     */
    public static function buildOptQueryStr($string, $abrv)
    {
        return $string === '' ? '' : $abrv . $string;
    }

    /**
     * Get/Download file from Microsoft Azure Blob Storage using REST API method
     * @param string $stored_name File stored name
     * @return string
     */
    public function getBlob($stored_name)
    {
        $azure_app_name = $GLOBALS['azure_app_name'];
        $secret_key = $GLOBALS['azure_app_secret'];
        $container_name = $GLOBALS['azure_container'];
        $azure_environment = $GLOBALS['azure_environment'];

        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $version = "2019-12-12";

        $str_to_sign = "GET\n\n\n\n\n\n\n\n\n\n\n\nx-ms-date:" . $date . "\nx-ms-version:" . $version . "\n/" . $azure_app_name . "/" . $container_name . "/" . $stored_name;
        $signature = 'SharedKey' . ' ' . $azure_app_name . ':' . base64_encode(hash_hmac('sha256', $str_to_sign, base64_decode($secret_key), true));

        $header = array(
            "x-ms-date: " . $date,
            "x-ms-version: " . $version,
            "Authorization: " . $signature
        );

        $url = "https://$azure_app_name.$azure_environment/$container_name/$stored_name";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_exec($ch);
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return $result;
    }

    /**
     * Copy file from source to destination in same container in Microsoft Azure Blob Storage using REST API method
     * @param string $destination_file Destination blob name
     * @param string $source_file Source blob name
     */
    public function copyBlob($destination_file, $source_file)
    {
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $azure_app_name = $GLOBALS['azure_app_name'];
        $secret_key = $GLOBALS['azure_app_secret'];
        $azure_environment = $GLOBALS['azure_environment'];
        $source_container = $dest_container = $GLOBALS['azure_container'];

        $canonicalized_headers = "x-ms-copy-source:https://" . $azure_app_name . "." . $azure_environment . "/" . $source_container . "/" . $source_file . "\nx-ms-date:$date\nx-ms-version:2023-11-03";
        $canonicalized_resource = "/$azure_app_name/$dest_container/$destination_file";

        $arraysign = array();
        $arraysign[] = 'PUT';                     /*HTTP Verb*/
        $arraysign[] = '';                        /*Content-Encoding*/
        $arraysign[] = '';                        /*Content-Language*/
        $arraysign[] = '';                        /*Content-Length (include value when zero)*/
        $arraysign[] = '';                        /*Content-MD5*/
        $arraysign[] = '';                        /*Content-Type*/
        $arraysign[] = '';                        /*Date*/
        $arraysign[] = '';                        /*If-Modified-Since */
        $arraysign[] = '';                        /*If-Match*/
        $arraysign[] = '';                        /*If-None-Match*/
        $arraysign[] = '';                        /*If-Unmodified-Since*/
        $arraysign[] = '';                        /*Range*/
        $arraysign[] = $canonicalized_headers;    /*CanonicalizedHeaders*/
        $arraysign[] = $canonicalized_resource;   /*CanonicalizedResource*/

        $str_to_sign = implode("\n", $arraysign);

        $signature = 'SharedKey' . ' ' . $azure_app_name . ':' . base64_encode(hash_hmac('sha256', $str_to_sign, base64_decode($secret_key), true));

        $endpoint = 'https://' . $azure_app_name . '.' . $azure_environment;
        $url = $endpoint . '/' . $dest_container . '/' . $destination_file;

        $headers = [
            'x-ms-copy-source:https://' . $azure_app_name . '.' . $azure_environment . '/' . $source_container . '/' . $source_file,
            "x-ms-date:{$date}",
            'x-ms-version:2023-11-03',
            'Accept:application/json;odata=nometadata',
            'Accept-Charset:UTF-8',
            'Content-Length:0',
            "Authorization:{$signature}"
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpCode !== 202) {
            throw new \Exception("HTTP error code $httpCode received.");
        }
    }
}