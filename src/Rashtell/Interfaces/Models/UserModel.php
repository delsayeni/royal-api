<?php

namespace Rashtell\Interfaces\Models;

interface CRUDModel
{
    public function createUser(array $inputs): array;

    public function loginUser(array $inputs): array;

    public function getALLUser(): array;

    public function getOneUser(int $pk): array;

    public function updateUser(array $inputs): array;

    public function deleteUser(int $pk): array;

    public function logoutUser($pk): array;
}
