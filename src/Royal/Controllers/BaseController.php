<?php

namespace Royal\Controllers;

use Aws\S3\S3Client;
use Royal\Domain\MailHandler;
use Rashtell\Domain\CodeLibrary;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Rashtell\Domain\KeyManager;
use Rashtell\Domain\MCrypt;
use Rashtell\Domain\JSON;
use Royal\Models\BaseModel;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Royal\Domain\Constants;
use Royal\Models\EventModel;
use Psr\Http\Message\UploadedFileInterface;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;
use MicrosoftAzure\Storage\Common\Exceptions\InvalidArgumentTypeException;

abstract class BaseController
{
    protected function getValidJsonOrError($request)
    {
        $json = new JSON();

        $data = $request->getParsedBody();
        $data = isset($data) ? $data : $request->getBody();

        if (!isset($data) || empty($data)) {
            return ["error" => null, "data" => []];
        }

        $validJson = $json->jsonFormat($data);

        if ($validJson == NULL) {
            $error = array("errorMessage" => "The parameter is not a valid objects", "errorStatus" => 1, "statusCode" => 400);

            return ["error" => $error, "data" => null];
        }

        // if (!isset($validJson->data)) {
        //     $error = array("errorMessage" => "The request object does not conform to standard", "errorStatus" => 1, "statusCode" => 400);

        //     return ["error" => $error, "data" => null];
        // }

        return ["data" => isset($validJson->data) ? $validJson->data : $validJson, "error" => ""];
    }

    protected function getPageNumOrError($request)
    {
        $data = $request->getAttributes();
        $page = 1;

        if (!(isset($data["page"]))) {
            // $error = array("errorMessage" => "Page is required", "errorStatus" => 1, "statusCode" => 400);

            // return ["error" => $error, "page" => null];
            $page = 1;
        } else {
            $page = $data["page"];
        }


        if (!(is_numeric($page) || (int) $page < 0)) {
            // $error = array("errorMessage" => "The page number is invalid", "errorStatus" => 1, "statusCode" => 400);

            // return ["error" => $error, "page" => null];
            $page = 1;
        }

        return ["page" => $page, "error" => null];
    }

    protected function getPageLimit($request)
    {
        $data = $request->getAttributes();

        $limit = isset($data["limit"]) && is_numeric($data["limit"]) ? $data["limit"] : 1000000000;

        ["page" => $page, "error" => $error] = $this->getPageNumOrError($request);
        // $start = ($page - 1) * $limit;

        return ["limit" => $limit, "error" => $error];
    }

    protected function getDateOrError($request)
    {
        $data = $request->getAttributes();

        if (!(isset($data["fromDate"]) && isset($data["toDate"]))) {
            $error = array("errorMessage" => "Date range is required", "errorStatus" => 1, "statusCode" => 400);

            return ["error" => $error, "page" => []];
        }

        $fromDate = $data["fromDate"];
        $toDate = $data["toDate"];

        if (!(is_numeric($fromDate) || is_numeric($toDate))) {
            $error = array("errorMessage" => "The date is invalid", "errorStatus" => 1, "statusCode" => 400);

            return ["error" => $error, "page" => []];
        }

        return ["fromDate" => $fromDate, "toDate" => $toDate, "error" => ""];
    }

    protected function getRouteParams($request, $details = null)
    {
        $data = $request->getAttributes();

        if (!$details) {
            return $data;
        }

        $existData = ["error" => null];

        foreach ($details as $detail) {
            if (!isset($data[$detail])) {

                $error = array("errorMessage" => "Invalid request: " . $detail . " not set", "errorStatus" => 1, "statusCode" => 400);

                return array_merge($existData, ["error" => $error]);
            }

            $existData = array_merge($existData, [$detail => $data[$detail]]);
        }

        return $existData;

        // return $request->getAttributes();
    }

    protected function getRouteTokenOrError($request)
    {
        if (!isset($request->getAttributes()["token"])) {
            $error = array("errorMessage" => "Invalid url", "errorStatus" => 1, "statusCode" => 400);
            return ["error" => $error, "token" => ""];
        }

        $token = $request->getAttributes()["token"];

        return ["data" => $token, "error" => null];
    }

    protected function valuesExistsOrError($data, array $details = [], $options = ["all" => true])
    {
        $existData = ["error" => null];

        foreach ($details as $detail) {
            if (!isset($data->$detail)) {
                $json = new JSON();

                $error = array("errorMessage" => "All fields are required: " . $detail . " not set", "errorStatus" => 1, "statusCode" => 400);

                $existData = array_merge($existData, ["error" => $error]);
                return $existData;
            }

            $existData = array_merge($existData, [$detail => $data->$detail]);
        }

        if (isset($options["all"]) && $options["all"]) {
            foreach ($data as $key => $value) {
                $existData[$key] = $value;
            }
        }
        return $existData;
    }

    public static function getTokenInputsFromRequest($request)
    {
        $token = static::getToken($request);

        if (!$token) {
            return [];
        }

        $authDetails = (new BaseModel)->getTokenInputs($token);

        return $authDetails;
    }

    public static function getToken($request)
    {
        $headers = $request->getHeaders();

        $authorization = isset($headers["Token"]) ? $headers["Token"] : (isset($headers["token"]) ? $headers["token"] : null);

        if (!$authorization) {
            return null;
        }

        $token = isset($authorization[0]) ? $authorization[0] : null;

        $tokenArr = $token ? explode(" ", $token) : [];

        return isset($tokenArr[1]) ? $tokenArr[1] : null;
    }



    /**
     * Parses base64 medias to url
     * 
     * $accountOptions["mediaOptions"=>[
     *  ["mediaKey"=>"", "mediaPrefix"=>"", multiple=>false]
     * ]
     *
     * @param array $data
     * @param array $accountOptions
     * @return array
     */
    public function parseMedia($data, $accountOptions = [])
    {
        if (isset($accountOptions["mediaOptions"])) {
            foreach ($accountOptions["mediaOptions"] as $mediaOptions) {

                $mediaKey = $mediaOptions["mediaKey"];
                $cdn = $mediaOptions["cdn"] ?? Constants::CDN["s3Bucket"];

                if (!isset($data[$mediaKey])) {

                    $mediaExtError = ["errorMessage" => $mediaKey . " not set", "errorStatus" => 1, "statusCode" => 400];

                    // $data["error"] = $mediaExtError;
                    // break;

                    continue;
                }

                $return = [];

                if (
                    (isset($mediaOptions["multiple"]) && $mediaOptions["multiple"] && gettype($data[$mediaKey]) == "array")
                    || gettype($data[$mediaKey]) == "array"
                ) {
                    $index = 1;
                    foreach ($data[$mediaKey] as $media) {

                        $mediaOptions["clientOptions"]["index"] = $index;


                        $return[] = $this->$cdn($mediaOptions, $media);

                        $index++;
                    }

                    $data[$mediaKey] = $return;
                } else {
                    $return = $this->$cdn($mediaOptions, $data[$mediaKey]);

                    $data[$mediaKey] = $return["url"] ?? $return["path"]  ?? "";

                    $data[$mediaKey . "Type"] = $return["type"] ?? "";
                }
            }
        }

        return $data;
    }

