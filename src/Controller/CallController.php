<?php

namespace App\Controller;

use LeadFieldMapper;
use CompanyFieldMapper;
use ContactFieldMapper;
use App\Service\Bitrix24Service;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CallController extends AbstractController
{
    private Bitrix24Service $bitrix24Service;
    private string $clientId;
    private string $clientSecret;
    private string $domain;

    public function __construct(Bitrix24Service $bitrix24Service, string $clientId, string $clientSecret, string $domain)
    {
        $this->bitrix24Service = $bitrix24Service;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->domain = $domain;
    }

    #[Route('/call-card', name: 'call_card', methods: ['GET', 'POST'])]
    public function callCard(Request $request): Response
    {
        $placementOptions = $request->query->get('PLACEMENT_OPTIONS') ?: $request->request->get('PLACEMENT_OPTIONS');
        $callId = null;
        $phone = null;
        $message = '';
        $isEmbedded = false;



        if ($placementOptions) {
            $isEmbedded = true;
            $options = json_decode($placementOptions, true);
            $callId = $options['CALL_ID'] ?? null;
            $phone = $options['PHONE_NUMBER'] ?? null;
        }

        if (!$phone) {
            $filePath = $this->getParameter('kernel.project_dir') . '/var/call_data.txt';
            if (file_exists($filePath)) {
                $callData = json_decode(file_get_contents($filePath), true);
                $callId = $callData['call_id'] ?? null;
                $phone = $callData['phone'] ?? null;
            }
        }

        if (!$phone) {
            return $this->render('call_card.html.twig', [
                'callId' => $callId,
                'message' => 'Номер телефона не найден.',
                'isEmbedded' => $isEmbedded,
            ]);
        }

        try {
            $entityInfo = $this->bitrix24Service->findEntityByPhone($phone, $request);
            if (!$entityInfo) {
                return $this->render('call_card.html.twig', [
                    'callId' => $callId,
                    'message' => 'Сущность не найдена для номера: ' . $phone,
                    'isEmbedded' => $isEmbedded,
                ]);
            }

            $entityType = $entityInfo['entityType'];
            $entityId = $entityInfo['entityId'];
            $entityData = $this->bitrix24Service->getEntityData($entityType, $entityId, $request);

            if ($request->isMethod('POST')) {
                $name = trim($request->request->get('name', '')) ?: null;
                $phone = trim($request->request->get('phone', '')) ?: null;
                $email = trim($request->request->get('email', '')) ?: null;

                $fields = [];
                $mappers = [
                    'LEAD' => new LeadFieldMapper(),
                    'CONTACT' => new ContactFieldMapper(),
                    'COMPANY' => new CompanyFieldMapper(),
                ];

                if (isset($mappers[$entityType])) {
                    $mappers[$entityType]->mapFields($fields, $name, $phone, $email);
                    if (!empty($fields)) {
                        $this->bitrix24Service->updateEntity($entityType, $entityId, $fields, $request);
                        $message = 'Данные успешно сохранены!';
                        $entityData = $this->bitrix24Service->getEntityData($entityType, $entityId, $request);
                    }
                }
            }

            return $this->render('call_card.html.twig', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'entityData' => $entityData,
                'callId' => $callId,
                'message' => $message,
                'isEmbedded' => $isEmbedded,
            ]);
        } catch (\Exception $e) {
            return $this->render('call_card.html.twig', [
                'callId' => $callId ?? '',
                'message' => 'Ошибка: ' . $e->getMessage(),
                'isEmbedded' => $isEmbedded,
            ]);
        }
    }

    #[Route('/call/start', name: 'call_start', methods: ['POST'])]
    public function callStart(Request $request): Response
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (isset($data['call_id']) && isset($data['phone'])) {
            $callId = $data['call_id'];
            $phone = $data['phone'];
            $callType = $data['call_type'] ?? 'OUTGOING';

            $result = $this->bitrix24Service->registerCall($callId, $phone, $callType, $request);

            $filePath = $this->getParameter('kernel.project_dir') . '/var/call_data.txt';
            $callData = [
                'call_id' => $result['callId'],
                'phone' => $phone,
            ];
            file_put_contents($filePath, json_encode([
                'data' => $callData,
                'time' => date('Y-m-d H:i:s'),
            ]));

            return new Response('Call started', 200);
        }

        return new Response('Missing call_id or phone', 400);
    }

    #[Route('/call/finish', name: 'call_finish', methods: ['POST'])]
    public function callFinish(Request $request): Response
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (isset($data['call_id']) && isset($data['duration']) && isset($data['status_code']) && isset($data['user_id'])) {
            $this->bitrix24Service->finishCall(
                $data['call_id'],
                (int)$data['duration'],
                (int)$data['status_code'],
                (int)$data['user_id'],
                $request
            );
            return new Response('Call finished', 200);
        }

        return new Response('Missing required fields', 400);
    }
    
    #[Route('/bitrix/handler', name: 'bitrix_handler', methods: ['POST', 'GET'])]
    public function handler(Request $request): Response
    {

        $logPath = $this->getParameter('kernel.project_dir') . '/var/log/bitrix_handler.log';
        $logData = [
            'time' => date('Y-m-d H:i:s'),
            'method' => $request->getMethod(),
            'query_params' => $request->query->all(),
            'request_params' => $request->request->all(),
            'headers' => $request->headers->all(),
        ];
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        $appSid = $request->query->get('APP_SID');
        if ($appSid) {
            return new RedirectResponse($this->generateUrl('app_install', $request->query->all()));
        }

        $data = json_decode($request->getContent(), true) ?: $request->request->all();
        if (isset($data['event']) && $data['event'] === 'OnVoximplantCallInit') {
            $callId = $data['data']['CALL_ID'] ?? null;
            $phone = $data['data']['PHONE_NUMBER'] ?? '';
            $placementOptions = json_encode([
                'CALL_ID' => $callId,
                'PHONE_NUMBER' => $phone,
            ]);
            return new RedirectResponse($this->generateUrl('call_card', [
                'PLACEMENT' => 'CALL_CARD',
                'PLACEMENT_OPTIONS' => $placementOptions,
                'AUTH_ID' => $request->query->get('AUTH_ID'),
                'REFRESH_ID' => $request->query->get('REFRESH_ID'),
                'DOMAIN' => $request->query->get('DOMAIN'),
            ]));
        }

        return new Response('Event processed or ignored', 200);
    }

    #[Route('/error', name: 'error', methods: ['GET', 'POST'])]
    public function error(Request $request): Response
    {
        $error = $request->query->get('error', 'Unknown error');
        return new Response('Application error: ' . $error, 500);
    }
}