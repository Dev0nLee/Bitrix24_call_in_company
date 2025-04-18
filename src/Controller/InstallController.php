<?php

namespace App\Controller;

use App\Entity\Installation;
use App\Service\Bitrix24Service;
use App\Repository\InstallationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InstallController extends AbstractController
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

    #[Route('/install', name: 'app_install', methods: ['GET', 'POST'])]
    public function install(Request $request, InstallationRepository $installationRepository): Response
    {
        require_once '../vendor/autoload.php'; 

        $logPath = $this->getParameter('kernel.project_dir') . '/var/log/install.log';
        $logData = [
            'time' => date('Y-m-d H:i:s'),
            'method' => $request->getMethod(),
            'query_params' => $request->query->all(),
            'request_params' => $request->request->all(),
            'headers' => $request->headers->all(),
        ];
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        $authId = $request->query->get('AUTH_ID') ?: $request->request->get('AUTH_ID');
        $refreshId = $request->query->get('REFRESH_ID') ?: $request->request->get('REFRESH_ID');
        $authExpires = $request->query->get('AUTH_EXPIRES') ?: $request->request->get('AUTH_EXPIRES');
        $domain = $request->query->get('DOMAIN') ?: $request->request->get('DOMAIN') ?: $this->domain;

        if (!$authId || !$refreshId) {
            $error = 'Missing required OAuth parameters';
            file_put_contents($logPath, json_encode(['time' => date('Y-m-d H:i:s'), 'error' => $error], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            return new Response(
                json_encode([
                    'rest_only' => true,
                    'install' => false,
                    'error' => $error,
                ]),
                400,
                ['Content-Type' => 'application/json']
            );
        }

        $expires = (int)$authExpires;

        $cachePath = $this->getParameter('kernel.project_dir') . '/var/auth_cache.json';
        $tokenData = [
            'access_token' => $authId,
            'refresh_token' => $refreshId,
            'expires_in' => $expires,
            'domain' => $domain,
        ];
        file_put_contents($cachePath, json_encode($tokenData, JSON_PRETTY_PRINT));

        $authLogPath = $this->getParameter('kernel.project_dir') . '/var/log/auth_params.log';
        $authLogData = [
            'time' => date('Y-m-d H:i:s'),
            'AUTH_ID' => $authId,
            'REFRESH_ID' => $refreshId,
            'AUTH_EXPIRES' => $expires,
            'DOMAIN' => $domain,
        ];
        file_put_contents($authLogPath, json_encode($authLogData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        $appProfile = ApplicationProfile::initFromArray([
            'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID' => $this->clientId,
            'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => $this->clientSecret,
            'BITRIX24_PHP_SDK_APPLICATION_SCOPE' => 'crm,telephony,user,placement',
        ]);

        $b24 = ServiceBuilderFactory::createServiceBuilderFromPlacementRequest(
            Request::createFromGlobals(),
            $appProfile
        );
        $eventBindResult = $b24->core->call('event.bind', [
            'event' => 'ONVOXIMPLANTCALLINIT',
            'handler' => 'https://insanely-festive-dhole.cloudpub.ru/bitrix/handler',
        ]);
        $eventBindData = json_decode($eventBindResult->getHttpResponse()->getContent(), true);

        file_put_contents($logPath, json_encode([
            'time' => date('Y-m-d H:i:s'),
            'event_bind_response' => $eventBindData,
        ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        $existingEvents = $b24->core->call('event.get', []);
        $eventsData = json_decode($existingEvents->getHttpResponse()->getContent(), true);
        file_put_contents($logPath, json_encode([
            'time' => date('Y-m-d H:i:s'),
            'event_get_response' => $eventsData,
        ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        if (isset($eventBindData['error'])) {
            throw new \Exception('Failed to bind event: ' . $eventBindData['error_description']);
        }

        try {
            $existingInstallation = $installationRepository->findOneBy(['domain' => $this->domain]);
            if ($existingInstallation) {
                return new Response(
                    json_encode([
                        'rest_only' => true,
                        'install' => true,
                    ]),
                    200,
                    ['Content-Type' => 'application/json']
                );
            }

            $installation = new Installation();
            $installation->setDomain($this->domain);
            $installation->setInstalledAt(new \DateTime());
            $installationRepository->save($installation, true);

            $b24->core->call('placement.bind', [
                'PLACEMENT' => 'CALL_CARD',
                'HANDLER' => 'https://insanely-festive-dhole.cloudpub.ru/call-card',
                'TITLE' => 'Карточка звонка',
                'DESCRIPTION' => 'Интерфейс для управления звонками',
                'GROUP_NAME' => 'call_management',
                'OPTIONS' => [
                    'errorHandlerUrl' => 'https://insanely-festive-dhole.cloudpub.ru/error',
                ],
            ]);
            return new Response(
                json_encode([
                    'rest_only' => true,
                    'install' => true,
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        } catch (\Exception $e) {
            file_put_contents($logPath, json_encode(['time' => date('Y-m-d H:i:s'), 'error' => $e->getMessage()], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            return new Response(
                json_encode([
                    'rest_only' => true,
                    'install' => false,
                    'error' => $e->getMessage(),
                ]),
                500,
                ['Content-Type' => 'application/json']
            );
        }
    }
}