    public function handleS3ParseMedia($mediaOptions, $media)
    {
        $return = $this->handleParseMedia($mediaOptions, $media);

        $name = $return["name"] ?? "";
        $ext = $return["ext"] ?? "";
        $type = $return["type"] ?? "";
        $path = $return["path"] ?? "";
        $url = $return["url"] ?? "";
        $error = $return["error"] ?? "";

        $mediaKey = $mediaOptions["mediaKey"];
        $folderName = $mediaOptions["folder"] ?? "";
        $clientOptions = isset($mediaOptions["clientOptions"]) ? $mediaOptions["clientOptions"] : [];

        if ($path) {
            $bucket = $clientOptions["containerName"] ?? "assets";

            $contentType = $this->getContentType($ext, $type);
            $content = fopen($path, "r");

            $mediaName = $clientOptions["mediaName"] ?? $name;
            $index = isset($clientOptions["index"]) ? "-" . $clientOptions["index"] : "";

            $file = "$folderName/$mediaName$index.$ext";

            $aws_key =  $_ENV["AWS_KEY"];
            $aws_secret =  $_ENV["AWS_SECRET"];
            try {
                $s3 = new S3Client([
                    'region'  => 'us-west-2',
                    'version' => 'latest',
                    'credentials' => [
                        'key'    => $aws_key,
                        'secret' => $aws_secret,
                    ]
                ]);

                $s3->putObject([
                    'Bucket' => $bucket,
                    'Key'    => $file,
                    'Body'   => $content,
                    'ACL'    => 'public-read',
                    'ContentType'    => $contentType
                ]);
            } catch (\Exception $e) {
                $data["error"] = $e->getMessage();
                return $data;
            }

            $s3baseUrl =  ($clientOptions["baseUrl"]) ?? ($_ENV["AWS_S3_BASE_URL"] ?? Constants::AWS_S3_BASE_URL);

            $url = $s3baseUrl . "$file";
            fclose($content);
            unlink($path);

            return ["name" => $name, "ext" => $ext, "type" => $type, "url" => $url, "error" => $error];
        }

        return $return;
    }

    public function handleAzureParseMedia($mediaOptions, $media, $azureOptions = [])
    {
        $return = $this->handleParseMedia($mediaOptions, $media);

        $name = $return["name"] ?? "";
        $ext = $return["ext"] ?? "";
        $type = $return["type"] ?? "";
        $path = $return["path"] ?? "";
        $url = $return["url"] ?? "";
        $error = $return["error"] ?? "";

        $mediaKey = $mediaOptions["mediaKey"];
        $folderName = $mediaOptions["folder"] ?? "";
        $clientOptions = isset($mediaOptions["clientOptions"]) && $mediaOptions["clientOptions"];

        if ($path) {
            $connectionString = $_ENV["AZURE_STORAGE_CONNECTION_STRING"];

            // Create blob client.
            $blobClient = BlobRestProxy::createBlobService($connectionString);

            $createContainerOptions = new CreateContainerOptions();
            $createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);
            // $createContainerOptions->setDecodeContent();


            $containerName = isset($clientOptions["containerName"]) ? $clientOptions["containerName"] : "static";

            try {
                // Create container.
                // $blobClient->createContainer($containerName, $createContainerOptions);

                // Getting local file so that we can upload it to Azure
                // $myfile = fopen($path, "w");
                // fclose($myfile);

                $content = fopen($path, "r");

                $blobName = strtolower($type) . "/$folderName/$name.$ext";

                $mimetype = $this->getContentType($ext, $type);

                $blobOptions = new CreateBlockBlobOptions();
                $blobOptions->setContentType($mimetype);

                //Upload blob
                $blobClient->createBlockBlob($containerName, $blobName, $content, $blobOptions);

                $url = $_ENV["AZURE_STORAGE_BASE_URL"] . "$containerName/$blobName";
                fclose($content);
                unlink($path);

                return ["name" => $name, "ext" => $ext, "type" => $type, "url" => $url, "error" => $error];

                #region
                // List blobs.
                $listBlobsOptions = new ListBlobsOptions();
                // $listBlobsOptions->setPrefix("HelloWorld");

                echo "These are the blobs present in the container: ";

                do {
                    $result = $blobClient->listBlobs($containerName, $listBlobsOptions);
                    foreach ($result->getBlobs() as $blob) {
                        echo $blob->getName() . ": " . $blob->getUrl() . "<br />";
                    }

                    $listBlobsOptions->setContinuationToken($result->getContinuationToken());
                } while ($result->getContinuationToken());
                echo "<br />";

                // Get blob.
                echo "This is the content of the blob uploaded: ";
                $blob = $blobClient->getBlob($containerName, $blobName);
                fpassthru($blob->getContentStream());
                echo "<br />";
                #endregion
            } catch (ServiceException $e) {
                // Handle exception based on error codes and messages.
                // Error codes and messages are here:
                // http://msdn.microsoft.com/library/azure/dd179439.aspx
                $code = $e->getCode();
                $error_message = $e->getMessage();

                // echo $code . ": " . $error_message . "<br />";

                $return["error"] .= $return["error"] ? ". $error_message" : "$error_message";
            } catch (InvalidArgumentTypeException $e) {
                // Handle exception based on error codes and messages.
                // Error codes and messages are here:
                // http://msdn.microsoft.com/library/azure/dd179439.aspx
                $code = $e->getCode();
                $error_message = $e->getMessage();

                // echo $code . ": " . $error_message . "<br />";

                $return["error"] .= $return["error"] ? ". $error_message" : "$error_message";
            }

            return $return;
        }

