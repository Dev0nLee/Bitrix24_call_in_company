<?php

namespace App\Mapper;

interface EntityFieldMapper {
    public function mapFields(array &$fields, ?string $name, ?string $phone, ?string $email);
}
?>