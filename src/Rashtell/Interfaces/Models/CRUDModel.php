<?php

namespace Rashtell\Interfaces\Models;

interface CRUDModel
{
    public function createSelf(array $inputs): array;

    public function getALL(): array;

    public function getOne(int $pk): array;

    public function update(array $inputs): array;

    public function delete(int $pk): array;
}