        return $return;
    }

    public function handleParseMedia($mediaOptions, $media)
    {
        $cdl = new CodeLibrary();
        $mediaKey = $mediaOptions["mediaKey"];

        if (strpos($media, "data:image/") !== 0 && strpos($media, "data:video/") !== 0 && strpos($media, "data:application/pdf") !== 0) {
            //it means its a url or a path
            //if path first folder name is >10, ki olohun so e

            if (strpos($media, "/") < 30) {
                $data["url"] = $media;
                return $data;
            }

            $data["error"] = "Unacceptable picture format";
            return $data;
        }

        $mediaPrefix = isset($mediaOptions["mediaPrefix"]) ? $mediaOptions["mediaPrefix"] . " - " : "";

        $mediaName = bin2hex(random_bytes(8));
        $mediaName .= $mediaPrefix . (new DateTime())->getTimeStamp();

        $mediaExtType = $this->getFileTypeOfBase64($media);
        if (!$mediaExtType) {
            return ["error" => "Invalid media type"];
        }

        $mediaExt = strtolower($mediaExtType["ext"]);
        $mediaType = strtoupper($mediaExtType["type"]);

        if (!in_array($mediaExt, Constants::IMAGE_TYPES_ACCEPTED) && !in_array($mediaExt, Constants::VIDEO_TYPES_ACCEPTED) && !in_array($mediaExt, Constants::MEDIA_TYPES_ACCEPTED)) {
            $mediaExtError = "Unsupported media type. ";
            $mediaExtError .= $this->getSupportedMediaTypes();

            $data["error"] = $mediaExtError;
            return $data;
        }

        if (in_array($mediaExt, Constants::IMAGE_TYPES_ACCEPTED)) {
            $mediaType = CONSTANTS::MEDIA_TYPE_IMAGE;
            $mediaPath = Constants::IMAGE_PATH;
        } else if (in_array($mediaExt, Constants::VIDEO_TYPES_ACCEPTED)) {
            $mediaType = CONSTANTS::MEDIA_TYPE_VIDEO;
            $mediaPath = Constants::VIDEO_PATH;
        } else {
            $mediaPath = Constants::MEDIA_PATH;
        }

        if (isset($mediaOptions["folder"])) {
            $folder = $mediaOptions["folder"];
            $mediaPath .= $folder . "/";
        }
        if (!is_dir($mediaPath)) {
            mkdir($mediaPath, 0777, true);
        }

        $newMediaPath = "$mediaPath$mediaName.$mediaExt";

        try {
            $mediaContent = file_get_contents($media);
            if ($mediaContent) {
                file_put_contents($newMediaPath, $mediaContent);
            } else {
                $outputPath = $cdl->base64ToMedia($media, $newMediaPath, 100);

                if (!$outputPath) {
                    return ["error" => "Invalid media"];
                }

                // $data["error"] = "Unable to get the media content";
                // return $data;
            }
        } catch (Exception $e) {
            $outputPath = $cdl->base64ToMedia($media, $newMediaPath, 100);

            if (!$outputPath) {
                return ["error" => "Invalid media"];
            }
        }

        if (!file_exists($newMediaPath)) {
            return ["error" => "File content error."];
        }

        $resizedMediaPath = null;
        if ($mediaType === CONSTANTS::MEDIA_TYPE_IMAGE) {
            $cdl = new CodeLibrary();

            if (
                $cdl->resize_crop_image(
                    Constants::IMAGE_RESIZE_MAX_WIDTH,
                    Constants::IMAGE_RESIZE_MAX_HEIGHT,
                    $newMediaPath,
                    "$mediaPath$mediaName.$mediaExt",
                    Constants::IMAGE_RESIZE_QUALITY
                )
            ) {
                $resizedMediaPath = "$mediaPath$mediaName.$mediaExt";
            }
        }

        $data["name"] = $mediaName;
        $data["ext"] = $mediaExt;
        $data["type"] = $mediaType;
        $data["path"] = $resizedMediaPath ?? $newMediaPath;
        $data["url"] = $_ENV["HTTP_PROTOCOL"] . "://" . ($_ENV["MEDIA_HOST"] ?? $_SERVER["HTTP_HOST"]) . $_ENV["BASE_PATH"] . "/" . ($resizedMediaPath ?? $newMediaPath);

        return $data;
    }

    public function getFileTypeOfBase64($data_media)
    {
        if (empty($data_media)) :
            return;
        endif;

        $string_pieces = [];
        if (strpos($data_media, ";base64,") > 0) :
            $string_pieces = explode(";base64,", $data_media);
        elseif (strpos($data_media, ";charset=UTF-8,") > 0) :
            $string_pieces = explode(";charset=UTF-8,", $data_media);
        elseif (strpos($data_media, ";utf") > 0) :
            $string_pieces = explode(";utf", $data_media);
        endif;

        if (strpos($string_pieces[0], ":") > 0) :
            $string_pieces = explode(":", $string_pieces[0]);
        endif;

        if (strpos($string_pieces[1], "/") > 0) :
            $media_type_pieces = explode("/", $string_pieces[1]);

            $type = $media_type_pieces[0];
            $ext = $media_type_pieces[1];

            return ["ext" => $ext, "type" => $type];
        endif;
    }

    public function convertBase64ToMedia($base64_code, $path, $media_name = null)
    {

        if (!empty($base64_code) && !empty($path)) :

            $string_pieces = explode(";base64,", $base64_code);

            $media_type_pieces = ["", ""];
            if (strpos($string_pieces[0], "image/") > 0) :
                $media_type_pieces = explode("image/", $string_pieces[0]);
            endif;
            if (strpos($string_pieces[0], "video/") > 0) :
                $media_type_pieces = explode("video/", $string_pieces[0]);
            endif;

            $media_type = $media_type_pieces[1];

            /*@ Create full path with media name and extension */
            $store_at = $path . md5(uniqid()) . "." . $media_type;

            /*@ If media name available then use that  */
            if (!empty($media_name)) :
                $store_at = $path . $media_name . "." . $media_type;
            endif;

            $decoded_string = base64_decode($string_pieces[1]);

            file_put_contents($store_at, $decoded_string);

        endif;
    }

    public function getSupportedMediaTypes()
    {
        $tense = count(Constants::IMAGE_TYPES_ACCEPTED) > 0 ? "are" : "is";
        $mediatypes = "Supported image types $tense ";
        foreach (Constants::IMAGE_TYPES_ACCEPTED as $acceptedImageType) {
            $appender = array_search($acceptedImageType, Constants::IMAGE_TYPES_ACCEPTED) === sizeof(Constants::IMAGE_TYPES_ACCEPTED) - 1 ? "." : ", ";
            $mediatypes .= $acceptedImageType . $appender;
        }

        $tense = count(Constants::VIDEO_TYPES_ACCEPTED) > 0 ? "are" : "is";
        $mediatypes .= " , supported video types $tense ";
        foreach (Constants::VIDEO_TYPES_ACCEPTED as $acceptedVideoType) {
            $appender = array_search($acceptedVideoType, Constants::VIDEO_TYPES_ACCEPTED) === sizeof(Constants::VIDEO_TYPES_ACCEPTED) - 1 ? "." : ", ";
            $mediatypes .= $acceptedVideoType . $appender;
        }

        $tense = count(Constants::MEDIA_TYPES_ACCEPTED) > 0 ? "are" : "is";
        $mediatypes .= " And supported application types $tense ";
        foreach (Constants::MEDIA_TYPES_ACCEPTED as $acceptedMediaType) {
            $appender = array_search($acceptedMediaType, Constants::MEDIA_TYPES_ACCEPTED) === sizeof(Constants::MEDIA_TYPES_ACCEPTED) - 1 ? "." : ", ";
            $mediatypes .= $acceptedMediaType . $appender;
        }

        return $mediatypes;
    }

    public function getContentType($filetype, $mediaType)
    {
        $filetype = strtolower($filetype);
        $mediaType = strtolower($mediaType);

        $return = "$mediaType/$filetype";

        if ($filetype == 'mp4') {
            $return = "$mediaType/$filetype";
        }
        if ($filetype == 'avi') {
            $return = "$mediaType/x-msvideo";
        }
        if ($filetype == 'flv') {
            $return = "$mediaType/x-flv";
        }
        if ($filetype == 'mov') {
            $return = "$mediaType/quicktime";
        }

        return $return;
    }

    public function curl_get_contents($URL)
    {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $URL);
        $contents = curl_exec($c);
        curl_close($c);

        if ($contents) return $contents;
        else return FALSE;
    }


    /**
     * Moves the uploaded file to the upload directory and assigns it a unique name
     * to avoid overwriting an existing uploaded file.
     *
     * @param string $directory The directory to which the file is moved
     * @param UploadedFileInterface $uploadedFile The file uploaded file to move
     *
     * @return string The filename of moved file
     */
    function handleUploadMedias($request)
    {
        // $directory = $this->get('upload_directory');
        $directory = "assets/medias";
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $type = $request->getParsedBody()["type"] ?? "";
        $directory .= "/$type";

        $mediaOptions = ["folderName" => $type];

        $outputs = [];

        // handle single input with multiple file uploads
        foreach ($uploadedFiles as $uploadedFileName => $uploadedFile) {
            if (gettype($uploadedFile) == "object") {
                $this->uploadFile(
                    $uploadedFile,
                    $directory,
                    function ($output) use (&$outputs, $directory, $mediaOptions) {
                        $outputs[] = $this->uploadMediaCallback($output, $directory, $mediaOptions);
                    }
                );
            }

            if (gettype($uploadedFile) == "array") {
                foreach ($uploadedFile as $index => $uploadedFil) {
                    $this->uploadFile(
                        $uploadedFil,
                        $directory,
                        function ($output) use (&$outputs, $directory, $mediaOptions) {
                            $outputs[] = $this->uploadMediaCallback($output, $directory, $mediaOptions);
                        }
                    );
                }
            }
        }

        return $outputs;
    }

    function uploadFile($uploadedFile, $directory, $callback)
    {
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $fileDetails = $this->moveUploadedFile($directory, $uploadedFile);

            $callback($fileDetails);
        }
    }

    function moveUploadedFile(string $directory, UploadedFileInterface $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);

        // see http://php.net/manual/en/function.random-bytes.php
        $basename = bin2hex(random_bytes(8));
        $basename .= (new DateTime())->getTimeStamp();
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return ["name" => $filename, "type" => $extension];
    }

    /**
     * callback after media uploads
     *
     * @param array $mediaDetails [name: stirng, type => string]
     * @param string $directory
     * @param array $mediaOptions
     */
    abstract public function uploadMediaCallback($mediaDetails, $directory, $mediaOptions): array;



    public function appendSecurity($allInputs, $accountOptions = [])
    {
        $passwordKey = "password";
        $publicKeyKey = "publicKey";
        $password = null;
        $publicKey = null;

        if (isset($accountOptions["securityOptions"])) {

            $passwordKey = isset($accountOptions["securityOptions"]["passwordKey"]) ?
                $accountOptions["securityOptions"]["passwordKey"] :
                $passwordKey;

            $publicKeyKey = isset($accountOptions["securityOptions"]["publicKeyKey"]) ?
                $accountOptions["securityOptions"]["publicKeyKey"] :
                $publicKeyKey;


            if (isset($accountOptions["securityOptions"]["hasPassword"]) && $accountOptions["securityOptions"]["hasPassword"]) {

                $password = (new KeyManager())->getDigest($allInputs[$passwordKey]);

                if (!$allInputs[$passwordKey]) {
                    $allInputs["error"] =  ["errorMessage" => "Password not set", "errorStatus" => 1, "statusCode" => 400];
                }

                $allInputs[$passwordKey] = $password;
            }

            if (isset($accountOptions["securityOptions"]["hasPublicKey"]) && $accountOptions["securityOptions"]["hasPublicKey"]) {

                $publicKey = (new CodeLibrary())->genID(12, 1);
                $allInputs[$publicKeyKey] = $publicKey;
            }
        }

        return $allInputs;
    }

    public function sendMail($allInputs, $accountOptions = [])
    {
        $return = [];

        if (isset($accountOptions["mailOptions"])) {

            foreach ($accountOptions["mailOptions"] as $mailOption) {

                if (isset($allInputs[$mailOption["emailKey"]])) {

                    $emailKey = $mailOption["emailKey"];
                    $nameKey = $mailOption["nameKey"];

                    $emailVerificationToken = (new MCrypt())->mCryptThis(time() * rand(111111111, 999999999));
                    // $emailVerificationToken = (new KeyManager)->createClaims(["email" => $allInputs[$emailKey], "name" => $allInputs[$nameKey]], true);

                    //Send and email with the emailVerificationToken
                    $mail = new MailHandler($mailOption["mailtype"], $mailOption["usertype"], $allInputs[$emailKey], ["username" => $allInputs[$nameKey], "emailVerificationToken" => $emailVerificationToken]);

                    ["error" => $error, "success" => $success] = $mail->sendMail();

                    $return[] = ["error" => $error, "success" => $success, $emailKey . "VerificationToken" => $emailVerificationToken, "mailOption" => $mailOption];
                }
            }
        }

        return $return;
    }

    public function modifyInputKeys($allInputs, $accountOptions)
    {
        if (isset($accountOptions["dataOptions"]) && isset($accountOptions["dataOptions"]["overrideKeys"])) {

            foreach ($accountOptions["dataOptions"]["overrideKeys"] as $passedKey => $acceptedKey) {
                $value = null;

                if (isset($allInputs[$passedKey])) {
                    $value = $allInputs[$passedKey];
                    unset($allInputs[$passedKey]);
                }

                $allInputs[$acceptedKey] = $value;
            }
        }

        return $allInputs;
    }

    public function checkOrGetPostBody($request, $inputs)
    {
        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return null;
        }

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs, ["all" => false]);
        if ($allInputs["error"]) {
            return null;
        }

        unset($allInputs["error"]);

        return $allInputs;
    }

    /**
     * @param Request $request
     * @param ResponseInterface $response
     * @param Model $model
     * @param Array $inputs
     * @param Arrat $accountOptions = []
     * 
     */

    public function createSelf(Request $request, ResponseInterface $response, $model, array $inputs = ["required" => [], "expected" => []], array $accountOptions = [], array $override = [], array $checks = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $allInputs = $this->appendSecurity($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->parseMedia($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $mailResponses = $this->sendMail($allInputs, $accountOptions);
        $mailResponseSuccess = "";
        $mailResponseError = "";
        foreach ($mailResponses as $mailResponse) {

            $tokenKey = $mailResponse["mailOption"]["emailKey"] . "VerificationToken";

            $allInputs[$tokenKey] = $mailResponse[$tokenKey];

            $mailResponseSuccess .=  $mailResponse["success"] . ". ";

            $mailResponseError .=  $mailResponse["error"] . ". ";

            if ($mailResponse["error"] && isset($mailResponse["mailOption"]["strict"]) && $mailResponse["mailOption"]["strict"]) {
                $error = ["errorMessage" => $mailResponse["error"], "errorStatus" => 1, "statusCode" => 406];

                return $json->withJsonResponse($response, $error);
            }
        }

        $newAllInputs = [];
        if (isset($inputs["expected"])) {
            foreach ($inputs["expected"] as $key) {
                $newAllInputs[$key] = $allInputs[$key] ?? null;
            }
        } else {
            $newAllInputs = $allInputs;
        }

        foreach ($override as $overrideKey => $overrideValue) {
            $newAllInputs[$overrideKey] = $overrideValue;
        }

        $data = $model->createSelf($newAllInputs, $checks);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Created successfully", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $mailResponseError];

        return $json->withJsonResponse($response, $payload);
    }

    public function createManySelfs(Request $request, ResponseInterface $response, Model $model, array $inputs = ["required" => [], "expected" => []], array $accountOptions = [], array $override = [], array $checks = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $returnData = [];

        foreach ($data as $key => $eachData) {

            $allInputs = $this->valuesExistsOrError($eachData, isset($inputs["required"]) ? $inputs["required"] : $inputs);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }
            $allInputs = $this->appendSecurity($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }
            $allInputs = $this->parseMedia($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }
            $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }
            $mailResponses = $this->sendMail($allInputs, $accountOptions);
            $mailResponseSuccess = "";
            $continue = false;
            foreach ($mailResponses as $mailResponse) {

                $tokenKey = $mailResponse["mailOption"]["emailKey"] . "VerificationToken";

                $allInputs[$tokenKey] = $mailResponse[$tokenKey];

                $mailResponseSuccess .=  $mailResponse["success"] . ". ";


                if ($mailResponse["error"] && isset($mailResponse["mailOption"]["strict"]) && $mailResponse["mailOption"]["strict"]) {

                    $returnData[$key] = $mailResponse["error"];
                    $continue = true;
                    break;
                }
            }
            if ($continue) {
                continue;
            }

            $newAllInputs = [];
            if (isset($inputs["expected"])) {
                foreach ($inputs["expected"] as $expectedKey) {
                    $newAllInputs[$expectedKey] = $allInputs[$expectedKey] ?? null;
                }
            } else {
                $newAllInputs = $allInputs;
            }

            foreach ($override as $overrideKey => $overrideValue) {
                $newAllInputs[$overrideKey] = $overrideValue;
            }

            $modelData = method_exists($model, "createManySelfs") ? $model->createManySelfs($newAllInputs, $checks) : $model->createSelf($newAllInputs, $checks);
            if ($modelData["error"]) {

                $returnData[$key] = $modelData["error"];

                continue;
            }

            $returnData[$key] = $modelData["data"];
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Success", "statusCode" => 201, "data" => $returnData];

        return $json->withJsonResponse($response, $payload);
    }

    public function loginSelf(Request $request, ResponseInterface $response, Model $model = null, array $inputs = [], array $queryOptions = ["passwordKey" => "password", "publicKeyKey" => "publicKey"], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();
        $passwordKey = $queryOptions["passwordKey"];
        $publicKeyKey = $queryOptions["publicKeyKey"];

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $allInputs =  $this->valuesExistsOrError($data, $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        if ($allInputs[$passwordKey] == Constants::DEFAULT_RESET_PASSWORD) {
            //TODO Redirect user to change password page
        }

        $kmg = new KeyManager();
        $password = $kmg->getDigest($allInputs[$passwordKey]);

        $cLib = new CodeLibrary();
        $publicKey = $cLib->genID(12, 1);

        $allInputs[$passwordKey] = $password;
        $allInputs[$publicKeyKey] = $publicKey;


        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $data = $model->login($allInputs);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 401, "data" => null);

            return $json->withJsonResponse($response, $payload);
        }

        $token = (new KeyManager)->createClaims(json_decode($data["data"], true));

        if (isset($data["users"])) {
            $data["data"]["users"] = $data["users"];
        }

        unset($data["data"][$publicKeyKey]);

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Login successful", "statusCode" => 200, "data" => $data["data"], "token" => $token);

        return $json->withJsonResponse($response, $payload)->withHeader("token", "bearer " . $token);
    }

    public function getSelfDashboard(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();
        $authDetails = static::getTokenInputsFromRequest($request);

        $pk = $authDetails[$model->primaryKey];

        $data = $model->getDashboard([$model->primaryKey => $pk], $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Dashboard request success", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function getDashboardByConditions(Request $request, ResponseInterface $response, $model, $conditions = [], array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();

        $data = $model->getDashboard($conditions, $queryOptions);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Dashboard request success", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function getByPage(Request $request, ResponseInterface $response, $model, $return = null, $conditions = null, $relationships = null, $queryOptions = [], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["page" => $page, "error" => $error] = $this->getPageNumOrError($request);
        ["limit" => $limit, "error" => $error] = $this->getPageLimit($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $data = $model->getByPage($page, $limit, $return, $conditions, $relationships, $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => "1", "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Request success", "statusCode" => 200, "data" => $data["data"], "errorMessage" => $data["error"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function getByDate(Request $request, ResponseInterface $response, $model, $return = null, $conditions = null, $relationships = null, $queryOptions = ["dateCreatedColumn" => "dateCreated"], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        $routeParams = $this->getRouteParams($request, ["from", "to"]);
        $from = null;
        $to = null;

        // if ($routeParams["error"]) {
        $from = (isset($routeParams["from"]) && $routeParams["from"]) ? $routeParams["from"] : "-";

        $to = (isset($routeParams["to"]) && $routeParams["to"]) ? $routeParams["to"] : "-";
        // }

        if ($from == "-") {
            $from = date("U") - 86400;
        }

        if ($to == "-") {
            $to = date("U") + 86400;
        }

        $data = $model->getByDate($from, $to, $return, $conditions, $relationships, $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => "1", "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Request success", "statusCode" => 200, "data" => $data["data"], "errorMessage" => $data["error"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function getSelf(Request $request, ResponseInterface $response, $model,  $return = null, $relationships = null,  $queryOptions = [], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();


        $authDetails = static::getTokenInputsFromRequest($request);

        [$model->primaryKey => $pk] = $authDetails;

        $data = $model->getByPK($pk, $return, $relationships, $queryOptions);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Request success", "statusCode" => 200, "data" => $data["data"], "errorMessage" => $data["error"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function getByPK(Request $request, ResponseInterface $response, $model, $return = null, $relationships = null,  $queryOptions = [], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        [$model->primaryKey => $pk, "error" => $error] = $this->getRouteParams($request, [$model->primaryKey]);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $data = $model->getByPK($pk, $return, $relationships, $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Requst success", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function getByConditions(Request $request, ResponseInterface $response, Model $model, array $conditions, array $return = null, array $relationships = null, array $queryOptions = null, array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        $data = $model->getByConditions($conditions, $return, $relationships, $queryOptions);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $payload = array("errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400);

            return $json->withJsonResponse($response, $payload);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Requst success", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function updateSelf(Request $request, ResponseInterface $response, $model, array $inputs = ["required" => [], "expected" => []], array $accountOptions = [], $override = [], $checks = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);
        $pk = $authDetails[$model->primaryKey];

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $allInputs = $this->parseMedia($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $newAllInputs = [];
        if (isset($inputs["expected"])) {
            foreach ($inputs["expected"] as $key) {
                $newAllInputs[$key] = $allInputs[$key] ?? null;
            }
        } else {
            $newAllInputs = $allInputs;
        }

        foreach ($override as $key => $value) {
            $newAllInputs[$key] = $value;
        }

        $newAllInputs[$model->primaryKey] = $authDetails[$model->primaryKey];

        $data = $model->updateByPK($pk, $newAllInputs, $checks);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response, $payload);
    }

    public function updateByPK(Request $request, ResponseInterface $response, $model, array $inputs = ["required" => [], "expected" => []], $accountOptions = [], $override = [], $checks = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);
        $routeParams = $this->getRouteParams($request, [$model->primaryKey]);
        $pk = $routeParams[$model->primaryKey];

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->parseMedia($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $newAllInputs = [];
        if (isset($inputs["expected"])) {
            foreach ($inputs["expected"] as $key) {
                $newAllInputs[$key] = $allInputs[$key] ?? null;
            }
        } else {
            $newAllInputs = $allInputs;
        }

        $newAllInputs[$model->primaryKey] = $pk;

        foreach ($override as $key => $value) {
            $newAllInputs[$key] = $value;
        }

        $data = $model->updateByPK($pk, $newAllInputs, $checks);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response, $payload);
    }

    public function updateManySelfsByPK(Request $request, ResponseInterface $response, $model, array $inputs = ["required" => [], "expected" => []], $accountOptions = [], $checks = [], $override = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $returnData = [];
        foreach ($data as $key => $eachData) {

            $allInputs = $this->valuesExistsOrError($eachData, isset($inputs["required"]) ? $inputs["required"] : $inputs);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }
            $allInputs = $this->parseMedia($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }
            $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }

            $newAllInputs = [];
            if (isset($inputs["expected"])) {
                foreach ($inputs["expected"] as $expKey) {
                    $newAllInputs[$expKey] = $allInputs[$expKey] ?? null;
                }
            } else {
                $newAllInputs = $allInputs;
            }

            $pk = $newAllInputs[$model->primaryKey] ?? 0;

            foreach ($override as $overrideKey => $overrideValue) {
                $newAllInputs[$overrideKey] = $overrideValue;
            }

            $modelData = $model->updateByPK($pk, $newAllInputs, $checks);
            if ($modelData["error"]) {
                $returnData[$key] = $modelData["error"];
                continue;
            }

            $returnData[$key] = $modelData["data"];
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $returnData];

        return $json->withJsonResponse($response, $payload);
    }

    public function updateByColumnNames(Request $request, ResponseInterface $response, $model, array $inputs = ["required" => [], "expected" => []], $columnsNames, $checks = [], $override = [], $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();
        $error = "";

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $allInputs = $this->parseMedia($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $newAllInputs = [];
        if (isset($inputs["expected"])) {
            foreach ($inputs["expected"] as $key) {
                $newAllInputs[$key] = $allInputs[$key] ?? null;
            }
        } else {
            $newAllInputs = $allInputs;
        }

        foreach ($override as $key => $value) {
            $newAllInputs[$key] = $value;
        }

        $data = $model->updateByColumnNames($columnsNames, $newAllInputs, $checks, $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response, $payload);
    }

    public function updateManySelfsByColumnNames(Request $request, ResponseInterface $response, $model, array $inputs, $columnNames, $checks = [], $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);

        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $returnData = [];

        foreach ($data as $key => $eachData) {

            $allInputs = $this->valuesExistsOrError($eachData, $inputs);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }

            $allInputs = $this->parseMedia($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];

                continue;
            }
            $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];

                continue;
            }

            $newAllInputs = [];
            foreach ($inputs as $key) {
                $newAllInputs[$key] = $allInputs[$key];
            }

            $modelData = $model->updateByColumnNames($columnNames, $newAllInputs, $checks);
            if ($modelData["error"]) {
                $error = ["errorMessage" => $modelData["error"], "errorStatus" => 1, "statusCode" => 406];

                $returnData[$key] = $modelData["error"];

                continue;
            }

            $returnData[$key] = $modelData["data"];
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $returnData];

        return $json->withJsonResponse($response, $payload);
    }

    public function updateByConditions(
        Request $request,
        ResponseInterface $response,
        $model,
        array $inputs = ["required" => [], "expected" => []],
        $conditions,
        $checks = [],
        $override = [],
        $accountOptions = [],
        $queryOptions = []
    ): ResponseInterface {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $allInputs = $this->parseMedia($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $newAllInputs = [];
        if (isset($inputs["expected"])) {
            foreach ($inputs["expected"] as $key) {
                $newAllInputs[$key] = $allInputs[$key] ?? null;
            }
        } else {
            $newAllInputs = $allInputs;
        }

        foreach ($override as $key => $value) {
            $newAllInputs[$key] = $value;
        }

        $error = $newAllInputs["error"] ?? null;
        unset($newAllInputs["error"]);

        $data = $model->updateByConditions($conditions, $newAllInputs, $checks, $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $error,  "errorStatus" => -1];

        return $json->withJsonResponse($response, $payload);
    }

    public function updateManySelfsByConditions(Request $request, ResponseInterface $response, $model, array $inputs, $conditions, $checks = [], $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);

        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $returnData = [];

        foreach ($data as $key => $eachData) {

            $allInputs = $this->valuesExistsOrError($eachData, $inputs);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];
                continue;
            }

            $allInputs = $this->parseMedia($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];

                continue;
            }
            $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
            if ($allInputs["error"]) {
                $returnData[$key] = $allInputs["error"];

                continue;
            }

            $newAllInputs = [];
            foreach ($inputs as $key) {
                $newAllInputs[$key] = $allInputs[$key];
            }

            $modelData = $model->updateByConditions($conditions, $newAllInputs, $checks);

            if ($modelData["error"]) {
                $error = ["errorMessage" => $modelData["error"], "errorStatus" => 1, "statusCode" => 406];

                $returnData[$key] = $modelData["error"];
                continue;
            }

            $returnData[$key] = $modelData["data"];
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Update success", "statusCode" => 201, "data" => $returnData];

        return $json->withJsonResponse($response, $payload);
    }

    public function updatePassword(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $allInputs = $this->valuesExistsOrError($data, ["newPassword", "oldPassword"]);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        ["newPassword" => $newPassword, "oldPassword" => $oldPassword, "error" => $error] = $allInputs;

        $kmg = new KeyManager();

        $newPassword = $kmg->getDigest($newPassword);
        $oldPassword = $kmg->getDigest($oldPassword);

        $pk = $authDetails[$model->primaryKey];

        $data = $model->updatePassword($pk, $newPassword, $oldPassword);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $this->logoutSelf($request, $response, $model);

            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Password update success", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response,  $payload);
    }

    public function resetPassword(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();
        $authDetails = static::getTokenInputsFromRequest($request);
        $routeParams = $this->getRouteParams($request, [$model->primaryKey]);

        $pk = $routeParams[$model->primaryKey];
        $newPassword = Constants::DEFAULT_RESET_PASSWORD;

        $kmg = new KeyManager();
        $encryptedPassword = $kmg->getDigest($newPassword);

        $data = $model->resetPassword($pk, $encryptedPassword, $queryOptions);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Password reset to default: " . Constants::DEFAULT_RESET_PASSWORD, "statusCode" => 201, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response,  $payload);
    }

    public function verifyEmail(Request $request, ResponseInterface $response, $model, array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $emailVerificationToken, "error" => $error] = $this->getRouteTokenOrError($request);

        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $status = Constants::EMAIL_VERIFIED;

        $data = $model->verifyEmail($emailVerificationToken, $status);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406, "data" => null];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Email verification success", "statusCode" => 200, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        //TODO redirect to login
        return $json->withJsonResponse($response, $payload);
    }

    public function forgotPassword(Request $request, ResponseInterface $response, $model, $inputs = [], $override = [], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();
        $authDetails = static::getTokenInputsFromRequest($request);

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $allInputs = $this->valuesExistsOrError($data, $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        foreach ($override as $key => $value) {
            $allInputs[$key] = $value;
        }

        $mcrypt = new MCrypt();
        $emailVerificationToken = $mcrypt->mCryptThis(time() * rand(111111111, 999999999));
        $allInputs["name"] = $allInputs["email"];
        $allInputs["emailVerificationToken"] = $emailVerificationToken;
        $usertype = $allInputs["usertype"];
        $mailtype = MailHandler::TEMPLATE_FORGOT_PASSWORD;

        $this->sendMail($allInputs, [["emailKey" => "email", "nameKey" => "name", "usertype" => $usertype, "mailtype" => $mailtype]]);

        $data = $model->forgotPassword($allInputs);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406, "data" => null];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Password reset link sent to your email", "statusCode" => 200, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response, $payload);
    }

    public function verifyForgotPassword(Request $request, ResponseInterface $response, $model, array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $forgotPasswordVerificationToken, "error" => $error] = $this->getRouteTokenOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }
        $allInputs = ["forgotPasswordVerificationToken" => $forgotPasswordVerificationToken];

        $data = $model->verifyForgotPassword($allInputs);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406, "data" => null];

            return $json->withJsonResponse($response, $error);
        }

        $token = (new KeyManager)->createClaims(json_decode($data["data"], true));

        if (isset($data["users"])) {
            $data["data"]["users"] = $data["users"];
        }

        unset($data["data"]["publicKey"]);

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Login successful", "statusCode" => 200, "data" => $data["data"], "token" => $token);

        return $json->withJsonResponse($response, $payload)->withHeader("token", "bearer " . $token);
    }

    public function updateForgotPassword(Request $request, ResponseInterface $response, $model, $queryOptions = ["passwordKey" => "password", "publicKeyKey" => "publicKey"], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        $passwordKey = $queryOptions["passwordKey"];
        $publicKeyKey = $queryOptions["publicKeyKey"];

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $allInputs = $this->valuesExistsOrError($data, [$passwordKey]);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        [$passwordKey => $password,] = $allInputs;

        $kmg = new KeyManager();

        $password = $kmg->getDigest($password);

        $publicKey =  $authDetails[$publicKeyKey];
        $pk = $authDetails[$model->primaryKey];

        $data = $model->updateForgotPassword($pk, $password);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $this->logoutSelf($request, $response, $model);

            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Password change success", "statusCode" => 201, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response,  $payload);
    }

    public function verifySelfUser(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();
        $authDetails = static::getTokenInputsFromRequest($request);

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $allInputs = $this->valuesExistsOrError($data, [$model->primaryKey]);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        [$model->primaryKey => $pk] = $allInputs;

        $status = Constants::USER_VERIFIED;

        $data = $model->verifyUser($pk, $status);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406, "data" => null];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "User verified successfully", "statusCode" => 200, "data" => $data["data"], "errorMessage" => $data["error"] ?? "", "errorStatus" => isset($data["error"]) && $data["error"] ? -1 : 0];

        return $json->withJsonResponse($response, $payload);
    }

    public function toggleUserAccessStatusByPK(Request $request, ResponseInterface $response, $model, $inputs = ["required" => ["accessStatus"], "expected" => ["accessStatus"]], $conditions = [], $accountOptions = []): ResponseInterface
    {
        if (!$inputs) {
            $inputs = ["required" => ["accessStatus"], "expected" => ["accessStatus"]];
        }

        $statusInputKey = $inputs["required"][0];
        $statusColumnKey = $inputs["expected"][0];

        $routeParams = $this->getRouteParams($request, [$model->primaryKey]);
        if (isset($routeParams[$model->primaryKey])) {
            $conditions[$model->primaryKey] = $routeParams[$model->primaryKey];
        }

        $json = new JSON();
        $body = $this->checkOrGetPostBody($request, [$statusInputKey]);
        if (!$body || !isset($body[$statusInputKey])) {
            $error = ["errorMessage" => "Invalid status", "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $accessStatus = $body[$statusInputKey];
        if ($accessStatus != Constants::USER_DISABLED && $accessStatus != Constants::USER_ENABLED) {
            $error = ["errorMessage" => "Invalid status", "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $responseMessage = null;
        if (isset($accountOptions["responseMessage"])) {
            $responseMessage = $accountOptions["responseMessage"];
        } else {
            $responseMessage = $accessStatus == Constants::USER_DISABLED ? "Account disabled successfully" : "Account enabled successfully";
        }

        return $this->updateByConditions(
            $request,
            $response,
            $model,
            $inputs,
            $conditions,
            [],
            [],
            [
                "responseMessage" => $responseMessage,
                "dataOptions" => [
                    "overrideKeys" => [$statusInputKey => $statusColumnKey]
                ]
            ],
            ["useParentModel" => true]
        );
    }

    public function deleteSelf(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();


        $authDetails = static::getTokenInputsFromRequest($request);

        [$model->primaryKey => $pk] = $authDetails;

        $data = $model->deleteByPK($pk);

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Deleted successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function deleteByPK(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();

        $allInputs = $this->getRouteParams($request, [$model->primaryKey]);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        [$model->primaryKey => $pk, "error" => $error] = $allInputs;
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $data = $model->deleteByPK($pk);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Deleted successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function deleteManyByPK(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $authDetails = static::getTokenInputsFromRequest($request);

        $allInputs = $this->valuesExistsOrError($data, [$model->primaryKey]);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        [$model->primaryKey => $pks] = $allInputs;

        $data = $model->deleteManyByPK($pks);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Deleted successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function deleteByConditions(Request $request, ResponseInterface $response, $model, $conditions, $queryOptions = [], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();


        $data = isset($queryOptions["forceDelete"]) && $queryOptions["forceDelete"] ?
            $model->forceDeleteByConditions($conditions, $queryOptions) :
            $model->deleteByConditions($conditions, $queryOptions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Deleted successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function logoutSelf(Request $request, ResponseInterface $response, $model, array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        $authDetails = static::getTokenInputsFromRequest($request);
        $pk = isset($authDetails[$model->primaryKey]) ? $authDetails[$model->primaryKey] : null;
        if (!$pk) {
            $error = ["errorMessage" => "Invalid request", "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $data = $model->logout($pk);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Logout successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function logoutByPK(Request $request, ResponseInterface $response, $model, array $accountOptions = [], $queryOptions = []): ResponseInterface
    {
        $json = new JSON();

        $routeParams = $this->getRouteParams($request, [$model->primaryKey]);
        if ($routeParams["error"]) {
            return $json->withJsonResponse($response, $routeParams["error"]);
        }

        [$model->primaryKey => $pk] = $routeParams;

        $data = $model->logout($pk);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Logout successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function logoutByCondition(Request $request, ResponseInterface $response, $model, $conditions = [], array $accountOptions = []): ResponseInterface
    {
        $json = new JSON();

        $data = $model->logoutByCondition($conditions);
        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 400];

            return $json->withJsonResponse($response,  $error);
        }

        $payload = array("successMessage" => $accountOptions["responseMessage"] ?? "Logout successfully", "statusCode" => 200, "data" => $data["data"]);

        return $json->withJsonResponse($response, $payload);
    }

    public function toggleAssign(
        Request $request,
        ResponseInterface $response,
        $model,
        $inputs = ["required" => [], "expected" => []],
        $accountOptions = [],
        $queryOptions = [],
        $override = []
    ): ResponseInterface {

        $json = new JSON();

        $keys = $accountOptions["keys"] ?? [
            "toggleKey" => null, "parentKey" => null, "relationKey" => null, "pivotMethodName" => null
        ];

        $parentKey = $keys["parentKey"] ?? $model->primaryKey;
        $relationKey = $keys["relationKey"] ?? [];
        $pivotMethodName = $keys["pivotMethodName"] ?? "";
        $toggleKey = $keys["toggleKey"] ?? "toggle";

        $assignOptions = [];
        $assignOptions["parentKey"] = $parentKey;
        $assignOptions["relationKey"] = $relationKey;
        $assignOptions["pivotMethodName"] = $pivotMethodName;

        $queryOptions["assignOptions"] = $assignOptions;

        if (!$parentKey || !$relationKey || !$pivotMethodName) {
            $error = ["errorMessage" => "An error occured while changing assign status", "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        ["data" => $data, "error" => $error] = $this->getValidJsonOrError($request);
        if ($error) {
            return $json->withJsonResponse($response, $error);
        }

        $allInputs = $this->valuesExistsOrError($data, isset($inputs["required"]) ? $inputs["required"] : $inputs);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $allInputs = $this->parseMedia($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }
        $allInputs = $this->modifyInputKeys($allInputs, $accountOptions);
        if ($allInputs["error"]) {
            return $json->withJsonResponse($response, $allInputs["error"]);
        }

        $newAllInputs = [];
        if (isset($inputs["expected"])) {
            foreach ($inputs["expected"] as $key) {
                $newAllInputs[$key] = $allInputs[$key] ?? null;
            }
        } else {
            $newAllInputs = $allInputs;
        }

        foreach ($override as $overrideKey => $overrideValue) {
            $newAllInputs[$overrideKey] = $overrideValue;
        }

        $pk = $newAllInputs[$parentKey];
        $relationKeys = $newAllInputs[$relationKey];
        $toggle = $newAllInputs[$toggleKey] ?? Constants::ASSIGN;

        unset($newAllInputs[$parentKey]);
        unset($newAllInputs[$relationKey]);

        $data = [];
        if ($toggle == Constants::ASSIGN) {
            $data = $model->assign($pk, $relationKeys, $pivotMethodName, $newAllInputs, $queryOptions);
        } elseif ($toggle == Constants::UNASSIGN) {
            $data = $model->unassign($pk, $relationKeys, $pivotMethodName, $queryOptions);
        }

        if (isset($data["error"]) && $data["error"] && (!isset($data["data"]) || !$data["data"])) {
            $error = ["errorMessage" => $data["error"], "errorStatus" => 1, "statusCode" => 406];

            return $json->withJsonResponse($response, $error);
        }

        $payload = ["successMessage" => $accountOptions["responseMessage"] ?? "Action done successfully. ", "statusCode" => 201, "data" => $data["data"], "errorMessage" => ""];

        return $json->withJsonResponse($response, $payload);
    }
}
