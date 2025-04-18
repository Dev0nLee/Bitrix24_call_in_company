<?php

use App\Mapper\EntityFieldMapper;

class ContactFieldMapper implements EntityFieldMapper {
    public function mapFields(array &$fields, ?string $name, ?string $phone, ?string $email) {
        if ($name) $fields['NAME'] = $name;
        if ($phone) $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
        if ($email) $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
    }
}
?>