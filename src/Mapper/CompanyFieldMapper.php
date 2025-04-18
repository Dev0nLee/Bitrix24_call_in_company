<?php

use App\Mapper\EntityFieldMapper;

class CompanyFieldMapper implements EntityFieldMapper {
    public function mapFields(array &$fields, ?string $name, ?string $phone, ?string $email) {
        if ($name) $fields['TITLE'] = $name;
        if ($phone) $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
        if ($email) $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
    }
}
?>