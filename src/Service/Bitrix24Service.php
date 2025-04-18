<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;

class Bitrix24Service
{
    private string $clientId;
    private string $clientSecret;
    private string $domain;
    private ApplicationProfile $appProfile;

    public function __construct(string $clientId, string $clientSecret, string $domain)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->domain = $domain;

        $this->appProfile = ApplicationProfile::initFromArray([
            'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID' => $this->clientId,
            'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => $this->clientSecret,
            'BITRIX24_PHP_SDK_APPLICATION_SCOPE' => 'crm,telephony,user,placement',
        ]);
    }

    public function getB24(Request $request)
    {
        $cachePath = __DIR__ . '/../../var/auth_cache.json';
        if (file_exists($cachePath)) {
            $tokenData = json_decode(file_get_contents($cachePath), true);
            $authId = $tokenData['access_token'] ?? null;
            $refreshId = $tokenData['refresh_token'] ?? null;
            $domain = $tokenData['domain'] ?? $this->domain;
            $expires = (int)$tokenData['expires_in'] ?? null;

            if ($authId && $refreshId) {
                $request->request->set('AUTH_ID', $authId);
                $request->request->set('REFRESH_ID', $refreshId);
                $request->request->set('DOMAIN', $domain);
                $request->request->set('AUTH_EXPIRES', $expires);
            }
        }
        $authId = $request->query->get('AUTH_ID');
        $refreshId = $request->query->get('REFRESH_ID');
        $domain = $request->query->get('DOMAIN') ?: $this->domain;
        $expires = $request->query->get('AUTH_EXPIRES');

        if ($authId && $refreshId) {
            $request->request->set('AUTH_ID', $authId);
            $request->request->set('REFRESH_ID', $refreshId);
            $request->request->set('DOMAIN', $domain);
            $request->request->set('AUTH_EXPIRES', $expires);
        }
        $logPath = __DIR__ . '/../../var/log/getb24.log';
        file_put_contents($logPath, json_encode([
            'time' => date('Y-m-d H:i:s'),
            'query_params' => $request->query->all(),
            'request_params' => $request->request->all(),
        ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        return ServiceBuilderFactory::createServiceBuilderFromPlacementRequest($request, $this->appProfile);
    }

    public function getCurrentUser(Request $request): array
    {
        $b24 = $this->getB24($request);
        $userResult = $b24->core->call('user.current');
        $data = json_decode($userResult->getHttpResponse()->getContent(), true);
        return $data['result'];
    }

    public function createCompany(string $phone, Request $request): int
    {
        $b24 = $this->getB24($request);
        $newCompanyResult = $b24->core->call('crm.company.add', [
            'fields' => [
                'TITLE' => 'Новая компания от звонка ' . $phone,
                'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
            ],
        ]);
        $newCompanyData = json_decode($newCompanyResult->getHttpResponse()->getContent(), true);
        return (int)$newCompanyData['result'];
    }

    public function createCompanybyEmail(string $email, Request $request): int
    {
        $b24 = $this->getB24($request);
        $newCompanyResult = $b24->core->call('crm.company.add', [
            'fields' => [
                'TITLE' => 'Новая компания от email ' . $email,
                'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
            ],
        ]);
        $newCompanyData = json_decode($newCompanyResult->getHttpResponse()->getContent(), true);
        return (int)$newCompanyData['result'];
    }

    public function findCompany(string $phone, Request $request): array
    {
        $b24 = $this->getB24($request);
        $contactResult = $b24->core->call('crm.contact.list', [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $contactData = json_decode($contactResult->getHttpResponse()->getContent(), true);
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

            $companyId = $this->createCompany($phone, $request);
            $b24->core->call('crm.contact.update', [
                'id' => $contactId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);
            return [
                'entityType' => 'CONTACT',
                'entityId' => $contactId,
                'companyId' => $companyId,
            ];
        }

        $dealResult = $b24->core->call('crm.deal.list', [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $dealData = json_decode($dealResult->getHttpResponse()->getContent(), true);
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

            $companyId = $this->createCompany($phone, $request);
            $b24->core->call('crm.deal.update', [
                'id' => $dealId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);

            return [
                'entityType' => 'DEAL',
                'entityId' => $dealId,
                'companyId' => $companyId,
            ];
        }

        $companyResult = $b24->core->call('crm.company.list', [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'TITLE'],
        ]);
        $companyData = json_decode($companyResult->getHttpResponse()->getContent(), true);
        $companies = $companyData['result'];

        if (!empty($companies)) {
            return [
                'entityType' => 'COMPANY',
                'entityId' => (int)$companies[0]['ID'],
                'companyId' => (int)$companies[0]['ID'],
            ];
        }

        $companyId = $this->createCompany($phone, $request);
        return [
            'entityType' => 'COMPANY',
            'entityId' => $companyId,
            'companyId' => $companyId,
        ];
    }

    public function findEntityByEmail(string $email, Request $request): array
    {
        $b24 = $this->getB24($request);
        $contactResult = $b24->core->call('crm.contact.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $contactData = json_decode($contactResult->getHttpResponse()->getContent(), true);
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

            $companyId = $this->createCompanybyEmail($email, $request);
            $b24->core->call('crm.contact.update', [
                'id' => $contactId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);

            return [
                'entityType' => 'CONTACT',
                'entityId' => $contactId,
                'companyId' => $companyId,
            ];
        }

        $dealResult = $b24->core->call('crm.deal.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'COMPANY_ID'],
        ]);
        $dealData = json_decode($dealResult->getHttpResponse()->getContent(), true);
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

            $companyId = $this->createCompanybyEmail($email, $request);
            $b24->core->call('crm.deal.update', [
                'id' => $dealId,
                'fields' => ['COMPANY_ID' => $companyId],
            ]);

            return [
                'entityType' => 'DEAL',
                'entityId' => $dealId,
                'companyId' => $companyId,
            ];
        }

        $companyResult = $b24->core->call('crm.company.list', [
            'filter' => ['EMAIL' => $email],
            'select' => ['ID', 'TITLE'],
        ]);
        $companyData = json_decode($companyResult->getHttpResponse()->getContent(), true);
        $companies = $companyData['result'];

        if (!empty($companies)) {
            return [
                'entityType' => 'COMPANY',
                'entityId' => (int)$companies[0]['ID'],
                'companyId' => (int)$companies[0]['ID'],
            ];
        }

        $companyId = $this->createCompanybyEmail($email, $request);
        return [
            'entityType' => 'COMPANY',
            'entityId' => $companyId,
            'companyId' => $companyId,
        ];
    }

    public function registerCall(string $callId, string $phone, string $callType, Request $request): array
    {
        $b24 = $this->getB24($request);
        $user = $this->getCurrentUser($request);
        $entityInfo = $this->findCompany($phone, $request);
        $entityType = $entityInfo['entityType'];
        $entityId = $entityInfo['entityId'];
        $companyId = $entityInfo['companyId'];

        $registerResult = $b24->core->call('telephony.externalcall.register', [
            'CALL_ID' => $callId,
            'USER_ID' => (int)$user['ID'],
            'PHONE_NUMBER' => $phone,
            'LINE_NUMBER' => 'external',
            'TYPE' => $callType === 'INCOMING' ? 2 : 1,
            'CRM_ENTITY_TYPE' => $entityType,
            'CRM_ENTITY_ID' => $entityId,
        ]);

        $registerData = json_decode($registerResult->getHttpResponse()->getContent(), true);

        if ($companyId && $entityType !== 'COMPANY') {
            $b24->core->call('crm.activity.add', [
                'fields' => [
                    'OWNER_TYPE_ID' => 4,
                    'OWNER_ID' => $companyId,
                    'TYPE_ID' => 2,
                    'SUBJECT' => 'Звонок ' . ($callType === 'INCOMING' ? 'входящий' : 'исходящий'),
                    'DIRECTION' => $callType === 'INCOMING' ? 1 : 2,
                    'COMPLETED' => 'N',
                    'RESPONSIBLE_ID' => (int)$user['ID'],
                    'START_TIME' => (new \DateTime())->format('c'),
                    'COMMUNICATIONS' => [
                        [
                            'VALUE' => $phone,
                            'ENTITY_ID' => $companyId,
                            'ENTITY_TYPE_ID' => 4,
                            'TYPE' => 'PHONE',
                        ],
                    ],
                ],
            ]);
        }

        return [
            'callId' => $registerData['result']['CALL_ID'] ?? $callId,
            'companyId' => $companyId,
        ];
    }

    public function finishCall(string $callId, int $duration, int $statusCode, int $userId, Request $request): void
    {
        $b24 = $this->getB24($request);
        $b24->core->call('telephony.externalcall.finish', [
            'CALL_ID' => $callId,
            'USER_ID' => $userId,
            'DURATION' => $duration,
            'STATUS_CODE' => $statusCode,
        ]);

        $activityResult = $b24->core->call('crm.activity.list', [
            'filter' => [
                'CALL_ID' => $callId,
                'OWNER_TYPE_ID' => 4,
            ],
        ]);
        $activityData = json_decode($activityResult->getHttpResponse()->getContent(), true);
        if (!empty($activityData['result'])) {
            $activityId = (int)$activityData['result'][0]['ID'];
            $b24->core->call('crm.activity.update', [
                'ID' => $activityId,
                'fields' => [
                    'COMPLETED' => 'Y',
                    'END_TIME' => (new \DateTime())->format('c'),
                ],
            ]);
        }
    }

    public function logEmail(int $companyId, string $email, string $subject, string $body, string $direction, Request $request): void
    {
        $b24 = $this->getB24($request);
        $user = $this->getCurrentUser($request);
        $entityInfo = $this->findEntityByEmail($email, $request);
        $entityType = $entityInfo['entityType'];
        $entityId = $entityInfo['entityId'];
        $companyId = $entityInfo['companyId'];

        $ownerTypeId = match ($entityType) {
            'CONTACT' => 1,
            'DEAL' => 2,
            'COMPANY' => 4,
            default => throw new \RuntimeException('Unsupported entity type: ' . $entityType),
        };

        $b24->core->call('crm.activity.add', [
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

        if ($entityType !== 'COMPANY') {
            $b24->core->call('crm.activity.add', [
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
        }
    }

    public function getCallData(string $callId, Request $request): array
    {
        $b24 = $this->getB24($request);
        $callResult = $b24->core->call('telephony.externalcall.get', ['CALL_ID' => $callId]);
        $callData = json_decode($callResult->getHttpResponse()->getContent(), true);
        return $callData['result'];
    }

    public function findEntityByPhone(string $phone, Request $request): ?array
    {
        $b24 = $this->getB24($request);
        $duplicateResult = $b24->core->call('crm.duplicate.findbycomm', [
            'TYPE' => 'PHONE',
            'VALUES' => [$phone],
        ]);
        $duplicateData = json_decode($duplicateResult->getHttpResponse()->getContent(), true);

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

    public function getEntityData(string $entityType, int $entityId, Request $request): array
    {
        $b24 = $this->getB24($request);
        $method = match ($entityType) {
            'LEAD' => 'crm.lead.get',
            'CONTACT' => 'crm.contact.get',
            'COMPANY' => 'crm.company.get',
            default => throw new \RuntimeException('Unsupported entity type: ' . $entityType),
        };

        $entityResult = $b24->core->call($method, ['ID' => $entityId]);
        $entityData = json_decode($entityResult->getHttpResponse()->getContent(), true);
        return $entityData['result'];
    }

    public function updateEntity(string $entityType, int $entityId, array $fields, Request $request): void
    {
        $b24 = $this->getB24($request);
        $method = match ($entityType) {
            'LEAD' => 'crm.lead.update',
            'CONTACT' => 'crm.contact.update',
            'COMPANY' => 'crm.company.update',
            default => throw new \RuntimeException('Unsupported entity type: ' . $entityType),
        };

        $b24->core->call($method, [
            'ID' => $entityId,
            'fields' => $fields,
        ]);
    }

    public function getActivity(string $activityId, Request $request): array
    {
        $b24 = $this->getB24($request);
        $result = $b24->core->call('crm.activity.get', [
            'ID' => $activityId,
        ]);
        $data = json_decode($result->getHttpResponse()->getContent(), true);
        return $data['result'] ?? [];
    }
}