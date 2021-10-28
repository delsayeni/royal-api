<?php

namespace Royal\Models;

use Rashtell\Domain\KeyManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Royal\Controllers\BaseController;
use Royal\Domain\Constants;

class BaseModel extends Model
{

    public function authenticate($token)
    {
        $authDetails = $this->getTokenInputs($token);

        if ($authDetails == []) {
            return ["isAuthenticated" => false, "error" => "Invalid token"];
        }

        $publicKey = $authDetails["publicKey"] ?? "";
        $username = $authDetails["username"] ?? "";
        $usertype = $authDetails["usertype"] ?? "";

        $userQuery =  $this->where("publicKey", $publicKey)
            ->where("username", "=", $username)
            ->where("usertype", "=", $usertype)
            ->where("accessStatus", Constants::USER_ENABLED);

        return ($userQuery->exists()) ? ["isAuthenticated" => true, "error" => ""] : ["isAuthenticated" => false, "error" => "Expired session"];
    }

    public function getTokenInputs($token, $key = null)
    {
        $kmg = new KeyManager();
        $auth = $kmg->validateClaim($token, $key);

        if (!$auth) {
            return [];
        }

        return (array) $auth;
    }

    public function checkInputError($details, $uniqueColumns, $model = null)
    {
        $checkExistsError =  $this->checkExistsError($details, $uniqueColumns, $model);
        if (null != $checkExistsError) {
            return $checkExistsError;
        }

        $checkFormatError = $this->checkFormatError($details, $uniqueColumns);
        if (null != $checkFormatError) {
            return $checkFormatError;
        }
    }

    /**
     * Create mode - no primaryKey 
     * Checks if the unique columns exists in the details payload and 
     * also have unique values in the parent model
     * returns null if truly unique else returns an error stating the 
     * column not found in the detail payload or with common value in 
     * the parent model
     * 
     * Update mode - primaryKey is required
     * Checks if the unique columns exists in the details payload and 
     * also have unique values in the parent model excluding itself
     * returns null if truly unique else returns an error stating the 
     * column not found in the detail payload or with common value in 
     * the parent model excluding itself
     *
     *
     * @param associative-array $details
     * @param multi-dimensional-assosia-array[["detailsKey"=>String,"columnName"=>String,"errorText"=>String,"primaryKey"=>Boolean]]|string $uniqueColumns
     * @param Model $model
     * @return array[$error=>String]|null 
     */
    public function checkExistsError(array $details, array $uniqueColumns, Model $model = null)
    {
        $model = $model ?? $this;
        $isCreate = true;
        $pk = null;

        $pk = ((in_array($model->primaryKey, $uniqueColumns)
            && (gettype($uniqueColumns[array_search($model->primaryKey, $uniqueColumns)])) === "string"))
            ? $uniqueColumns[array_search($model->primaryKey, $uniqueColumns)]
            : $pk;

        foreach ($uniqueColumns as $uniqueColumn) {
            if (gettype($uniqueColumn) === "array" && isset($uniqueColumn["primaryKey"])) {
                $pk =  $uniqueColumn["columnName"] ?? $uniqueColumn["detailsKey"];
                break;
            }
        }

        if (isset($details[$pk])) {
            $modelExists = $model->find($details[$pk]);
            if (!$modelExists) {
                return ["error" => Constants::ERROR_NOT_FOUND];
            }

            $isCreate = false;
        }

        return $this->checkUniqueColumns($model, $details, $uniqueColumns, $pk, $isCreate);
    }

