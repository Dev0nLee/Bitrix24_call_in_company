<?php

namespace App\Controller;

use App\Service\Bitrix24Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class CallController extends AbstractController
{

    private $logger;
    private $bitrix24Service;

    public function __construct(LoggerInterface $logger, Bitrix24Service $bitrix24Service)
    {
        $this->logger = $logger;
        $this->bitrix24Service = $bitrix24Service;
    }

    #[Route('/call/start', name: 'start_call', methods: ['POST'])]
    public function startCall(Request $request, Bitrix24Service $bitrix24Service): Response
    {
        $data = json_decode($request->getContent(), true);
        $callId = $data['call_id'] ?? null;
        $phone = $data['phone'] ?? '';
        $callType = $data['call_type'] ?? 'INCOMING';

        if (empty($callId) || empty($phone)) {
            return new Response('Call ID and phone number are required', 400);
        }

        $result = $bitrix24Service->registerCall($callId, $phone, $callType);

        $filePath = $this->getParameter('kernel.project_dir') . '/var/call_data.txt';
        $callData = [
            'call_id' => $result['callId'],
            'phone' => $phone,
        ];
        try {
            file_put_contents($filePath, json_encode($callData));
            $this->logger->info('Saved call data to file', ['file' => $filePath, 'data' => $callData]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to write call data to file', ['error' => $e->getMessage(), 'file' => $filePath]);
            return new Response('Failed to save call data', 500);
        }

        return new Response(json_encode(['message' => 'Call registered successfully', 'call_id' => $result['callId']]), 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/call/finish', name: 'finish_call', methods: ['POST'])]
    public function finishCall(Request $request, Bitrix24Service $bitrix24Service): Response
    {
        $data = json_decode($request->getContent(), true);
        $callId = $data['call_id'] ?? null;
        $duration = (int)($data['duration'] ?? 0);
        $statusCode = (int)($data['status_code'] ?? 200);
        $userId = (int)($data['user_id'] ?? null);

        if (empty($callId) || empty($userId)) {
            return new Response('Call ID and user ID are required', 400);
        }

        $bitrix24Service->finishCall($callId, $duration, $statusCode, $userId);

        return new Response('Call finished successfully', 200);
    }

    #[Route('/email', name: 'handle_email', methods: ['POST'])]
    public function handleEmail(Request $request, Bitrix24Service $bitrix24Service): Response
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $subject = $data['subject'] ?? 'Без темы';
        $body = $data['body'] ?? 'Без содержания';
        $direction = $data['direction'] ?? 'INCOMING';

        if (empty($email)) {
            return new Response('Email is required', 400);
        }

        $bitrix24Service->logEmail(0, $email, $subject, $body, $direction);

        return new Response('Email processed successfully', 200);
    }

    #[Route('/call-card', name: 'call_card', methods: ['GET', 'POST'])]
    public function callCard(Request $request): Response
    {
        $this->logger->info('callCard endpoint called');

        $placement = $request->query->get('PLACEMENT');
        $placementOptions = $request->query->get('PLACEMENT_OPTIONS');

        $this->logger->info('Placement parameters', ['placement' => $placement, 'placement_options' => $placementOptions]);

        $callId = null;
        $phone = null;
        $message = '';

        if ($placement === 'TELEPHONY_CALL_CARD' && !empty($placementOptions)) {
            $options = json_decode($placementOptions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $callId = $options['CALL_ID'] ?? null;
                if ($callId) {
                    $this->logger->info('CALL_ID extracted from PLACEMENT_OPTIONS', ['call_id' => $callId]);
                }
            }
        }

        if (!$callId) {
            $filePath = $this->getParameter('kernel.project_dir') . '/var/call_data.txt';
            if (file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                if ($fileContent !== false) {
                    $callData = json_decode($fileContent, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $callId = $callData['call_id'] ?? null;
                        $phone = $callData['phone'] ?? null;
                        if ($callId) {
                            $this->logger->info('CALL_ID extracted from file', ['call_id' => $callId, 'phone' => $phone]);
                        } else {
                            $this->logger->warning('No CALL_ID found in file data', ['file_content' => $fileContent]);
                            $message = 'No CALL_ID found in file.';
                        }
                    } else {
                        $this->logger->error('Failed to decode file data', ['error' => json_last_error_msg(), 'file_content' => $fileContent]);
                        $message = 'Invalid data format in file.';
                    }
                } else {
                    $this->logger->error('Failed to read file', ['file' => $filePath]);
                    $message = 'Failed to read call data file.';
                }
            } else {
                $this->logger->warning('Call data file does not exist', ['file' => $filePath]);
                $message = 'Call data file not found. Please start a call first.';
            }
        }
        if (!$callId) {
            return $this->render('call_card.html.twig', [
                'callId' => '',
                'message' => $message,
            ]);
        }

        if (!$phone) {
            try {
                $callData = $this->bitrix24Service->getCallData($callId);
                $phone = $callData['PHONE_NUMBER'] ?? '';
                $this->logger->info('Call data fetched', ['call_data' => $callData]);
            } catch (\Exception $e) {
                $this->logger->error('Error fetching call data', ['error' => $e->getMessage(), 'call_id' => $callId]);
                return $this->render('call_card.html.twig', [
                    'callId' => $callId,
                    'message' => 'Error fetching call data: ' . $e->getMessage(),
                ]);
            }
        }

        if (!$phone) {
            $this->logger->error('Phone number not found in call data', ['call_id' => $callId]);
            return $this->render('call_card.html.twig', [
                'callId' => $callId,
                'message' => 'Phone number not found in call data',
            ]);
        }

        try {
            $entityInfo = $this->bitrix24Service->findEntityByPhone($phone);
            $this->logger->info('Entity info fetched', ['entity_info' => $entityInfo]);
        } catch (\Exception $e) {
            $this->logger->error('Error finding entity', ['error' => $e->getMessage(), 'phone' => $phone]);
            return $this->render('call_card.html.twig', [
                'callId' => $callId,
                'message' => 'Error finding entity: ' . $e->getMessage(),
            ]);
        }

        if (!$entityInfo) {
            $this->logger->warning('Entity not found for phone', ['phone' => $phone]);
            return $this->render('call_card.html.twig', [
                'callId' => $callId,
                'message' => 'Entity not found for phone: ' . $phone,
            ]);
        }

        $entityType = $entityInfo['entityType'];
        $entityId = $entityInfo['entityId'];

        try {
            $entityData = $this->bitrix24Service->getEntityData($entityType, $entityId);
            $this->logger->info('Entity data fetched', ['entity_data' => $entityData]);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching entity data', ['error' => $e->getMessage(), 'entity_type' => $entityType, 'entity_id' => $entityId]);
            return $this->render('call_card.html.twig', [
                'callId' => $callId,
                'message' => 'Error fetching entity data: ' . $e->getMessage(),
            ]);
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $phone = $request->request->get('phone');
            $email = $request->request->get('email');

            $fields = [];
            if ($entityType === 'LEAD') {
                if ($name) $fields['NAME'] = $name;
                if ($phone) $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
                if ($email) $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
            } elseif ($entityType === 'CONTACT') {
                if ($name) $fields['NAME'] = $name;
                if ($phone) $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
                if ($email) $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
            } elseif ($entityType === 'COMPANY') {
                if ($name) $fields['TITLE'] = $name;
                if ($phone) $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
                if ($email) $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
            }

            try {
                $this->bitrix24Service->updateEntity($entityType, $entityId, $fields);
                $message = 'Данные успешно сохранены!';
                $entityData = $this->bitrix24Service->getEntityData($entityType, $entityId);
                $this->logger->info('Entity updated successfully', ['entity_type' => $entityType, 'entity_id' => $entityId]);
            } catch (\Exception $e) {
                $message = 'Ошибка сохранения: ' . $e->getMessage();
                $this->logger->error('Error updating entity', ['error' => $e->getMessage(), 'entity_type' => $entityType, 'entity_id' => $entityId]);
            }
        }

        return $this->render('call_card.html.twig', [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'entityData' => $entityData,
            'callId' => $callId,
            'message' => $message,
        ]);
    }

    #[Route('/call/webhook', name: 'call_webhook', methods: ['GET', 'POST'])]
    public function callWebhook(Request $request): Response
    {
        $this->logger->info('callWebhook endpoint called');

        if ($request->isMethod('GET')) {
            $this->logger->info('GET request to callWebhook');
            return new Response('This is the webhook endpoint. Use POST to send data.', 200);
        }

        $content = $request->getContent();
        if (empty($content)) {
            $this->logger->error('Webhook request body is empty');
            return new Response('Empty request body', 400);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to decode webhook data', ['error' => json_last_error_msg()]);
            return new Response('Invalid JSON', 400);
        }

        $this->logger->info('Webhook data received', ['data' => $data]);

        if ($data['event'] === 'OnVoximplantCallInit') {
            $callId = $data['data']['CALL_ID'] ?? null;
            $phone = $data['data']['PHONE_NUMBER'] ?? '';
            $this->logger->info('Processing OnVoximplantCallInit event', ['call_id' => $callId, 'phone' => $phone]);
        }

        return new Response('Webhook received', 200);
    }

    #[Route('/call/test', name: 'test_call')]
    public function test(Bitrix24Service $bitrix24Service): Response
    {
        $user = $bitrix24Service->getCurrentUser();
        return $this->render('call/index.html.twig', [
            'user' => $user,
        ]);
    }
}
