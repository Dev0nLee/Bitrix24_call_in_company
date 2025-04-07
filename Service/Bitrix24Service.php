<?php

declare(strict_types=1);

namespace App\Service;

use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Bitrix24\SDK\Core\Exceptions\BaseException;

class Bitrix24Service
{
    private $b24;

    public function __construct(string $webhookUrl)
    {
        $this->b24 = ServiceBuilderFactory::createServiceBuilderFromWebhook($webhookUrl);
    }

    public function getCurrentUser(): array
    {
        $userResult = $this->b24->core->call('user.current');
        $httpResponse = $userResult->getHttpResponse();
        $body = $httpResponse->getContent();
        $data = json_decode($body, true);
        return $data['result'];
    }

    public function findOrCreateCompany(string $phone): array
    {
        $contactResult = $this->b24->core->call('crm.contact.list', [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $contactResponse = $contactResult->getHttpResponse();
        $contactData = json_decode($contactResponse->getContent(), true);
        $contacts = $contactData['result'];

        if (!empty($contacts)) {
            $contactId = (int)$contacts[0]['ID'];
            $companyId = !empty($contacts[0]['COMPANY_ID']) ? (int)$contacts[0]['COMPANY_ID'] : null;
            if ($companyId) {
                return [
                    'entityType' => 'CONTACT',
                    'entityId' => $contactId,
                    'companyId' => $companyId,
                ];
            }
            $newCompanyResult = $this->b24->core->call('crm.company.add', [
                'fields' => [
                    'TITLE' => 'Новая компания от звонка ' . $phone,
                    'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
                ],
            ]);
            $newCompanyResponse = $newCompanyResult->getHttpResponse();
            $newCompanyData = json_decode($newCompanyResponse->getContent(), true);
            $companyId = (int)$newCompanyData['result'];

            $this->b24->core->call('crm.contact.update', [
                'id' => $contactId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);
            return [
                'entityType' => 'CONTACT',
                'entityId' => $contactId,
                'companyId' => $companyId,
            ];
        }

        $dealResult = $this->b24->core->call('crm.deal.list', [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $dealResponse = $dealResult->getHttpResponse();
        $dealData = json_decode($dealResponse->getContent(), true);
        $deals = $dealData['result'];

        if (!empty($deals)) {
            $dealId = (int)$deals[0]['ID'];
            $companyId = !empty($deals[0]['COMPANY_ID']) ? (int)$deals[0]['COMPANY_ID'] : null;

            if ($companyId) {
                return [
                    'entityType' => 'DEAL',
                    'entityId' => $dealId,
                    'companyId' => $companyId,
                ];
            }

            $newCompanyResult = $this->b24->core->call('crm.company.add', [
                'fields' => [
                    'TITLE' => 'Новая компания от звонка ' . $phone,
                    'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
                ],
            ]);
            $newCompanyResponse = $newCompanyResult->getHttpResponse();
            $newCompanyData = json_decode($newCompanyResponse->getContent(), true);
            $companyId = (int)$newCompanyData['result'];

            $this->b24->core->call('crm.deal.update', [
                'id' => $dealId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);

            return [
                'entityType' => 'DEAL',
                'entityId' => $dealId,
                'companyId' => $companyId,
            ];
        }
        $companyResult = $this->b24->core->call('crm.company.list', [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'TITLE'],
        ]);
        $companyResponse = $companyResult->getHttpResponse();
        $companyData = json_decode($companyResponse->getContent(), true);
        $companies = $companyData['result'];

        if (!empty($companies)) {
            return [
                'entityType' => 'COMPANY',
                'entityId' => (int)$companies[0]['ID'],
                'companyId' => (int)$companies[0]['ID'],
            ];
        }

        $newCompanyResult = $this->b24->core->call('crm.company.add', [
            'fields' => [
                'TITLE' => 'Новая компания от звонка ' . $phone,
                'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
            ],
        ]);
        $newCompanyResponse = $newCompanyResult->getHttpResponse();
        $newCompanyData = json_decode($newCompanyResponse->getContent(), true);
        $companyId = (int)$newCompanyData['result'];

        return [
            'entityType' => 'COMPANY',
            'entityId' => $companyId,
            'companyId' => $companyId,
        ];
    }

    public function findOrCreateEntityByEmail(string $email): array
    {
        $contactResult = $this->b24->core->call('crm.contact.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $contactResponse = $contactResult->getHttpResponse();
        $contactData = json_decode($contactResponse->getContent(), true);
        $contacts = $contactData['result'];

        if (!empty($contacts)) {
            $contactId = (int)$contacts[0]['ID'];
            $companyId = !empty($contacts[0]['COMPANY_ID']) ? (int)$contacts[0]['COMPANY_ID'] : null;

            if ($companyId) {
                return [
                    'entityType' => 'CONTACT',
                    'entityId' => $contactId,
                    'companyId' => $companyId,
                ];
            }

            $newCompanyResult = $this->b24->core->call('crm.company.add', [
                'fields' => [
                    'TITLE' => 'Новая компания от email ' . $email,
                    'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
                ],
            ]);
            $newCompanyResponse = $newCompanyResult->getHttpResponse();
            $newCompanyData = json_decode($newCompanyResponse->getContent(), true);
            $companyId = (int)$newCompanyData['result'];

            $this->b24->core->call('crm.contact.update', [
                'id' => $contactId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);

            return [
                'entityType' => 'CONTACT',
                'entityId' => $contactId,
                'companyId' => $companyId,
            ];
        }

        $dealResult = $this->b24->core->call('crm.deal.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $dealResponse = $dealResult->getHttpResponse();
        $dealData = json_decode($dealResponse->getContent(), true);
        $deals = $dealData['result'];

        if (!empty($deals)) {
            $dealId = (int)$deals[0]['ID'];
            $companyId = !empty($deals[0]['COMPANY_ID']) ? (int)$deals[0]['COMPANY_ID'] : null;

            if ($companyId) {
                return [
                    'entityType' => 'DEAL',
                    'entityId' => $dealId,
                    'companyId' => $companyId,
                ];
            }

            $newCompanyResult = $this->b24->core->call('crm.company.add', [
                'fields' => [
                    'TITLE' => 'Новая компания от email ' . $email,
                    'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
                ],
            ]);
            $newCompanyResponse = $newCompanyResult->getHttpResponse();
            $newCompanyData = json_decode($newCompanyResponse->getContent(), true);
            $companyId = (int)$newCompanyData['result'];

            $this->b24->core->call('crm.deal.update', [
                'id' => $dealId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);

            return [
                'entityType' => 'DEAL',
                'entityId' => $dealId,
                'companyId' => $companyId,
            ];
        }

        $companyResult = $this->b24->core->call('crm.company.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'TITLE'],
        ]);
        $companyResponse = $companyResult->getHttpResponse();
        $companyData = json_decode($companyResponse->getContent(), true);
        $companies = $companyData['result'];

        if (!empty($companies)) {
            return [
                'entityType' => 'COMPANY',
                'entityId' => (int)$companies[0]['ID'],
                'companyId' => (int)$companies[0]['ID'],
            ];
        }

        $newCompanyResult = $this->b24->core->call('crm.company.add', [
            'fields' => [
                'TITLE' => 'Новая компания от email ' . $email,
                'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
            ],
        ]);
        $newCompanyResponse = $newCompanyResult->getHttpResponse();
        $newCompanyData = json_decode($newCompanyResponse->getContent(), true);
        $companyId = (int)$newCompanyData['result'];

        return [
            'entityType' => 'COMPANY',
            'entityId' => $companyId,
            'companyId' => $companyId,
        ];
    }

    public function registerCall(string $callId, string $phone, string $callType): array
    {
        $user = $this->getCurrentUser();
        $entityInfo = $this->findOrCreateCompany($phone);
        $entityType = $entityInfo['entityType'];
        $entityId = $entityInfo['entityId'];
        $companyId = $entityInfo['companyId'];


        $registerResult = $this->b24->core->call('telephony.externalcall.register', [
            'CALL_ID' => $callId,
            'USER_ID' => (int)$user['ID'],
            'PHONE_NUMBER' => $phone,
            'LINE_NUMBER' => 'external',
            'TYPE' => $callType === 'INCOMING' ? 2 : 1,
            'CRM_ENTITY_TYPE' => $entityType,
            'CRM_ENTITY_ID' => $entityId,
        ]);

        $registerResponse = $registerResult->getHttpResponse();
        $registerData = json_decode($registerResponse->getContent(), true);
        if (isset($registerData['error'])) {
            throw new \RuntimeException('Register API Error: ' . $registerData['error_description']);
        }

        return [
            'callId' => $registerData['result']['CALL_ID'] ?? $callId,
            'companyId' => $companyId,
        ];
    }

    public function finishCall(string $callId, int $duration, int $statusCode, int $userId): void
    {
        $finishResult = $this->b24->core->call('telephony.externalcall.finish', [
            'CALL_ID' => $callId,
            'USER_ID' => $userId,
            'DURATION' => $duration,
            'STATUS_CODE' => $statusCode,
        ]);

        $finishResponse = $finishResult->getHttpResponse();
        $finishData = json_decode($finishResponse->getContent(), true);
        if (isset($finishData['error'])) {
            throw new \RuntimeException('Finish API Error: ' . $finishData['error_description']);
        }
    }
    public function logEmail(int $companyId, string $email, string $subject, string $body, string $direction = 'INCOMING'): void
    {
        $user = $this->getCurrentUser();
    
        $entityInfo = $this->findOrCreateEntityByEmail($email);
        $entityType = $entityInfo['entityType'];
        $entityId = $entityInfo['entityId'];
        $companyId = $entityInfo['companyId'];
    
        $ownerTypeId = match ($entityType) {
            'CONTACT' => 1, 
            'DEAL' => 2,    
            'COMPANY' => 4, 
            default => throw new \RuntimeException('Unsupported entity type: ' . $entityType),
        };
        $activityResult = $this->b24->core->call('crm.activity.add', [
            'fields' => [
                'OWNER_TYPE_ID' => $ownerTypeId,
                'OWNER_ID' => $entityId,
                'TYPE_ID' => 4, 
                'SUBJECT' => $subject,
                'DESCRIPTION' => $body,
                'DIRECTION' => $direction === 'INCOMING' ? 1 : 2, 
                'COMPLETED' => 'Y',
                'RESPONSIBLE_ID' => (int)$user['ID'],
                'START_TIME' => (new \DateTime())->format('c'),
                'END_TIME' => (new \DateTime())->format('c'),
                'COMMUNICATIONS' => [
                    [
                        'VALUE' => $email,
                        'ENTITY_ID' => $entityId,
                        'ENTITY_TYPE_ID' => $ownerTypeId,
                        'TYPE' => 'EMAIL',
                    ],
                ],
            ],
        ]);
        $activityResponse = $activityResult->getHttpResponse();
        $activityData = json_decode($activityResponse->getContent(), true);
        if (isset($activityData['error'])) {
            throw new \RuntimeException('Activity API Error: ' . $activityData['error_description']);
        }

        if ($entityType !== 'COMPANY') {
            $companyActivityResult = $this->b24->core->call('crm.activity.add', [
                'fields' => [
                    'OWNER_TYPE_ID' => 4, 
                    'OWNER_ID' => $companyId,
                    'TYPE_ID' => 4, 
                    'SUBJECT' => $subject,
                    'DESCRIPTION' => $body,
                    'DIRECTION' => $direction === 'INCOMING' ? 1 : 2,
                    'COMPLETED' => 'Y',
                    'RESPONSIBLE_ID' => (int)$user['ID'],
                    'START_TIME' => (new \DateTime())->format('c'),
                    'END_TIME' => (new \DateTime())->format('c'),
                    'COMMUNICATIONS' => [
                        [
                            'VALUE' => $email,
                            'ENTITY_ID' => $companyId,
                            'ENTITY_TYPE_ID' => 4, 
                            'TYPE' => 'EMAIL',
                        ],
                    ],
                ],
            ]);
            $companyActivityResponse = $companyActivityResult->getHttpResponse();
            $companyActivityData = json_decode($companyActivityResponse->getContent(), true);
            if (isset($companyActivityData['error'])) {
                throw new \RuntimeException('Company Activity API Error: ' . $companyActivityData['error_description']);
            }
        }
    }
    public function getCallData(string $callId): array
    {
        $callResult = $this->b24->core->call('telephony.externalcall.get', ['CALL_ID' => $callId]);
        $callResponse = $callResult->getHttpResponse();
        $callData = json_decode($callResponse->getContent(), true);
        error_log('telephony.externalcall.get response: ' . json_encode($callData));
        if (isset($callData['error'])) {
            throw new \RuntimeException('Call API Error: ' . $callData['error_description']);
        }

        return $callData['result'];
    }

    public function findEntityByPhone(string $phone): ?array
    {
        $duplicateResult = $this->b24->core->call('crm.duplicate.findbycomm', [
            'TYPE' => 'PHONE',
            'VALUES' => [$phone],
        ]);
        $duplicateResponse = $duplicateResult->getHttpResponse();
        $duplicateData = json_decode($duplicateResponse->getContent(), true);

        if (isset($duplicateData['error'])) {
            throw new \RuntimeException('Duplicate API Error: ' . $duplicateData['error_description']);
        }

        $duplicates = $duplicateData['result'];
        if (!empty($duplicates['CONTACT']) && !empty($duplicates['CONTACT'][0])) {
            return ['entityType' => 'CONTACT', 'entityId' => (int)$duplicates['CONTACT'][0]];
        } elseif (!empty($duplicates['COMPANY']) && !empty($duplicates['COMPANY'][0])) {
            return ['entityType' => 'COMPANY', 'entityId' => (int)$duplicates['COMPANY'][0]];
        } elseif (!empty($duplicates['LEAD']) && !empty($duplicates['LEAD'][0])) {
            return ['entityType' => 'LEAD', 'entityId' => (int)$duplicates['LEAD'][0]];
        }

        return null;
    }

    public function getEntityData(string $entityType, int $entityId): array
    {
        $method = match ($entityType) {
            'LEAD' => 'crm.lead.get',
            'CONTACT' => 'crm.contact.get',
            'COMPANY' => 'crm.company.get',
            default => throw new \RuntimeException('Unsupported entity type: ' . $entityType),
        };

        $entityResult = $this->b24->core->call($method, ['ID' => $entityId]);
        $entityResponse = $entityResult->getHttpResponse();
        $entityData = json_decode($entityResponse->getContent(), true);

        if (isset($entityData['error'])) {
            throw new \RuntimeException('Entity API Error: ' . $entityData['error_description']);
        }

        return $entityData['result'];
    }


    public function updateEntity(string $entityType, int $entityId, array $fields): void
    {
        $method = match ($entityType) {
            'LEAD' => 'crm.lead.update',
            'CONTACT' => 'crm.contact.update',
            'COMPANY' => 'crm.company.update',
            default => throw new \RuntimeException('Unsupported entity type: ' . $entityType),
        };

        $updateResult = $this->b24->core->call($method, [
            'ID' => $entityId,
            'fields' => $fields,
        ]);
        $updateResponse = $updateResult->getHttpResponse();
        $updateData = json_decode($updateResponse->getContent(), true);

        if (isset($updateData['error'])) {
            throw new \RuntimeException('Update API Error: ' . $updateData['error_description']);
        }
    }

    public function getActivity(string $activityId): array
    {
        $result = $this->b24->core->call('crm.activity.get', [
            'ID' => $activityId,
        ]);
        $response = $result->getHttpResponse();
        $data = json_decode($response->getContent(), true);
        error_log('crm.activity.get response: ' . json_encode($data));
        if (isset($data['error'])) {
            throw new \RuntimeException('Error fetching activity: ' . $data['error_description']);
        }
        return $data['result'] ?? [];
    }
}