    public function checkUniqueColumns($model, $details, $uniqueColumns, $pk, $isCreate)
    {
        $existExceptions =  ["password"];
        $returnVal = null;

        foreach ($uniqueColumns as $uniqueColumn) {
            $columnName = null;
            $key = null;
            $errorText = null;

            $key = gettype($uniqueColumn) === "string" ? $uniqueColumn : $key;
            if (gettype($uniqueColumn) === "array") {
                $key = $uniqueColumn["detailsKey"] ?? $key;
                $columnName = $uniqueColumn["columnName"] ?? $uniqueColumn["detailsKey"] ?? $columnName;
                $errorText = $uniqueColumn["errorText"] ?? $errorText;
            }

            if (!isset($details[$key])) {
                $returnVal = ["error" => ($errorText ?? $key) . " is required"];
                break;
            }

            if (!$details[$key]) {
                $returnVal = ["error" => ($errorText ?? $key) . " is invalid"];
                break;
            }

            if (in_array($key, $existExceptions)) {
                continue;
            }

            if ($isCreate) {
                if ($model->isExist($model->where($columnName ?? $key, $details[$key]))) {
                    $returnVal = ["error" => ($errorText ?? $key) . " exists"];
                    break;
                }
            } else {
                if ($model->isExist($model->where($columnName ?? $key, $details[$key])->where($pk, "!=", $details[$pk]))) {
                    $returnVal = ["error" => ($errorText ?? $key) . " exists"];
                    break;
                }
            }
        }

        return $returnVal;
    }

    public function checkFormatError($details, $required)
    {
        foreach ($required as $require) {
            $columnName = null;
            $key = null;
            $errorText = null;

            $key = gettype($require) === "string" ? $require : $key;
            if (gettype($require) === "array") {
                $key = $require["detailsKey"];
                $columnName = $require["columnName"] ?? $require["detailsKey"];
                $errorText = $require["errorText"];
            }

            if (!isset($details[$key])) {
                return ["error" => ($errorText ?? $key) . " is required"];
            }

            $username = array("admin", "administrator", "username", "social", "intagram", "facebook", "twitter", "error");

            if ($key == "username" && (!$details["username"] || preg_match("/[^a-z_\-0-9]/i", $details["username"]) || in_array($details["username"], $username))) {

                return ["error" => "Invalid username"];
            }
        }
    }

    protected function isExist($query): bool
    {

        return $query->exists();
    }

    protected static function search($searchTerm)
    {
        return static::where("", "LIKE", "%" . $searchTerm . "%")->get();
    }

    //TODO create a query contructor to append conditions, relationships and other query options

