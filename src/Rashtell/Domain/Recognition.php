<?php

use Aws\Rekognition\RekognitionClient;

trait Recognition
{
    public function createRecognition($collectionId)
    {
        $aws_key = $_ENV["AWS_S3_KEY"];
        $aws_secret = $_ENV["AWS_S3_SECRET"];

        try {
            $recognition = new RekognitionClient([
                'region'  => 'us-west-2',
                'version' => 'latest',
                'credentials' => [
                    'key'    => $aws_key,
                    'secret' => $aws_secret,
                ]
            ]);

            $result = $recognition->createCollection([
                'CollectionId' => $collectionId, // REQUIRED
            ]);

            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }

        return $status;
    }



    public function checkFaceMatchForEvent($base64, $collectionId, $callback = null, $config = ["faceMatchThreshold" => 90.0, "maxFaces" => 1])
    {
        $is_approved = false;

        $byte_image = base64_decode($base64);

        $aws_key = $_ENV["AWS_S3_KEY"];
        $aws_secret = $_ENV["AWS_S3_SECRET"];

        try {
            $recognition = new RekognitionClient([
                'region'  => 'us-west-2',
                'version' => 'latest',
                'credentials' => [
                    'key'    => $aws_key,
                    'secret' => $aws_secret,
                ]
            ]);

            $img_result = $recognition->searchFacesByImage([ // REQUIRED
                'CollectionId' => $collectionId,
                'FaceMatchThreshold' => $config["faceMatchThreshold"],
                'Image' => [ // REQUIRED
                    'Bytes' => $byte_image,
                ],
                'MaxFaces' => $config["maxFaces"]
            ]);
        } catch (\Exception $e) {
            return false;
        }


        if (isset($img_result["FaceMatches"][0]["Face"]["FaceId"])) {
            $similarity = round($img_result["FaceMatches"][0]["Similarity"]);

            if ($similarity > 90) {
                $face_id = $img_result["FaceMatches"][0]["Face"]["FaceId"];

                $callback && $callback($face_id);

                $is_approved = true;
            }
        }

        return $is_approved;
    }
}