    public function createSelf($allInputs, $checks = [])
    {
        $inputError = $this->checkInputError($allInputs, $checks);
        if (null != $inputError) {
            return $inputError;
        }

        foreach ($allInputs as $key => $value) {
            $this->$key = $value;
        }
        $this->save();

        $pk = null;
        if (isset($checks[0]["columnName"])) {
            $uniqueColumnKey = $checks[0]["columnName"];
            $pk = $this->select($this->primaryKey)->where($uniqueColumnKey, $allInputs[$uniqueColumnKey])->first()[$this->primaryKey];
        } else {
            $pk = $this->select($this->primaryKey)->latest($this->primaryKey)->first()[$this->primaryKey];
        }

        $model = $this->getByPK($pk);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function createManySelfs($allInputs, $checks = [])
    {
        $inputError = $this->checkInputError($allInputs, $checks);
        if (null != $inputError) {
            return $inputError;
        }

        $createReturn = $this->create($allInputs);

        return ["data" => $createReturn, "error" => null];
    }

    public function getByPage($page, $limit, $return = null, $conditions = null, $relationships = [], $queryOptions = null)
    {
        $minID = 1;

        if ($this->keyType == "int" && $this->incrementing) {
            $minID = $this->min($this->primaryKey);
        } elseif (isset($this->pageKey)) {
            $this->primaryKey = $this->pageKey;
            $minID = $this->min($this->primaryKey);
        } else {
            try {
                $minID = $this->min("id");
            } catch (QueryException $e) {
                error_log($e->getMessage());

                return ["error" => "Service unavailable", "data" => null];
            }
        }

        $start = $minID + (($page - 1) * $limit) - 1;

        if (!$this->isExist($this->select($this->primaryKey)->where($this->primaryKey, ">", $start))) {
            return ["data" => [], "error" => Constants::ERROR_EMPTY_DATA];
        }

        $query = $return ?
            $this->select($return)->where($this->primaryKey, ">", $start) :
            $this->getStruct()->where($this->primaryKey, ">", $start);

        if ($conditions) {
            $newCondtions = [];
            foreach ($conditions as $key => $value) {
                if ($key == "from") {
                    $newCondtions[] = ["dateCreated", ">=", $value];
                    continue;
                }

                if ($key == "to") {
                    $newCondtions[] = ["dateCreated", "<=", $value];
                    continue;
                }

                $newCondtions[] = [$key, "=", $value];
            }

            $query = $query->where($newCondtions);
        }

        if (isset($queryOptions["whereIn"])) {
            foreach ($queryOptions["whereIn"] as $whereIn) {
                foreach ($whereIn as $whereInKey => $whereInValue) {
                    $query = $query->whereIn($whereInKey, $whereInValue);
                }
            }
        }

        if (isset($queryOptions["whereHas"])) {
            foreach ($queryOptions["whereHas"] as $relationshipName => $relationshipCondition) {
                $query = $query->whereHas($relationshipName, $relationshipCondition);
            }
        }

        if ($queryOptions && isset($queryOptions["distinct"]) && $queryOptions["distinct"]) {
            $query = $query->distinct();
        }

        if ($queryOptions && isset($queryOptions["orderBy"])) {
            foreach ($queryOptions["orderBy"] as $columnName => $direction) {
                $query = $query->orderBy($columnName, $direction);
            }
        }

        $query = $query->orderBy(
            $this->primaryKey,
            'DESC'
        )->limit($limit);

        if (!$query->exists()) {
            return ["data" => null, "error" => Constants::ERROR_EMPTY_DATA];
        }

        if ($relationships) {
            $query = $query->with($relationships);
        }

        $allmodels = [];
        try {
            $allmodels = $query->get();
        } catch (QueryException $e) {
            error_log($e->getMessage());
            return ["error" => "Service unavailable", "data" => null];
        }

        $total = count($allmodels);

        return ["data" => ["total" => $total, "all" => $allmodels,], "error" => ""];
    }

    public function getByDate($from, $to, $return = null, $conditions = [],  $relationships = null, $queryOptions = [])
    {
        $columnDateCreated = "dateCreated";
        if (isset($queryOptions["dateCreatedColumn"])) {
            $columnDateCreated = $queryOptions["dateCreatedColumn"];
        }

        if (!$this->select($this->primaryKey)->where($columnDateCreated, ">=", $from)->where($columnDateCreated, "<=", $to)->where($conditions)->exists()) {
            return ["data" => null, "error" => Constants::ERROR_EMPTY_DATA];
        }

        $query = $return ?
            $this->select($return)
            :
            $this->getStruct();

        $query = $query->where($columnDateCreated, ">=", $from)->where($columnDateCreated, "<=", $to);

        if ($conditions) {
            $query = $query->where($conditions);
        }

        if (isset($queryOptions["whereIn"])) {
            foreach ($queryOptions["whereIn"] as $whereIn) {
                foreach ($whereIn as $whereInKey => $whereInValue) {
                    $query = $query->whereIn($whereInKey, $whereInValue);
                }
            }
        }

        if (isset($queryOptions["whereHas"])) {
            foreach ($queryOptions["whereHas"] as $relationshipName => $relationshipCondition) {
                $query = $query->whereHas($relationshipName, $relationshipCondition);
            }
        }

        if ($queryOptions && isset($queryOptions["orderBy"])) {
            foreach ($queryOptions["orderBy"] as $columnName => $direction) {
                $query = $query->orderBy($columnName, $direction);
            }
        }

        $query = $query->orderBy($this->primaryKey, 'DESC');

        if ($relationships) {
            $query = $query->with($relationships);
        }

        $allmodels = [];
        try {
            $allmodels = $query->get();
        } catch (QueryException $e) {
            error_log($e->getMessage());
            return ["error" => "Service unavailable", "data" => null];
        }
        $total = count($allmodels);

        return ["data" => ["total" => $total, "all" => $allmodels], "error" => ""];
    }

    public function getByPK($pk, $return = null, $relationships = [], $queryOptions = null)
    {
        if (!$this->where([$this->primaryKey => $pk])->exists()) {
            return ["data" => null, "error" => Constants::ERROR_NOT_FOUND];
        }

        $query = ($return ? $this->select($return) : $this->getStruct());

        $query = $query->where($this->primaryKey, $pk);

        if ($relationships) {
            $query = $query->with($relationships);
        }

        $model = [];
        try {
            $model = $query->first();
        } catch (QueryException $e) {
            error_log($e->getMessage());
            return ["error" => "Service unavailable", "data" => null];
        }

        return ["data" => $model, "error" => ""];
    }

    public function getByConditions($conditions, $return = null, $relationships = [], $queryOptions = null)
    {
        if (!$this->select($this->primaryKey)->where($conditions)->exists()) {
            return ["data" => null, "error" => Constants::ERROR_NOT_FOUND];
        }

        $query = $return ? $this->select($return) : $this->getStruct();

        if ($conditions) {
            $query = $query->where($conditions);
        }

        if (isset($queryOptions["whereIn"])) {
            foreach ($queryOptions["whereIn"] as $whereIn) {
                foreach ($whereIn as $whereInKey => $whereInValue) {
                    $query = $query->whereIn($whereInKey, $whereInValue);
                }
            }
        }

        if (isset($queryOptions["whereHas"])) {
            foreach ($queryOptions["whereHas"] as $relationshipName => $relationshipCondition) {
                $query = $query->whereHas($relationshipName, $relationshipCondition);
            }
        }

        if ($queryOptions && isset($queryOptions["orderBy"])) {
            foreach ($queryOptions["orderBy"] as $columnName => $direction) {
                $query = $query->orderBy($columnName, $direction);
            }
        }

        if (isset($queryOptions["latest"])) {
            $query = $queryOptions["latest"] ? $query->latest($queryOptions["latest"]) : $query->latest();
        } else {
            $query = $query->orderBy($this->primaryKey, 'DESC');
        }

        if ($relationships) {
            $query = $query->with($relationships);
        }

        $allmodels = [];
        try {
            $allmodels = $query->get();
        } catch (QueryException $e) {
            error_log($e->getMessage());
            return ["error" => "Service unavailable", "data" => null];
        }

        return ["data" => $allmodels, "error" => ""];
    }

    public function updateByPK($pk, $allInputs, $checks = [], $queryOptions = [])
    {
        $allInputs[$this->primaryKey] = $pk;

        $inputError = $this->checkInputError($allInputs, $checks);
        if (null != $inputError) {
            return $inputError;
        }

        unset($allInputs[$this->primaryKey]);

        $query = $this->find($pk);
        if (!$query) {
            return ["error" => Constants::ERROR_NOT_FOUND, "data" => null];
        }

        $query->update($allInputs);

        $model = $this->getByPK($pk);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function updateByColumnNames($columnNames, $allInputs, $checks = [], $queryOptions = [])
    {
        $inputError = $this->checkInputError($allInputs, $checks);
        if (null != $inputError) {
            return $inputError;
        }

        $query = $this;
        $conditions = [];
        foreach ($columnNames as $columnName) {
            $query = $query->where($columnName, $allInputs[$columnName]);
            $conditions[$columnName] = $allInputs[$columnName];

            if (!$query->exists()) {
                return ["error" => "No match found", "data" => null];
            }
        }

        if (isset($queryOptions["modelOverrides"])) {
            foreach ($queryOptions["modelOverrides"] as $modelOverrides) {
                $allInputs[$modelOverrides["property"]] = $modelOverrides["model"]->where($modelOverrides["modelColumn"], $allInputs[$modelOverrides["inputKey"]])->first()[$modelOverrides["property"]];
            }
        }

        $query->update($allInputs);

        $model = $this->getByConditions($conditions);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function updateByConditions($conditions, $allInputs, $checks = [], $queryOptions = [])
    {
        $inputError = $this->checkInputError($allInputs, $checks);
        if (null != $inputError) {
            return $inputError;
        }

        $query = $this->where($conditions);
        if (!$query->exists()) {

            return ["error" => "An error occured while updating", "data" => null];
        }

        $query->update($allInputs);

        $model = $this->getByConditions($conditions);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function updatePassword($pk, $newPassword, $oldPassword)
    {
        $model = $this->find($pk);
        if (!$model) {
            return ["error" => "Error while updating the password", "data" => null];
        }

        $password  = $model[$this->passwordKey];
        if ($oldPassword != $password) {

            return ["error" => "Incorrect password", "data" => [],];
        }

        $model[$this->passwordKey] = $newPassword;
        $model->publicKey = null;
        $model->save();

        return ["data" => [$this->primaryKey => $pk], "error" => null];
    }

    public function resetPassword($pk, $newPassword, $queryOptions)
    {
        $modelQuery = $this->where($this->primaryKey, $pk);

        $where = $queryOptions["where"] ?? [];
        if ($where) {
            $modelQuery = $modelQuery->where($where);
        }

        if (!$this->Exists($modelQuery)) {
            return ["error" => "Error while updating the password", "data" => null];
        }

        $passwordKey = $queryOptions["passwordKey"] ?? "password";

        $publicKeyKey = $queryOptions["publicKeyKey"] ?? "publicKey";

        $modelQuery->update([$passwordKey => $newPassword, $publicKeyKey => null]);

        $model = $this->getByPK($pk);
        $model["data"][$passwordKey] = Constants::DEFAULT_RESET_PASSWORD;

        //TODO send password to mail
        $email = $model["data"]["email"];

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function forgotPassword($allInputs)
    {
        $email = $allInputs["email"];
        $forgotPasswordToken = $allInputs["emailVerificationToken"];

        if (!$this->select($this->primaryKey)->where("email", $email)->exists()) {
            return ["error" => "Email not registered", "data" => null];
        }

        if ($this->select("emailVerified")->where("email", $email)->first()["emailVerified"] !== Constants::EMAIL_VERIFIED) {
            return ["error" => "Email not verified", "data" => null];
        }

        $this->select("emailVerified")->where("email", $email)->update(["forgotPasswordToken" => $forgotPasswordToken]);


        return ["data" => null, "error" => null];
    }

    public function verifyForgotPassword($allInputs)
    {
        $forgotPasswordToken = $allInputs["forgotPasswordVerificationToken"];

        if (!$this->select($this->primaryKey)->where("forgotPasswordToken", $forgotPasswordToken)->exists()) {
            return ["error" => "", "data" => null];
        }

        $this->select("emailVerified")->where("forgotPasswordToken", $forgotPasswordToken)->update(["forgotPasswordToken" => null]);

        $model = $this->select($this->primaryKey, "username",  "publicKey", "usertype")->where("forgotPasswordToken", $forgotPasswordToken)->first();

        return ["data" => $model, "error" => null];
    }

    public function verifyEmail($emailVerificationToken, $status)
    {
        if (!$this->isExist($this->select($this->primaryKey)->where("emailVerificationToken", $emailVerificationToken))) {
            return ["error" => "User not found, please register again", "data" => null];
        }

        $pk = $this->select($this->primaryKey)->where("emailVerificationToken", $emailVerificationToken)->first()[$this->primaryKey];

        $model = $this->find($pk);

        if ($model->verified > 0) {
            return ["error" => "This email has already been verified, please login.", "data" => []];
        }

        $model->verified = $status;
        $model->save();

        $model = $this->getByPK($pk);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function verifyUser($pk, $status)
    {
        $model = $this->find($pk);
        if (!$model) {
            return ["error" => "Error while updating the status", "data" => null];
        }

        if ($model->verified == 0) {
            return ["error" => "Email not verified"];
        }

        if ($model->verified > 1) {
            return ["error" => "User already verified"];
        }

        $model->verified = $status;
        $model->save();

        $model = $this->getByPK($pk);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function updateForgotPassword($pk, $password)
    {
        $model = $this->find($pk);
        if (!$model) {
            return ["error" => "Error while changing the password", "data" => null];
        }

        $model->password = $password;
        $model->publicKey = null;
        $model->save();

        $model = $this->getByPK($pk);

        return ["data" => $model["data"], "error" => $model["error"]];
    }

    public function deleteByPK($pk)
    {
        $model = $this->find($pk);

        if (!$model) {
            return ["error" => Constants::ERROR_NOT_FOUND, "data" => null];
        }

        $model->runSoftDelete();

        return ["data" => ["deleted" => true], "error" => ""];
    }

    public function deleteByConditions($conditions, $queryOptions = [])
    {
        $model = $this->where($conditions);

        if (!$model->exists()) {
            return ["error" => Constants::ERROR_NOT_FOUND, "data" => null];
        }

        $model->delete();

        return ["data" => ["deleted" => true], "error" => ""];
    }

    public function forceDeleteByConditions($conditions, $queryOptions = [])
    {
        $model = $this->where($conditions);

        if (!$model->exists()) {
            return ["error" => Constants::ERROR_NOT_FOUND, "data" => null];
        }

        $model->forceDelete();

        return ["data" => ["deleted" => true], "error" => ""];
    }

    public function logout($pk)
    {
        $model = $this->find($pk);

        if (!$model) {
            return ["error" => "Invalid request. " . Constants::ERROR_NOT_FOUND, "data" => []];
        }

        $model->publicKey = null;
        $model->save();

        return ["data" => ["logout" => true], "error" => null];
    }

    public function logoutByCondition($conditions)
    {
        $query = $this->where($conditions);

        if (!$query->exists()) {
            return ["error" => "Invalid request. " . Constants::ERROR_NOT_FOUND, "data" => []];
        }

        $query->update(["publicKey" => null]);

        return ["data" => ["logout" => true], "error" => null];
    }

    public function assign($pk, array $relatedKeys, $relationMethod, $extraGeneralInputs = [], $queryOptions = [])
    {
        if (empty($relatedKeys)) {
            return ["error" => "invalid data", "data" => null];
        }

        $model = $this->find($pk);

        if (!$model) {
            return ["error" => Constants::ERROR_NOT_FOUND, "data" => null];
        }

        $assignOptions = $queryOptions["assignOptions"] ?? [];

        if (
            !isset($assignOptions["duplicate"])
            || !$assignOptions["duplicate"]
        ) {
            $tempRelatedKeys = json_decode(json_encode($relatedKeys));

            foreach ($tempRelatedKeys as $key => $tempRelatedKey) {

                $relatedKeyKey = $assignOptions["relationKey"];

                if ($model->$relationMethod->some(function ($item) use ($tempRelatedKey, $relatedKeyKey) {

                    return $item["pivot"][$relatedKeyKey] == $tempRelatedKey;
                })) {
                    unset($relatedKeys[$key]);
                }
            }
        }

        try {
            $model->$relationMethod()->attach($relatedKeys, $extraGeneralInputs);
        } catch (QueryException $e) {
            error_log($e->getMessage());

            return ["error" => $e->getMessage(), "data" => null];
        }

        return ["data" => ["assigned" => true], "error" => null];
    }

    public function unassign($pk, array $relatedKeys, $relationMethod, $queryOptions)
    {
        if (empty($relatedKeys)) {
            return ["error" => "invalid data", "data" => null];
        }

        $model = $this->find($pk);

        try {
            $model->$relationMethod()->detach($relatedKeys);
        } catch (QueryException $e) {
            error_log($e->getMessage());

            return ["error" => $e->getMessage(), "data" => null];
        }

        return ["data" => ["unassigned" => true], "error" => null];
    }
}
